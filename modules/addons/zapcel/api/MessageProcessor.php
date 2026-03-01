<?php
namespace WHMCS\Module\Addon\Zapcel\Api;

use function \zapcel_trans;

/**
 * Zapcel WHMCS - Processador de Templates de Mensagem
 * Processa templates com variáveis e formatação para WhatsApp
 * 
 * @package    Zapcel
 * @author     Hostcel
 * @version    2.0.1
 */

// Bloqueia acesso direto
if (!defined('WHMCS')) {
    die(zapcel_trans('access_denied'));
}

use WHMCS\Database\Capsule;

/**
 * Classe para processamento de templates de mensagem
 */
class MessageProcessor
{
    /**
     * @var array Configurações do módulo
     */
    private $settings;
    
    /**
     * @var array Variáveis padrão disponíveis
     */
    private $defaultVariables;
    
    /**
     * @var array Mapeamento de eventos para variáveis
     */
    private $eventVariablesMap;

    /**
     * Construtor
     * 
     * @param array $settings Configurações do módulo
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->defaultVariables = $this->initializeDefaultVariables();
        $this->eventVariablesMap = $this->initializeEventVariablesMap();
    }

    /**
     * Processa template com variáveis e quebras de mensagem
     * 
     * @param string $template Template da mensagem
     * @param array $variables Variáveis para substituição
     * @param string $eventType Tipo de evento (opcional)
     * @return array|string Array de partes da mensagem OU string única
     */
    public function processTemplate(string $template, array $variables = [], string $eventType = '', bool $returnParts = false)
    {
        try {
            // Combina variáveis padrão com as fornecidas
            $allVariables = array_merge($this->defaultVariables, $variables);
            
            // Adiciona variáveis específicas do evento se fornecido
            if ($eventType && isset($this->eventVariablesMap[$eventType])) {
                $allVariables = array_merge($allVariables, $this->eventVariablesMap[$eventType]);
            }
            
            // Processa o template
            $processedMessage = $this->replaceVariables($template, $allVariables);
            
            // Se solicitado para retornar partes OU se contém quebra de mensagem
            if ($returnParts || strpos($processedMessage, '{quebrar_mensagem}') !== false) {
                return $this->splitMessageIntoParts($processedMessage);
            }
            
            // Processamento normal (sem quebras)
            $processedMessage = str_replace('{quebrar_mensagem}', "\n\n", $processedMessage);
            $processedMessage = str_replace(['{{quebrar_mensagem}}', '[quebrar_mensagem]'], "\n\n", $processedMessage);
            $processedMessage = $this->applyFormatting($processedMessage);
            $processedMessage = $this->cleanupMessage($processedMessage);
            
            return $processedMessage;
            
        } catch (\Exception $e) {
            // Fallback para template original em caso de erro
            $this->logError(zapcel_trans('template_error_prefix') . $e->getMessage());
            return $returnParts ? [$template] : $template;
        }
    }

    /**
     * Divide a mensagem em partes baseadas no marcador {quebrar_mensagem}
     * 
     * @param string $message Mensagem completa
     * @return array Partes da mensagem
     */
    private function splitMessageIntoParts(string $message): array
    {
        // Divide a mensagem usando o marcador como separador
        $parts = explode('{quebrar_mensagem}', $message);
        
        // Remove partes vazias e aplica formatação
        $processedParts = [];
        foreach ($parts as $part) {
            $cleanPart = trim($part);
            
            // Remove outras variações do marcador
            $cleanPart = str_replace(['{{quebrar_mensagem}}', '[quebrar_mensagem]'], '', $cleanPart);
            
            // CORREÇÃO v2.0.1: Detecta {qr_code_url} e separa em partes estruturadas
            if (strpos($cleanPart, '{qr_code_url}') !== false) {
                $qrParts = $this->splitQrCodePart($cleanPart);
                foreach ($qrParts as $qrPart) {
                    $processedParts[] = $qrPart;
                }
            } else {
                // Processamento normal de texto
                $cleanPart = $this->applyFormatting($cleanPart);
                $cleanPart = $this->cleanupMessage($cleanPart);
                
                if (!empty($cleanPart)) {
                    $processedParts[] = [
                        'type' => 'text',
                        'content' => $cleanPart
                    ];
                }
            }
        }
        
        // Log para debug
        $this->logDebug('splitMessageIntoParts', zapcel_trans('message_split_into_parts'), [
            'original_length' => strlen($message),
            'parts_count' => count($processedParts),
            'has_qr_code' => strpos($message, '{qr_code_url}') !== false
        ]);
        
        return $processedParts;
    }

