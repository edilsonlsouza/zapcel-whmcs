<?php
namespace WHMCS\Module\Addon\Zapcel\Api;

/**
 * Zapcel WHMCS - Classe de Integração WhatsApp API
 * Integração profissional com a API do Zapcel
 * 
 * @package    Zapcel
 * @author     Hostcel
 * @version    2.1.0
 */

// Bloqueia acesso direto
if (!defined('WHMCS')) {
    die('Acesso não autorizado.');
}

/**
 * Classe principal de integração com a API WhatsApp
 */
class WhatsAppAPI
{
    /**
     * @var array Configurações do módulo
     */
    private $settings;

    /**
     * @var array URLs de QR Code identificadas por índice
     */
    private $qrCodeUrls = [];

    /**
     * @var array Mensagens processadas para referência
     */
    private $processedMessages = [];
    
    /**
     * @var string URL base da API
     */
    private $apiBaseUrl = 'https://zap.hostcel.com.br/api';
    
    /**
     * @var int Timeout para requisições
     */
    private $timeout = 30;
    
    /**
     * @var array Headers padrão
     */
    private $defaultHeaders = [
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: Zapcel-WHMCS/2.1.0'
    ];

    /**
     * Construtor
     * 
     * @param array $settings Configurações do módulo
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
        
        // Aplica configurações de tentativas e delay
        $this->maxAttempts = (int)($settings['max_attempts'] ?? 3);
        $this->messageDelay = (int)($settings['message_delay'] ?? 2);
    }

    /**
     * Envia mensagem de texto com suporte a partes separadas e detecção automática de QR Code
     * 
     * @param string $phoneNumber Número de telefone (formato internacional)
     * @param string|array $message Mensagem a ser enviada (string ou array de partes)
     * @param array $options Opções adicionais
     * @return array Resultado do envio
     */
    public function sendMessage(string $phoneNumber, $message, array $options = []): array
    {
        try {
            // Valida parâmetros básicos
            $validation = $this->validateSendParameters($phoneNumber, is_array($message) ? implode(' ', $message) : $message);
            if (!$validation['success']) {
                return $validation;
            }

            // Processa a mensagem - se já for array, usa como está
            if (is_array($message)) {
                $processedMessages = $message;
            } else {
                $processedMessages = $this->processMessageForSending($message);
            }
            
            // ✅ Armazena as mensagens processadas para referência
            $this->processedMessages = $processedMessages;
            
            // Usa configurações do módulo para retry e delay
            $maxAttempts = $this->maxAttempts;
            $messageDelay = $this->messageDelay;
            
            $results = [];
            $allSuccess = true;
            $messageIds = [];
            
            $this->logDebug('sendMessage', 'Iniciando envio SEQUENCIAL de mensagem com partes', [
                'phone' => substr($phoneNumber, 0, 6) . '...',
                'parts_count' => count($processedMessages),
                'max_attempts' => $maxAttempts,
                'message_delay' => $messageDelay,
                'qr_codes_to_send' => count($this->qrCodeUrls)
            ]);
            
            // ✅ ENVIO SEQUENCIAL: Garante que cada mensagem seja enviada e processada antes da próxima
            foreach ($processedMessages as $index => $messagePart) {
                
                // ✅ DETECÇÃO AUTOMÁTICA DE QR CODE (usando índice atual)
                $isQRCode = $this->isQRCodeMessage($messagePart, $index, $processedMessages);
                
                if ($isQRCode) {
                    $this->logDebug('sendMessage', 'Enviando QR Code como imagem (parte ' . ($index + 1) . ')', [
                        'part_index' => $index,
                        'total_parts' => count($processedMessages)
                    ]);
                    
                    // Envia via endpoint de mídia para QR Code
                    $result = $this->sendQRCodeAsImage($phoneNumber, $messagePart, $maxAttempts);
                } else {
                    $this->logDebug('sendMessage', 'Enviando mensagem de texto (parte ' . ($index + 1) . ')', [
                        'part_index' => $index,
                        'total_parts' => count($processedMessages),
                        'text_preview' => substr($messagePart, 0, 50) . '...'
                    ]);
                    
                    // Envia como mensagem de texto normal
                    $payload = $this->buildTextPayload($phoneNumber, $messagePart);
                    $result = $this->sendWithRetry('/send', $payload, $maxAttempts);
                }
                
                // ✅ AGUARDA RESULTADO ANTES DE CONTINUAR
                if ($result['success']) {
                    // Armazena ID da mensagem se disponível
                    if (!empty($result['message_id'])) {
                        $messageIds[] = $result['message_id'];
                    }
                    
                    $this->logDebug('sendMessage', 'Mensagem parte ' . ($index + 1) . ' enviada com sucesso', [
                        'type' => $isQRCode ? 'media' : 'text',
                        'message_id' => $result['message_id'] ?? null
                    ]);
                } else {
                    $this->logError('Falha ao enviar parte ' . ($index + 1) . ': ' . ($result['error'] ?? 'Erro desconhecido'));
                }
                
                $results[] = [
                    'part' => $index + 1,
                    'success' => $result['success'] ?? false,
                    'type' => $isQRCode ? 'media' : 'text',
                    'message_id' => $result['message_id'] ?? null,
                    'error' => $result['error'] ?? null,
                    'data' => $result['data'] ?? null,  // ✅ ADICIONA DADOS COMPLETOS DA API
                    'http_code' => $result['http_code'] ?? null  // ✅ ADICIONA HTTP CODE
                ];
                
                if (!($result['success'] ?? false)) {
                    $allSuccess = false;
                    
                    // Se uma parte falhar, podemos optar por continuar ou parar
                    $continueOnFailure = $options['continue_on_failure'] ?? true;
                    if (!$continueOnFailure) {
                        $this->logError('Parando envio devido a falha na parte ' . ($index + 1));
                        break; // Para o envio se uma parte falhar
                    }
                }
                
                // ✅ PAUSA ENTRE MENSAGENS (mesmo após imagens)
                if ($index < count($processedMessages) - 1 && $messageDelay > 0) {
                    $this->logDebug('sendMessage', 'Aguardando delay entre mensagens', [
                        'delay_seconds' => $messageDelay,
                        'current_part' => $index + 1,
                        'total_parts' => count($processedMessages)
                    ]);
                    sleep($messageDelay);
                }
            }
            
            $finalResult = [
                'success' => $allSuccess,
                'results' => $results,
                'message_parts' => count($processedMessages),
                'message_ids' => $messageIds,
                'used_settings' => [
                    'max_attempts' => $maxAttempts,
                    'message_delay' => $messageDelay
                ]
            ];
            
            $this->logDebug('sendMessage', 'Envio sequencial concluído', [
                'success' => $allSuccess,
                'parts_sent' => count(array_filter($results, function($r) { return $r['success']; })),
                'total_parts' => count($processedMessages),
                'media_parts' => count(array_filter($results, function($r) { return $r['type'] === 'media'; }))
            ]);
            
            return $finalResult;
            
        } catch (\Exception $e) {
            $this->logError('sendMessage Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Detecta se a mensagem deve ser enviada como mídia (QR Code)
     * 
     * @param string $messagePart Parte da mensagem
     * @param int $index Índice da parte atual
     * @param array $allParts Todas as partes da mensagem
     * @return bool True se é QR Code
     */
    private function isQRCodeMessage(string $messagePart, int $index, array $allParts): bool
    {
        // ✅ FALLBACK SIMPLES: Garante que é string
        if (!is_string($messagePart)) {
            $messagePart = '';
        }
        
        // ✅ DETECÇÃO SIMPLES: Apenas verifica se contém {qr_code_url}
        $hasQRCodeVariable = (strpos($messagePart, '{qr_code_url}') !== false);
        
        if ($hasQRCodeVariable) {
            $this->logDebug('isQRCodeMessage', 'QR Code detectado - contém {qr_code_url}', [
                'part_index' => $index,
                'part_content' => trim($messagePart)
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Envia QR Code como imagem via endpoint de mídia
     * 
     * @param string $phoneNumber Número de telefone
     * @param string $messagePart Parte da mensagem com {qr_code_url}
     * @param int $maxAttempts Número máximo de tentativas
     * @return array Resultado do envio
     */
    private function sendQRCodeAsImage(string $phoneNumber, string $messagePart, int $maxAttempts): array
    {
        try {
            $startTime = microtime(true);
            $this->logDebug('sendQRCodeAsImage', 'INICIANDO envio de QR Code como imagem', [
                'message_part_preview' => substr($messagePart, 0, 150),
                'max_attempts' => $maxAttempts
            ]);
            
            // ✅ EXTRAI a URL do QR Code
            $qrCodeUrl = $this->extractQRCodeUrlFromVariable($messagePart);
            
            $this->logDebug('sendQRCodeAsImage', 'Resultado da extração', [
                'qr_code_url_found' => !empty($qrCodeUrl),
                'qr_code_url' => $qrCodeUrl
            ]);
            
            if (empty($qrCodeUrl)) {
                $this->logError('URL do QR Code não encontrada na variável {qr_code_url}');
                return [
                    'success' => false,
                    'error' => 'URL do QR Code não encontrada na variável {qr_code_url}',
                    'code' => 'QRCODE_URL_NOT_FOUND'
                ];
            }
            
            // ✅ CORREÇÃO: Garante que a URL do QR Code tenha extensão
            $processedQrCodeUrl = $this->ensureImageUrlHasExtension($qrCodeUrl);
            
            $this->logDebug('sendQRCodeAsImage', 'Enviando QR Code como imagem', [
                'original_url' => $qrCodeUrl,
                'processed_url' => $processedQrCodeUrl,
                'phone' => substr($phoneNumber, 0, 6) . '...'
            ]);
            
            // Envia via endpoint de mídia com URL processada
            // Usa legenda específica para QR Code PIX
            $result = $this->sendImage($phoneNumber, $processedQrCodeUrl, 'QR Code PIX - Pagamento Instantâneo');

            $this->logDebug('sendQRCodeAsImage', 'FINALIZANDO envio de QR Code', [
                'success' => $result['success'] ?? false,
                'time_taken' => round((microtime(true) - $startTime), 2) . 's'
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError('sendQRCodeAsImage Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Extrai URL do QR Code da variável {qr_code_url} (VERSÃO CORRIGIDA)
     * 
     * @param string $messagePart Parte da mensagem
     * @return string URL extraída ou string vazia
     */
    private function extractQRCodeUrlFromVariable(string $messagePart): string
    {
        $this->logDebug('extractQRCodeUrlFromVariable', 'Analisando mensagem para QR Code', [
            'full_message' => $messagePart
        ]);
        
        // ✅ MÉTODO DIRETO: Procura por {qr_code_url} seguido de qualquer coisa e depois uma URL
        if (preg_match('/\{qr_code_url\}(.*?)(https?:\/\/[^\s]+)/s', $messagePart, $matches)) {
            $url = trim($matches[2]);
            $this->logDebug('extractQRCodeUrlFromVariable', 'URL encontrada via regex', ['url' => $url]);
            return $url;
        }
        
        // ✅ MÉTODO ALTERNATIVO: Se não encontrou com regex, usa método simples
        $startPos = strpos($messagePart, '{qr_code_url}');
        if ($startPos !== false) {
            $textAfterMarker = substr($messagePart, $startPos + strlen('{qr_code_url}'));
            
            // Procura por qualquer URL no texto após o marcador
            if (preg_match('/https?:\/\/[^\s]+/i', $textAfterMarker, $matches)) {
                $url = trim($matches[0]);
                $this->logDebug('extractQRCodeUrlFromVariable', 'URL encontrada via método simples', ['url' => $url]);
                return $url;
            }
        }
        
        $this->logDebug('extractQRCodeUrlFromVariable', 'Nenhuma URL encontrada após {qr_code_url}');
        return '';
    }

    /**
     * Envia imagem
     * 
     * @param string $phoneNumber Número de telefone
     * @param string $imageUrl URL da imagem
     * @param string $caption Legenda da imagem
     * @return array Resultado do envio
     */
    public function sendImage(string $phoneNumber, string $imageUrl, string $caption = ''): array
    {
        try {
            $validation = $this->validateSendParameters($phoneNumber, $caption, true);
            if (!$validation['success']) {
                return $validation;
            }

            // ✅ CORREÇÃO: Adiciona extensão .jpg se a URL não tiver extensão
            $processedImageUrl = $this->ensureImageUrlHasExtension($imageUrl);
            
            $this->logDebug('sendImage', 'Processando URL da imagem', [
                'original_url' => $imageUrl,
                'processed_url' => $processedImageUrl
            ]);

            $payload = $this->buildMediaPayload($phoneNumber, $processedImageUrl, $caption, 'image');
            
            // Aplica sistema de tentativas para mídia também
            return $this->sendWithRetry('/send', $payload);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Garante que a URL da imagem tenha uma extensão
     * 
     * @param string $imageUrl URL original
     * @return string URL com extensão .jpg se necessário
     */
    private function ensureImageUrlHasExtension(string $imageUrl): string
    {
        // Remove parâmetros de query para verificar a extensão
        $urlWithoutParams = strtok($imageUrl, '?');
        
        // Lista de extensões de imagem comuns
        $imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg'];
        
        // Verifica se a URL já tem uma extensão de imagem
        foreach ($imageExtensions as $extension) {
            if (stripos($urlWithoutParams, $extension) !== false) {
                $this->logDebug('ensureImageUrlHasExtension', 'URL já tem extensão de imagem', [
                    'url' => $imageUrl,
                    'extension' => $extension
                ]);
                return $imageUrl; // Retorna original se já tem extensão
            }
        }
        
        // ✅ Se não tem extensão, adiciona .jpg
        $processedUrl = $imageUrl . '.jpg';
        
        $this->logDebug('ensureImageUrlHasExtension', 'Extensão .jpg adicionada à URL', [
            'original_url' => $imageUrl,
            'processed_url' => $processedUrl
        ]);
        
        return $processedUrl;
    }

    /**
     * Envia arquivo/documento
     * 
     * @param string $phoneNumber Número de telefone
     * @param string $fileUrl URL do arquivo
     * @param string $filename Nome do arquivo
     * @param string $caption Legenda do arquivo
     * @return array Resultado do envio
     */
    public function sendFile(string $phoneNumber, string $fileUrl, string $filename, string $caption = ''): array
    {
        try {
            $validation = $this->validateSendParameters($phoneNumber, $caption, true);
            if (!$validation['success']) {
                return $validation;
            }

            $payload = $this->buildMediaPayload($phoneNumber, $fileUrl, $caption, 'file');
            $payload['filename'] = $filename;
            
            // Aplica sistema de tentativas
            return $this->sendWithRetry('/send', $payload);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Envia mensagem para grupo
     * 
     * @param string $groupId ID do grupo
     * @param string $message Mensagem a ser enviada
     * @param string $type Tipo de mensagem (text/media)
     * @param array $mediaOptions Opções de mídia (opcional)
     * @return array Resultado do envio
     */
    public function sendGroupMessage(string $groupId, string $message, string $type = 'text', array $mediaOptions = []): array
    {
        try {
            if (empty($groupId) || empty($message)) {
                return [
                    'success' => false,
                    'error' => 'ID do grupo e mensagem são obrigatórios',
                    'code' => 'MISSING_PARAMETERS'
                ];
            }

            $payload = [
                'group_id' => $groupId,
                'type' => $type,
                'message' => $message,
                'instance_id' => $this->settings['zapcel_instance_id'],
                'access_token' => $this->settings['zapcel_access_token']
            ];

            // Adiciona opções de mídia se for o caso
            if ($type === 'media' && !empty($mediaOptions)) {
                $payload['media_url'] = $mediaOptions['media_url'] ?? '';
                $payload['filename'] = $mediaOptions['filename'] ?? '';
            }

            return $this->sendWithRetry('/send_group', $payload);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Cria uma nova instância
     * 
     * @return array Resultado da criação
     */
    public function createInstance(): array
    {
        try {
            $payload = [
                'access_token' => $this->settings['zapcel_access_token']
            ];

            return $this->makeApiRequest('/create_instance', $payload);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Obtém QR Code para login
     * 
     * @return array Resultado com QR Code
     */
    public function getQRCode(): array
    {
        try {
            $payload = [
                'instance_id' => $this->settings['zapcel_instance_id'],
                'access_token' => $this->settings['zapcel_access_token']
            ];

            return $this->makeApiRequest('/get_qrcode', $payload);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Define webhook para recebimento de mensagens
     * 
     * @param string $webhookUrl URL do webhook
     * @param bool $enable Habilitar/desabilitar
     * @return array Resultado da configuração
     */
    public function setWebhook(string $webhookUrl, bool $enable = true): array
    {
        try {
            $payload = [
                'webhook_url' => $webhookUrl,
                'enable' => $enable ? 'true' : 'false',
                'instance_id' => $this->settings['zapcel_instance_id'],
                'access_token' => $this->settings['zapcel_access_token']
            ];

            return $this->makeApiRequest('/set_webhook', $payload);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Reinicia a instância
     * 
     * @return array Resultado do reinício
     */
    public function rebootInstance(): array
    {
        try {
            $payload = [
                'instance_id' => $this->settings['zapcel_instance_id'],
                'access_token' => $this->settings['zapcel_access_token']
            ];

            return $this->makeApiRequest('/reboot', $payload);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Reseta a instância (logout completo)
     * 
     * @return array Resultado do reset
     */
    public function resetInstance(): array
    {
        try {
            $payload = [
                'instance_id' => $this->settings['zapcel_instance_id'],
                'access_token' => $this->settings['zapcel_access_token']
            ];

            return $this->makeApiRequest('/reset_instance', $payload);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Reconecta a instância
     * 
     * @return array Resultado da reconexão
     */
    public function reconnectInstance(): array
    {
        try {
            $payload = [
                'instance_id' => $this->settings['zapcel_instance_id'],
                'access_token' => $this->settings['zapcel_access_token']
            ];

            return $this->makeApiRequest('/reconnect', $payload);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Obtém lista de grupos
     * 
     * @return array Lista de grupos
     */
    public function getGroups(): array
    {
        try {
            $payload = [
                'instance_id' => $this->settings['zapcel_instance_id'],
                'access_token' => $this->settings['zapcel_access_token']
            ];

            return $this->makeApiRequest('/get_groups', $payload);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Envia mensagem com botões
     * 
     * @param string $chatId ID do chat
     * @param string $template Template dos botões
     * @param string $type Tipo de mensagem
     * @return array Resultado do envio
     */
    public function sendButtonMessage(string $chatId, string $template, string $type = '2'): array
    {
        try {
            $payload = [
                'instance_id' => $this->settings['zapcel_instance_id'],
                'access_token' => $this->settings['zapcel_access_token'],
                'chat_id' => $chatId,
                'template' => $template,
                'type' => $type
            ];

            // Endpoint específico para botões
            return $this->makeApiRequest('/send_button_message', $payload, 'GET');
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Verifica status da instância usando o endpoint set_webhook como teste de credenciais/status.
     * 
     * @return array Status da conexão
     */
    public function checkInstanceStatus(): array
    {
        try {
            if (empty($this->settings['zapcel_instance_id']) || empty($this->settings['zapcel_access_token'])) {
                return [
                    'success' => false,
                    'error' => 'Configurações incompletas',
                    'code' => 'MISSING_CONFIG'
                ];
            }

            // Usamos o endpoint set_webhook com enable=false e uma URL de teste
            // para verificar se as credenciais são aceitas e se a instância está ativa.
            $payload = [
                'instance_id' => $this->settings['zapcel_instance_id'],
                'access_token' => $this->settings['zapcel_access_token'],
                'webhook_url' => 'https://webhook.site/e613a9a8-7c7b-4011-8ca0-21ec435afecb', // SUA URL AQUI
                'enable' => 'false' // Não queremos alterar a configuração real
            ];

            // Chamada síncrona para o endpoint set_webhook
            $result = $this->makeApiRequest('/set_webhook', $payload, 'GET' );
            
            // A API Zapcel retorna 'status: success' se a instância existe e está conectada.
            if (($result['data']['status'] ?? '') === 'success') {
                return [
                    'success' => true,
                    'data' => ['status' => 'connected'], // Simulamos o status 'connected' para o AdminDispatcher
                    'http_code' => 200
                ];
            }
            
            // Se o status for 'error', extraímos a mensagem de erro. 
            $errorMessage = 'Acesse o Zapcel e reconecte seu celular para continuar.'; 
            //$result['data']['message'] ?? 'Erro desconhecido na API Zapcel.';
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'code' => 'API_ERROR',
                'http_code' => $result['http_code'] ?? 0
            ];
            
        } catch (\Exception $e ) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Envia com sistema de retry baseado nas configurações (ATUALIZADO para suportar mídia)
     * 
     * @param string $endpoint Endpoint da API
     * @param array $payload Dados para envio
     * @return array Resultado
     */
    private function sendWithRetry(string $endpoint, array $payload): array
    {
        $result = null;
        
        // ✅ AJUSTA TIMEOUT para mídia (imagens podem demorar mais)
        $isMedia = isset($payload['type']) && ($payload['type'] === 'media' || $payload['type'] === 'image');
        $timeout = $isMedia ? 60 : $this->timeout; // 60 segundos para mídia, 30 para texto
        
        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            $result = $this->makeApiRequest($endpoint, $payload, 'POST', $timeout);
            
            if ($result['success']) {
                break; // Sucesso, sai do loop
            }
            
            // Log de tentativa falha
            $this->logDebug('sendWithRetry', 'Tentativa ' . $attempt . ' falhou', [
                'attempt' => $attempt, 
                'max_attempts' => $this->maxAttempts, 
                'endpoint' => $endpoint,
                'is_media' => $isMedia,
                'error' => $result['error'] ?? 'Unknown error'
            ]);
            
            // Se não é a última tentativa, aguarda antes de tentar novamente
            if ($attempt < $this->maxAttempts) {
                $retryDelay = $isMedia ? 3 : 2; // Delay maior para mídia
                sleep($retryDelay);
            }
        }
        
        return $result;
    }

    /**
     * Valida número de telefone
     * 
     * @param string $phoneNumber Número a ser validado
     * @return array Resultado da validação
     */
    public function validatePhoneNumber(string $phoneNumber): array
    {
        // Remove todos os caracteres não numéricos exceto +
        $cleanNumber = preg_replace('/[^\d+]/', '', $phoneNumber);
        
        // Verifica formato internacional básico
        if (!preg_match('/^\+\d{1,3}\d{4,14}$/', $cleanNumber)) {
            return [
                'success' => false,
                'error' => 'Formato de número inválido. Use: +5511999999999 (celular) ou +551130303030 (fixo)',
                'code' => 'INVALID_FORMAT'
            ];
        }
        
        // Extrai código do país e número
        $countryCode = substr($cleanNumber, 1, 2); // +55
        $number = substr($cleanNumber, 3); // 11999999999 ou 1130303030
        
        // Validações específicas por país
        switch ($countryCode) {
            case '55': // Brasil
                // Aceita tanto celular (11 dígitos) quanto fixo (10 dígitos)
                if (strlen($number) === 11) {
                    // Celular: deve começar com 9 após o DDD
                    if (substr($number, 2, 1) !== '9') {
                        return [
                            'success' => false,
                            'error' => 'Número de celular brasileiro inválido. Deve começar com 9 após o DDD. Formato: +5511999999999',
                            'code' => 'INVALID_BRAZIL_MOBILE'
                        ];
                    }
                } elseif (strlen($number) === 10) {
                    // Fixo: deve começar com 2, 3, 4 ou 5 após o DDD
                    $firstDigit = substr($number, 2, 1);
                    if (!in_array($firstDigit, ['2', '3', '4', '5'])) {
                        return [
                            'success' => false,
                            'error' => 'Número fixo brasileiro inválido. Deve começar com 2, 3, 4 ou 5 após o DDD. Formato: +551130303030',
                            'code' => 'INVALID_BRAZIL_LANDLINE'
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'error' => 'Número brasileiro inválido. Celular: 11 dígitos (+5511999999999), Fixo: 10 dígitos (+551130303030)',
                        'code' => 'INVALID_BRAZIL_NUMBER'
                    ];
                }
                break;
                
            case '1': // EUA/Canadá
                if (strlen($number) !== 10) {
                    return [
                        'success' => false,
                        'error' => 'Invalid US/Canada number. Format: +11234567890',
                        'code' => 'INVALID_US_NUMBER'
                    ];
                }
                break;
                
            // Adicione mais validações por país conforme necessário
        }
        
        return [
            'success' => true,
            'clean_number' => $cleanNumber,
            'country_code' => $countryCode,
            'local_number' => $number
        ];
    }

    /**
     * Processa mensagem para envio com suporte a quebras manuais e detecção de QR Code
     * 
     * @param string $message Mensagem original
     * @return array Partes da mensagem processadas
     */
    private function processMessageForSending(string $message): array
    {
        // Limpa cache de QR Codes
        $this->qrCodeUrls = [];
        
        // PRIMEIRO: Verifica se há quebras manuais {quebrar_mensagem}
        if (strpos($message, '{quebrar_mensagem}') !== false) {
            $parts = explode('{quebrar_mensagem}', $message);
            
            // Processa cada parte
            $processedParts = [];
            foreach ($parts as $index => $part) {
                $cleanPart = trim($part);
                
                // Remove outras variações do marcador
                $cleanPart = str_replace(['{{quebrar_mensagem}}', '[quebrar_mensagem]'], '', $cleanPart);
                
                if (!empty($cleanPart)) {
                    // ✅ CORREÇÃO CRÍTICA: Se esta parte contém {qr_code_url}, processa como QR Code
                    if (strpos($cleanPart, '{qr_code_url}') !== false) {
                        // ✅ EXTRAI a URL e marca esta parte como QR Code
                        $qrCodeUrl = $this->extractQRCodeUrlFromVariable($cleanPart);
                        if (!empty($qrCodeUrl)) {
                            $this->qrCodeUrls[count($processedParts)] = $qrCodeUrl;
                            $this->logDebug('processMessageForSending', 'QR Code identificado - parte será enviada como imagem', [
                                'part_index' => count($processedParts),
                                'qr_code_url' => $qrCodeUrl,
                                'original_content' => substr($cleanPart, 0, 100) . '...'
                            ]);
                            
                            // ✅ ADICIONA a parte processada (será enviada como imagem)
                            $processedParts[] = $cleanPart;
                        } else {
                            // Se não encontrou URL, envia como texto normal
                            $processedParts[] = $cleanPart;
                        }
                    } else {
                        // Se a parte ainda for muito longa, quebra automaticamente
                        if (strlen($cleanPart) > 1500) {
                            $subParts = $this->splitLongMessage($cleanPart);
                            $processedParts = array_merge($processedParts, $subParts);
                        } else {
                            $processedParts[] = $cleanPart;
                        }
                    }
                }
            }
            
            $this->logDebug('processMessageForSending', 'Mensagem processada com quebras manuais', [
                'original_length' => strlen($message),
                'manual_parts' => count($parts),
                'final_parts' => count($processedParts),
                'qr_codes_found' => count($this->qrCodeUrls)
            ]);
            
            return $processedParts;
        }
        
        // SEGUNDO: Quebra automática se a mensagem for muito longa
        if (strlen($message) > 1500) {
            $autoParts = $this->splitLongMessage($message);
            
            $this->logDebug('processMessageForSending', 'Mensagem quebrada automaticamente', [
                'original_length' => strlen($message),
                'auto_parts' => count($autoParts)
            ]);
            
            return $autoParts;
        }
        
        // TERCEIRO: Mensagem curta, envia como está
        return [trim($message)];
    }

    /**
     * Divide mensagens longas em partes menores
     * 
     * @param string $message Mensagem longa
     * @param int $maxLength Tamanho máximo por parte
     * @return array Partes da mensagem
     */
    private function splitLongMessage(string $message, int $maxLength = 1500): array
    {
        $parts = [];
        $lines = explode("\n", $message);
        $currentPart = '';
        
        foreach ($lines as $line) {
            // Se adicionar esta linha exceder o limite, inicia nova parte
            if (strlen($currentPart) + strlen($line) + 1 > $maxLength) {
                if (!empty($currentPart)) {
                    $parts[] = trim($currentPart);
                    $currentPart = '';
                }
                
                // Se uma linha individual é muito longa, quebra ela
                if (strlen($line) > $maxLength) {
                    $chunks = str_split($line, $maxLength - 100);
                    foreach ($chunks as $chunk) {
                        $parts[] = trim($chunk);
                    }
                    continue;
                }
            }
            
            $currentPart .= $line . "\n";
        }
        
        // Adiciona a última parte se não estiver vazia
        if (!empty(trim($currentPart))) {
            $parts[] = trim($currentPart);
        }
        
        return $parts;
    }

    /**
     * Valida parâmetros básicos para envio
     * 
     * @param string $phoneNumber Número de telefone
     * @param string $message Mensagem
     * @param bool $allowEmptyMessage Permite mensagem vazia para mídia
     * @return array Resultado da validação
     */
    private function validateSendParameters(string $phoneNumber, string $message, bool $allowEmptyMessage = false): array
    {
        // Verifica se o módulo está habilitado
        if (!$this->settings['zapcel_enabled']) {
            return [
                'success' => false,
                'error' => 'Módulo Zapcel desativado',
                'code' => 'MODULE_DISABLED'
            ];
        }

        // Verifica configurações da API
        if (empty($this->settings['zapcel_instance_id']) || empty($this->settings['zapcel_access_token'])) {
            return [
                'success' => false,
                'error' => 'Configurações da API incompletas',
                'code' => 'MISSING_API_CONFIG'
            ];
        }

        // Valida número de telefone
        $phoneValidation = $this->validatePhoneNumber($phoneNumber);
        if (!$phoneValidation['success']) {
            return $phoneValidation;
        }

        // Valida mensagem (se aplicável)
        if (!$allowEmptyMessage && empty(trim($message))) {
            return [
                'success' => false,
                'error' => 'Mensagem não pode estar vazia',
                'code' => 'EMPTY_MESSAGE'
            ];
        }

        return ['success' => true];
    }

    /**
     * Constrói payload para mensagem de texto
     * 
     * @param string $phoneNumber Número de telefone
     * @param string $message Mensagem
     * @return array Payload para API
     */
    private function buildTextPayload(string $phoneNumber, string $message): array
    {
        return [
            'number' => $this->validatePhoneNumber($phoneNumber)['clean_number'],
            'type' => 'text',
            'message' => $message,
            'instance_id' => $this->settings['zapcel_instance_id'],
            'access_token' => $this->settings['zapcel_access_token']
        ];
    }

    /**
     * Constrói payload para mídia
     * 
     * @param string $phoneNumber Número de telefone
     * @param string $mediaUrl URL da mídia
     * @param string $caption Legenda
     * @param string $mediaType Tipo de mídia
     * @return array Payload para API
     */
    private function buildMediaPayload(string $phoneNumber, string $mediaUrl, string $caption, string $mediaType): array
    {
        $payload = [
            'number' => $this->validatePhoneNumber($phoneNumber)['clean_number'],
            'type' => 'media',
            'media_url' => $mediaUrl,
            'message' => $caption,
            'instance_id' => $this->settings['zapcel_instance_id'],
            'access_token' => $this->settings['zapcel_access_token']
        ];

        // ✅ Para imagens, força o tipo como image
        if ($mediaType === 'image') {
            $payload['type'] = 'image';
        }
        // Para arquivos, podemos especificar o tipo
        else if ($mediaType === 'file') {
            $payload['type'] = 'document';
        }

        return $payload;
    }

    /**
     * Faz requisição para a API (ATUALIZADO com timeout customizável)
     * 
     * @param string $endpoint Endpoint da API
     * @param array $payload Dados da requisição
     * @param string $method Método HTTP
     * @param int $timeout Timeout personalizado
     * @return array Resposta da API
     */
    private function makeApiRequest(string $endpoint, array $payload, string $method = 'POST', int $timeout = null): array
    {
        $url = $this->apiBaseUrl . $endpoint;
        
        // Usa timeout personalizado ou padrão
        $timeout = $timeout ?? $this->timeout;
        
        // Prepara os dados para envio
        $postData = http_build_query($payload);
        
        // Configura cURL
        $ch = curl_init();
        
        if ($method === 'GET') {
            $url .= '?' . $postData;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $this->defaultHeaders,
        ]);
        
        if ($method === 'POST') {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
            ]);
        }
        
        // Executa a requisição
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Processa a resposta
        return $this->processApiResponse($response, $httpCode, $error, $payload);
    }

    /**
     * Processa a resposta da API
     * 
     * @param mixed $response Resposta bruta
     * @param int $httpCode Código HTTP
     * @param string $error Erro cURL
     * @param array $payload Payload original
     * @return array Resposta processada
     */
    private function processApiResponse($response, int $httpCode, string $error, array $payload): array
    {
        // Verifica erros de conexão
        if (!empty($error)) {
            return [
                'success' => false,
                'error' => 'Erro de conexão: ' . $error,
                'code' => 'CURL_ERROR',
                'http_code' => $httpCode
            ];
        }
        
        // Tenta decodificar JSON
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode === 200) {
            if (is_array($decodedResponse)) {
                // Resposta bem-sucedida da API
                return [
                    'success' => true,
                    'data' => $decodedResponse,
                    'http_code' => $httpCode,
                    'message_id' => $decodedResponse['id'] ?? null
                ];
            } else {
                // Resposta não-JSON mas com código 200
                return [
                    'success' => true,
                    'raw_response' => $response,
                    'http_code' => $httpCode
                ];
            }
        } else {
            // Erro HTTP
            $errorMessage = $this->getErrorMessageFromCode($httpCode);
            
            if (is_array($decodedResponse)) {
                $errorMessage = $decodedResponse['error'] ?? $decodedResponse['message'] ?? $errorMessage;
            }
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'code' => 'HTTP_' . $httpCode,
                'http_code' => $httpCode,
                'api_response' => $decodedResponse
            ];
        }
    }

    /**
     * Obtém mensagem de erro amigável baseada no código HTTP
     * 
     * @param int $httpCode Código HTTP
     * @return string Mensagem de erro
     */
    private function getErrorMessageFromCode(int $httpCode): string
    {
        $errors = [
            400 => 'Requisição inválida para a API',
            401 => 'Token de acesso inválido ou expirado',
            403 => 'Acesso negado à instância',
            404 => 'Instância não encontrada',
            429 => 'Limite de requisições excedido',
            500 => 'Erro interno do servidor Zapcel',
            502 => 'Serviço temporariamente indisponível',
            503 => 'Serviço em manutenção'
        ];
        
        return $errors[$httpCode] ?? "Erro HTTP {$httpCode}";
    }

    /**
     * Verifica se a instância está conectada
     * 
     * @return bool True se conectada
     */
    public function isInstanceConnected(): bool
    {
        $status = $this->checkInstanceStatus();
        return $status['success'] && ($status['status'] ?? '') === 'connected';
    }

    /**
     * Obtém informações da instância
     * 
     * @return array Informações da instância
     */
    public function getInstanceInfo(): array
    {
        $status = $this->checkInstanceStatus();
        
        if (!$status['success']) {
            return $status;
        }
        
        return [
            'success' => true,
            'instance' => [
                'status' => $status['status'] ?? 'unknown',
                'instance_id' => $this->settings['zapcel_instance_id'],
                'connected' => ($status['status'] ?? '') === 'connected'
            ]
        ];
    }

    /**
     * Obtém estatísticas de uso (método auxiliar)
     * 
     * @return array Estatísticas básicas
     */
    public function getUsageInfo(): array
    {
        return [
            'success' => true,
            'usage' => [
                'max_attempts' => $this->maxAttempts,
                'message_delay' => $this->messageDelay,
                'instance_id' => $this->settings['zapcel_instance_id'],
                'module_enabled' => $this->settings['zapcel_enabled'] ?? false
            ]
        ];
    }

    /**
     * Log de debug
     * 
     * @param string $context Contexto do log
     * @param string $message Mensagem de debug
     * @param array $data Dados adicionais
     * @return void
     */
    private function logDebug(string $context, string $message, array $data = []): void
    {
        if (function_exists('zapcel_log_debug')) {
            zapcel_log_debug($context, $message, $data);
        }
    }

    /**
     * Log de erro
     * 
     * @param string $message Mensagem de erro
     * @return void
     */
    private function logError(string $message): void
    {
        if (function_exists('zapcel_log_error')) {
            zapcel_log_error($message);
        }
        
        // Fallback para error_log se a função não existir
        error_log("Zapcel WhatsAppAPI: " . $message);
    }
}