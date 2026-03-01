<?php
namespace WHMCS\Module\Addon\Zapcel\Api;

/**
 * Zapcel WHMCS - Validador de Números WhatsApp
 * Especializado apenas em validação de números e códigos
 * 
 * @package    Zapcel
 * @author     Hostcel
 * @version    2.1.1
 */

use WHMCS\Database\Capsule;

use function \zapcel_trans;
use function \zapcel_load_lang;

/**
 * Classe especializada em validação de números WhatsApp
 */
class NumberValidator
{
    private $whatsappAPI;
    private $settings;

    public function __construct($settings)
    {
        $this->settings = $settings;
        $this->whatsappAPI = new WhatsAppAPI($settings);
    }

    /**
     * Valida formato do número de telefone (INTERNACIONAL)
     */
    public function validatePhoneFormat($phoneNumber)
    {
        // Remove tudo que não for número
        $onlyDigits = preg_replace('/\D+/', '', $phoneNumber);

        // Se já começa com 55 ou outro DDI válido
        if (strpos($onlyDigits, '55') !== 0 && strlen($onlyDigits) <= 11) {
            // Se não tem DDI e for número brasileiro, adiciona +55
            $onlyDigits = '55' . $onlyDigits;
        }

        // Verifica se sobrou algo válido
        if (strlen($onlyDigits) < 10 || strlen($onlyDigits) > 15) {
            return false;
        }

        zapcel_log_debug('VALIDATOR 1', '📞 validatePhoneFormat()', [
            'input_original' => $phoneNumber,
            'clean_final' => '+' . $onlyDigits
        ]);

        // Retorna no formato E.164
        return '+' . $onlyDigits;
    }