    /**
     * Obtém lista de variáveis disponíveis para um evento
     * 
     * @param string $eventType Tipo de evento
     * @return array Variáveis disponíveis
     */
    public function getAvailableVariables(string $eventType = ''): array
    {
        $variables = $this->defaultVariables;
        
        if ($eventType && isset($this->eventVariablesMap[$eventType])) {
            $variables = array_merge($variables, $this->eventVariablesMap[$eventType]);
        }
        
        // Ordena variáveis por nome para melhor exibição
        ksort($variables);
        
        return $variables;
    }

    /**
     * Valida template para garantir que todas as variáveis necessárias estão presentes
     * 
     * @param string $template Template a validar
     * @param string $eventType Tipo de evento
     * @return array Resultado da validação
     */
    public function validateTemplate(string $template, string $eventType = ''): array
    {
        $availableVars = $this->getAvailableVariables($eventType);
        $usedVars = $this->extractVariablesFromTemplate($template);
        
        $missingVars = [];
        $unknownVars = [];
        
        foreach ($usedVars as $var) {
            if (!isset($availableVars[$var])) {
                $unknownVars[] = $var;
            }
        }
        
        // Para eventos específicos, verifica variáveis obrigatórias
        if ($eventType && isset($this->eventVariablesMap[$eventType])) {
            $requiredVars = array_keys($this->eventVariablesMap[$eventType]);
            foreach ($requiredVars as $requiredVar) {
                if (!in_array($requiredVar, $usedVars)) {
                    $missingVars[] = $requiredVar;
                }
            }
        }
        
        return [
            'valid' => empty($unknownVars) && empty($missingVars),
            'unknown_variables' => $unknownVars,
            'missing_variables' => $missingVars,
            'used_variables' => $usedVars,
            'available_variables' => array_keys($availableVars)
        ];
    }

    /**
     * Inicializa variáveis padrão disponíveis
     * 
     * @return array Variáveis padrão
     */
    private function initializeDefaultVariables(): array
    {
        global $CONFIG;
        
        return [
            'assinatura' => $this->settings['zapcel_signature'] ?? '',
            'provedor' => $CONFIG['CompanyName'] ?? zapcel_trans('provider_default_name'),
            'data_atual' => date('d/m/Y'),
            'hora_atual' => date('H:i'),
            'data_hora_atual' => date('d/m/Y H:i'),
            'url_sistema' => rtrim(\App::getSystemUrl(), '/'),
            'url_whmcs' => rtrim(\App::getSystemUrl(), '/'),
            'ano_atual' => date('Y'),
            'mes_atual' => date('m'),
            'dia_atual' => date('d'),
            'quebrar_mensagem' => '{quebrar_mensagem}',
        ];
    }

    /**
     * Inicializa mapeamento de variáveis por evento COMPLETO
     * 
     * @return array Mapeamento evento -> variáveis
     */
    private function initializeEventVariablesMap(): array
    {
        return [
            // === CLIENTES ===
            'client_added' => [
                'cliente' => zapcel_trans('client_full_name'), // Já estava internacionalizado
                'email' => zapcel_trans('client_email_var'), // Já estava internacionalizado
                'data_cadastro' => zapcel_trans('registration_date_var'),
                'cliente_id' => zapcel_trans('client_id_var'),
                'telefone' => zapcel_trans('client_phone_var'),
                'endereco' => zapcel_trans('client_address_var'),
                'bairro' => zapcel_trans('client_neighborhood_var'),
                'cidade' => zapcel_trans('client_city_var'),
                'estado' => zapcel_trans('client_state_var'),
                'cep' => zapcel_trans('client_zipcode_var'),
                'pais' => zapcel_trans('client_country_var'),
                'cpf_cnpj' => zapcel_trans('cpf_cnpj_var'),
            ],
            
            'client_edited' => [
                'cliente' => zapcel_trans('client_full_name'), // Correspondente a 'Nome completo do cliente'
                'email' => zapcel_trans('client_email_var'), // Correspondente a 'E-mail do cliente'
                'telefone' => zapcel_trans('client_phone_var'),
                'endereco' => zapcel_trans('client_address_var'),
                'bairro' => zapcel_trans('client_neighborhood_var'),
                'cidade' => zapcel_trans('client_city_var'),
                'estado' => zapcel_trans('client_state_var'),
                'cep' => zapcel_trans('client_zipcode_var'),
                'pais' => zapcel_trans('client_country_var'),
                'alteracoes' => zapcel_trans('changes_var'),
                'data_alteracao' => zapcel_trans('modification_date_var')
            ],
            
            // === FATURAS ===
            'invoice_created' => [
                'numero_fatura' => zapcel_trans('invoice_number_var'),
                'titulo' => zapcel_trans('title_var'),
                'valor' => zapcel_trans('value_var'),
                'vencimento' => zapcel_trans('due_date_var'),
                'descricao' => zapcel_trans('invoice_items_var'), // Descrição dos itens da fatura
                'codigopix' => zapcel_trans('pix_code_var'),
                'qr_code_url' => zapcel_trans('qr_code_url_var'),
                'linhadigitavel' => zapcel_trans('barcode_var'),
                'link_fatura' => zapcel_trans('invoice_link_var'),
                'itens_fatura' => zapcel_trans('invoice_items_var'),
                'data_criacao' => zapcel_trans('creation_date_var')
            ],
            
            'invoice_reminder' => [
                'numero_fatura' => zapcel_trans('invoice_number_var'),
                'titulo' => zapcel_trans('title_var'),
                'valor' => zapcel_trans('value_var'),
                'vencimento' => zapcel_trans('due_date_var'),
                'dias_vencimento' => zapcel_trans('days_until_due_var'),
                'codigopix' => zapcel_trans('pix_code_var'),
                'qr_code_url' => zapcel_trans('qr_code_url_var'),
                'linhadigitavel' => zapcel_trans('barcode_var'),
                'link_fatura' => zapcel_trans('invoice_link_var')
            ],
            
            'invoice_paid' => [
                'numero_fatura' => zapcel_trans('invoice_number_var'),
                'titulo' => zapcel_trans('title_var'),
                'valor' => zapcel_trans('value_var'), // Valor pago
                'data_pagamento' => zapcel_trans('payment_date_var'),
                'metodo_pagamento' => zapcel_trans('payment_method_var')
            ],
            
            'invoice_cancelled' => [
                'numero_fatura' => zapcel_trans('invoice_number_var'),
                'titulo' => zapcel_trans('title_var'),
                'valor' => zapcel_trans('value_var'), // Valor da fatura cancelada
                'motivo_cancelamento' => zapcel_trans('cancellation_reason_var'),
                'data_cancelamento' => zapcel_trans('cancellation_date_var')
            ],
            
            // === TICKETS ===
            'ticket_created' => [
                'numero_ticket' => zapcel_trans('ticket_number_var'),
                'assunto' => zapcel_trans('subject_var'),
                'departamento' => zapcel_trans('department_var'),
                'prioridade' => zapcel_trans('priority_var'),
                'link_ticket' => zapcel_trans('ticket_link_var')
            ],
            
            'ticket_opened' => [
                'numero_ticket' => zapcel_trans('ticket_number_var'),
                'assunto' => zapcel_trans('subject_var'),
                'departamento' => zapcel_trans('department_var'),
                'prioridade' => zapcel_trans('priority_var'),
                'link_ticket' => zapcel_trans('ticket_link_var')
            ],
            
            'ticket_reply' => [
                'numero_ticket' => zapcel_trans('ticket_number_var'),
                'assunto' => zapcel_trans('subject_var'),
                'atendente' => zapcel_trans('admin_name_var'),
                'link_ticket' => zapcel_trans('ticket_link_var')
            ],
            
            'ticket_replied' => [
                'numero_ticket' => zapcel_trans('ticket_number_var'),
                'assunto' => zapcel_trans('subject_var'),
                'atendente' => zapcel_trans('admin_name_var'),
                'link_ticket' => zapcel_trans('ticket_link_var')
            ],
            
            // === SERVIÇOS ===
            'service_activated' => [
                'servico' => zapcel_trans('service_name_var'),
                'nome_servico' => zapcel_trans('service_name_alt_var'),
                'id_servico' => zapcel_trans('service_id_var'),
                'dominio' => zapcel_trans('domain_var'),
                'data_ativacao' => zapcel_trans('activation_date_var'),
                'usuario' => zapcel_trans('username_var'),
                'senha' => zapcel_trans('password_var'),
                'ip_dedicado' => zapcel_trans('dedicated_ip_var')
            ],
            
            'service_suspended' => [
                'servico' => zapcel_trans('service_name_var'),
                'nome_servico' => zapcel_trans('service_name_alt_var'),
                'id_servico' => zapcel_trans('service_id_var'),
                'dominio' => zapcel_trans('domain_var'),
                'motivo' => zapcel_trans('suspension_reason_var'),
                'data_suspensao' => zapcel_trans('suspension_date_var')
            ],
            
            'service_unsuspended' => [
                'servico' => zapcel_trans('service_name_var'),
                'nome_servico' => zapcel_trans('service_name_alt_var'),
                'id_servico' => zapcel_trans('service_id_var'),
                'dominio' => zapcel_trans('domain_var'),
                'data_reativacao' => zapcel_trans('unsuspension_date_var')
            ],
            
            'service_terminated' => [
                'servico' => zapcel_trans('service_name_var'),
                'nome_servico' => zapcel_trans('service_name_alt_var'),
                'id_servico' => zapcel_trans('service_id_var'),
                'dominio' => zapcel_trans('domain_var'),
                'data_cancelamento' => zapcel_trans('termination_date_var')
            ],
            
            // === CANCELAMENTOS ===
            'cancellation_request' => [
                'id_servico' => zapcel_trans('service_id_var'),
                'nome_servico' => zapcel_trans('service_name_alt_var'),
                'razao_cancelamento' => zapcel_trans('cancellation_reason_var'), // Motivo/Razão do cancelamento
                'tipo_cancelamento' => zapcel_trans('cancellation_type_var'),
                'data_solicitacao' => zapcel_trans('request_date_var'),
                'dominio' => zapcel_trans('domain_var')
            ],
            
            // === COTAÇÕES ===
            'quote_created' => [
                'numero_cotacao' => zapcel_trans('quote_number_var'),
                'subject_cotacao' => zapcel_trans('quote_subject_var'),
                'valor_cotacao' => zapcel_trans('quote_value_var'),
                'validade_cotacao' => zapcel_trans('quote_validity_var'),
                'status_cotacao' => zapcel_trans('quote_status_var'),
                'itens_cotacao' => zapcel_trans('quote_items_var')
            ],
            
            'quote_modified' => [
                'numero_cotacao' => zapcel_trans('quote_number_var'),
                'subject_cotacao' => zapcel_trans('quote_subject_var'),
                'valor_cotacao' => zapcel_trans('quote_value_var'),
                'validade_cotacao' => zapcel_trans('quote_validity_var'),
                'status_cotacao' => zapcel_trans('quote_status_var'),
                'itens_cotacao' => zapcel_trans('quote_items_var'),
                'alteracoes' => zapcel_trans('changes_var')
            ],
            
            'quote_accepted' => [
                'numero_cotacao' => zapcel_trans('quote_number_var'),
                'subject_cotacao' => zapcel_trans('quote_subject_var'),
                'valor_cotacao' => zapcel_trans('quote_value_var'),
                'data_aceitacao' => zapcel_trans('acceptance_date_var'),
                'status_cotacao' => zapcel_trans('quote_status_var'),
                'itens_cotacao' => zapcel_trans('quote_items_var')
            ],
            
            // === VALIDAÇÃO WHATSAPP ===
            'whatsapp_validation' => [
                'cliente' => zapcel_trans('client_full_name'), // Correspondente a 'Nome completo do cliente'
                'codigo_verificacao' => zapcel_trans('verification_code_var'),
                'link_validacao' => zapcel_trans('link_fatura_autologin') // Pode ser link_fatura_autologin ou outra variável de link se existir uma específica
            ],
            
            // === SEGURANÇA ===
            'password_changed' => [
                'cliente' => zapcel_trans('client_full_name'), // Correspondente a 'Nome completo do cliente'
                'nova_senha' => zapcel_trans('new_password_var'),
                'data_alteracao' => zapcel_trans('modification_date_var')
            ],
            
            // === EMAIL/SISTEMA ===
            'email_presend' => [
                'assunto' => zapcel_trans('subject_var'), // Assunto do email
                'mensagem' => zapcel_trans('ticket_message_var'), // Mensagem do email
                'tipo_servico' => zapcel_trans('service_type_var'),
                'dominio' => zapcel_trans('domain_var'),
                'nome_produto' => zapcel_trans('product_name_var'),
                'ip_dedicado' => zapcel_trans('dedicated_ip_var'),
                'usuario' => zapcel_trans('username_var'),
                'senha' => zapcel_trans('password_var')
            ],
            
            'email_replaced' => [
                'assunto' => zapcel_trans('subject_var'), // Assunto do email
                'tipo_servico' => zapcel_trans('service_type_var')
            ]
        ];
    }