    /**
     * Inicia processo de validação para um cliente
     */
    public function initiateValidation($clientId, $phoneNumber)
    {
        try {
        
            // Usa a validação do WhatsAppAPI para consistência
            zapcel_log_debug('VALIDATOR', '🚀 Antes de enviar código (sendVerificationCode)', [
                'formattedNumber' => $formattedNumber,
                'verificationCode' => $verificationCode
            ]);

            $validationResult = $this->whatsappAPI->validatePhoneNumber($phoneNumber);

            zapcel_log_debug('VALIDATOR', '🧩 Após WhatsAppAPI::validatePhoneNumber()', [
                'numero_enviado_para_api' => $phoneNumber,
                'retorno_clean_number' => $validationResult['clean_number'] ?? null
            ]);
            
            if (!$validationResult['success']) {
                throw new \Exception($validationResult['error']);
            }

            $formattedNumber = $validationResult['clean_number'];

            // Verifica se já existe validação pendente
            $existingValidation = $this->getValidationByClient($clientId);
            if ($existingValidation && $existingValidation->status == 'pending' && !empty($existingValidation->verification_code)) {
                // Reenvia o código existente se ainda estiver válido
                if (strtotime($existingValidation->updated_at) > strtotime('-15 minutes')) {
                    // Reenvia o código existente
                    // Limpa número antes de reenviar
                    $cleanNumber = preg_replace('/[^\d+]/', '', $existingValidation->phone_number);

                    // Atualiza número limpo na tabela
                    Capsule::table('mod_zapcel_validation')
                        ->where('client_id', $clientId)
                        ->update(['phone_number' => $cleanNumber]);

                    // Reenvia o código com número limpo
                    $sendResult = $this->sendVerificationCode($cleanNumber, $existingValidation->verification_code, $clientId);
                    
                    if ($sendResult['success']) {
                        // Registra log de reenvio
                        $apiResponse = is_string($sendResult) ? json_decode($sendResult, true) : $sendResult;
                        $this->logValidationAction($clientId, zapcel_trans('validation_resent'), zapcel_trans('log_code_resent'), [
                            'phone_number' => $cleanNumber,
                            'api_response' => $apiResponse ?? []
                        ]);
                        
                        return [
                            'success' => true,
                            'message' => zapcel_trans('validation_code_resent_success')
                        ];
                    }
                }
            }

            // Gera código de verificação
            $verificationCode = $this->generateVerificationCode();

            // Salva/atualiza registro de validação
            $this->saveValidationRecord($clientId, $formattedNumber, $verificationCode);

            // Envia código via WhatsApp
            $sendResult = $this->sendVerificationCode($formattedNumber, $verificationCode, $clientId);

            if (!$sendResult['success']) {
                throw new \Exception($sendResult['error'] ?? zapcel_trans('send_code_failed'));
            }

            // Registra log

            $apiResponse = is_string($sendResult) ? json_decode($sendResult, true) : $sendResult;
            $this->logValidationAction($clientId, zapcel_trans('validation_sent'), zapcel_trans('log_validation_code_sent'), [
                'phone_number' => $formattedNumber, 
                'verification_code' => $verificationCode,
                'api_response' => $apiResponse ?? []
            ]);

            return [
                'success' => true,
                'message' => zapcel_trans('validation_code_sent_success'),
                'verification_code' => $verificationCode,
                'phone_number' => $formattedNumber
            ];

        } catch (\Exception $e) {
            // Registra erro no log
            $this->logValidationAction($clientId, zapcel_trans('validation_failed'), zapcel_trans('log_send_code_failed_prefix'), false, [
                'phone_number' => $formattedNumber ?? $phoneNumber ?? 'N/A',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Valida código de verificação
     */
    public function verifyCode($clientId, $code)
    {
        try {
            // Busca validação pendente
            $validation = $this->getValidationByClient($clientId);
            
            if (!$validation) {
                throw new \Exception(zapcel_trans('validation_not_found'));
            }
            
            if ($validation->status != 'pending') {
                throw new \Exception(zapcel_trans('validation_already_processed'));
            }

            // Verifica expiração do código (15 minutos)
            if (strtotime($validation->updated_at) < strtotime('-15 minutes')) {
                $this->updateValidationStatus($clientId, 'expired');
                return [
                    'success' => false,
                    'error' => zapcel_trans('code_expired'),
                    'expired' => true
                ];
            }

            // Verifica tentativas
            if ($validation->attempts >= 5) {
                $this->updateValidationStatus($clientId, 'blocked');
                throw new \Exception(zapcel_trans('too_many_attempts_blocked'));
            }

            // Verifica código
            if ($validation->verification_code !== $code) {
                // Incrementa tentativas
                $this->incrementValidationAttempts($clientId);
                
                $attemptsLeft = 5 - ($validation->attempts + 1);
                
                if ($attemptsLeft <= 0) {
                    $this->updateValidationStatus($clientId, 'blocked');
                    throw new \Exception(zapcel_trans('too_many_attempts_blocked'));
                }

                throw new \Exception(str_replace('{attempts}', $attemptsLeft, zapcel_trans('invalid_code_attempts_left')));
            }

            // Validação bem-sucedida
            $this->updateValidationStatus($clientId, 'validated');

            // Garantir que o numero esta limpo
            $cleanNumber = preg_replace('/[^\d+]/', '', $validation->phone_number);

            // Atualiza telefone do cliente se necessário
            $this->updateClientPhoneNumber($clientId, $cleanNumber);
            
            // Registra log de sucesso
            $apiResponse = is_string($sendResult) ? json_decode($sendResult, true) : $sendResult;
            $this->logValidationAction($clientId, zapcel_trans('validation_success'), zapcel_trans('log_whatsapp_validated'), [
                'phone_number' => $cleanNumber,
                'code' => $code,
                'status' => 'validated',
                'api_response' => $apiResponse ?? []
            ]);

            return [
                'success' => true,
                'message' => zapcel_trans('whatsapp_validated_success'),
                'phone_number' => $cleanNumber
            ];

        } catch (\Exception $e) {
            // Registra erro no log
            $validation = $this->getValidationByClient($clientId);
            $this->logValidationAction($clientId, zapcel_trans('validation_failed'), zapcel_trans('log_validation_failed_prefix'), false, [
                'client_id' => $clientId,
                'phone_number' => $validation->phone_number ?? 'N/A',
                'code' => $code,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém status da validação do cliente
     */
    public function getValidationStatus($clientId)
    {
        try {
            $validation = $this->getValidationByClient($clientId);
            
            if (!$validation) {
                return [
                    'status' => 'not_started',
                    'message' => zapcel_trans('validation_not_started')
                ];
            }

            $cleanNumber = preg_replace('/[^\d+]/', '', $validation->phone_number);

            return [
                'status' => $validation->status,
                'phone_number' => $cleanNumber,
                'attempts' => $validation->attempts,
                'last_attempt' => $validation->updated_at,
                'is_expired' => strtotime($validation->updated_at) < strtotime('-15 minutes')
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Reenvia código de verificação
     */
    public function resendCode($clientId)
    {
        try {
            $validation = $this->getValidationByClient($clientId);
            
            if (!$validation) {
                throw new \Exception(zapcel_trans('validation_none_found'));
            }

            // Gera novo código
            $newCode = $this->generateVerificationCode();

            // Atualiza registro
            $this->updateValidationCode($clientId, $newCode);
            
            // Limpa e atualiza número na tabela
            $cleanNumber = preg_replace('/[^\d+]/', '', $validation->phone_number);

            Capsule::table('mod_zapcel_validation')
                ->where('client_id', $clientId)
                ->update(['phone_number' => $cleanNumber]);

            $sendResult = $this->sendVerificationCode($cleanNumber, $newCode, $clientId);

            if (!$sendResult['success']) {
                throw new \Exception($sendResult['error'] ?? zapcel_trans('resend_code_failed'));
            }

            // Registra log
            $apiResponse = is_string($sendResult) ? json_decode($sendResult, true) : $sendResult;
            $this->logValidationAction($clientId, zapcel_trans('validation_resent'), zapcel_trans('log_code_resent'), [
                'phone_number' => $cleanNumber,
                'api_response' => $apiResponse ?? []
            ]);

            return [
                'success' => true,
                'message' => zapcel_trans('code_resent_success')
            ];

        } catch (\Exception $e) {
            $validation = $this->getValidationByClient($clientId);
            $this->logValidationAction($clientId, zapcel_trans('validation_failed'), zapcel_trans('log_resend_failed_prefix'), false, [
                'phone_number' => $validation->phone_number ?? 'N/A',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // ========== MÉTODOS PRIVADOS ESPECÍFICOS DA VALIDAÇÃO ==========

    /**
     * Gera código de verificação de 6 dígitos
     */
    private function generateVerificationCode()
    {
        try {
            return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            return sprintf('%06d', mt_rand(0, 999999)); // fallback
        }
    }

    /**
     * Obtém registro de validação do cliente
     */
    private function getValidationByClient($clientId)
    {
        return Capsule::table('mod_zapcel_validation')
            ->where('client_id', $clientId)
            ->first();
    }

    /**
     * Salva registro de validação
     */
    private function saveValidationRecord($clientId, $phoneNumber, $verificationCode)
    {
        $existing = $this->getValidationByClient($clientId);

        if ($existing) {
            // Atualiza registro existente
            Capsule::table('mod_zapcel_validation')
                ->where('client_id', $clientId)
                ->update([
                    'phone_number' => preg_replace('/[^\d+]/', '', $phoneNumber),
                    'verification_code' => $verificationCode,
                    'status' => 'pending',
                    'attempts' => 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            // Cria novo registro
            Capsule::table('mod_zapcel_validation')
                ->insert([
                    'client_id' => $clientId,
                    'phone_number' => preg_replace('/[^\d+]/', '', $phoneNumber),
                    'verification_code' => $verificationCode,
                    'status' => 'pending',
                    'attempts' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        }
    }

    /**
     * Atualiza status da validação
     */
    private function updateValidationStatus($clientId, $status)
    {
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Se status é 'validated', atualiza também o campo validated
        if ($status === 'validated') {
            $updateData['validated'] = 1;
            $updateData['validated_at'] = date('Y-m-d H:i:s');
        }
        
        Capsule::table('mod_zapcel_validation')
            ->where('client_id', $clientId)
            ->update($updateData);
    }

    /**
     * Incrementa contador de tentativas
     */
    private function incrementValidationAttempts($clientId)
    {
        Capsule::table('mod_zapcel_validation')
            ->where('client_id', $clientId)
            ->increment('attempts');
    }

    /**
     * Atualiza código de verificação
     */
    private function updateValidationCode($clientId, $newCode)
    {
        Capsule::table('mod_zapcel_validation')
            ->where('client_id', $clientId)
            ->update([
                'verification_code' => $newCode,
                'attempts' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Atualiza número de telefone do cliente no WHMCS
     */
    /*private function updateClientPhoneNumber($clientId, $phoneNumber)
    {
        try {
            // Remove o +55 para salvar no formato do WHMCS
            $formattedForWhmcs = preg_replace('/^\+55/', '', $phoneNumber);
            
            Capsule::table('tblclients')
                ->where('id', $clientId)
                ->update([
                    'phonenumber' => $formattedForWhmcs
                ]);

        } catch (\Exception $e) {
            // Não falha a validação se não conseguir atualizar o telefone
            $this->logSystemAction('phone_update_failed', 'Falha ao atualizar telefone do cliente: ' . $e->getMessage(), [
                'client_id' => $clientId,
                'phone_number' => $phoneNumber
            ]);
        }
    }*/

    /**
     * Atualiza número de telefone do cliente no WHMCS
     * Formata no padrão WHMCS: +55.81 99407-7774 (COM ESPAÇO!)
     */
    private function updateClientPhoneNumber($clientId, $phoneNumber)
    {
        try {
            // Remove tudo exceto números
            $cleanNumber = preg_replace('/\D/', '', $phoneNumber);
            
            // Detecta se é número brasileiro (começa com 55)
            if (substr($cleanNumber, 0, 2) === '55') {
                // Formato Brasil: +55.XX XXXXX-XXXX ou +55.XX XXXX-XXXX
                $ddi = substr($cleanNumber, 0, 2);   // 55
                $ddd = substr($cleanNumber, 2, 2);   // 81
                $resto = substr($cleanNumber, 4);     // 994077774
                
                // Verifica se tem 9 dígitos (celular com 9º dígito)
                if (strlen($resto) === 9) {
                    $parte1 = substr($resto, 0, 5);  // 99407
                    $parte2 = substr($resto, 5);      // 7774
                    $formatted = "+{$ddi}.{$ddd} {$parte1}-{$parte2}";  // COM ESPAÇO!
                } else {
                    // Fixo ou celular antigo (8 dígitos)
                    $parte1 = substr($resto, 0, 4);
                    $parte2 = substr($resto, 4);
                    $formatted = "+{$ddi}.{$ddd} {$parte1}-{$parte2}";  // COM ESPAÇO!
                }
            } else {
                // Outros países: formato genérico +XX XXXXXXXXXX
                $ddi = substr($cleanNumber, 0, 2);
                $resto = substr($cleanNumber, 2);
                $formatted = "+{$ddi} {$resto}";  // COM ESPAÇO!
            }
            
            Capsule::table('tblclients')
                ->where('id', $clientId)
                ->update([
                    'phonenumber' => $formatted
                ]);
                
            // Registra no log do módulo
            $apiResponse = ['success' => true];
            $this->logValidationAction($clientId, zapcel_trans('phone_updated'), zapcel_trans('log_phone_updated_whmcs'), [
                'phone_number' => $cleanNumber,
                'old_format' => $phoneNumber,
                'new_format' => $formatted,
                'api_response' => $apiResponse ?? []
            ]);
            
        } catch (\Exception $e) {
            // Registra erro no log do módulo
            $this->logValidationAction($clientId, zapcel_trans('phone_update_error'), zapcel_trans('log_phone_update_error'), false, [
                'phone_number' => $phoneNumber ?? null,
                'old_format' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Envia código de verificação via WhatsApp
     */
    private function sendVerificationCode($phoneNumber, $verificationCode, $clientId = null)
    {
        // Carrega template de validação
        $template = $this->getValidationTemplate();
        
        // Prepara variáveis
        $clientData = $clientId ? $this->getClientData($clientId) : [];
        $variables = array_merge($clientData, [
            'codigo_validacao'   => $verificationCode,
            'codigo_verificacao' => $verificationCode,
            'provedor' => $this->getProviderName()
        ]);

        // Processa template
        $message = $this->processTemplate($template, $variables);

        // Envia via API (usa WhatsAppAPI para não duplicar)
        return $this->whatsappAPI->sendMessage($phoneNumber, $message, [
            'type' => 'validation',
            'client_id' => $clientId,
            'verification_code' => $verificationCode
        ]);
    }

    /**
     * Obtém template de validação
     */
    private function getValidationTemplate()
    {
        // Tenta carregar template personalizado
        $customTemplate = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'whatsapp_validation')
            ->where('active', 1)
            ->value('template');

        if ($customTemplate) {
            return $customTemplate;
        }

        // Template padrão
        return zapcel_trans('validation_template_default');
    }

    /**
     * Processa template com variáveis
     */
    private function processTemplate($template, $variables)
    {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    /**
     * Obtém dados do cliente
     */
    private function getClientData($clientId)
    {
        $client = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->first();

        if (!$client) {
            return [];
        }

        return [
            'cliente' => $client->firstname . ' ' . $client->lastname,
            'cliente_nome' => $client->firstname,
            'cliente_sobrenome' => $client->lastname,
            'cliente_email' => $client->email
        ];
    }

    /**
     * Obtém nome do provedor
     */
    private function getProviderName()
    {
        return Capsule::table('tblconfiguration')
            ->where('setting', 'CompanyName')
            ->value('value') ?: zapcel_trans('provider_default_name');
    }

    /**
     * Mascara número de telefone para logs
     */
    private function maskPhoneNumber($phoneNumber)
    {
        return substr($phoneNumber, 0, 6) . '****' . substr($phoneNumber, -4);
    }

    /**
     * Registra ação de validação no log
     */
    private function logValidationAction($clientId, $type, $message, $details = [], $failed = true)
    {
        try {
            Capsule::table('mod_zapcel_logs')->insert([
                'client_id' => $clientId,
                'event_type' => $type,
                'phone_number' => $details['phone_number'] ?? null,
                'message' => $message,
                'success' => $failed === false ? 0 : 1,
                'response' => !empty($details) ? json_encode($details) : null,
                'message_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Falha silenciosa, mas registra erro básico
            error_log("Zapcel Log Error: " . $e->getMessage());
        }
    }

    /**
     * Registra ação do sistema no log
     */
    private function logSystemAction($type, $message, $details = [])
    {
        Capsule::table('mod_zapcel_logs')->insert([
            'client_id' => null,
            'event_type' => $type,
            'phone_number' => null,
            'message' => $message,
            'success' => strpos($type, 'failed') === false ? 1 : 0,
            'response' => !empty($details) ? json_encode($details) : null,
            'message_id' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Estatísticas de validação
     */
    public function getValidationStatistics()
    {
        try {
            $total = Capsule::table('mod_zapcel_validation')->count();
            $validated = Capsule::table('mod_zapcel_validation')->where('status', 'validated')->count();
            $pending = Capsule::table('mod_zapcel_validation')->where('status', 'pending')->count();
            $invalid = Capsule::table('mod_zapcel_validation')->where('status', 'invalid')->count();

            // Validações dos últimos 7 dias
            $recentValidations = Capsule::table('mod_zapcel_validation')
                ->where('updated_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
                ->selectRaw('DATE(updated_at) as date, status, COUNT(*) as count')
                ->groupBy('date', 'status')
                ->get();

            return [
                'success' => true,
                'statistics' => [
                    'total' => $total,
                    'validated' => $validated,
                    'pending' => $pending,
                    'invalid' => $invalid,
                    'validation_rate' => $total > 0 ? round(($validated / $total) * 100, 2) : 0,
                    'recent_validations' => $recentValidations
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Reseta validação do cliente
     */
    public function resetValidation($clientId)
    {
        try {
            Capsule::table('mod_zapcel_validation')
                ->where('client_id', $clientId)
                ->delete();

            // Registra log
            $apiResponse = is_string($sendResult) ? json_decode($sendResult, true) : $sendResult;
            $this->logValidationAction($clientId, zapcel_trans('validation_reset'), zapcel_trans('log_validation_reset'), [
                'phone_number' => $validation->phone_number,
                'api_response' => $apiResponse ?? []
            ]);

            return [
                'success' => true,
                'message' => zapcel_trans('validation_reset_success'),
                'phone_number' => $validation->phone_number,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Envia códigos de validação em massa para todos os clientes pendentes
     */
    public function sendPendingValidations($limit = 50)
    {
        try {
            // Busca todos os clientes com status 'pending'
            $pendingValidations = Capsule::table('mod_zapcel_validation')
                ->where('status', 'pending')
                ->limit($limit)
                ->get();

            $total = count($pendingValidations);
            $success = 0;
            $failed = 0;
            $errors = [];

            foreach ($pendingValidations as $validation) {
                $result = $this->resendCode($validation->client_id);
                
                if ($result['success']) {
                    $success++;
                } else {
                    $failed++;
                    $errors[] = [
                        'client_id' => $validation->client_id,
                        'error' => $result['error'] ?? 'Unknown error'
                    ];
                }
            }

            return [
                'success' => true,
                'results' => [
                    'total' => $total,
                    'success' => $success,
                    'failed' => $failed,
                    'errors' => $errors
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

}