    /**
     * Substitui variáveis no template
     * 
     * @param string $template Template original
     * @param array $variables Variáveis para substituir
     * @return string Template com variáveis substituídas
     */
    private function replaceVariables(string $template, array $variables): string
    {
        $result = $template;
        
        foreach ($variables as $key => $value) {
            if ($key === 'qr_code_url') {
                continue; // ✅ Ignora qr_code_url
            }
            // Suporte a {variavel} e {{variavel}}
            $placeholders = [
                '{' . $key . '}',
                '{{' . $key . '}}',
                '[' . $key . ']'
            ];
            foreach ($placeholders as $placeholder) {
                $result = str_replace($placeholder, $value, $result);
            }
        }
        
        return $result;
    }

    /**
     * Aplica formatação específica para WhatsApp
     * 
     * @param string $message Mensagem a formatar
     * @return string Mensagem formatada
     */
    private function applyFormatting(string $message): string
    {
        // Remove espaços em branco desnecessários
        $message = preg_replace('/[ \t]+/u', ' ', $message);
        $message = preg_replace('/\n\s*\n\s*\n/u', "\n\n", $message);
        
        // Garante que quebras de linha sejam consistentes
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        
        // Aplica trim em cada linha (mas preserva indentação intencional)
        $lines = explode("\n", $message);
        $lines = array_map('trim', $lines);
        $message = implode("\n", $lines);
        
        return trim($message);
    }

    /**
     * Limpa a mensagem final
     * 
     * @param string $message Mensagem a limpar
     * @return string Mensagem limpa
     */
    private function cleanupMessage(string $message): string
    {
        // Remove variáveis não substituídas (evita {variavel} na mensagem final)
        $message = preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $message);
        $message = preg_replace('/\{\{[a-zA-Z0-9_]+\}\}/', '', $message);
        $message = preg_replace('/\[[a-zA-Z0-9_]+\]/', '', $message);
        
        // Remove linhas vazias excessivas no final
        $message = preg_replace('/\n+$/', "\n", $message);
        
        return trim($message);
    }

    /**
     * Extrai variáveis usadas no template
     * 
     * @param string $template Template a analisar
     * @return array Lista de variáveis encontradas
     */
    private function extractVariablesFromTemplate(string $template): array
    {
        $variables = [];
        
        // Encontra padrões {variavel}, {{variavel}} e [variavel]
        preg_match_all('/\{(\w+)\}/', $template, $matches1);
        preg_match_all('/\{\{(\w+)\}\}/', $template, $matches2);
        preg_match_all('/\[(\w+)\]/', $template, $matches3);
        
        $allMatches = array_merge($matches1[1], $matches2[1], $matches3[1]);
        $variables = array_unique($allMatches);
        
        // Remove o marcador especial de quebra
        $variables = array_filter($variables, function($var) {
            return $var !== 'quebrar_mensagem';
        });
        
        return array_values($variables);
    }

    /**
     * Prepara variáveis específicas para um cliente COMPLETO
     * 
     * @param int $clientId ID do cliente
     * @param string $eventType Tipo de evento
     * @return array Variáveis preparadas
     */
    public function prepareClientVariables(int $clientId, string $eventType = ''): array
    {
        $variables = [];
        
        try {
            $client = Capsule::table('tblclients')
                ->where('id', $clientId)
                ->first();
                
            if ($client) {
                // Dados básicos do cliente
                $variables['cliente'] = trim($client->firstname . ' ' . $client->lastname);
                $variables['nome'] = trim($client->firstname);
                $variables['sobrenome'] = trim($client->lastname);
                $variables['cliente_id'] = $clientId;
                $variables['email'] = $client->email;
                $variables['cliente_primeiro_nome'] = $client->firstname;
                $variables['cliente_sobrenome'] = $client->lastname;
                
                // ✅ TODOS OS DADOS DE ENDEREÇO ADICIONADOS
                $variables['telefone'] = $client->phonenumber ?? '';
                $variables['endereco'] = $client->address1 ?? '';
                $variables['bairro'] = $client->address2 ?? '';
                $variables['cidade'] = $client->city ?? '';
                $variables['estado'] = $client->state ?? '';
                $variables['cep'] = $client->postcode ?? '';
                $variables['pais'] = $client->country ?? '';

                // ✅ BUSCAR CPF/CNPJ NOS CAMPOS PERSONALIZADOS
                $cpfCnpjField = Capsule::table('tblcustomfieldsvalues')
                    ->join('tblcustomfields', 'tblcustomfields.id', '=', 'tblcustomfieldsvalues.fieldid')
                    ->where('tblcustomfieldsvalues.relid', $clientId)
                    ->where(function($query) {
                        $query->where('tblcustomfields.fieldname', 'LIKE', '%cpf%')
                            ->orWhere('tblcustomfields.fieldname', 'LIKE', '%cnpj%');
                    })
                    ->select('tblcustomfieldsvalues.value')
                    ->first();

                $variables['cpf_cnpj'] = $cpfCnpjField->value ?? '0';
                
                // Adiciona dados específicos baseados no evento
                switch ($eventType) {
                    case 'whatsapp_validation':
                        $variables['codigo_verificacao'] = $this->generateValidationCode();
                        $variables['link_validacao'] = rtrim(\App::getSystemUrl(), '/') . '/index.php?m=zapcel';
                        break;
                        
                    case 'client_added':
                        $variables['data_cadastro'] = date('d/m/Y', strtotime($client->datecreated));
                        break;
                }
            }
        } catch (\Exception $e) {
            $this->logError(zapcel_trans('client_variables_error_prefix') . $e->getMessage());
        }
        
        return $variables;
    }

    /**
     * Prepara variáveis específicas para uma fatura COM FORMATAÇÃO INTERNACIONAL
     * 
     * @param int $invoiceId ID da fatura
     * @return array Variáveis preparadas
     */
    public function prepareInvoiceVariables(int $invoiceId): array
    {
        $variables = [];
        
        try {
            $invoice = Capsule::table('tblinvoices')
                ->where('id', $invoiceId)
                ->first();
                
            if ($invoice) {
                $clientId = $invoice->userid;
                
                // ✅ USA FUNÇÃO DO HOOK.PHP PARA FORMATAÇÃO INTERNACIONAL
                if (function_exists('zapcel_get_client_language')) {
                    $lang = zapcel_get_client_language($clientId);
                } else {
                    $lang = 'portuguese';
                }
                
                if (function_exists('zapcel_format_currency')) {
                    $valorFormatado = zapcel_format_currency($invoice->total, $invoiceId, $lang);
                } else {
                    $valorFormatado = 'R$ ' . number_format($invoice->total, 2, ',', '.');
                }
                
                if (function_exists('zapcel_format_date')) {
                    $vencimentoFormatado = zapcel_format_date($invoice->duedate, $lang);
                    $dataEmissaoFormatada = zapcel_format_date($invoice->date, $lang);
                } else {
                    $vencimentoFormatado = date('d/m/Y', strtotime($invoice->duedate));
                    $dataEmissaoFormatada = date('d/m/Y', strtotime($invoice->date));
                }
                
                $variables['numero_fatura'] = $invoiceId;
                $variables['valor'] = $valorFormatado;
                $variables['vencimento'] = $vencimentoFormatado;
                $variables['data_emissao'] = $dataEmissaoFormatada;
                $variables['data_criacao'] = $dataEmissaoFormatada;
                $variables['link_fatura'] = rtrim(\App::getSystemUrl(), '/') . "/viewinvoice.php?id={$invoiceId}";
                $variables['titulo'] = "Fatura #{$invoiceId}";
                
                // Itens da fatura
                $items = Capsule::table('tblinvoiceitems')
                    ->where('invoiceid', $invoiceId)
                    ->get();
                    
                $itemsDescription = '';
                $itemsList = [];
                foreach ($items as $item) {
                    if (function_exists('zapcel_format_currency')) {
                        $itemValue = zapcel_format_currency($item->amount, $invoiceId, $lang);
                    } else {
                        $itemValue = 'R$ ' . number_format($item->amount, 2, ',', '.');
                    }
                    $itemsDescription .= "• {$item->description}: {$itemValue}\n";
                    $itemsList[] = $item->description;
                }
                
                $variables['descricao'] = trim($itemsDescription);
                $variables['itens_fatura'] = implode(', ', $itemsList);
                
                // ✅ Dados de pagamento CORRIGIDOS - usa função do Hook.php
                if (function_exists('zapcel_get_invoice_payment_data')) {
                    $paymentData = zapcel_get_invoice_payment_data($invoiceId);
                } else {
                    $paymentData = $this->getInvoicePaymentDataFallback($invoiceId);
                }
                
                $variables['codigopix'] = $paymentData['pix_code'] ?? '';
                $variables['qr_code_url'] = $paymentData['pix_qrcode'] ?? $paymentData['qr_code_url'] ?? '';
                $variables['linhadigitavel'] = $paymentData['barcode'] ?? '';
                $variables['metodo_pagamento'] = $paymentData['gateway'] ?? '';

                // ✅ LOG para debug
                $this->logDebug('prepareInvoiceVariables', zapcel_trans('invoice_variables_prepared'), [
                    'invoice_id' => $invoiceId,
                    'client_id' => $clientId,
                    'language' => $lang,
                    'currency_format' => $valorFormatado,
                    'date_format' => $dataEmissaoFormatada
                ]);
            }
        } catch (\Exception $e) {
            $this->logError(zapcel_trans('invoice_variables_error_prefix') . $e->getMessage());
        }
        
        return $variables;
    }

    /**
     * Prepara variáveis específicas para um ticket
     * 
     * @param int $ticketId ID do ticket
     * @return array Variáveis preparadas
     */
    public function prepareTicketVariables(int $ticketId): array
    {
        $variables = [];
        
        try {
            $ticket = Capsule::table('tbltickets')
                ->where('id', $ticketId)
                ->first();
                
            if ($ticket) {
                $variables['numero_ticket'] = $ticket->tid;
                $variables['assunto'] = $ticket->title;
                $variables['link_ticket'] = rtrim(\App::getSystemUrl(), '/') . "/viewticket.php?tid={$ticket->tid}&c={$ticket->c}";
                
                // Departamento
                $department = Capsule::table('tblticketdepartments')
                    ->where('id', $ticket->did)
                    ->first();
                $variables['departamento'] = $department ? $department->name : 'Geral';
                
                // Prioridade
                $variables['prioridade'] = $this->getTicketPriorityName($ticket->urgency);
            }
        } catch (\Exception $e) {
            $this->logError(zapcel_trans('ticket_variables_error_prefix') . $e->getMessage());
        }
        
        return $variables;
    }

    /**
     * Prepara variáveis específicas para um serviço
     * 
     * @param int $serviceId ID do serviço
     * @return array Variáveis preparadas
     */
    public function prepareServiceVariables(int $serviceId): array
    {
        $variables = [];
        
        try {
            $service = Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->first();
                
            if ($service) {
                $variables['id_servico'] = $serviceId;
                $variables['dominio'] = $service->domain ?? '';
                $variables['ip_dedicado'] = $service->dedicatedip ?? '';
                
                // Nome do produto/serviço
                $product = Capsule::table('tblproducts')
                    ->where('id', $service->packageid)
                    ->first();
                    
                if ($product) {
                    $variables['servico'] = $product->name;
                    $variables['nome_servico'] = $product->name;
                    $variables['nome_produto'] = $product->name;
                }
                
                // Dados de acesso
                $variables['usuario'] = $service->username ?? '';
                if ($service->password) {
                    if (function_exists('decrypt')) {
                        $variables['senha'] = decrypt($service->password);
                    } else {
                        $variables['senha'] = $service->password;
                    }
                }
                
                // Datas
                if ($service->regdate) {
                    $variables['data_ativacao'] = date('d/m/Y', strtotime($service->regdate));
                }
            }
        } catch (\Exception $e) {
            $this->logError(zapcel_trans('service_variables_error_prefix') . $e->getMessage());
        }
        
        return $variables;
    }

    /**
     * Prepara variáveis específicas para uma cotação
     * 
     * @param int $quoteId ID da cotação
     * @return array Variáveis preparadas
     */
    public function prepareQuoteVariables(int $quoteId): array
    {
        $variables = [];
        
        try {
            $quote = Capsule::table('tblquotes')
                ->where('id', $quoteId)
                ->first();
                
            if ($quote) {
                $variables['numero_cotacao'] = $quoteId;
                $variables['subject_cotacao'] = $quote->subject ?? '';
                
                if (function_exists('zapcel_format_currency')) {
                    $variables['valor_cotacao'] = zapcel_format_currency($quote->total ?? 0);
                } else {
                    $variables['valor_cotacao'] = 'R$ ' . number_format($quote->total ?? 0, 2, ',', '.');
                }
                
                if ($quote->validuntil) {
                    if (function_exists('zapcel_format_date')) {
                        $variables['validade_cotacao'] = zapcel_format_date($quote->validuntil);
                    } else {
                        $variables['validade_cotacao'] = date('d/m/Y', strtotime($quote->validuntil));
                    }
                }
                
                $variables['status_cotacao'] = $this->getQuoteStatusName($quote->stage);
                
                // Itens da cotação
                $items = Capsule::table('tblquoteitems')
                    ->where('quoteid', $quoteId)
                    ->get();
                    
                $itemsDescription = '';
                foreach ($items as $item) {
                    $itemsDescription .= "• {$item->description}\n";
                }
                
                $variables['itens_cotacao'] = trim($itemsDescription);
            }
        } catch (\Exception $e) {
            $this->logError(zapcel_trans('quote_variables_error_prefix') . $e->getMessage());
        }
        
        return $variables;
    }

    /**
     * Gera código de validação para WhatsApp
     * 
     * @param int $length Comprimento do código
     * @return string Código gerado
     */
    private function generateValidationCode(int $length = 6): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $code;
    }

    /**
     * Fallback para dados de pagamento (quando função do Hook.php não está disponível)
     * 
     * @param int $invoiceId ID da fatura
     * @return array Dados de pagamento
     */
    private function getInvoicePaymentDataFallback(int $invoiceId): array
    {
        $data = [
            'pix_code' => '',
            'pix_qrcode' => '',
            'barcode' => '',
            'gateway' => 'unknown'
        ];
        
        try {
            // Tenta obter dados PIX da IUGU
            $pixData = Capsule::table('iugupix')
                ->where('invoice', $invoiceId)
                ->first();
                
            if ($pixData) {
                $data['pix_code'] = $pixData->qrcode_text ?? '';
                $data['pix_qrcode'] = $pixData->qrcode ?? '';
                $data['barcode'] = $pixData->digitable_line ?? $pixData->barcode ?? '';
                $data['gateway'] = 'iugupix';
            }
            
        } catch (\Exception $e) {
            // Silencia erro
        }
        
        return $data;
    }

    /**
     * Obtém nome da prioridade do ticket
     * 
     * @param string $priority Código da prioridade
     * @return string Nome da prioridade
     */
    private function getTicketPriorityName(string $priority): string
    {
        $priorities = [
            'Low' => 'Baixa',
            'Medium' => 'Média',
            'High' => 'Alta',
            'Critical' => 'Crítica'
        ];
        
        return $priorities[$priority] ?? $priority;
    }

    /**
     * Obtém nome do status da cotação
     * 
     * @param string $status Código do status
     * @return string Nome do status
     */
    private function getQuoteStatusName(string $status): string
    {
        $statuses = [
            'Draft' => 'Rascunho',
            'Delivered' => 'Entregue',
            'On Hold' => 'Em Espera',
            'Accepted' => 'Aceita',
            'Lost' => 'Perdida',
            'Dead' => 'Cancelada'
        ];
        
        return $statuses[$status] ?? $status;
    }

    /**
     * Sanitiza texto para evitar problemas de encoding
     * 
     * @param string $text Texto a sanitizar
     * @return string Texto sanitizado
     */
    public function sanitizeText(string $text): string
    {
        // Remove caracteres de controle exceto quebras de linha
        $text = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Converte para UTF-8 se necessário
        if (!mb_detect_encoding($text, 'UTF-8', true)) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        // Normaliza quebras de linha
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        return trim($text);
    }

    /**
     * NOVO MÉTODO v2.0.1: Divide parte que contém {qr_code_url}
     * 
     * Separa a parte em: texto antes + imagem + texto depois
     * Respeita a ORDEM DO TEMPLATE (dinâmica)
     * 
     * @param string $part Parte da mensagem contendo {qr_code_url}
     * @return array Array de partes estruturadas
     */
    private function splitQrCodePart(string $part): array
    {
        $result = [];
        
        // Divide pela variável {qr_code_url}
        $segments = explode('{qr_code_url}', $part);
        
        // PARTE 1: Texto ANTES do QR Code (se existir)
        if (isset($segments[0])) {
            $textBefore = $this->applyFormatting($segments[0]);
            $textBefore = $this->cleanupMessage($textBefore);
            
            if (!empty(trim($textBefore))) {
                $result[] = [
                    'type' => 'text',
                    'content' => $textBefore
                ];
            }
        }
        
        // PARTE 2: QR Code (IMAGEM)
        // A URL será substituída no hooks.php pela variável real
        $result[] = [
            'type' => 'image',
            'content' => '{qr_code_url}', // Marcador que será substituído
            'caption' => 'QR Code PIX'
        ];
        
        // PARTE 3: Texto DEPOIS do QR Code (se existir)
        if (isset($segments[1])) {
            $textAfter = $this->applyFormatting($segments[1]);
            $textAfter = $this->cleanupMessage($textAfter);
            
            if (!empty(trim($textAfter))) {
                $result[] = [
                    'type' => 'text',
                    'content' => $textAfter
                ];
            }
        }
        
        $this->logDebug('splitQrCodePart', zapcel_trans('qr_code_part_split'), [
            'segments_count' => count($segments),
            'result_parts' => count($result),
            'has_text_before' => isset($segments[0]) && !empty(trim($segments[0])),
            'has_text_after' => isset($segments[1]) && !empty(trim($segments[1]))
        ]);
        
        return $result;
    }

    /**
     * Log de erro
     * 
     * @param string $message Mensagem de erro
     * @return void
     */
    private function logError(string $message): void
    {
        error_log("Zapcel MessageProcessor: " . $message);
        
        // Tenta usar o sistema de logs do módulo se disponível
        if (function_exists('zapcel_log_error')) {
            zapcel_log_error($message);
        }
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
}