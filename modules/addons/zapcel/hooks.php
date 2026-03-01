<?php
/**
 * Zapcel WHMCS - Hooks Consolidados
 * Todos os hooks do sistema em um único arquivo organizado
 *
 * @package    Zapcel
 * @author     Hostcel
 * @copyright  2017-2025 Hostcel
 * @version    2.0.0
 */

// Bloqueia acesso direto
if (!defined('WHMCS')) {
    die('Acesso não autorizado.');
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Zapcel\Api\WhatsAppAPI;
use WHMCS\Module\Addon\Zapcel\Api\MessageProcessor;

// Função auxiliar para obter textos traduzidos (SEM carregar arquivo toda vez)
function zapcel_trans($key) {
    static $zapcel_lang_strings = null;
    
    // Carrega idioma apenas uma vez (lazy loading)
    if ($zapcel_lang_strings === null) {
        $zapcel_language_setting = Capsule::table('tbladdonmodules')
            ->where('module', 'zapcel')
            ->where('setting', 'language')
            ->value('value') ?? 'portuguese';
        
        $langFile = $zapcel_language_setting === 'english' 
            ? __DIR__ . '/langs/en.php' 
            : __DIR__ . '/langs/pt.php';
        
        if (file_exists($langFile)) {
            $zapcel_lang_strings = include $langFile;
        } else {
            $zapcel_lang_strings = [];
        }
    }
    
    return $zapcel_lang_strings[$key] ?? $key;
}

function zapcel_load_lang() {
    static $lang_cache = null;
    
    if ($lang_cache !== null) {
        return $lang_cache;
    }
    
    try {
        $zapcel_language_setting = Capsule::table('tbladdonmodules')
            ->where('module', 'zapcel')
            ->where('setting', 'language')
            ->value('value') ?? 'portuguese';
        
        $langFile = $zapcel_language_setting === 'english' 
            ? __DIR__ . '/langs/en.php' 
            : __DIR__ . '/langs/pt.php';
        
        if (file_exists($langFile)) {
            $lang_cache = include $langFile;
        } else {
            $lang_cache = [];
        }
    } catch (\Exception $e) {
        $lang_cache = [];
    }
    
    return $lang_cache;
}

/**
 * ════════════════════════════════════════════════════════════════════════
 * ADICIONAR ESTE CÓDIGO LOGO APÓS A LINHA 73 DO SEU hooks.php
 * (Depois do fechamento da função zapcel_load_lang)
 * ════════════════════════════════════════════════════════════════════════
 */

/**
 * ========================================================================
 * PROCESSAMENTO AJAX - VALIDAÇÃO WHATSAPP
 * ========================================================================
 */
/*if (!empty($_POST) && isset($_POST['acao'])) {
    
    // Validação Whatsapp - Enviar Código
    if ($_POST['acao'] == 'validarWhatsapp') {
        
        require_once __DIR__ . '/api/NumberValidator.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';
        
        try {
            // Configurações do módulo
            $settings = Capsule::table('tbladdonmodules')
                ->where('module', 'zapcel')
                ->pluck('value', 'setting')
                ->toArray();

            // Gera código de 6 dígitos
            $codigo_validacao = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            $Numero = $_POST["country-calling-code-phonenumber"] . $_POST["phonenumber"];
            $Numero_Whatsapp = ltrim(preg_replace('/\D/', '', $Numero), 0);
            $iddocliente = $_POST["id"];

            if ($iddocliente == $_SESSION["uid"]) {

                // Deleta código anterior
                try {
                    Capsule::table("mod_zapcel_validation")
                        ->where("client_id", $iddocliente)
                        ->delete();
                } catch (\Throwable $th) {}

                // Insere novo código
                try {
                    Capsule::table("mod_zapcel_validation")->insert([
                        "client_id" => $iddocliente,
                        "phone_number" => $Numero_Whatsapp,
                        "verification_code" => $codigo_validacao,
                        "validated" => 0,
                        "attempts" => 0,
                        "created_at" => date('Y-m-d H:i:s')
                    ]);
                    
                } catch (\Exception $e) {
                    echo '<div class="alert alert-danger" role="alert">Erro ao gerar código: ' . $e->getMessage() . '</div>';
                    exit;
                }

                // Envia código via WhatsApp
                $validator = new \WHMCS\Module\Addon\Zapcel\Api\NumberValidator($settings);
                $result = $validator->initiateValidation($iddocliente, '+' . $Numero_Whatsapp);

                if ($result['success']) {
                    echo '<div class="texto" id="div_retorno">
                        <h1>Código Enviado</h1>
                        <p>Por favor, informe o código de 6 dígitos enviado agora.</p>

                        <form method="POST" id="validarWhatsapp" onSubmit="sendValidarWhatsapp();return false;">
                            <input type="hidden" name="acao" value="validarCodigo">
                            <input class="input_codigo_validacao" type="text" name="codigo" id="codigo" placeholder="000000" maxlength="6">
                            <button class="btn btn-lg btn-block btn-primary" type="submit">Validar agora</button>
                        </form>
                    </div>';
                } else {
                    echo '<div class="alert alert-danger" role="alert">' . ($result['error'] ?? 'Erro ao enviar código') . '</div>';
                }

            }
            
        } catch (\Throwable $th) {
            echo '<div class="alert alert-danger" role="alert">Erro: ' . $th->getMessage() . '</div>';
        }
        
        exit;
    }

    // Validação Whatsapp - Validar Código
    if ($_POST['acao'] == 'validarCodigo') {

        try {
            $settings = Capsule::table('tbladdonmodules')
                ->where('module', 'zapcel')
                ->pluck('value', 'setting')
                ->toArray();
        } catch (\Throwable $th) {
            echo '<div class="alert alert-danger" role="alert">Erro ao carregar configurações.</div>';
            exit;
        }

        $CodigoBancoDados = Capsule::table('mod_zapcel_validation')
            ->where('client_id', $_SESSION["uid"])
            ->first();

        if ($CodigoBancoDados && $CodigoBancoDados->client_id == $_SESSION["uid"]) {

            if (strtoupper($CodigoBancoDados->verification_code) == strtoupper($_POST["codigo"])) {

                try {
                    // Atualiza status de validação
                    Capsule::table('mod_zapcel_validation')
                        ->where('client_id', $_SESSION["uid"])
                        ->update([
                            "validated" => 1,
                            "validated_at" => date('Y-m-d H:i:s')
                        ]);

                    // Atualiza telefone no WHMCS
                    Capsule::table('tblclients')
                        ->where('id', $_SESSION["uid"])
                        ->update([
                            "phonenumber" => '+' . ltrim(preg_replace('/\D/', '', $CodigoBancoDados->phone_number), 0)
                        ]);

                    echo '<style>
                        .voltar {
                            color: "#25D366" !important;
                            text-decoration: none !important;
                        }
                        .voltar:hover {
                            color: "#128C7E" !important;
                        }
                    </style>
                    <div class="texto" id="div_retorno">
                        <h1>✅ Validado com Sucesso!</h1>
                        <p>Seu WhatsApp foi validado. Você receberá notificações importantes.</p>
                        <br><br>
                        <a class="voltar" href="index.php"><i class="fal fa-arrow-circle-left"></i> Voltar para área do cliente</a>
                    </div>';
        
                } catch (\Throwable $th) {
                    echo '<div class="alert alert-danger" role="alert">Erro ao validar: ' . $th->getMessage() . '</div>';
                }

            } else {

                echo '<div class="alert alert-danger" role="alert">
                    Código incorreto. Tente novamente.
                </div>
                <form method="POST" id="validarWhatsapp" onSubmit="sendValidarWhatsapp();return false;">
                    <input type="hidden" name="acao" value="validarCodigo">
                    <input class="input_codigo_validacao" type="text" name="codigo" id="codigo" placeholder="000000" maxlength="6">
                    <button class="btn btn-lg btn-block btn-primary" type="submit">Validar agora</button>
                </form>';

            }

        }
        
        exit;
    }

}*/

/**
 * ========================================================================
 * INCLUSÃO DE BIBLIOTECAS
 * ========================================================================
 */

// Função para incluir SweetAlert2 nos headers
function zapcel_include_sweetalert2() {
    $html = '
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    
    <style>
    .swal2-popup {
        font-size: 1.6rem !important;
    }
    .swal2-title {
        font-size: 2.0rem !important;
    }
    .swal2-content {
        font-size: 1.6rem !important;
    }
    .swal2-confirm {
        font-size: 1.4rem !important;
        padding: 1rem 2rem !important;
    }
    </style>';
    
    return $html;
}

/**
 * ========================================================================
 * HOOKS DE EVENTOS
 * ========================================================================
 */

/**
 * HOOK: Cliente Adicionado - TESTADO
 * Envia mensagem de boas-vindas para novos clientes
 */
add_hook('ClientAdd', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $clientId = $vars['client_id'];

        if (!zapcel_validate_before_send($clientId, $settings, 'client_added')) {
            return;
        }

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'client_added')
            ->where('active', true)
            ->first();

        if (!$template) return;

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        // USO DAS VARIÁVEIS PADRÃO COMPLETAS
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'email' => $client->email,
            'data_cadastro' => zapcel_format_date(date('Y-m-d'), $lang),
        ];

        $variables = array_merge($eventVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, 'client_added', $phoneNumber, ($lang['new_client_log'] ?? 'new_client_log'), true, json_encode($result));
            } else {
                zapcel_log_message($clientId, 'client_added', $phoneNumber, ($lang['new_client_log'] ?? 'new_client_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['client_add_hook_error'] ?? 'client_add_hook_error') . $e->getMessage());
    }
});

/**
 * HOOK: Senha Alterada - TESTADO OK
 */
add_hook('UserChangePassword', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        if (empty($vars['userid'])) {
            zapcel_log_error('UserChangePassword: userid não encontrado nos vars: ' . json_encode($vars));
            return;
        }

        $userId = $vars['userid'];

        // ✅ SEGUINDO A LÓGICA DO EXEMPLO: Buscar client_id via tblusers_clients
        $userClient = Capsule::table('tblusers_clients')
            ->where('auth_user_id', $userId)
            ->first();

        if (!$userClient) {
            zapcel_log_error("Relação usuário-cliente não encontrada para userid {$userId}");
            
            // ✅ TENTATIVA ALTERNATIVA: Buscar direto na tblusers
            $user = Capsule::table('tblusers')
                ->where('id', $userId)
                ->first();
                
            if ($user && !empty($user->client_id)) {
                $clientId = $user->client_id;
                zapcel_log_debug('password_changed', ($lang['client_id_found_via_tblusers'] ?? 'client_id_found_via_tblusers'), [
                    'userid' => $userId,
                    'client_id' => $clientId
                ]);
            } else {
                zapcel_log_error("Não foi possível encontrar client_id para o usuário ID {$userId}");
                return;
            }
        } else {
            $clientId = $userClient->client_id;
            zapcel_log_debug('password_changed', ($lang['client_id_found_via_tblusers_clients'] ?? 'client_id_found_via_tblusers_clients'), [
                'userid' => $userId,
                'client_id' => $clientId
            ]);
        }

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'password_changed')
            ->where('active', true)
            ->first();

        if (!$template) {
            zapcel_log_error(($lang["template_password_changed_not_found"] ?? "template_password_changed_not_found"));
            return;
        }

        // ✅ Busca cliente
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            zapcel_log_error("Cliente ID {$clientId} não encontrado");
            return;
        }

        // ✅ DEBUG: Log do cliente encontrado
        zapcel_log_debug('password_changed', ($lang['client_found'] ?? 'client_found'), [
            'client_id' => $clientId,
            'client_name' => $client->firstname . ' ' . $client->lastname,
            'client_email' => $client->email
        ]);

        // ✅ VALIDAÇÃO WHATSAPP (com log)
        if (!zapcel_validate_before_send($clientId, $settings, 'password_changed')) {
            return;
        }

        // ✅ VARIÁVEIS COM SENHA (como solicitado)
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'data_alteracao' => date('d/m/Y H:i'),
            'senha' => $vars['password'],
            'usuario' => $client->email
        ];

        $variables = array_merge($eventVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if (!$phoneNumber) {
            zapcel_log_error("Número de telefone não encontrado para cliente {$clientId}");
            return;
        }

        // ✅ DEBUG: Log antes do envio
        zapcel_log_debug('password_changed', ($lang['preparing_send'] ?? 'preparing_send'), [
            'client_id' => $clientId,
            'phone_number' => $phoneNumber,
            'template_used' => $template->name,
            'message_parts' => count($messageParts)
        ]);

        $api = new WhatsAppAPI($settings);
        $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

        // ✅ LOG DETALHADO (com senha no log como solicitado)
        if ($result['success']) {
            // ✅ TRATAR O RETORNO DA API - SUBSTITUIR MENSAGEM POR ($lang["confidential"] ?? "confidential")
            $sanitizedResult = $result;
            if (isset($sanitizedResult['results'])) {
                foreach ($sanitizedResult['results'] as &$resultItem) {
                    if (isset($resultItem['results'])) {
                        foreach ($resultItem['results'] as &$part) {
                            if (isset($part['data']['status']) && 
                                isset($part['data']['message']['message'])) {
                                // ✅ SUBSTITUIR CONTEÚDO DA MENSAGEM POR ($lang["confidential"] ?? "confidential")
                                $part['data']['message']['message'] = ($lang['confidential'] ?? 'confidential');
                            }
                        }
                    }
                }
            }
            zapcel_log_message($clientId, 'password_changed', $phoneNumber, 
                ($lang["password_changed_log"] ?? "password_changed_log"), 
                true, json_encode($sanitizedResult));
            
            zapcel_log_debug('password_changed', ($lang['password_notification_sent_successfully'] ?? 'password_notification_sent_successfully'), [
                'client_id' => $clientId,
                'password_in_message' => true,
                'password_in_log' => true,
                'message_sent' => true,
                'sanitized_response' => true
            ]);
        } else {
            zapcel_log_message($clientId, 'password_changed', $phoneNumber, ($lang['password_changed_log'] ?? 'password_changed_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            
            zapcel_log_debug('password_changed', ($lang['error_sending_notification'] ?? 'error_sending_notification'), [
                'client_id' => $clientId,
                'error' => json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'),
            ]);
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['user_change_password_hook_error'] ?? 'user_change_password_hook_error') . $e->getMessage());
        zapcel_log_debug('password_changed', ($lang['detailed_exception'] ?? 'detailed_exception'), [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

/**
 * HOOK: Fatura Criada - MELHORADO
 * Notifica cliente sobre nova fatura com formatação internacional
 */
add_hook('InvoiceCreated', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $invoiceId = $vars['invoiceid'];

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'invoice_created')
            ->where('active', true)
            ->first();

        if (!$template) return;

        $invoice = Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->first();

        if (!$invoice) return;

        $clientId = $invoice->userid;
        
        // VALIDAÇÃO WHATSAPP
        if (!zapcel_validate_before_send($clientId, $settings, 'invoice_created')) {
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        // OBTÉM IDIOMA DO CLIENTE PARA FORMATAÇÃO
        //$lang = zapcel_get_client_language($clientId);
        
        // VARIÁVEIS BASE
        $baseVariables = zapcel_get_default_variables($clientId, $settings);

        // PREPARA VARIÁVEIS DA FATURA
        $invoiceVariables = $processor->prepareInvoiceVariables($invoiceId);

        // VARIÁVEIS ESPECÍFICAS DO EVENTO
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'data_criacao' => zapcel_format_date($invoice->date, $lang),
        ];

        // MESCLA VARIÁVEIS ESPECÍFICAS COM VARIÁVEIS DA FATURA
        $eventVariables = array_merge($eventVariables, $invoiceVariables);

        // ADICIONA AUTOLOGIN
        $autologinVars = zapcel_get_autologin_invoice_variables($clientId, $invoiceId);
        $eventVariables = array_merge($eventVariables, $autologinVars);

        // COMBINA COM VARIÁVEIS BASE (BASE POR ÚLTIMO!)
        $variables = array_merge($eventVariables, $baseVariables);

        
        // GARANTE VARIÁVEIS OBRIGATÓRIAS
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            // CORREÇÃO v2.0.1: Usa nova função que processa texto e imagem corretamente
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, 'invoice_created', $phoneNumber, ($lang['invoice_created_log'] ?? 'invoice_created_log'), true, json_encode($result));
                zapcel_log_debug('invoice_created', ($lang['invoice_created_notification_sent'] ?? 'invoice_created_notification_sent'), [
                    'client_id' => $clientId,
                    'invoice_id' => $invoiceId,
                    'currency_format' => $variables['valor'],
                    'date_format' => $variables['data_criacao'],
                    'items_count' => count(explode(',', $variables['itens_fatura']))
                ]);
            } else {
                zapcel_log_message($clientId, 'invoice_created', $phoneNumber, ($lang['invoice_created_log'] ?? 'invoice_created_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['invoice_created_hook_error'] ?? 'invoice_created_hook_error') . $e->getMessage());
    }
});

/**
 * Garante que todas as variáveis obrigatórias existam
 */
function zapcel_ensure_required_variables($variables, $clientId) {
    $lang = zapcel_load_lang();
    $required = [
        'url_whmcs', 'telefone', 'endereco', 'bairro', 'cidade', 
        'estado', 'cep', 'pais', 'ip_dedicado'
    ];
    
    foreach ($required as $var) {
        if (!isset($variables[$var])) {
            $variables[$var] = '';
            zapcel_log_debug('required_variables', ($lang['variable_defined_as_empty'] ?? 'variable_defined_as_empty'), [
                'client_id' => $clientId,
                'variable' => $var,
                'reason' => 'not_found'
            ]);
        }
    }
    
    return $variables;
}

/**
 * Obtém configuração de idioma do módulo ZapCel
 */
function zapcel_get_client_language($clientId) {
    $lang = zapcel_load_lang();
    try {
        $languageSetting = Capsule::table('tbladdonmodules')
            ->where('module', 'zapcel')
            ->where('setting', 'language')
            ->value('value');

        return $languageSetting ?? 'portuguese';
    } catch (\Exception $e) {
        return 'portuguese';
    }
}

/**
 * Formata data conforme idioma
 */
function zapcel_format_date($date, $lang = null) {
    // Carrega o idioma se não foi passado
    if ($lang === null) {
        $lang = zapcel_load_lang();
    }
    
    // Garante que $lang seja string
    if (is_array($lang)) {
        $lang = 'portuguese'; // Se veio array, foda-se, usa português
    }
    
    // Define idioma padrão
    if (!$lang || empty($lang)) {
        $lang = 'portuguese';
    }

    // Verifica se é uma data inválida do WHMCS
    if ($date === '0000-00-00' || $date === '0000-00-00 00:00:00' || empty($date)) {
        return '-';
    }
    
    $timestamp = strtotime($date);
    if (!$timestamp) return $date;
    
    $day = date('d', $timestamp);
    $month = date('m', $timestamp); 
    $year = date('Y', $timestamp);
    
    if (strtolower($lang) === 'portuguese') {
        return "{$day}/{$month}/{$year}";
    } else {
        return "{$month}/{$day}/{$year}";
    }
}

/**
 * Formata valor monetário
 */
function zapcel_format_currency($value, $invoiceId = null, $lang = null) {
    $lang = zapcel_load_lang();
    // Usa a função nativa do WHMCS como fallback
    if (function_exists('formatCurrency')) {
        return formatCurrency($value);
    }
    
    // Fallback manual
    if (!$lang || $lang === 'portuguese') {
        return 'R$ ' . number_format($value, 2, ',', '.');
    } else {
        return '$ ' . number_format($value, 2, '.', ',');
    }
}

/**
 * HOOK: Fatura Cancelada - NOVO
 * Notifica cliente sobre cancelamento de fatura
 */
add_hook('InvoiceCancelled', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $invoiceId = $vars['invoiceid'];

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'invoice_cancelled')
            ->where('active', true)
            ->first();

        if (!$template) {
            zapcel_log_debug('InvoiceCancelled', ($lang['template_not_found'] ?? 'template_not_found'), ['invoice_id' => $invoiceId]);
            return;
        }

        $invoice = Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->first();

        if (!$invoice) {
            zapcel_log_error(($lang['invoice_cancelled_invoice_not_found_prefix'] ?? 'invoice_cancelled_invoice_not_found_prefix') . $invoiceId);
            return;
        }

        $clientId = $invoice->userid;
        
        // VALIDAÇÃO WHATSAPP
        if (!zapcel_validate_before_send($clientId, $settings, 'invoice_cancelled')) {
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        // OBTÉM IDIOMA DO CLIENTE
        //$lang = zapcel_get_client_language($clientId);
        
        // PREPARA VARIÁVEIS DA FATURA (similar ao InvoiceCreated)
        $invoiceVariables = $processor->prepareInvoiceVariables($invoiceId);
        
        // VARIÁVEIS BASE
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        // VARIÁVEIS ESPECÍFICAS DO CANCELAMENTO
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'data_cancelamento' => zapcel_format_date(date('Y-m-d'), $lang),
            'motivo_cancelamento' => $vars['reason'] ?? ($lang['not_specified'] ?? 'not_specified'),
            'valor' => zapcel_format_currency($invoice->total, $invoiceId, $lang),
        ];

        // COMBINA VARIÁVEIS
        $variables = array_merge($eventVariables, $invoiceVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, 'invoice_cancelled', $phoneNumber, ($lang['invoice_cancelled_log'] ?? 'invoice_cancelled_log'), true, json_encode($result));
                zapcel_log_debug('invoice_cancelled', ($lang['invoice_cancelled_notification_sent'] ?? 'invoice_cancelled_notification_sent'), [
                    'client_id' => $clientId,
                    'invoice_id' => $invoiceId,
                    'cancel_reason' => $variables['motivo_cancelamento']
                ]);
            } else {
                zapcel_log_message($clientId, 'invoice_cancelled', $phoneNumber, ($lang['invoice_cancelled_log'] ?? 'invoice_cancelled_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['invoice_cancelled_hook_error'] ?? 'invoice_cancelled_hook_error') . $e->getMessage());
        zapcel_log_debug('InvoiceCancelled', ($lang['exception'] ?? 'exception'), [
            'invoice_id' => $invoiceId,
            'error' => $e->getMessage()
        ]);
    }
});

/**
 * Hook EmailPreSend - Detecta tipo de evento baseado no template de email
 * Armazena em variáveis globais para uso nos outros hooks
 */
add_hook('EmailPreSend', 1, function($vars) {
    global $zapcel_current_reminder_num;
    global $zapcel_current_service_type;
    global $zapcel_current_event_type;
    
    $messagename = $vars['messagename'] ?? '';
    $relid = $vars['relid'] ?? 0;
    
    // ========================================
    // DETECÇÃO DE LEMBRETES DE FATURA
    // ========================================
    if (stripos($messagename, 'Invoice Payment Reminder') !== false ||
        stripos($messagename, 'Lembrete de Pagamento') !== false) {
        // Lembrete normal (antes do vencimento)
        $zapcel_current_reminder_num = 0; // 0 = lembrete normal
        $zapcel_current_event_type = 'invoice_reminder';
        
    } elseif (stripos($messagename, 'First') !== false && 
            (stripos($messagename, 'Overdue') !== false || stripos($messagename, 'Vencida') !== false)) {
        // 1º aviso de fatura vencida
        $zapcel_current_reminder_num = 1;
        $zapcel_current_event_type = 'invoice_reminder_1';
        
    } elseif (stripos($messagename, 'Second') !== false && 
            (stripos($messagename, 'Overdue') !== false || stripos($messagename, 'Vencida') !== false)) {
        // 2º aviso de fatura vencida
        $zapcel_current_reminder_num = 2;
        $zapcel_current_event_type = 'invoice_reminder_2';
        
    } elseif (stripos($messagename, 'Third') !== false && 
            (stripos($messagename, 'Overdue') !== false || stripos($messagename, 'Vencida') !== false)) {
        // 3º aviso (final) de fatura vencida
        $zapcel_current_reminder_num = 3;
        $zapcel_current_event_type = 'invoice_reminder_3';
    }
    
    // ========================================
    // DETECÇÃO DE ATIVAÇÃO DE SERVIÇOS
    // ========================================
    elseif (stripos($messagename, 'Hosting Account Welcome') !== false ||
            stripos($messagename, 'Hospedagem') !== false) {
        // Ativação de hospedagem
        $zapcel_current_service_type = 'hosting';
        $zapcel_current_event_type = 'service_activated_hosting';
        
    } elseif (stripos($messagename, 'Reseller Account Welcome') !== false ||
            stripos($messagename, 'Revenda') !== false) {
        // Ativação de revenda
        $zapcel_current_service_type = 'reseller';
        $zapcel_current_event_type = 'service_activated_reseller';
        
    } elseif (stripos($messagename, 'Dedicated/VPS Server Welcome') !== false ||
            stripos($messagename, 'VPS') !== false ||
            stripos($messagename, 'Dedicado') !== false) {
        // Ativação de VPS/Dedicado
        $zapcel_current_service_type = 'vps';
        $zapcel_current_event_type = 'service_activated_vps';
        
    } elseif (stripos($messagename, 'Other Product/Service Welcome') !== false ||
            stripos($messagename, 'Outro') !== false) {
        // Ativação de outros serviços
        $zapcel_current_service_type = 'other';
        $zapcel_current_event_type = 'service_activated_other';
    }
    
    // ========================================
    // DETECÇÃO DE SUSPENSÃO/REATIVAÇÃO
    // ========================================
    elseif (stripos($messagename, 'Service Suspension') !== false ||
            stripos($messagename, 'Suspensão') !== false) {
        // Suspensão de serviço
        $zapcel_current_event_type = 'service_suspended';
        
    } elseif (stripos($messagename, 'Service Unsuspension') !== false ||
            stripos($messagename, 'Reativação') !== false) {
        // Reativação de serviço
        $zapcel_current_event_type = 'service_unsuspended';
    }
    
    // ========================================
    // DETECÇÃO DE OUTROS SERVIÇOS (OPCIONAL)
    // ========================================
    elseif (stripos($messagename, 'Cloud') !== false) {
        $zapcel_current_service_type = 'cloud';
        $zapcel_current_event_type = 'service_activated_cloud';
    }
    elseif (stripos($messagename, 'Domain') !== false || stripos($messagename, 'Domínio') !== false) {
        $zapcel_current_service_type = 'domain';
        $zapcel_current_event_type = 'service_activated_domain';
    }
    elseif (stripos($messagename, 'Email') !== false && stripos($messagename, 'Welcome') !== false) {
        $zapcel_current_service_type = 'email';
        $zapcel_current_event_type = 'service_activated_email';
    }
    elseif (stripos($messagename, 'SSL') !== false) {
        $zapcel_current_service_type = 'ssl';
        $zapcel_current_event_type = 'service_activated_ssl';
    }
    elseif (stripos($messagename, 'Microsoft') !== false) {
        $zapcel_current_service_type = 'microsoft';
        $zapcel_current_event_type = 'service_activated_microsoft';
    }
    elseif (stripos($messagename, 'Software') !== false) {
        $zapcel_current_service_type = 'software';
        $zapcel_current_event_type = 'service_activated_software';
    }
    elseif (stripos($messagename, 'Consulting') !== false || stripos($messagename, 'Consultoria') !== false) {
        $zapcel_current_service_type = 'consulting';
        $zapcel_current_event_type = 'service_activated_consulting';
    }
    
    // Log para debug (opcional - pode remover em produção)
    if (isset($zapcel_current_event_type)) {
        zapcel_log_debug('email_presend_detection', 'Evento detectado via EmailPreSend', [
            'messagename' => $messagename,
            'relid' => $relid,
            'event_type' => $zapcel_current_event_type,
            'reminder_num' => $zapcel_current_reminder_num ?? null,
            'service_type' => $zapcel_current_service_type ?? null
        ]);
    }
    
    return [];
});

/**
 * Determina tipo de serviço baseado no template de email
 */
function zapcel_determine_service_type($messageType, $clientId) {
    $lang = zapcel_load_lang();
    // Mapeamento básico baseado no tipo de mensagem
    $serviceMapping = [
        'hosting' => ($lang['hosting_account'] ?? 'hosting_account'),
        'reseller' => ($lang['reseller_account'] ?? 'reseller_account'), 
        'server' => ($lang['dedicated_vps_server'] ?? 'dedicated_vps_server'),
        'vps' => ($lang['dedicated_vps_server'] ?? 'dedicated_vps_server'),
        'dedicated' => ($lang['dedicated_vps_server'] ?? 'dedicated_vps_server')
    ];
    
    foreach ($serviceMapping as $key => $type) {
        if (stripos($messageType, $key) !== false) {
            return $type;
        }
    }
    
    // Tenta determinar pelo serviço ativo do cliente
    try {
        $service = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->first();
            
        if ($service) {
            $product = Capsule::table('tblproducts')
                ->where('id', $service->packageid)
                ->first();
                
            if ($product) {
                $productName = strtolower($product->name);
                
                // Verifica por Servidor Dedicado/VPS primeiro
                if (strpos($productName, 'vps') !== false || 
                    strpos($productName, 'dedicado') !== false ||
                    strpos($productName, 'dedicated') !== false ||
                    strpos($productName, 'servidor') !== false) {
                    return ($lang['dedicated_vps_server'] ?? 'dedicated_vps_server');
                }
                // Verifica por Conta de Revenda
                elseif (strpos($productName, 'revenda') !== false || 
                    strpos($productName, 'reseller') !== false) {
                    return ($lang['reseller_account'] ?? 'reseller_account');
                }
                // Verifica por Conta de Hospedagem
                elseif (strpos($productName, 'hospedagem') !== false || 
                    strpos($productName, 'hosting') !== false ||
                    strpos($productName, 'shared') !== false ||
                    strpos($productName, 'web') !== false) {
                    return ($lang['hosting_account'] ?? 'hosting_account');
                }
            }
        }
    } catch (Exception $e) {
        // Log opcional do erro
    }
    
    // Padrão para qualquer outro serviço
    return ($lang['others'] ?? 'others');
}

/**
 * Obtém template configurado para o tipo de serviço
 */
/*function zapcel_get_email_template($serviceType, $settings) {
    $lang = zapcel_load_lang();
    $templateMap = [
        ($lang['hosting'] ?? 'hosting') => $settings['zapcel_template_hosting'] ?? 'email_hosting',
        ($lang['reseller'] ?? 'reseller') => $settings['zapcel_template_reseller'] ?? 'email_reseller',
        'Dedicado/VPS' => $settings['zapcel_template_dedicated'] ?? 'email_dedicated',
        ($lang['other_services'] ?? 'other_services') => $settings['zapcel_template_other'] ?? 'email_general'
    ];
    
    $templateName = $templateMap[$serviceType] ?? 'email_general';
    
    return Capsule::table('mod_zapcel_templates')
        ->where('name', $templateName)
        ->where('active', true)
        ->first();
}*/

/**
 * Prepara variáveis específicas para templates de email
 */
/*function zapcel_prepare_email_variables($vars, $clientId, $serviceType) {
    $lang = zapcel_load_lang();
    $variables = [
        'assunto' => $vars['subject'] ?? '',
        'mensagem' => $vars['message'] ?? '',
        'tipo_servico' => $serviceType,
    ];
    
    // ADICIONA VARIÁVEIS ESPECÍFICAS POR TIPO DE SERVIÇO
    switch ($serviceType) {
        case 'Dedicado/VPS':
            $variables['ip_dedicado'] = zapcel_get_dedicated_ip($clientId);
            $variables['dominio'] = zapcel_get_service_domain($clientId);
            $variables['nome_produto'] = zapcel_get_product_name($clientId);
            break;
            
        case ($lang['hosting'] ?? 'hosting'):
        case ($lang['reseller'] ?? 'reseller'):
            $variables['dominio'] = zapcel_get_service_domain($clientId);
            $variables['nome_produto'] = zapcel_get_product_name($clientId);
            $variables['usuario'] = zapcel_get_service_username($clientId);
            $variables['senha'] = zapcel_get_service_password($clientId);
            break;
            
        default:
            $variables['dominio'] = zapcel_get_service_domain($clientId) ?? '';
            $variables['nome_produto'] = zapcel_get_product_name($clientId) ?? '';
            break;
    }
    
    return $variables;
}*/

/**
 * HOOK: Cotação Criada - SALVA
 * Notifica quando uma cotação é criada
 */
add_hook('QuoteStatusChange', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $quoteId = $vars['quoteid'];

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'quote_created')
            ->where('active', true)
            ->first();

        if (!$template) {
            zapcel_log_debug('QuoteCreated', ($lang['template_not_found'] ?? 'template_not_found'), ['quote_id' => $quoteId]);
            return;
        }

        $quote = Capsule::table('tblquotes')
            ->where('id', $quoteId)
            ->where('stage', 'Draft')
            ->first();

        if (!$quote) {
            zapcel_log_error(($lang['quote_created_quote_not_found_prefix'] ?? 'quote_created_quote_not_found_prefix') . $quoteId);
            return;
        }

        $clientId = $quote->userid;
        
        // VALIDAÇÃO WHATSAPP
        if (!zapcel_validate_before_send($clientId, $settings, 'quote_created')) {
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        // PREPARA VARIÁVEIS DA COTAÇÃO
        $quoteVariables = zapcel_prepare_quote_variables($quoteId);
        
        // VARIÁVEIS BASE
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        // VARIÁVEIS ESPECÍFICAS
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'numero_cotacao' => $quote->id,
            'status_cotacao' => zapcel_get_quote_status($quote->stage),
        ];

        // COMBINA VARIÁVEIS
        $variables = array_merge($eventVariables, $quoteVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, 'quote_created', $phoneNumber, ($lang['quote_created_log'] ?? 'quote_created_log'), true, json_encode($result));
                zapcel_log_debug('QuoteCreated', ($lang['quote_created_notification_sent'] ?? 'quote_created_notification_sent'), [
                    'client_id' => $clientId,
                    'quote_id' => $quoteId,
                    'quote_number' => $variables['numero_cotacao'],
                    'status' => $variables['status_cotacao']
                ]);
            } else {
                zapcel_log_message($clientId, 'quote_created', $phoneNumber, ($lang['quote_created_log'] ?? 'quote_created_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['quote_created_hook_error'] ?? 'quote_created_hook_error') . $e->getMessage());
    }
});

/**
 * HOOK: Cotação Alterada - MODIFICADAS
 * Notifica quando uma cotação é modificada
 */
add_hook('QuoteStatusChange', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $quoteId = $vars['quoteid'];

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'quote_modified')
            ->where('active', true)
            ->first();

        if (!$template) {
            zapcel_log_debug('QuoteModified', ($lang['template_not_found'] ?? 'template_not_found'), ['quote_id' => $quoteId]);
            return;
        }

        $quote = Capsule::table('tblquotes')
            ->where('id', $quoteId)
            ->whereIn('stage', ['Delivered', 'On Hold', 'Lost', 'Dead'])
            ->first();

        if (!$quote) {
            zapcel_log_error(($lang['quote_modified_quote_not_found_prefix'] ?? 'quote_modified_quote_not_found_prefix') . $quoteId);
            return;
        }

        $clientId = $quote->userid;
        
        // VALIDAÇÃO WHATSAPP
        if (!zapcel_validate_before_send($clientId, $settings, 'quote_modified')) {
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        // PREPARA VARIÁVEIS DA COTAÇÃO
        $quoteVariables = zapcel_prepare_quote_variables($quoteId);
        
        // VARIÁVEIS BASE
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        // VARIÁVEIS ESPECÍFICAS
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'numero_cotacao' => $quote->id,
            'status_cotacao' => zapcel_get_quote_status($quote->stage),
            'alteracoes' => $vars['changes'] ?? ($lang['details_updated'] ?? 'details_updated'),
        ];

        // COMBINA VARIÁVEIS
        $variables = array_merge($eventVariables, $quoteVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, 'quote_modified', $phoneNumber, ($lang['quote_updated_log'] ?? 'quote_updated_log'), true, json_encode($result));
            } else {
                zapcel_log_message($clientId, 'quote_modified', $phoneNumber, ($lang['quote_updated_log'] ?? 'quote_updated_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['quote_modified_hook_error'] ?? 'quote_modified_hook_error') . $e->getMessage());
    }
});

/**
 * HOOK: Cotação Aceita - NOVO
 * Notifica quando uma cotação é aceita pelo cliente
 */
add_hook('QuoteAccepted', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $quoteId = $vars['quoteid'];

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'quote_accepted')
            ->where('active', true)
            ->first();

        if (!$template) {
            zapcel_log_debug('QuoteAccepted', ($lang['template_not_found'] ?? 'template_not_found'), ['quote_id' => $quoteId]);
            return;
        }

        $quote = Capsule::table('tblquotes')
            ->where('id', $quoteId)
            ->first();

        if (!$quote) {
            zapcel_log_error(($lang['quote_accepted_quote_not_found_prefix'] ?? 'quote_accepted_quote_not_found_prefix') . $quoteId);
            return;
        }

        $clientId = $quote->userid;
        
        // VALIDAÇÃO WHATSAPP
        if (!zapcel_validate_before_send($clientId, $settings, 'quote_accepted')) {
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        // PREPARA VARIÁVEIS DA COTAÇÃO
        $quoteVariables = zapcel_prepare_quote_variables($quoteId);
        
        // VARIÁVEIS BASE
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        // VARIÁVEIS ESPECÍFICAS
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'numero_cotacao' => $quote->id,
            'status_cotacao' => ($lang['quote_status_accepted'] ?? 'quote_status_accepted'),
            'data_aceitacao' => zapcel_format_date(date('Y-m-d'), $lang),
        ];

        // COMBINA VARIÁVEIS
        $variables = array_merge($eventVariables, $quoteVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, 'quote_accepted', $phoneNumber, ($lang['quote_accepted_log'] ?? 'quote_accepted_log'), true, json_encode($result));
                zapcel_log_debug('QuoteAccepted', ($lang['quote_accepted_notification_sent'] ?? 'quote_accepted_notification_sent'), [
                    'client_id' => $clientId,
                    'quote_id' => $quoteId,
                    'acceptance_date' => $variables['data_aceitacao']
                ]);
            } else {
                zapcel_log_message($clientId, 'quote_accepted', $phoneNumber, ($lang['quote_accepted_log'] ?? 'quote_accepted_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['quote_accepted_hook_error'] ?? 'quote_accepted_hook_error') . $e->getMessage());
    }
});

/**
 * Prepara variáveis específicas para cotações
 */
function zapcel_prepare_quote_variables($quoteId) {
    $lang = zapcel_load_lang();
    $variables = [];
    
    try {
        $quote = Capsule::table('tblquotes')
            ->where('id', $quoteId)
            ->first();
            
        if ($quote) {
            $variables['numero_cotacao'] = $quote->id;
            $variables['subject_cotacao'] = $quote->subject ?? '';
            $variables['valor_cotacao'] = zapcel_format_currency($quote->total ?? 0);
            $variables['validade_cotacao'] = zapcel_format_date($quote->validuntil ?? '0000-00-00', $lang);
            $variables['status_cotacao'] = zapcel_get_quote_status($quote->stage);
            
            // Itens da cotação
            $quoteItems = Capsule::table('tblquoteitems')
                ->where('quoteid', $quoteId)
                ->get();
                
            $itemsDescription = '';
            foreach ($quoteItems as $item) {
                $itemsDescription .= "• {$item->description}\n";
            }
            
            $variables['itens_cotacao'] = trim($itemsDescription);
        }
    } catch (Exception $e) {
        zapcel_log_error(($lang['error_preparing_quote_variables_prefix'] ?? 'error_preparing_quote_variables_prefix') . $e->getMessage());
    }
    
    return $variables;
}

/**
 * Obtém status da cotação formatado
 */
function zapcel_get_quote_status($stage) {
    $lang = zapcel_load_lang();
    $statusMap = [
        'Draft' => ($lang['quote_status_draft'] ?? 'quote_status_draft'),
        'Delivered' => ($lang['quote_status_delivered'] ?? 'quote_status_delivered'),
        'On Hold' => ($lang['quote_status_on_hold'] ?? 'quote_status_on_hold'),
        'Accepted' => ($lang['quote_status_accepted'] ?? 'quote_status_accepted'),
        'Lost' => ($lang['quote_status_lost'] ?? 'quote_status_lost'),
        'Dead' => ($lang['quote_status_dead'] ?? 'quote_status_dead')
    ];
    
    return $statusMap[$stage] ?? $stage;
}

/**
 * HOOK: Solicitação de Cancelamento - NOVO
 * Notifica quando um cliente solicita cancelamento de serviço
 */
add_hook('CancellationRequest', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $serviceId = $vars['relid'] ?? 0;
        $cancellationId = $vars['cancellationid'] ?? 0;

        if (!$serviceId) {
            zapcel_log_error(($lang['cancellation_request_service_id_not_provided'] ?? 'cancellation_request_service_id_not_provided'));
            return;
        }

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'cancellation_request')
            ->where('active', true)
            ->first();

        if (!$template) {
            zapcel_log_debug('CancellationRequest', ($lang['template_not_found'] ?? 'template_not_found'), ['service_id' => $serviceId]);
            return;
        }

        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if (!$service) {
            zapcel_log_error(($lang['cancellation_request_service_not_found_prefix'] ?? 'cancellation_request_service_not_found_prefix') . $serviceId);
            return;
        }

        $clientId = $service->userid;
        
        // VALIDAÇÃO WHATSAPP
        if (!zapcel_validate_before_send($clientId, $settings, 'cancellation_request')) {
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->first();

        // OBTÉM DADOS DA SOLICITAÇÃO DE CANCELAMENTO
        $cancellationData = zapcel_get_cancellation_data($cancellationId, $serviceId);
        
        // VARIÁVEIS BASE
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        // VARIÁVEIS ESPECÍFICAS
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'id_servico' => $serviceId,
            'nome_servico' => $product ? $product->name : ($lang['service'] ?? 'service'),
            'razao_cancelamento' => $cancellationData['reason'] ?? ($lang['not_specified'] ?? 'not_specified'),
            'tipo_cancelamento' => $cancellationData['type'] ?? ($lang['cancellation_type_immediate'] ?? 'cancellation_type_immediate'),
            'data_solicitacao' => zapcel_format_date(date('Y-m-d'), $lang),
            'dominio' => $service->domain ?? '',
        ];

        // COMBINA VARIÁVEIS
        $variables = array_merge($eventVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, 'cancellation_request', $phoneNumber, ($lang['cancellation_request_log'] ?? 'cancellation_request_log'), true, json_encode($result));
                zapcel_log_debug('CancellationRequest', ($lang['cancellation_request_notified'] ?? 'cancellation_request_notified'), [
                    'client_id' => $clientId,
                    'service_id' => $serviceId,
                    'service_name' => $variables['nome_servico'],
                    'cancellation_reason' => $variables['razao_cancelamento'],
                    'cancellation_type' => $variables['tipo_cancelamento']
                ]);
            } else {
                zapcel_log_message($clientId, 'cancellation_request', $phoneNumber, ($lang['cancellation_request_log'] ?? 'cancellation_request_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['cancellation_request_hook_error'] ?? 'cancellation_request_hook_error') . $e->getMessage());
    }
});

/**
 * Obtém dados da solicitação de cancelamento
 */
function zapcel_get_cancellation_data($cancellationId, $serviceId) {
    $lang = zapcel_load_lang();
    $data = [
        'reason' => ($lang['not_specified'] ?? 'not_specified'),
        'type' => ($lang['cancellation_type_immediate'] ?? 'cancellation_type_immediate')
    ];
    
    try {
        // Tenta obter da tblcancelrequests
        if ($cancellationId) {
            $cancellation = Capsule::table('tblcancelrequests')
                ->where('id', $cancellationId)
                ->first();
                
            if ($cancellation) {
                $data['reason'] = $cancellation->reason ?? ($lang['not_specified'] ?? 'not_specified');
                $data['type'] = ($cancellation->type == 'End of Billing Period') ? ($lang['cancellation_type_end_of_period'] ?? 'cancellation_type_end_of_period') : ($lang['cancellation_type_immediate'] ?? 'cancellation_type_immediate');
            }
        }
        
        // Fallback para dados do serviço
        if ($data['reason'] == ($lang['not_specified'] ?? 'not_specified')) {
            $service = Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->first();
                
            if ($service && $service->cancelreason) {
                $data['reason'] = $service->cancelreason;
            }
        }
        
        // Padroniza razões comuns
        $reasonMapping = [
            'No Longer Required' => ($lang['no_longer_required'] ?? 'no_longer_required'),
            'Going to a competitor' => ($lang['going_to_competitor'] ?? 'going_to_competitor'),
            'Too expensive' => ($lang['too_expensive'] ?? 'too_expensive'),
            'Technical Issues' => ($lang['technical_issues'] ?? 'technical_issues'),
            'Other' => ($lang['other_reason'] ?? 'other_reason')
        ];
        
        if (isset($reasonMapping[$data['reason']])) {
            $data['reason'] = $reasonMapping[$data['reason']];
        }
        
    } catch (Exception $e) {
        zapcel_log_error(($lang['error_getting_cancellation_data_prefix'] ?? 'error_getting_cancellation_data_prefix') . $e->getMessage());
    }
    
    return $data;
}

/**
 * HOOK: Lembrete de Fatura
 * Envia lembretes de vencimento
 */
add_hook('InvoicePaymentReminder', 1, function($vars) {
    global $zapcel_current_reminder_num;
    global $zapcel_current_event_type;

    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $invoiceId = $vars['invoiceid'];
        
        // USA VARIÁVEL GLOBAL DO EmailPreSend
        $reminderNum = $zapcel_current_reminder_num ?? 1;
        $templateEvent = $zapcel_current_event_type ?? "invoice_reminder_{$reminderNum}";

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', $templateEvent)
            ->where('active', true)
            ->first();

        if (!$template) {
            $template = Capsule::table('mod_zapcel_templates')
                ->where('trigger_event', 'invoice_reminder')
                ->where('active', true)
                ->first();
        }

        if (!$template) return;

        $invoice = Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->first();

        if (!$invoice) return;

        $clientId = $invoice->userid;
        
        // ✅ VALIDAÇÃO WHATSAPP (APENAS UMA VEZ)
        if (!zapcel_validate_before_send($clientId, $settings, "invoice_reminder_{$reminderNum}")) {
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        // USAR VARIÁVEIS PADRÃO
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        $paymentData = zapcel_get_invoice_payment_data($invoiceId);
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'numero_fatura' => $invoiceId,
            'valor' => zapcel_format_currency($invoice->total, $invoiceId),
            'vencimento' => zapcel_format_date($invoice->duedate, $lang),
            'dias_vencimento' => zapcel_get_days_until_due($invoice->duedate),
            'codigopix' => $paymentData['pix_code'] ?? $paymentData['codigopix'] ?? '', /// ALTERAÇÃO MINHA
            'linhadigitavel' => $paymentData['barcode'] ?? $paymentData['linhadigitavel'] ?? '',
            'qr_code_url' => $paymentData['pix_qrcode'] ?? $paymentData['qr_code_url'] ?? '',
            'link_fatura' => rtrim(\App::getSystemUrl(), '/') . "/viewinvoice.php?id={$invoiceId}",
        ];
        
        $autologinVars = zapcel_get_autologin_invoice_variables($clientId, $invoiceId);
        $eventVariables = array_merge($eventVariables, $autologinVars);

        $variables = array_merge($eventVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        // ✅ CORREÇÃO: REMOVIDA VALIDAÇÃO DUPLICADA
        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            // ✅ LOG COMPLETO IGUAL AO INVOICECREATED
            if ($result['success']) {
                zapcel_log_message($clientId, $template->trigger_event, $phoneNumber, ($lang['invoice_reminder_log'] ?? 'invoice_reminder_log'), true, json_encode($result));
                zapcel_log_debug('invoice_reminder', ($lang['reminder_sent'] ?? 'reminder_sent'), [
                    'client_id' => $clientId,
                    'invoice_id' => $invoiceId,
                    'reminder_num' => $reminderNum,
                    'template_event' => $templateEvent
                ]);
            } else {
                zapcel_log_message($clientId, $template->trigger_event, $phoneNumber, ($lang['invoice_reminder_log'] ?? 'invoice_reminder_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['invoice_payment_reminder_hook_error'] ?? 'invoice_payment_reminder_hook_error') . $e->getMessage());
    }
});

/**
 * HOOK: Fatura Paga - ATUALIZADO
 * Confirmação de pagamento com validação moderna
 */
add_hook('InvoicePaid', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $invoiceId = $vars['invoiceid'];

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'invoice_paid')
            ->where('active', true)
            ->first();

        if (!$template) return;

        $invoice = Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->first();

        if (!$invoice) return;

        $clientId = $invoice->userid;
        
        // VALIDAÇÃO WHATSAPP MODERNA
        if (!zapcel_validate_before_send($clientId, $settings, 'invoice_paid')) {
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        // VARIÁVEIS BASE MODERNAS
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'numero_fatura' => $invoiceId,
            'valor' => zapcel_format_currency($invoice->total, $invoiceId),
            'data_pagamento' => zapcel_format_date($invoice->datepaid ?? date('Y-m-d H:i:s'), $lang),
            'metodo_pagamento' => zapcel_get_payment_method($invoiceId),
        ];

        // COMBINA VARIÁVEIS
        $variables = array_merge($eventVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, 'invoice_paid', $phoneNumber, ($lang['invoice_paid_log'] ?? 'invoice_paid_log'), true, json_encode($result));
                zapcel_log_debug('invoice_paid', ($lang['payment_confirmed_notification_sent'] ?? 'payment_confirmed_notification_sent'), [
                    'client_id' => $clientId,
                    'invoice_id' => $invoiceId,
                    'payment_method' => $variables['metodo_pagamento']
                ]);
            } else {
                zapcel_log_message($clientId, 'invoice_paid', $phoneNumber, ($lang['invoice_paid_log'] ?? 'invoice_paid_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['invoice_paid_hook_error'] ?? 'invoice_paid_hook_error') . $e->getMessage());
    }
});

/**
 * HOOK: Ticket Aberto - ATUALIZADO
 * Notifica sobre novo ticket com validação moderna
 */
add_hook('TicketOpen', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $ticketId = $vars['ticketid'];

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'ticket_opened')
            ->where('active', true)
            ->first();

        if (!$template) return;

        $ticket = Capsule::table('tbltickets')
            ->where('id', $ticketId)
            ->first();

        if (!$ticket) return;

        $clientId = $ticket->userid;
        
        // VALIDAÇÃO WHATSAPP MODERNA
        if (!zapcel_validate_before_send($clientId, $settings, 'ticket_opened')) {
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        $department = Capsule::table('tblticketdepartments')
            ->where('id', $ticket->did)
            ->first();

        // VARIÁVEIS BASE MODERNAS
        $baseVariables = zapcel_get_default_variables($clientId, $settings);

        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'numero_ticket' => $ticket->tid,
            'assunto' => $ticket->title,
            'departamento' => $department ? $department->name : ($lang['general'] ?? 'general'),
            'prioridade' => zapcel_get_ticket_priority($ticket->urgency),
            'link_ticket' => rtrim(\App::getSystemUrl(), '/') . "/viewticket.php?tid={$ticket->tid}&c={$ticket->c}",
        ];

        // ADICIONA AUTOLOGIN
        $autologinVars = zapcel_get_autologin_ticket_variables($clientId, $ticketId);
        $eventVariables = array_merge($eventVariables, $autologinVars);

        // COMBINA VARIÁVEIS
        $variables = array_merge($eventVariables, $baseVariables);

        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, 'ticket_opened', $phoneNumber, ($lang['ticket_opened_log'] ?? 'ticket_opened_log'), true, json_encode($result));
                zapcel_log_debug('ticket_opened', ($lang['ticket_opened_notification_sent'] ?? 'ticket_opened_notification_sent'), [
                    'client_id' => $clientId,
                    'ticket_id' => $ticketId,
                    'department' => $variables['departamento'],
                    'priority' => $variables['prioridade']
                ]);
            } else {
                zapcel_log_message($clientId, 'ticket_opened', $phoneNumber, ($lang['ticket_opened_log'] ?? 'ticket_opened_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['ticket_open_hook_error'] ?? 'ticket_open_hook_error') . $e->getMessage());
    }
});

/**
 * HOOK: Resposta no Ticket - ATUALIZADO
 * Notifica sobre resposta no ticket com validação moderna
 */
add_hook('TicketAdminReply', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $ticketId = $vars['ticketid'];

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'ticket_reply')
            ->where('active', true)
            ->first();

        if (!$template) return;

        $ticket = Capsule::table('tbltickets')
            ->where('id', $ticketId)
            ->first();

        if (!$ticket) return;

        $clientId = $ticket->userid;
        
        // VALIDAÇÃO WHATSAPP MODERNA
        if (!zapcel_validate_before_send($clientId, $settings, 'ticket_reply')) {
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        $admin = Capsule::table('tbladmins')
            ->where('id', $vars['adminid'])
            ->first();

        // VARIÁVEIS BASE MODERNAS
        $baseVariables = zapcel_get_default_variables($clientId, $settings);

        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'numero_ticket' => $ticket->tid,
            'assunto' => $ticket->title,
            'atendente' => $admin ? trim($admin->firstname . ' ' . $admin->lastname) : ($lang['our_team'] ?? 'our_team'),
            'link_ticket' => rtrim(\App::getSystemUrl(), '/') . "/viewticket.php?tid={$ticket->tid}&c={$ticket->c}",
        ];

        // ADICIONA AUTOLOGIN
        $autologinVars = zapcel_get_autologin_ticket_variables($clientId, $ticketId);
        $eventVariables = array_merge($eventVariables, $autologinVars);

        // COMBINA VARIÁVEIS
        $variables = array_merge($eventVariables, $baseVariables);

        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, 'ticket_reply', $phoneNumber, ($lang['ticket_replied_log'] ?? 'ticket_replied_log'), true, json_encode($result));
                zapcel_log_debug('ticket_reply', ($lang['ticket_reply_notification_sent'] ?? 'ticket_reply_notification_sent'), [
                    'client_id' => $clientId,
                    'ticket_id' => $ticketId,
                    'admin_name' => $variables['atendente']
                ]);
            } else {
                zapcel_log_message($clientId, 'ticket_reply', $phoneNumber, ($lang['ticket_replied_log'] ?? 'ticket_replied_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['ticket_admin_reply_hook_error'] ?? 'ticket_admin_reply_hook_error') . $e->getMessage());
    }
});

/**
 * HOOK: Serviço Ativado - ATUALIZADO
 * Notifica sobre ativação de serviço com validação moderna
 */
/**
 * HOOK: Serviço Ativado - COM DETECÇÃO DE TIPO
 * Envia notificação quando um serviço é ativado (detecta tipo automaticamente)
 */
add_hook('AfterModuleCreate', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $serviceId = $vars['params']['serviceid'];

        global $zapcel_current_service_type;
        global $zapcel_current_event_type;
        
        // Busca informações do serviço
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();
        
        if (!$service) return;
        
        $clientId = $service->userid;
        
        // Busca informações do produto para determinar o tipo
        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->first();
        
        if (!$product) return;
        
        // Determina o tipo de serviço baseado no grupo de produtos
        $productGroup = Capsule::table('tblproductgroups')
            ->where('id', $product->gid)
            ->first();
        
        // USA TIPO DETECTADO PELO EmailPreSend, ou detecta manualmente como fallback
        $triggerEvent = $zapcel_current_event_type ?? null;
        
        // Se não foi detectado pelo EmailPreSend, detecta manualmente
        if (!$triggerEvent && $productGroup) {
            $groupName = strtolower($productGroup->name);
            $productName = strtolower($product->name);
            
            // Detecta HOSPEDAGEM
            if (strpos($groupName, 'hospedagem') !== false || 
                strpos($groupName, 'hosting') !== false ||
                strpos($groupName, 'compartilhada') !== false ||
                strpos($groupName, 'shared') !== false ||
                strpos($productName, 'hospedagem') !== false ||
                strpos($productName, 'hosting') !== false) {
                $triggerEvent = 'service_activated_hosting';
            }
            // Detecta REVENDA
            elseif (strpos($groupName, 'revenda') !== false || 
                    strpos($groupName, 'reseller') !== false ||
                    strpos($productName, 'revenda') !== false ||
                    strpos($productName, 'reseller') !== false) {
                $triggerEvent = 'service_activated_reseller';
            }
            // Detecta VPS/DEDICADO
            elseif (strpos($groupName, 'vps') !== false || 
                    strpos($groupName, 'dedicado') !== false ||
                    strpos($groupName, 'dedicated') !== false ||
                    strpos($groupName, 'servidor') !== false ||
                    strpos($groupName, 'server') !== false ||
                    strpos($productName, 'vps') !== false ||
                    strpos($productName, 'dedicado') !== false ||
                    strpos($productName, 'servidor') !== false) {
                $triggerEvent = 'service_activated_vps';
            }else {
                $triggerEvent = 'service_activated_other';
            }
        }

        // VALIDAÇÃO WHATSAPP
        if (!zapcel_validate_before_send($clientId, $settings, $triggerEvent)) {
            return;
        }

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);
        
        // Busca template específico, se não encontrar usa o genérico
        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', $triggerEvent)
            ->where('active', true)
            ->first();

        // Fallback para template genérico
        if (!$template) {
            $template = Capsule::table('mod_zapcel_templates')
                ->where('trigger_event', 'service_activated')
                ->where('active', true)
                ->first();
        }

        if (!$template) return;

        // VARIÁVEIS BASE (usa função padrão)
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        // Busca informações do servidor
        $server = Capsule::table('tblservers')
            ->where('id', $service->server)
            ->first();
        
        // VARIÁVEIS ESPECÍFICAS DO EVENTO
        $eventVariables = [
            'servico' => $product->name ?? ($lang['service'] ?? 'service'),
            'dominio' => $service->domain ?? '',
            'data_ativacao' => zapcel_format_date(date('Y-m-d'), $lang),
            'servidor' => $server->hostname ?? '',
        ];

        // COMBINA TODAS AS VARIÁVEIS
        $variables = array_merge($eventVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                zapcel_log_message($clientId, $triggerEvent, $phoneNumber, ($lang['service_activated_log'] ?? 'service_activated_log'), true, json_encode($result));
                zapcel_log_debug($triggerEvent, ($lang['service_activated_notification_sent'] ?? 'service_activated_notification_sent'), [
                    'client_id' => $clientId,
                    'service_id' => $serviceId,
                    'service_type' => $triggerEvent,
                    'product_name' => $product->name
                ]);
            } else {
                zapcel_log_message($clientId, $triggerEvent, $phoneNumber, ($lang['service_activated_log'] ?? 'service_activated_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['after_module_create_hook_error'] ?? 'after_module_create_hook_error') . $e->getMessage());
    }
});

/**
 * Função auxiliar para testar cenários de validação
 */
function zapcel_test_validation_scenarios($clientId, $settings) {
    $lang = zapcel_load_lang();
    $scenarios = [
        'cliente_com_numero_validado' => zapcel_is_whatsapp_validated($clientId, $settings),
        'cliente_sem_numero_validado' => !zapcel_is_whatsapp_validated($clientId, $settings),
        'numero_invalido' => zapcel_validate_phone_number_format($clientId)
    ];
    
    zapcel_log_debug('ValidationTest', ($lang['validation_scenarios'] ?? 'validation_scenarios'), [
        'client_id' => $clientId,
        'scenarios' => $scenarios
    ]);
    
    return $scenarios;
}

/**
 * Valida formato do número de telefone
 */
function zapcel_validate_phone_number_format($clientId) {
    $lang = zapcel_load_lang();
    try {
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client || empty($client->phonenumber)) {
            return false;
        }
        
        $phone = zapcel_format_phone_number($client->phonenumber);
        return preg_match('/^\+\d{1,3}\d{4,14}$/', $phone);
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * HOOK: Cliente Editado - NOVO
 * Notifica quando os dados do cliente são alterados
 */
add_hook('ClientEdit', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return;

        $clientId = $vars['userid'];

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'client_edited')
            ->where('active', true)
            ->first();

        if (!$template) {
            zapcel_log_debug('ClientEdit', ($lang['template_not_found'] ?? 'template_not_found'), ['client_id' => $clientId]);
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return;

        // VALIDAÇÃO WHATSAPP
        if (!zapcel_validate_before_send($clientId, $settings, 'client_edited')) {
            return;
        }

        // OBTÉM ALTERAÇÕES (simplificado - em produção, comparar com dados anteriores)
        $changes = zapcel_get_client_changes($clientId, $vars);
        
        // VARIÁVEIS BASE
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        // VARIÁVEIS ESPECÍFICAS
        $eventVariables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'email' => $client->email,
            'telefone' => $client->phonenumber ?? '',
            'endereco' => $client->address1 ?? '',
            'bairro' => $client->address2 ?? '',
            'cidade' => $client->city ?? '',
            'estado' => $client->state ?? '',
            'cep' => $client->postcode ?? '',
            'pais' => $client->country ?? '',
            'alteracoes' => $changes,
            'data_alteracao' => zapcel_format_date(date('Y-m-d H:i'), $lang),
        ];

        // COMBINA VARIÁVEIS
        $variables = array_merge($eventVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);
        
        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);

        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

            if ($result['success']) {
                // LOG SEGURO - SEM DADOS SENSÍVEIS
                zapcel_log_message($clientId, 'client_edited', $phoneNumber, ($lang['client_data_updated_log'] ?? 'client_data_updated_log'), true, json_encode($result));
                zapcel_log_debug('ClientEdit', ($lang['client_data_updated_log'] ?? 'client_data_updated_log'), [
                    'client_id' => $clientId,
                    'changes_detected' => !empty($changes),
                    'sensitive_data_logged' => false
                ]);
            } else {
                zapcel_log_message($clientId, 'client_edited', $phoneNumber, ($lang['client_data_updated_log'] ?? 'client_data_updated_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
            }
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['client_edit_hook_error'] ?? 'client_edit_hook_error') . $e->getMessage());
    }
});

/**
 * Obtém alterações do cliente (versão simplificada)
 */
function zapcel_get_client_changes($clientId, $vars) {
    $lang = zapcel_load_lang();
    // Em uma implementação real, você compararia com os dados anteriores
    // Esta é uma versão simplificada
    $changes = [];
    
    if (isset($vars['firstname']) || isset($vars['lastname'])) {
        $changes[] = 'Nome';
    }
    if (isset($vars['email'])) {
        $changes[] = 'E-mail';
    }
    if (isset($vars['phonenumber'])) {
        $changes[] = 'Telefone';
    }
    if (isset($vars['address1'])) {
        $changes[] = 'Endereço';
    }
    
    return empty($changes) ? ($lang['info_updated'] ?? 'info_updated') : implode(', ', $changes);
}

/**
 * HOOK: Ações do Admin no Resumo do Cliente - NOVO
 * Adiciona botão para envio manual de mensagem WhatsApp
 */
add_hook('AdminAreaClientSummaryActionLinks', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $clientId = $vars['userid'];
        
        // VERIFICA PERMISSÕES DO ADMIN
        if (!zapcel_check_admin_permissions()) {
            return '';
        }

        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) {
            return '';
        }

        // VERIFICA SE CLIENTE TEM WHATSAPP VALIDADO
        $isValidated = zapcel_is_whatsapp_validated($clientId, $settings);
        $validationStatus = $isValidated ? '✅ Validado' : '❌ Pendente';
        
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        $clientName = $client ? trim($client->firstname . ' ' . $client->lastname) : ($lang['client'] ?? 'client');
        
        $html = '
        <div class="zapcel-admin-panel" style="margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
            <h4 style="margin: 0 0 10px 0; color: #25D366;">
                <i class="fab fa-whatsapp"></i> Zapcel - WhatsApp
            </h4>
            
            <div style="margin-bottom: 10px;">
                <strong>Status:</strong> <span id="zapcelValidationStatus">' . $validationStatus . '</span>
            </div>
            
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-success" onclick="zapcelSendManualMessage(' . $clientId . ')">
                    <i class="fas fa-paper-plane"></i> Enviar Mensagem
                </button>
                
                <button type="button" class="btn btn-sm btn-info" onclick="zapcelViewMessageLog(' . $clientId . ')">
                    <i class="fas fa-history"></i> Ver Histórico
                </button>
                
                ' . (!$isValidated ? '
                <button type="button" class="btn btn-sm btn-warning" onclick="zapcelSendValidation(' . $clientId . ')">
                    <i class="fas fa-check-circle"></i> Enviar Validação
                </button>' : '') . '
            </div>
            
            <div id="zapcelMessageContainer" style="margin-top: 10px; display: none;">
                <textarea id="zapcelManualMessage" class="form-control" rows="3" 
                          placeholder="Digite sua mensagem para ' . $clientName . '..."></textarea>
                <button type="button" class="btn btn-primary btn-sm mt-2" onclick="zapcelConfirmSend(' . $clientId . ')">
                    <i class="fas fa-whatsapp"></i> Enviar via WhatsApp
                </button>
            </div>
        </div>

        <script>
        function zapcelSendManualMessage(clientId) {
            const container = document.getElementById("zapcelMessageContainer");
            container.style.display = container.style.display === "none" ? "block" : "none";
        }
        
        function zapcelConfirmSend(clientId) {
            const message = document.getElementById("zapcelManualMessage").value;
            if (!message.trim()) {
                Swal.fire({
                    title: "Atenção",
                    text: "Por favor, digite uma mensagem.",
                    icon: "warning",
                    confirmButtonColor: "#ffc107",
                    confirmButtonText: "OK"
                });
                return;
            }
            
            Swal.fire({
                title: "Confirmar Envio",
                text: "Enviar esta mensagem via WhatsApp?",
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#25D366",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sim, Enviar",
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostra loading
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = \'<i class="fas fa-spinner fa-spin"></i> Enviando...\';
                    btn.disabled = true;
                    
                    fetch("addonmodules.php?module=zapcel&action=send_manual_message", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: "client_id=" + clientId + "&message=" + encodeURIComponent(message)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: "Sucesso!",
                                text: "Mensagem enviada com sucesso!",
                                icon: "success",
                                confirmButtonColor: "#25D366",
                                confirmButtonText: "OK"
                            });
                            document.getElementById("zapcelManualMessage").value = "";
                            document.getElementById("zapcelMessageContainer").style.display = "none";
                        } else {
                            Swal.fire({
                                title: "Erro",
                                text: "Erro: " + (data.error || "Falha no envio"),
                                icon: "error",
                                confirmButtonColor: "#d33",
                                confirmButtonText: "OK"
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: "Erro",
                            text: "Erro na requisição: " + error,
                            icon: "error",
                            confirmButtonColor: "#d33",
                            confirmButtonText: "OK"
                        });
                    })
                    .finally(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
                }
            });
        }
        
        function zapcelViewMessageLog(clientId) {
            window.open("addonmodules.php?module=zapcel&action=view_logs&client_id=" + clientId, "_blank");
        }
        
        function zapcelSendValidation(clientId) {
            Swal.fire({
                title: "Confirmar Validação",
                text: "Enviar código de validação para o cliente?",
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#25D366",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sim, Enviar",
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("addonmodules.php?module=zapcel&action=send_validation", {
                        method: "POST",
                        body: "client_id=" + clientId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: "Sucesso!",
                                text: "Código de validação enviado!",
                                icon: "success",
                                confirmButtonColor: "#25D366",
                                confirmButtonText: "OK"
                            });
                            document.getElementById("zapcelValidationStatus").textContent = "✅ Validado";
                        } else {
                            Swal.fire({
                                title: "Erro",
                                text: "Erro: " + (data.error || "Falha no envio"),
                                icon: "error",
                                confirmButtonColor: "#d33",
                                confirmButtonText: "OK"
                            });
                        }
                    });
                }
            });
        }
        </script>
        
        <style>
        .zapcel-admin-panel {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .zapcel-admin-panel .btn-group .btn {
            margin-right: 5px;
        }
        </style>';
        
        return $html;

    } catch (Exception $e) {
        zapcel_log_error(($lang['admin_area_client_summary_action_links_hook_error'] ?? 'admin_area_client_summary_action_links_hook_error') . $e->getMessage());
        return '';
    }
});

/**
 * Verifica permissões do administrador
 */
function zapcel_check_admin_permissions() {
    $lang = zapcel_load_lang();
    // Verifica se usuário está logado como admin
    if (!isset($_SESSION['adminid'])) {
        return false;
    }
    
    // Em uma implementação real, verificar permissões específicas
    // Por enquanto, permite para todos os admins logados
    return true;
}

/**
 * HOOK: Botão Flutuante WhatsApp na Área do Cliente - NOVO
 * Adiciona botão flutuante para contato rápido via WhatsApp
 */
add_hook('ClientAreaHeaderOutput', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) return '';

        // VERIFICA SE BOTÃO FLUTUANTE ESTÁ HABILITADO
        if (!($settings['zapcel_floating_button'] == '1')) {
            return '';
        }

        $clientId = $_SESSION['uid'] ?? 0;
        if (!$clientId) {
            return '';
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            return '';
        }

        // OBTÉM NÚMERO DO CLIENTE (apenas para log)
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);
        
        // REGISTRA EXIBIÇÃO DO BOTÃO (apenas se logging estiver habilitado)
        if ($settings['enable_logging'] ?? false) {
            zapcel_log_debug('FloatingButton', ($lang['button_displayed'] ?? 'button_displayed'), [
                'client_id' => $clientId,
                'phone_number' => $phoneNumber ? substr($phoneNumber, 0, 6) . '...' : 'Não encontrado'
            ]);
        }

        $companyName = zapcel_get_company_name();
        $companyPhone = $settings['zapcel_company_phone_full'] ?? '';
        $hideMobile = ($settings['zapcel_hide_mobile'] == '1') ? 'none' : 'block';
        
        $html = '
<!-- Zapcel Floating WhatsApp Button -->
<div id="zapcelFloatingButton" style="position: fixed; bottom: 20px; left: 20px; z-index: 10000;">
    <div id="zapcelButtonMain" style="background: linear-gradient(135deg, #25D366 0%, #25D366 100%); 
        width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; 
        justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
        transition: all 0.3s ease; animation: zapcelPulse 2s infinite;">
        <i class="fab fa-whatsapp" style="color: white; font-size: 28px;"></i>
    </div>
    
    <div id="zapcelButtonLabel" style="position: absolute; bottom: 70px; left: 0; background: white; 
        padding: 8px 12px; border-radius: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
        font-size: 12px; white-space: nowrap; display: none;">
        Fale conosco no WhatsApp!
    </div>
</div>

<style>
@keyframes zapcelPulse {
    0% { transform: scale(1); box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 6px 16px rgba(37, 211, 102, 0.6); }
    100% { transform: scale(1); box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4); }
}

#zapcelButtonMain:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(37, 211, 102, 0.6);
    animation: none;
}

@media (max-width: 768px) {
    #zapcelFloatingButton {
        display: ' . $hideMobile . ' !important;
    }
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const button = document.getElementById("zapcelButtonMain");
    const label = document.getElementById("zapcelButtonLabel");
    
    if (!button) return;
    
    let clickRegistered = false;
    
    // Mostra/oculta label
    button.addEventListener("mouseenter", function() {
        if (label) label.style.display = "block";
    });
    
    button.addEventListener("mouseleave", function() {
        if (label) label.style.display = "none";
    });
    
    // Clique no botão
    button.addEventListener("click", function() {
        if (!clickRegistered) {
            // Registra clique via AJAX (apenas uma vez)
            try {
                fetch("addonmodules.php?module=zapcel&action=log_button_click", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: "client_id=' . $clientId . '&action=floating_button_click"
                }).catch(() => { /* Silencia erros de log */ });
            } catch (e) {
                // Silencia erros
            }
            
            clickRegistered = true;
        }
        
        // Abre WhatsApp
        const message = encodeURIComponent("Olá ' . $companyName . '! Gostaria de mais informações.");
        const companyPhone = "' . $companyPhone . '";
        const whatsappUrl = "https://wa.me/" + companyPhone + "?text=" + message;
        window.open(whatsappUrl, "_blank");
    });
});
</script>';

        return $html;

    } catch (Exception $e) {
        // Silencia erros para não quebrar a página
        return '';
    }
});

/**
 * ========================================================================
 * FUNÇÕES AUXILIARES
 * ========================================================================
 */

/**
 * Obtém configurações do módulo
 *
 * @return array Configurações do módulo
 */
function zapcel_get_settings() {
    $lang = zapcel_load_lang();
    static $settings = null;

    if ($settings === null) {
        $rows = Capsule::table('tbladdonmodules')
            ->where('module', 'zapcel')
            ->get();

        $settings = [
            'zapcel_enabled' => 'on',
            'zapcel_instance_id' => '',
            'zapcel_access_token' => '',
            'zapcel_validation' => '0',
            'zapcel_phone_source' => 'phonenumber',
            'zapcel_signature' => '',
            // ADICIONAR NOVAS CONFIGURAÇÕES
            'zapcel_replace_emails' => '0',
            'zapcel_disable_emails' => '0',
            'zapcel_floating_button' => '0',
            'zapcel_hide_mobile' => '0',
            'log_retention_days' => '30',
            'require_validation' => '0',
            'enable_logging' => '0'
        ];

        foreach ($rows as $row) {
            $settings[$row->setting] = $row->value;
        }

        // Converter para boolean
        $settings['zapcel_enabled'] = ($settings['zapcel_enabled'] === 'on');
        $settings['zapcel_validation'] = (bool)($settings['zapcel_validation'] ?? false);
        $settings['zapcel_replace_emails'] = (bool)($settings['zapcel_replace_emails'] ?? false);
        $settings['zapcel_disable_emails'] = (bool)($settings['zapcel_disable_emails'] ?? false);
        $settings['zapcel_floating_button'] = (bool)($settings['zapcel_floating_button'] ?? false);
        $settings['zapcel_hide_mobile'] = (bool)($settings['zapcel_hide_mobile'] ?? false);
        $settings['require_validation'] = (bool)($settings['require_validation'] ?? false);
        $settings['enable_logging'] = (bool)($settings['enable_logging'] ?? false);
    }

    return $settings;
}

/**
 * Obtém número de telefone do cliente
 *
 * @param int $clientId ID do cliente
 * @param array $settings Configurações do módulo
 * @return string|null Número de telefone formatado
 */
function zapcel_get_client_phone_number($clientId, $settings)
{
    $lang = zapcel_load_lang();
    if ($settings['zapcel_phone_source'] !== 'phonenumber') {
        $customField = Capsule::table('tblcustomfields')
            ->where('fieldname', $settings['zapcel_phone_source'])
            ->first();

        if ($customField) {
            $customValue = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $customField->id)
                ->where('relid', $clientId)
                ->first();

            if ($customValue && !empty($customValue->value)) {
                return zapcel_format_phone_number($customValue->value);
            }
        }
    }

    $client = Capsule::table('tblclients')->where('id', $clientId)->first();
    if ($client && !empty($client->phonenumber)) {
        return zapcel_format_phone_number($client->phonenumber);
    }

    return null;
}

/**
 * Formata número de telefone para padrão internacional
 *
 * @param string $phone Número de telefone
 * @return string Número formatado
 */
function zapcel_format_phone_number($phone)
{
    $lang = zapcel_load_lang();
    $clean = preg_replace('/[^\d]/', '', $phone);

    if (strlen($clean) === 13 && substr($clean, 0, 2) === '55') {
        return '+' . $clean;
    } elseif (strlen($clean) === 11) {
        return '+55' . $clean;
    } elseif (strlen($clean) === 10) {
        return '+55' . $clean;
    } elseif (strlen($clean) > 11 && substr($clean, 0, 1) !== '+') {
        return '+' . $clean;
    }

    return $clean;
}

/**
 * Verifica se WhatsApp está validado para o cliente
 *
 * @param int $clientId ID do cliente
 * @param array $settings Configurações do módulo
 * @return bool True se validado ou validação desativada
 */
function zapcel_is_whatsapp_validated($clientId, $settings)
{
    $lang = zapcel_load_lang();
    if (!$settings['require_validation']) {
        return true;
    }

    $validation = Capsule::table('mod_zapcel_validation')
        ->where('client_id', $clientId)
        ->where('validated', true)
        ->first();

    return $validation !== null;
}

/**
 * Log de mensagens enviadas
 *
 * @param int $clientId ID do cliente
 * @param string $eventType Tipo de evento
 * @param string $phoneNumber Número de telefone
 * @param string $message Mensagem enviada
 * @param bool $success Status do envio
 * @param mixed $response Resposta da API
 * @return void
 */
function zapcel_log_message($clientId, $eventType, $phoneNumber, $message, $success, $response = null, $quickReplyNumber = null)
{
    $lang = zapcel_load_lang();
    try {
        Capsule::table('mod_zapcel_logs')->insert([
            'client_id' => $clientId,
            'event_type' => $eventType,
            'phone_number' => $phoneNumber,
            'message' => $message,
            'success' => $success,
            'response' => is_array($response) ? json_encode($response) : $response,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($quickReplyNumber) {
            // Incrementa todos os templates de resposta rápida ativos
            Capsule::table('mod_zapcel_templates')
                ->where('trigger_event', $quickReplyNumber)
                ->increment('usage_count');
        } else {
            // Comportamento original para outros eventos
            Capsule::table('mod_zapcel_templates')
                ->where('trigger_event', $eventType)
                ->increment('usage_count');
        }

    } catch (Exception $e) {
        // Silencia erros de log
    }
}

/**
 * Log de erros do sistema
 *
 * @param string $error Mensagem de erro
 * @return void
 */
function zapcel_log_error($error)
{
    $lang = zapcel_load_lang();
    try {
        Capsule::table('mod_zapcel_logs')->insert([
            'event_type' => 'system_error',
            'message' => $error,
            'success' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        // Silencia erros de log
    }
}

/**
 * Obtém dados de pagamento da fatura (PIX/Boleto)
 */
function zapcel_get_invoice_payment_data($invoiceId)
{
    $lang = zapcel_load_lang();
    
    zapcel_log_debug('zapcel_get_invoice_payment_data', ($lang['starting'] ?? 'starting'), ['invoice_id' => $invoiceId]);

    // DEBUG - VER DADOS REAIS DA TABELA IUGUPIX
    $pixData = \WHMCS\Database\Capsule::table('iugupix')
        ->where('invoice', $invoiceId)
        ->first();

    // LOG PARA VER O QUE TEM NA TABELA - COM SUA FUNÇÃO
    zapcel_log_debug('zapcel_get_invoice_payment_data', ($lang['iugupix_raw_data'] ?? 'iugupix_raw_data'), [
        'invoice_id' => $invoiceId,
        'existe_registro' => !is_null($pixData),
        'qrcode' => isset($pixData->qrcode) ? 'TEM DADO' : 'NULL/VAZIO',
        'qrcode_text' => isset($pixData->qrcode_text) ? 'TEM DADO' : 'NULL/VAZIO', 
        'digitable_line' => isset($pixData->digitable_line) ? 'TEM DADO' : 'NULL/VAZIO',
        'barcode' => isset($pixData->barcode) ? 'TEM DADO' : 'NULL/VAZIO'
    ]);
    
    $data = [
        'pix_code' => '',
        'pix_qrcode' => '',
        'barcode' => '',
        'pdf_url' => '',
        'gateway' => 'unknown'
    ];

    try {
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) {
            zapcel_log_debug('zapcel_get_invoice_payment_data', ($lang['invoice_not_found'] ?? 'invoice_not_found'), ['invoice_id' => $invoiceId]);
            return $data;
        }
        
        zapcel_log_debug('zapcel_get_invoice_payment_data', ($lang['invoice_data'] ?? 'invoice_data'), [
            'invoice_id' => $invoiceId,
            'payment_method' => $invoice->paymentmethod,
            'status' => $invoice->status
        ]);
        
        // LÓGICA CORRIGIDA - IGUAL AO CÓDIGO QUE FUNCIONA
        if ($pixData && !empty($pixData->qrcode_text)) {
            $data['pix_code'] = $pixData->qrcode_text;
            $data['pix_qrcode'] = $pixData->qrcode ?? '';
            $data['gateway'] = 'iugupix';
            
            zapcel_log_debug('zapcel_get_invoice_payment_data', ($lang['pix_found'] ?? 'pix_found'), [
                'invoice_id' => $invoiceId,
                'pix_code_length' => strlen($pixData->qrcode_text),
                'has_qrcode' => !empty($pixData->qrcode)
            ]);
        }
        
        if ($pixData && !empty($pixData->digitable_line)) {
            $data['barcode'] = $pixData->digitable_line;
            $data['gateway'] = 'iugupix';
            
            zapcel_log_debug('zapcel_get_invoice_payment_data', ($lang['boleto_found'] ?? 'boleto_found'), [
                'invoice_id' => $invoiceId,
                'barcode_length' => strlen($pixData->digitable_line)
            ]);
        }
        
        zapcel_log_debug('zapcel_get_invoice_payment_data', ($lang['final_extracted_data'] ?? 'final_extracted_data'), [
            'invoice_id' => $invoiceId,
            'pix_code_exists' => !empty($data['pix_code']),
            'pix_qrcode_exists' => !empty($data['pix_qrcode']),
            'barcode_exists' => !empty($data['barcode']),
            'gateway' => $data['gateway']
        ]);
        
        return $data;

    } catch (Exception $e) {
        zapcel_log_error(($lang['error_getting_payment_data_prefix'] ?? 'error_getting_payment_data_prefix') . $e->getMessage());
        zapcel_log_debug('zapcel_get_invoice_payment_data', 'Erro', [
            'invoice_id' => $invoiceId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    return $data;
}
/**
 * Obtém nome da empresa
 *
 * @return string Nome da empresa
 */
function zapcel_get_company_name()
{
    $lang = zapcel_load_lang();
    global $CONFIG;
    return $CONFIG['CompanyName'] ?? ($lang['host_default'] ?? 'host_default');
}

/**
 * Calcula dias até o vencimento
 *
 * @param string $dueDate Data de vencimento
 * @return string Descrição dos dias
 */
function zapcel_get_days_until_due($dueDate)
{
    $lang = zapcel_load_lang();
    $due = strtotime($dueDate);
    $now = zapcel_format_date(strtotime(date('Y-m-d')), $lang);
    $diff = ($due - $now) / 86400;

    if ($diff < 0) {
        $days = abs($diff);
        return $days == 1 ? ($lang['one_day_ago'] ?? 'one_day_ago') : "{$lang['overdue_for']} {$days} {$lang['days']}";
    } elseif ($diff == 0) {
        return ($lang['today'] ?? 'today');
    } elseif ($diff == 1) {
        return ($lang['tomorrow'] ?? 'tomorrow');
    } else {
        return "{$diff} {$lang['days']}";
    }
}

/**
 * Obtém método de pagamento formatado
 *
 * @param int $invoiceId ID da fatura
 * @return string Método de pagamento
 */
function zapcel_get_payment_method($invoiceId)
{
    $lang = zapcel_load_lang();
    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if (!$invoice) return ($lang['not_specified'] ?? 'not_specified');

    $gateway = Capsule::table('tblpaymentgateways')
        ->where('gateway', $invoice->paymentmethod)
        ->where('setting', 'name')
        ->first();

    return $gateway ? $gateway->value : ucfirst($invoice->paymentmethod);
}

/**
 * Obtém prioridade do ticket formatada
 *
 * @param string $priority Prioridade do ticket
 * @return string Prioridade formatada
 */
function zapcel_get_ticket_priority($priority)
{
    $lang = zapcel_load_lang();
    $priorities = [
        'Low' => '🟢 Baixa',
        'Medium' => '🟡 Média',
        'High' => '🟠 Alta',
        'Critical' => '🔴 Crítica'
    ];

    return $priorities[$priority] ?? '🟡 Média';
}

/**
 * Obtém tempo médio de resposta (placeholder)
 *
 * @return string Tempo médio de resposta
 */
function zapcel_get_avg_response_time()
{
    $lang = zapcel_load_lang();
    // Implementação futura: calcular tempo médio real
    return '2 horas';
}

/**
 * Verifica validação do WhatsApp antes do envio
 * 
 * @param int $clientId ID do cliente
 * @param array $settings Configurações do módulo
 * @param string $eventType Tipo de evento para log
 * @return bool True se pode enviar mensagem
 */
// JÁ IMPLEMENTADO - Função de validação robusta
function zapcel_validate_before_send($clientId, $settings, $eventType) {
    $lang = zapcel_load_lang();
    try {
        // Se validação não está habilitada, permite envio
        if (!$settings['require_validation']) {
            zapcel_log_debug($eventType, ($lang['whatsapp_validation_disabled'] ?? 'whatsapp_validation_disabled'), [
                'client_id' => $clientId,
                'validation_required' => false
            ]);
            return true;
        }

        // Verifica se WhatsApp está validado
        $isValidated = zapcel_is_whatsapp_validated($clientId, $settings);
        
        if (!$isValidated) {
            // LOG DETALHADO DE BLOQUEIO
            zapcel_log_message($clientId, $eventType . '_blocked', '', 
                ($lang['message_blocked_whatsapp_not_validated_colon'] ?? 'message_blocked_whatsapp_not_validated_colon'), false, 
                json_encode($result)
            );
            
            zapcel_log_debug($eventType, ($lang['message_blocked_whatsapp_not_validated'] ?? 'message_blocked_whatsapp_not_validated'), [
                'client_id' => $clientId,
                'validation_required' => true,
                'is_validated' => false,
                'event_type' => $eventType
            ]);
            
            return false;
        }

        // LOG DE SUCESSO NA VALIDAÇÃO
        zapcel_log_debug($eventType, ($lang['whatsapp_validated_send_allowed'] ?? 'whatsapp_validated_send_allowed'), [
            'client_id' => $clientId,
            'validation_required' => true,
            'is_validated' => true,
            'event_type' => $eventType
        ]);
        
        return true;

    } catch (Exception $e) {
        zapcel_log_error(($lang['error_in_whatsapp_validation_prefix'] ?? 'error_in_whatsapp_validation_prefix') . $e->getMessage());
        // Em caso de erro, bloqueia envio por segurança
        return false;
    }
}

/**
 * Carrega variáveis personalizadas de campos customizados
 */
function zapcel_get_custom_field_variables($clientId, $serviceId = null)
{
    $customVars = [];
    $servicesByPackage = [];
    
    try {
        // Carrega configuração JSON
        $jsonFile = __DIR__ . '/json/variaveis.json';
        
        if (!file_exists($jsonFile)) {
            return $customVars;
        }
        
        $config = json_decode(file_get_contents($jsonFile), true);
        
        if (!isset($config['variaveis']) || !is_array($config['variaveis'])) {
            return $customVars;
        }
        
        // Se serviceId não foi passado, busca o primeiro serviço do cliente
        if (!$serviceId) {
            foreach ($config['variaveis'] as $varConfig) {
                $packageId = $varConfig['packageid'] ?? null;
                
                if (!$packageId) {
                    continue;
                }
                
                // Busca serviço do cliente com este packageid
                $service = Capsule::table('tblhosting')
                    ->where('packageid', $packageId)
                    ->where('userid', $clientId)
                    ->where('orderid', '!=', 0)
                    ->first();
                
                if ($service) {
                    //$serviceId = $service->id;
                    //break;
                    $servicesByPackage[$packageId] = $service->id;
                }
            }
        }
        
        /*if (!$serviceId) {
            return $customVars;
        }*/
        
        if (!$serviceId && empty($servicesByPackage)) {
            return $customVars;
        }
        
        // Busca package do serviço
        /*$service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();
        
        if (!$service) {
            return $customVars;
        }*/
        
        $service = null;

        if ($serviceId) {
            // Busca package do serviço
            $service = Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->first();
        
            if (!$service) {
                return $customVars;
            }
        }
        
        // Procura configuração para este packageid
        foreach ($config['variaveis'] as $varConfig) {
            //if (($varConfig['packageid'] ?? null) != $service->packageid) {
            if ($serviceId && (($varConfig['packageid'] ?? null) != $service->packageid)) {
                continue;
            }
            
            // Processa cada campo customizado
            foreach ($varConfig['fields'] as $field) {
                $fieldId = $field['fieldid'] ?? null;
                $varName = $field['variable_name'] ?? null;
                
                if (!$fieldId || !$varName) {
                    continue;
                }
                
                $relid = $serviceId ?: ($servicesByPackage[$varConfig['packageid']] ?? null);
                if (!$relid) {
                    continue;
                }
                
                // Busca valor do campo customizado
                $fieldValue = Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $fieldId)
                    //->where('relid', $serviceId)
                    ->where('relid', $relid)
                    ->value('value');
                
                // Adiciona variável
                $customVars[$varName] = $fieldValue ?? '';
            }
        }
        
    } catch (\Exception $e) {
        // Silenciosamente ignora erros
    }
    
    return $customVars;
}

/**
 * Obtém todas as variáveis padrão para templates
 * 
 * @param int $clientId ID do cliente
 * @param array $settings Configurações do módulo
 * @return array Variáveis padrão
 */
function zapcel_get_default_variables($clientId, $settings) {
    $lang = zapcel_load_lang();
    global $CONFIG;
    
    $variables = [
        'assinatura' => $settings['zapcel_signature'] ?? '',
        'provedor' => $CONFIG['CompanyName'] ?? ($lang['our_provider'] ?? 'our_provider'),
        'data_atual' => date('d/m/Y'),
        'hora_atual' => date('H:i'),
        'data_hora_atual' => date('d/m/Y H:i'),
        'url_whmcs' => rtrim(\App::getSystemUrl(), "/"), // VARIÁVEL OBRIGATÓRIA
        'ano_atual' => date('Y'),
        'mes_atual' => date('m'),
        'dia_atual' => date('d'),
        'quebrar_mensagem' => '{quebrar_mensagem}',
    ];
    
    // Adiciona dados do cliente se disponível
    if ($clientId) {
        try {
            $client = Capsule::table('tblclients')->where('id', $clientId)->first();
            if ($client) {
                $variables['cliente'] = trim($client->firstname . ' ' . $client->lastname);
                $variables['cliente_id'] = $clientId;
                $variables['email'] = $client->email;
                $variables['cliente_primeiro_nome'] = $client->firstname;
                $variables['cliente_sobrenome'] = $client->lastname;
                
                // Variáveis de endereço obrigatórias
                $variables['telefone'] = $client->phonenumber ?? '';
                $variables['endereco'] = $client->address1 ?? '';
                $variables['bairro'] = $client->address2 ?? '';
                $variables['cidade'] = $client->city ?? '';
                $variables['estado'] = $client->state ?? '';
                $variables['cep'] = $client->postcode ?? '';
                $variables['pais'] = $client->country ?? '';
                $variables['ip_dedicado'] = ''; // Será preenchido quando aplicável 

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
                
                // Log de variáveis faltantes
                $missingVars = [];
                foreach (['telefone', 'endereco', 'cidade', 'estado'] as $requiredVar) {
                    if (empty($variables[$requiredVar])) {
                        $missingVars[] = $requiredVar;
                    }
                }
                
                if (!empty($missingVars)) {
                    zapcel_log_debug('default_variables', ($lang['missing_required_variables'] ?? 'missing_required_variables'), [
                        'client_id' => $clientId,
                        'missing_vars' => $missingVars
                    ]);
                }
            }
        } catch (\Exception $e) {
            zapcel_log_error(($lang['error_getting_client_variables_prefix'] ?? 'error_getting_client_variables_prefix') . $e->getMessage());
        }
    }
    
    return $variables;
}

/**
 * Funções auxiliares adicionais necessárias
 */

/**
 * Obtém IP dedicado do serviço
 */
function zapcel_get_dedicated_ip($clientId) {
    $lang = zapcel_load_lang();
    try {
        $service = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->first();
            
        return $service ? ($service->dedicatedip ?? '') : '';
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Obtém domínio do serviço
 */
function zapcel_get_service_domain($clientId) {
    $lang = zapcel_load_lang();
    try {
        $service = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->first();
            
        return $service ? ($service->domain ?? '') : '';
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Obtém nome do produto
 */
function zapcel_get_product_name($clientId) {
    $lang = zapcel_load_lang();
    try {
        $service = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->first();
            
        if ($service) {
            $product = Capsule::table('tblproducts')
                ->where('id', $service->packageid)
                ->first();
            return $product ? $product->name : '';
        }
    } catch (Exception $e) {
        // Silencia erro
    }
    
    return '';
}

/**
 * Obtém usuário do serviço
 */
function zapcel_get_service_username($clientId) {
    $lang = zapcel_load_lang();
    try {
        $service = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->first();
            
        return $service ? ($service->username ?? '') : '';
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Obtém senha do serviço (descriptografada)
 */
function zapcel_get_service_password($clientId) {
    $lang = zapcel_load_lang();
    try {
        $service = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->first();
            
        if ($service && $service->password) {
            // Usa a função de descriptografia do WHMCS se disponível
            if (function_exists('decrypt')) {
                return decrypt($service->password);
            }
            return $service->password;
        }
    } catch (Exception $e) {
        // Não loga por segurança
    }
    
    return '';
}

/**
 * HOOK: Serviço Suspenso
 * Envia notificação quando um serviço é suspenso
 */
add_hook('AfterModuleSuspend', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        // DEBUG: Log que o hook foi chamado
        zapcel_log_debug('AfterModuleSuspend', ($lang['hook_triggered'] ?? 'hook_triggered'), $vars);
        
        $settings = zapcel_get_settings();
        
        // DEBUG: Verifica se está habilitado
        if (!$settings['zapcel_enabled']) {
            zapcel_log_debug('AfterModuleSuspend', ($lang['module_disabled'] ?? 'module_disabled'), ['enabled' => $settings['zapcel_enabled']]);
            return;
        }

        $serviceId = $vars['params']['serviceid'];

        // Busca informações do serviço
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if (!$service) {
            zapcel_log_debug('AfterModuleSuspend', ($lang['service_not_found'] ?? 'service_not_found'), ['service_id' => $serviceId]);
            return;
        }

        $clientId = $service->userid;
        
        // VALIDAÇÃO WHATSAPP
        if (!zapcel_validate_before_send($clientId, $settings, 'service_suspended')) {
            return;
        }

        zapcel_log_debug('AfterModuleSuspend', ($lang['service_id_label'] ?? 'service_id_label'), ['service_id' => $serviceId]);

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'service_suspended')
            ->where('active', true)
            ->first();

        // DEBUG: Verifica se template existe
        if (!$template) {
            zapcel_log_debug('AfterModuleSuspend', ($lang['template_not_found_or_inactive'] ?? 'template_not_found_or_inactive'), ['event' => 'service_suspended']);
            return;
        }
        zapcel_log_debug('AfterModuleSuspend', ($lang['template_found'] ?? 'template_found'), ['template_id' => $template->id, 'name' => $template->name]);

        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if (!$service) {
            zapcel_log_debug('AfterModuleSuspend', ($lang['service_not_found'] ?? 'service_not_found'), ['service_id' => $serviceId]);
            return;
        }

        $clientId = $service->userid;
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            zapcel_log_debug('AfterModuleSuspend', ($lang['client_not_found'] ?? 'client_not_found'), ['client_id' => $clientId]);
            return;
        }
        zapcel_log_debug('AfterModuleSuspend', ($lang['client_found'] ?? 'client_found'), ['client_id' => $clientId, 'name' => $client->firstname . ' ' . $client->lastname]);

        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->first();

        // VARIÁVEIS BASE (usa função padrão)
        $baseVariables = zapcel_get_default_variables($clientId, $settings);
        
        // VARIÁVEIS ESPECÍFICAS DO EVENTO
        $eventVariables = [
            'servico' => $product ? $product->name : ($lang['service'] ?? 'service'),
            'dominio' => $service->domain,
            'data_suspensao' => date('d/m/Y'),
            'motivo' => $vars['params']['suspendreason'] ?? ($lang['not_specified'] ?? 'not_specified'),
        ];

        $variables = array_merge($eventVariables, $baseVariables);
        $variables = zapcel_ensure_required_variables($variables, $clientId);

        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);
        
        // DEBUG: Verifica número
        zapcel_log_debug('AfterModuleSuspend', ($lang['client_number_label'] ?? 'client_number_label'), ['phone' => $phoneNumber]);
        
        if (!$phoneNumber) {
            zapcel_log_debug('AfterModuleSuspend', ($lang['phone_number_not_found'] ?? 'phone_number_not_found'), ['client_id' => $clientId]);
            return;
        }
        
        if ($phoneNumber) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);
            
            // DEBUG: Resultado do envio
            zapcel_log_debug('AfterModuleSuspend', ($lang['send_result_label'] ?? 'send_result_label'), $result);

            zapcel_log_message($clientId, 'service_suspended', $phoneNumber, ($lang['service_suspended_log'] ?? 'service_suspended_log'), true, json_encode($result) ?? ($lang['no_return'] ?? 'no_return'));
        } else {
            zapcel_log_message($clientId, 'service_suspended', $phoneNumber, ($lang['service_suspended_log'] ?? 'service_suspended_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['after_module_suspend_hook_error'] ?? 'after_module_suspend_hook_error') . $e->getMessage());
        zapcel_log_debug('AfterModuleSuspend', ($lang['exception_caught'] ?? 'exception_caught'), ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
});

/**
 * HOOK: Serviço Reativado
 * Envia notificação quando um serviço é reativado
 */
add_hook('AfterModuleUnsuspend', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        // DEBUG: Log que o hook foi chamado
        zapcel_log_debug('AfterModuleUnsuspend', ($lang['hook_triggered'] ?? 'hook_triggered'), $vars);
        
        $settings = zapcel_get_settings();
        
        // DEBUG: Verifica se está habilitado
        if (!$settings['zapcel_enabled']) {
            zapcel_log_debug('AfterModuleUnsuspend', ($lang['module_disabled'] ?? 'module_disabled'), ['enabled' => $settings['zapcel_enabled']]);
            return;
        }

        $serviceId = $vars['params']['serviceid'];
        zapcel_log_debug('AfterModuleUnsuspend', ($lang['service_id_label'] ?? 'service_id_label'), ['service_id' => $serviceId]);

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'service_unsuspended')
            ->where('active', true)
            ->first();

        // DEBUG: Verifica se template existe
        if (!$template) {
            zapcel_log_debug('AfterModuleUnsuspend', ($lang['template_not_found_or_inactive'] ?? 'template_not_found_or_inactive'), ['event' => 'service_unsuspended']);
            return;
        }
        zapcel_log_debug('AfterModuleUnsuspend', ($lang['template_found'] ?? 'template_found'), ['template_id' => $template->id, 'name' => $template->name]);

        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if (!$service) {
            zapcel_log_debug('AfterModuleUnsuspend', ($lang['service_not_found'] ?? 'service_not_found'), ['service_id' => $serviceId]);
            return;
        }

        $clientId = $service->userid;
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            zapcel_log_debug('AfterModuleUnsuspend', ($lang['client_not_found'] ?? 'client_not_found'), ['client_id' => $clientId]);
            return;
        }
        zapcel_log_debug('AfterModuleUnsuspend', ($lang['client_found'] ?? 'client_found'), ['client_id' => $clientId, 'name' => $client->firstname . ' ' . $client->lastname]);

        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->first();

        $variables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'servico' => $product ? $product->name : ($lang['service'] ?? 'service'),
            'dominio' => $service->domain,
            'data_reativacao' => date('d/m/Y'),
            'assinatura' => $settings['zapcel_signature'],
            'provedor' => zapcel_get_company_name()
        ];

        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);
        
        // DEBUG: Verifica número
        zapcel_log_debug('AfterModuleUnsuspend', ($lang['client_number_label'] ?? 'client_number_label'), ['phone' => $phoneNumber]);
        
        if (!$phoneNumber) {
            zapcel_log_debug('AfterModuleUnsuspend', ($lang['phone_number_not_found'] ?? 'phone_number_not_found'), ['client_id' => $clientId]);
            return;
        }
        
        // DEBUG: Verifica validação
        $isValidated = zapcel_is_whatsapp_validated($clientId, $settings);
        zapcel_log_debug('AfterModuleUnsuspend', ($lang['validation_status_label'] ?? 'validation_status_label'), [
            'validated' => $isValidated,
            'validation_required' => $settings['zapcel_validation']
        ]);

        if ($phoneNumber && $isValidated) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);
            
            // DEBUG: Resultado do envio
            zapcel_log_debug('AfterModuleUnsuspend', ($lang['send_result_label'] ?? 'send_result_label'), $result);
            zapcel_log_message($clientId, 'service_unsuspended', $phoneNumber, ($lang['service_unsuspended_log'] ?? 'service_unsuspended_log'), true, json_encode($result) ?? ($lang['no_return'] ?? 'no_return'));
        } else {
            zapcel_log_message($clientId, 'service_unsuspended', $phoneNumber, ($lang['service_unsuspended_log'] ?? 'service_unsuspended_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['after_module_unsuspend_hook_error'] ?? 'after_module_unsuspend_hook_error') . $e->getMessage());
        zapcel_log_debug('AfterModuleUnsuspend', ($lang['exception_caught'] ?? 'exception_caught'), ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
});

/**
 * HOOK: Serviço Cancelado
 * Envia notificação quando um serviço é cancelado/terminado
 */
add_hook('AfterModuleTerminate', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        // DEBUG: Log que o hook foi chamado
        zapcel_log_debug('AfterModuleTerminate', ($lang['hook_triggered'] ?? 'hook_triggered'), $vars);
        
        $settings = zapcel_get_settings();
        
        // DEBUG: Verifica se está habilitado
        if (!$settings['zapcel_enabled']) {
            zapcel_log_debug('AfterModuleTerminate', ($lang['module_disabled'] ?? 'module_disabled'), ['enabled' => $settings['zapcel_enabled']]);
            return;
        }

        $serviceId = $vars['params']['serviceid'];
        zapcel_log_debug('AfterModuleTerminate', ($lang['service_id_label'] ?? 'service_id_label'), ['service_id' => $serviceId]);

        require_once __DIR__ . '/api/MessageProcessor.php';
        require_once __DIR__ . '/api/WhatsAppAPI.php';

        $processor = new MessageProcessor($settings);

        $template = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'service_terminated')
            ->where('active', true)
            ->first();

        // DEBUG: Verifica se template existe
        if (!$template) {
            zapcel_log_debug('AfterModuleTerminate', ($lang['template_not_found_or_inactive'] ?? 'template_not_found_or_inactive'), ['event' => 'service_terminated']);
            return;
        }
        zapcel_log_debug('AfterModuleTerminate', ($lang['template_found'] ?? 'template_found'), ['template_id' => $template->id, 'name' => $template->name]);

        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if (!$service) {
            zapcel_log_debug('AfterModuleTerminate', ($lang['service_not_found'] ?? 'service_not_found'), ['service_id' => $serviceId]);
            return;
        }

        $clientId = $service->userid;
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            zapcel_log_debug('AfterModuleTerminate', ($lang['client_not_found'] ?? 'client_not_found'), ['client_id' => $clientId]);
            return;
        }
        zapcel_log_debug('AfterModuleTerminate', ($lang['client_found'] ?? 'client_found'), ['client_id' => $clientId, 'name' => $client->firstname . ' ' . $client->lastname]);

        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->first();

        $variables = [
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'servico' => $product ? $product->name : ($lang['service'] ?? 'service'),
            'dominio' => $service->domain,
            'data_cancelamento' => date('d/m/Y'),
            'assinatura' => $settings['zapcel_signature'],
            'provedor' => zapcel_get_company_name()
        ];

        $messageParts = $processor->processTemplate($template->template, $variables, '', true);
        $phoneNumber = zapcel_get_client_phone_number($clientId, $settings);
        
        // DEBUG: Verifica número
        zapcel_log_debug('AfterModuleTerminate', ($lang['client_number_label'] ?? 'client_number_label'), ['phone' => $phoneNumber]);
        
        if (!$phoneNumber) {
            zapcel_log_debug('AfterModuleTerminate', ($lang['phone_number_not_found'] ?? 'phone_number_not_found'), ['client_id' => $clientId]);
            return;
        }
        
        // DEBUG: Verifica validação
        $isValidated = zapcel_is_whatsapp_validated($clientId, $settings);
        zapcel_log_debug('AfterModuleTerminate', ($lang['validation_status_label'] ?? 'validation_status_label'), [
            'validated' => $isValidated,
            'validation_required' => $settings['zapcel_validation']
        ]);

        if ($phoneNumber && $isValidated) {
            $api = new WhatsAppAPI($settings);
            $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);
            
            // DEBUG: Resultado do envio
            zapcel_log_debug('AfterModuleTerminate', ($lang['send_result_label'] ?? 'send_result_label'), $result);
            zapcel_log_message($clientId, 'service_terminated', $phoneNumber, ($lang['service_terminated_log'] ?? 'service_terminated_log'), true, json_encode($result) ?? ($lang['no_return'] ?? 'no_return'));
        } else {
            zapcel_log_message($clientId, 'service_terminated', $phoneNumber, ($lang['service_terminated_log'] ?? 'service_terminated_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'));
        }

    } catch (Exception $e) {
        zapcel_log_error(($lang['after_module_terminate_hook_error'] ?? 'after_module_terminate_hook_error') . $e->getMessage());
        zapcel_log_debug('AfterModuleTerminate', ($lang['exception_caught'] ?? 'exception_caught'), ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
});



/**
 * NOVA FUNÇÃO v2.0.1: Envia mensagem com suporte a mídia (texto + imagem)
 * 
 * Processa array retornado pelo MessageProcessor e envia cada parte
 * na ordem correta, respeitando tipo (texto/imagem)
 * 
 * @param WhatsAppAPI $api Instância da API WhatsApp
 * @param string $phoneNumber Número do telefone
 * @param array $messageParts Array de partes da mensagem
 * @param array $variables Variáveis para substituição (incluindo qr_code_url)
 * @return array Resultado do envio
 */
function zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables = []) {
    $lang = zapcel_load_lang();
    try {
        // Se for string, converte para array simples
        if (is_string($messageParts)) {
            $messageParts = [['type' => 'text', 'content' => $messageParts]];
        }
        
        // Se não for array, erro
        if (!is_array($messageParts)) {
            return [
                'success' => false,
                'error' => 'Formato de mensagem inválido'
            ];
        }
        
        $results = [];
        $allSuccess = true;
        $partIndex = 0;
        
        // Envia cada parte na ordem do array
        foreach ($messageParts as $part) {
            $partIndex++;
            $type = $part['type'] ?? 'text';
            $content = $part['content'] ?? '';
            
            if (empty($content)) {
                continue; // Pula partes vazias
            }
            
            if ($type === 'image') {
                // Substitui marcador {qr_code_url} pela URL real
                if ($content === '{qr_code_url}' && !empty($variables['qr_code_url'])) {
                    $content = $variables['qr_code_url'];
                    
                    // Adiciona .jpg se não tiver extensão
                    if (!preg_match('/\.(jpg|jpeg|png|gif)$/i', $content)) {
                        $content .= '.jpg';
                    }
                }
                
                // Envia imagem
                $caption = $part['caption'] ?? ($lang['pix_qr_code'] ?? 'pix_qr_code');
                if (!empty($variables['provedor'])) {
                    $caption .= ' - ' . $variables['provedor'];
                }
                
                $result = $api->sendImage($phoneNumber, $content, $caption);
                
                zapcel_log_debug('send_media', ($lang['image_sent'] ?? 'image_sent'), [
                    'phone' => $phoneNumber,
                    'image_url' => $content,
                    'caption' => $caption,
                    'success' => $result['success'] ?? false,
                    'part_index' => $partIndex
                ]);
                
            } else {
                // Envia texto
                $result = $api->sendMessage($phoneNumber, $content);
                
                zapcel_log_debug('send_media', ($lang['text_sent'] ?? 'text_sent'), [
                    'phone' => $phoneNumber,
                    'message_length' => strlen($content),
                    'success' => $result['success'] ?? false,
                    'part_index' => $partIndex
                ]);
            }

            // ✅ CORREÇÃO: Adiciona part_number ao resultado
            if (isset($result['results']) && is_array($result['results'])) {
                // Se o resultado tem estrutura de "results", atualiza o part
                foreach ($result['results'] as &$subResult) {
                    $subResult['part'] = $partIndex;
                }
            }
            
            $results[] = $result;
            
            if (!($result['success'] ?? false)) {
                $allSuccess = false;
            }
            
            // Delay entre envios para garantir ordem
            if ($partIndex < count($messageParts)) {
                if ($type === 'image') {
                    sleep(3); // 3 segundos após imagem
                } else {
                    sleep(1); // 1 segundo após texto
                }
            }
        }
        
        return [
            'success' => $allSuccess,
            'results' => $results,
            'parts_sent' => count($results)
        ];
        
    } catch (Exception $e) {
        zapcel_log_error(($lang['send_message_with_media_error_prefix'] ?? 'send_message_with_media_error_prefix') . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Log de debug detalhado
 *
 * @param string $hook Nome do hook
 * @param string $message Mensagem de debug
 * @param array $data Dados adicionais
 * @return void
 */
function zapcel_log_debug($hook, $message, $data = [])
{
    $lang = zapcel_load_lang();
    try {
        // VERIFICA SE LOGGING ESTÁ HABILITADO NAS CONFIGURAÇÕES
        $settings = zapcel_get_settings();
        if (!$settings['enable_logging']) {
            return; // NÃO GRAVA LOG SE enable_logging = 0
        }
        Capsule::table('mod_zapcel_logs')->insert([
            'event_type' => 'debug_' . strtolower($hook),
            'message' => "[DEBUG] {$hook}: {$message}",
            'response' => json_encode($data),
            'success' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        // Silencia erros de log
    }
}



/**
 * Hook executado diariamente para limpeza de logs antigos
 */
add_hook('DailyCronJob', 1, function() {
    $lang = zapcel_load_lang();
    try {
        // Obtém configuração de dias para manter logs
        $logRetentionDays = (int)zapcel_get_settings()['log_retention_days'] ?? 30;
        
        // Calcula data limite
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$logRetentionDays} days"));
        
        // Deleta logs antigos
        $deleted = Capsule::table('mod_zapcel_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        
        // Registra a limpeza
        if ($deleted > 0) {
            Capsule::table('mod_zapcel_logs')->insert([
                'event_type' => 'system_cleanup',
                'message' => "Limpeza automática: {$deleted} logs removidos (mais antigos que {$logRetentionDays} dias)",
                'success' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        
    } catch (Exception $e) {
        // Registra erro
        Capsule::table('mod_zapcel_logs')->insert([
            'event_type' => 'system_error',
            'message' => ($lang['error_in_automatic_log_cleanup_prefix'] ?? 'error_in_automatic_log_cleanup_prefix') . $e->getMessage(),
            'success' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
});

/**
 * Worker de processamento de fila de campanhas
 * Executado pelo cron do WHMCS a cada 5 minutos
 */
function zapcel_process_campaign_queue()
{
    // -------------------------------------------------------------------------
    // 1. OTIMIZAÇÃO DE AMBIENTE & INCLUDES
    // -------------------------------------------------------------------------
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    
    $lang = zapcel_load_lang();
    
    // Carrega arquivos uma única vez (Performance)
    require_once __DIR__ . '/api/WhatsAppAPI.php';
    require_once __DIR__ . '/api/MessageProcessor.php';

    // 🔧 CONFIGURAÇÕES DO RITMO (Segurança Anti-Bloqueio)
    $MAX_MESSAGES_PER_5_MIN = 40;  // Teto máximo de envios por janela de 5 min
    $WINDOW_TIME_MINUTES = 5;      // Tamanho da janela de verificação

    try {
        // -------------------------------------------------------------------------
        // 2. O LIXEIRO (Segurança contra travamentos)
        // -------------------------------------------------------------------------
        // Reseta itens que ficaram presos em 'processing' por mais de 20 min
        // (Isso acontece se o script anterior morrer por erro fatal ou timeout)
        $limitTime = date('Y-m-d H:i:s', strtotime('-20 minutes'));
        
        Capsule::table('mod_zapcel_campaign_queue')
            ->where('status', 'processing')
            ->where('processing_started_at', '<', $limitTime)
            ->update([
                'status'                => 'pending',
                'error_message'         => 'Resetado por timeout (Lixeiro)',
                'processing_started_at' => null,
                'updated_at'            => date('Y-m-d H:i:s')
            ]);

        // -------------------------------------------------------------------------
        // 3. INICIALIZAÇÃO
        // -------------------------------------------------------------------------
        $settings = zapcel_get_settings();
        if (empty($settings['zapcel_enabled'])) {
            return; // Módulo desativado
        }

        // Busca campanhas que precisam de atenção
        $campaigns = Capsule::table('mod_zapcel_campaigns')
            ->whereIn('status', ['active', 'scheduled'])
            ->get();

        // -------------------------------------------------------------------------
        // 4. LOOP DAS CAMPANHAS
        // -------------------------------------------------------------------------
        foreach ($campaigns as $campaign) {
            $now = date('Y-m-d H:i:s');

            // 4.1. Verifica Agendamento (Transforma Scheduled -> Active)
            if ($campaign->status === 'scheduled') {
                if (!empty($campaign->schedule_start) && $campaign->schedule_start <= $now) {
                    // Chegou a hora! Ativa a campanha.
                    Capsule::table('mod_zapcel_campaigns')
                        ->where('id', $campaign->id)
                        ->update(['status' => 'active', 'schedule_start' => null]);
                    
                    $campaign->status = 'active';
                    
                    $result = [
                        'campaign_id'    => $campaign->id,
                        'campaign_name'  => $campaign->name,
                        'evento'         => 'INICIO',
                        'total_contatos' => $campaign->total_contacts,
                        'data_inicio'    => $now
                    ];
                    
                    zapcel_log_message(
                        $campaign->id . '-CAMP', 
                        'campaign_start', 
                        'System', 
                        ($lang['campaign_start_log'] ?? 'Campanha Iniciada'), 
                        true, 
                        $result
                    );
                    
                } else {
                    // Ainda não é a hora
                    continue;
                }
            }

            // 4.2. Verifica Janela de Horário Comercial
            if ($campaign->send_mode === 'business_hours') {
                $hour = (int)date('H');
                $day  = (int)date('N'); // 1 (Seg) a 7 (Dom)
                
                // Se for Fim de semana OU antes das 7h OU depois das 18h -> Pula
                if ($day > 5 || $hour < 7 || $hour >= 18) {
                    continue;
                }
            }

            // -------------------------------------------------------------------------
            // 5. O CORAÇÃO DO RITMO (Lógica Matemática)
            // -------------------------------------------------------------------------
            
            // Pergunta ao banco: "Quantas eu mandei nos últimos 5 minutos?"
            $windowStart = date('Y-m-d H:i:s', strtotime("-{$WINDOW_TIME_MINUTES} minutes"));
            
            $recentlySent = Capsule::table('mod_zapcel_campaign_queue')
                ->where('campaign_id', $campaign->id)
                ->where('status', 'sent')
                ->where('sent_at', '>=', $windowStart)
                ->count();

            // Se já atingimos o teto de segurança da janela, paramos por aqui.
            if ($recentlySent >= $MAX_MESSAGES_PER_5_MIN) {
                // Opcional: Logar que o ritmo freou o envio (bom para debug)
                continue;
            }

            // Calcula o saldo: Quantas ainda posso mandar agora?
            $canTakeNow = $MAX_MESSAGES_PER_5_MIN - $recentlySent;
            
            // O limite final é o MENOR valor entre:
            // a) O saldo do ritmo ($canTakeNow)
            // b) O tamanho do lote configurado na campanha ($campaign->batch_size)
            $batchSize = $campaign->batch_size ?? 22;
            $limit = min($canTakeNow, $batchSize);
            
            if ($limit <= 0) continue;

            // -------------------------------------------------------------------------
            // 6. SELEÇÃO E RESERVA (Lock)
            // -------------------------------------------------------------------------

            // Busca os próximos itens pendentes respeitando o limite calculado
            $queue = Capsule::table('mod_zapcel_campaign_queue')
                ->where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->where(function($q) use ($now) {
                    $q->where('next_send_at', '<=', $now)
                      ->orWhereNull('next_send_at');
                })
                ->limit($limit)
                ->get();

            // Se não tem nada na fila, verifica se a campanha acabou
            if ($queue->isEmpty()) {
                $countRemaining = Capsule::table('mod_zapcel_campaign_queue')
                    ->where('campaign_id', $campaign->id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->count();
                
                if ($countRemaining === 0) {
                    Capsule::table('mod_zapcel_campaigns')
                        ->where('id', $campaign->id)
                        ->update(['status' => 'finished', 'updated_at' => $now]);
                        
                    $result = [
                        'campaign_id'    => $campaign->id,
                        'campaign_name'  => $campaign->name,
                        'evento'         => 'FIM',
                        'total_enviados' => $campaign->sent_count,
                        'total_falhas'   => $campaign->failed_count,
                        'data_fim'       => $now
                    ];
    
                    zapcel_log_message(
                        $campaign->id . '-CAMP', 
                        'campaign_end', 
                        'System', 
                        ($lang['campaign_end_log'] ?? 'Campanha Finalizada'), 
                        true, 
                        $result
                    );
                }
                continue;
            }

            // "Carimba" os itens como 'processing' imediatamente para evitar concorrência
            $queueIds = $queue->pluck('id')->toArray();
            Capsule::table('mod_zapcel_campaign_queue')
                ->whereIn('id', $queueIds)
                ->update([
                    'status' => 'processing', 
                    'processing_started_at' => $now,
                    'updated_at' => $now
                ]);

            // -------------------------------------------------------------------------
            // 7. LOOP DE ENVIO
            // -------------------------------------------------------------------------
            
            // Instancia as classes da API
            $api = new \WHMCS\Module\Addon\Zapcel\Api\WhatsAppAPI($settings);
            $processor = new \WHMCS\Module\Addon\Zapcel\Api\MessageProcessor($settings);

            foreach ($queue as $item) {
                $nowItem = date('Y-m-d H:i:s');
                // Sorteia o delay para esta mensagem específica
                $delay = rand((int)$campaign->delay_min, (int)$campaign->delay_max);

                try {
                    // 7.1. Validações de Cliente e Telefone
                    $client = Capsule::table('tblclients')->where('id', (int)$item->client_id)->first();
                    if (!$client) throw new Exception('Cliente não encontrado (ID: ' . $item->client_id . ')');

                    $phoneSource = $settings['zapcel_phone_source'] ?? 'phonenumber';
                    $phone = $client->{$phoneSource} ?? $client->phonenumber ?? '';
                    $phone = zapcel_format_phone_number($phone);
                    
                    if (!$phone) throw new Exception('Telefone inválido ou vazio');

                    // 7.2. Processamento de Variáveis
                    $variables = zapcel_get_default_variables($client->id, $settings);
                    $custom = zapcel_get_custom_field_variables($client->id);
                    if (is_array($custom)) {
                        $variables = array_merge($variables, $custom);
                    }

                    // 7.3. Montagem da Mensagem
                    $parts = $processor->processTemplate(
                        (string)$item->message, 
                        $variables, 
                        'campaign', 
                        true
                    );

                    // 7.4. Envio Efetivo via API
                    $result = zapcel_send_message_with_media($api, $phone, $parts, $variables);

                    if (!empty($result['success'])) {
                        // SUCESSO
                        Capsule::table('mod_zapcel_campaign_queue')->where('id', $item->id)->update([
                            'status'     => 'sent',
                            'sent_at'    => $nowItem,
                            'delay_used' => $delay,
                            'updated_at' => $nowItem
                        ]);
                        
                        // Atualiza contadores da campanha
                        Capsule::table('mod_zapcel_campaigns')->where('id', $campaign->id)->increment('sent_count');
                        Capsule::table('mod_zapcel_campaigns')->where('id', $campaign->id)->decrement('pending_count');
                    } else {
                        throw new Exception($result['error'] ?? 'Erro desconhecido na API');
                    }

                } catch (Throwable $e) {
                    // FALHA
                    Capsule::table('mod_zapcel_campaign_queue')->where('id', $item->id)->update([
                        'status'        => 'failed',
                        'error_message' => substr($e->getMessage(), 0, 255),
                        'delay_used'    => $delay,
                        'updated_at'    => $nowItem
                    ]);
                    
                    Capsule::table('mod_zapcel_campaigns')->where('id', $campaign->id)->increment('failed_count');
                    Capsule::table('mod_zapcel_campaigns')->where('id', $campaign->id)->decrement('pending_count');
                }

                // 7.5. Aplica o Delay Real (Pausa o script)
                sleep($delay);
            }
            
            // Atualiza a data da última execução da campanha
            Capsule::table('mod_zapcel_campaigns')
                ->where('id', $campaign->id)
                ->update(['last_run' => date('Y-m-d H:i:s')]);
        }

    } catch (Throwable $e) {
        // Log de erro fatal
        zapcel_log_debug('CampaignFatal', 'Erro Crítico', [
            'campaign_id'   => isset($campaign) ? $campaign->id : 'N/A',
            'campaign_name' => isset($campaign) ? $campaign->name : 'N/A',
            'error_msg'     => $e->getMessage()
        ]);
    }
}

add_hook('AfterCronJob', 1, 'zapcel_process_campaign_queue');

/**
 * HOOK: Botão Lembrar Fatura no Admin - CORRIGIDO/MELHORADO
 * Adiciona botão para disparar lembrete de fatura manualmente
 */
add_hook('AdminInvoicesControlsOutput', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        // VERIFICA PERMISSÕES
        if (!zapcel_check_admin_permissions()) {
            return '';
        }

        $settings = zapcel_get_settings();
        if (!$settings['zapcel_enabled']) {
            return '';
        }

        $invoiceId = $vars['invoiceid'] ?? 0;
        if (!$invoiceId) {
            return '';
        }

        $invoice = Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->first();

        if (!$invoice) {
            return '';
        }

        $clientId = $invoice->userid;
        
        // VERIFICA SE CLIENTE TEM WHATSAPP VALIDADO
        $isValidated = zapcel_is_whatsapp_validated($clientId, $settings);
        if (!$isValidated) {
            return '<div class="alert alert-warning" style="margin: 10px 0;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Cliente não tem WhatsApp validado para envio de lembretes.
                    </div>';
        }

        // ✅ VERIFICA SE FATURA ESTÁ UNPAID (NÃO PAGA)
        if ($invoice->status !== 'Unpaid') {
            return ''; // Não mostra botão para faturas pagas, canceladas, etc
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        $clientName = $client ? trim($client->firstname . ' ' . $client->lastname) : ($lang['client'] ?? 'client');
        
        $html = '
        <script>
        jQuery(document).ready(function($) {
            // Adiciona botão na página de fatura - WHMCS 8.x+
            const invoiceContainer = $(".pull-right-md-larger");
            if (invoiceContainer.length) {
                const zapcelBtn = $(\'<button type="button" class="btn btn-success btn-sm" id="zapcelInvoiceReminderBtn" style="margin-left: 5px;">\')
                    .html(\'<i class="fas fa-whatsapp"></i> Enviar Lembrete WhatsApp\');
                
                invoiceContainer.append(zapcelBtn);
                
                // Handler do clique
                $("#zapcelInvoiceReminderBtn").click(function() {
                    const btn = $(this);
                    const originalHtml = btn.html();
                    
                    // Desabilita botão e mostra loading
                    btn.prop("disabled", true);
                    btn.html(\'<i class="fas fa-spinner fa-spin"></i> Enviando...\');
                    
                    $.ajax({
                        url: "addonmodules.php?module=zapcel&action=send_invoice_reminder",
                        type: "POST",
                        data: {
                            invoice_id: ' . $invoiceId . ',
                            client_id: ' . $clientId . ',
                            type: "invoice_reminder",
                            admin_request: true
                        },
                        dataType: "json",
                        success: function(response) {
                            if (response.success) {
                                // SweetAlert2
                                Swal.fire({
                                    title: "Sucesso!",
                                    text: response.message || "Lembrete enviado com sucesso para ' . $clientName . '",
                                    icon: "success",
                                    confirmButtonColor: "#25D366"
                                });
                                
                                // Log de sucesso
                                console.log("Zapcel: Lembrete enviado -", response);
                            } else {
                                const errorMsg = response.error || "Erro ao enviar lembrete";
                                Swal.fire({
                                    title: "Erro",
                                    text: errorMsg,
                                    icon: "error"
                                });
                                
                                // Log de erro
                                console.error("Zapcel: Erro no envio -", errorMsg);
                            }
                        },
                        error: function(xhr, status, error) {
                            const errorMsg = "Erro na requisição: " + error;
                            Swal.fire({
                                title: "Erro",
                                text: errorMsg,
                                icon: "error"
                            });
                            console.error("Zapcel: Erro AJAX -", error);
                        },
                        complete: function() {
                            // Reabilita botão
                            btn.prop("disabled", false);
                            btn.html(originalHtml);
                        }
                    });
                });
            }
        });
        </script>

        <style>
        #zapcelInvoiceReminderBtn {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            border: none;
            margin-left: 8px;
            transition: all 0.3s ease;
        }

        #zapcelInvoiceReminderBtn:hover:not(:disabled) {
            background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(37, 211, 102, 0.3);
        }

        #zapcelInvoiceReminderBtn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        </style>';
        
        return $html;

    } catch (Exception $e) {
        zapcel_log_error(($lang['admin_invoices_controls_output_hook_error'] ?? 'admin_invoices_controls_output_hook_error') . $e->getMessage());
        return '';
    }
});

/**
 * HOOK: Botão de Mensagem Personalizada na Página do Cliente
 * Adiciona botão e modal para enviar mensagens via WhatsApp
 */
add_hook('AdminAreaClientSummaryActionLinks', 1, function($vars) {
    $clientId = $_GET["userid"] ?? 0;
    $lang = zapcel_load_lang();
    if (!$clientId) {
        return [];
    }
    
    // Busca validação WhatsApp
    $validation = Capsule::table('mod_zapcel_validation')
        ->where('client_id', $clientId)
        ->first();
    
    $isValidated = $validation && $validation->validated;
    $phoneNumber = $validation->phone_number ?? Capsule::table('tblclients')->where('id', $clientId)->value('phonenumber') ?? ($lang['not_registered'] ?? 'not_registered');


    
    // Busca respostas rápidas (templates com trigger_event começando com quick_reply)
    $quickReplies = Capsule::table('mod_zapcel_templates')
        ->where('trigger_event', 'LIKE', 'quick_reply%')
        ->where('active', 1)
        ->orderBy('name', 'asc')
        ->get();
    
    $contentArray = [];
    
    // ✅ BOTÃO WHATSAPP
    $contentArray[] = '<a href="#" class="btn btn-success btn-sm" data-toggle="modal" data-target="#zapcelMessageModal" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none; box-shadow: 0 2px 4px rgba(37, 211, 102, 0.3);">
        <i class="fab fa-whatsapp"></i> Mensagem Por Whatsapp
    </a>';
    
    // ✅ PAINEL DE VALIDAÇÃO
    $validationBadge = $isValidated 
        ? '<span class="badge badge-success" style="background-color: #118b19;"><i class="fas fa-check-circle"></i> Verificado</span>'
        : '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Não Verificado</span>';
    
    $contentArray[] = '<div class="panel panel-default" style="margin-top: 15px; border-left: 4px solid #25D366;">
        <div class="panel-heading" style="background: #f8f9fa;">
            <h3 class="panel-title">
                <i class="fab fa-whatsapp" style="color: #25D366;"></i> 
                <strong>Validação de WhatsApp</strong>
            </h3>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-6">
                    <strong>Número:</strong><br>
                    ' . htmlspecialchars($phoneNumber) . '
                </div>
                <div class="col-md-6 text-right">
                    <strong>Status:</strong><br>
                    ' . $validationBadge . '
                </div>
            </div>
        </div>
    </div>';
    
    // ✅ MODAL DE ENVIO
    $quickRepliesOptions = '<option value="">-- Selecione uma resposta rápida --</option>';
    foreach ($quickReplies as $reply) {
        $quickRepliesOptions .= '<option value="' . htmlspecialchars($reply->template) . '" data-quick-reply="' . $reply->trigger_event . '">' . htmlspecialchars($reply->name) . '</option>';
    }
    
    $contentArray[] = '
    <div class="modal fade" id="zapcelMessageModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white;">
                    <button type="button" class="close" data-dismiss="modal" style="color: white;">&times;</button>
                    <h4 class="modal-title">
                        <i class="fab fa-whatsapp"></i> Enviar Mensagem via WhatsApp
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Número:</strong> ' . htmlspecialchars($phoneNumber) . ' | 
                        <strong>Status:</strong> ' . ($isValidated ? '✅ Verificado' : '⚠️ Não Verificado') . '
                    </div>
                    
                    <form id="zapcelCustomMessageForm">
                        <input type="hidden" name="client_id" value="' . $clientId . '">
                        
                        <div class="form-group">
                            <label><i class="fas fa-comments"></i> <strong>Respostas Rápidas</strong></label>
                            <select class="form-control" id="quickReplySelect">
                                ' . $quickRepliesOptions . '
                            </select>
                            <small class="text-muted">Selecione uma resposta rápida predefinida ou escreva sua mensagem abaixo</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-edit"></i> <strong>Mensagem</strong></label>
                            <textarea name="message" id="customMessageText" class="form-control" rows="8" required placeholder= '. ($lang['type_your_message_here'] ?? 'type_your_message_here') .'></textarea>
                        </div>
                        
                        <div class="panel panel-default">
                            <div class="panel-heading" style="cursor: pointer;" data-toggle="collapse" data-target="#variablesPanel">
                                <i class="fas fa-code"></i> <strong>Variáveis Disponíveis</strong> 
                                <i class="fas fa-chevron-down pull-right"></i>
                            </div>
                            <div id="variablesPanel" class="panel-body collapse">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><strong>Dados do Cliente</strong></h5>
                                        <table class="table table-condensed table-bordered" style="font-size: 12px;">
                                            <tr><td><code>{cliente}</code></td><td>Nome completo</td></tr>
                                            <tr><td><code>{nome}</code></td><td>Primeiro nome</td></tr>
                                            <tr><td><code>{sobrenome}</code></td><td>Sobrenome</td></tr>
                                            <tr><td><code>{email}</code></td><td>Email</td></tr>
                                            <tr><td><code>{telefone}</code></td><td>Telefone</td></tr>
                                            <tr><td><code>{empresa}</code></td><td>Nome da empresa</td></tr>
                                            <tr><td><code>{cpf_cnpj}</code></td><td>CPF/CNPJ</td></tr>
                                        </table>
                                        
                                        <h5><strong>Endereço</strong></h5>
                                        <table class="table table-condensed table-bordered" style="font-size: 12px;">
                                            <tr><td><code>{endereco}</code></td><td>Endereço linha 1</td></tr>
                                            <tr><td><code>{endereco2}</code></td><td>Endereço linha 2</td></tr>
                                            <tr><td><code>{cidade}</code></td><td>Cidade</td></tr>
                                            <tr><td><code>{estado}</code></td><td>Estado</td></tr>
                                            <tr><td><code>{cep}</code></td><td>CEP</td></tr>
                                            <tr><td><code>{pais}</code></td><td>País</td></tr>
                                        </table>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5><strong>Serviços</strong></h5>
                                        <table class="table table-condensed table-bordered" style="font-size: 12px;">
                                            <tr><td><code>{total_servicos}</code></td><td>Total de serviços</td></tr>
                                            <tr><td><code>{servicos_ativos}</code></td><td>Serviços ativos</td></tr>
                                            <tr><td><code>{servicos_suspensos}</code></td><td>Serviços suspensos</td></tr>
                                            <tr><td><code>{lista_servicos}</code></td><td>Lista de serviços</td></tr>
                                        </table>
                                        
                                        <h5><strong>Faturas</strong></h5>
                                        <table class="table table-condensed table-bordered" style="font-size: 12px;">
                                            <tr><td><code>{ultima_fatura_id}</code></td><td>ID da última fatura</td></tr>
                                            <tr><td><code>{ultima_fatura_valor}</code></td><td>Valor</td></tr>
                                            <tr><td><code>{ultima_fatura_status}</code></td><td>Status</td></tr>
                                            <tr><td><code>{ultima_fatura_vencimento}</code></td><td>Vencimento</td></tr>
                                            <tr><td><code>{saldo_credito}</code></td><td>Saldo de crédito</td></tr>
                                        </table>
                                        
                                        <h5><strong>Domínios</strong></h5>
                                        <table class="table table-condensed table-bordered" style="font-size: 12px;">
                                            <tr><td><code>{total_dominios}</code></td><td>Total de domínios</td></tr>
                                            <tr><td><code>{lista_dominios}</code></td><td>Lista de domínios</td></tr>
                                            <tr><td><code>{primeiro_dominio}</code></td><td>Primeiro domínio</td></tr>
                                        </table>
                                        
                                        <h5><strong>Sistema</strong></h5>
                                        <table class="table table-condensed table-bordered" style="font-size: 12px;">
                                            <tr><td><code>{provedor}</code></td><td>Nome do provedor</td></tr>
                                            <tr><td><code>{url_whmcs}</code></td><td>URL do WHMCS</td></tr>
                                            <tr><td><code>{assinatura}</code></td><td>Assinatura</td></tr>
                                            <tr><td><code>{quebrar_mensagem}</code></td><td>Quebra de mensagem</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" id="sendCustomMessageBtn" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none;">
                        <i class="fab fa-whatsapp"></i> Enviar Mensagem
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        // Preenche textarea ao selecionar resposta rápida
        $("#quickReplySelect").change(function() {
            const template = $(this).val();
            if (template) {
                $("#customMessageText").val(template);
            }
        });
        
        // Envia mensagem
        $("#sendCustomMessageBtn").click(function() {
            const btn = $(this);
            const originalText = btn.html();
            const message = $("#customMessageText").val().trim();
            const selectedOption = $("#quickReplySelect").find(":selected");
            const quickReply = selectedOption.data("quick-reply") || "";
            
            console.log("Quick Reply selecionado:", quickReply); // DEBUG

            if (!message) {
                Swal.fire({
                    title: "Erro",
                    text: "Digite uma mensagem antes de enviar!",
                    icon: "error",
                    confirmButtonColor: "#d33",
                    confirmButtonText: "OK"
                });
                return;
            }
            
            btn.html("<i class=\"fas fa-spinner fa-spin\"></i> Enviando...").prop("disabled", true);
            
            $.ajax({
                url: "addonmodules.php?module=zapcel&action=ajax",
                method: "POST",
                data: {
                    admin_request: true,
                    client_id: ' . $clientId . ',
                    message: message,
                    quick_reply: quickReply
                },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: "Sucesso!",
                            text: response.message || "Mensagem enviada com sucesso!",
                            icon: "success",
                            confirmButtonColor: "#25D366",
                            confirmButtonText: "OK"
                        });
                        $("#zapcelMessageModal").modal("hide");
                        $("#customMessageText").val("");
                    } else {
                        Swal.fire({
                            title: "Erro",
                            text: response.error || "Erro ao enviar mensagem",
                            icon: "error",
                            confirmButtonColor: "#d33",
                            confirmButtonText: "OK"
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição:", error);
                    console.error("Response:", xhr.responseText);
                    Swal.fire({
                        title: "Erro",
                        text: "Erro na requisição: " + error,
                        icon: "error",
                        confirmButtonColor: "#d33",
                        confirmButtonText: "OK"
                    });
                },
                complete: function() {
                    btn.html(originalText).prop("disabled", false);
                }
            });
        });
    });
    </script>
    ';
    
    return $contentArray;
});

/**
 * HOOK: Enviar Mensagem Personalizada ao Cliente
 * Processa e envia mensagem personalizada (usado pelo botão na página do cliente)
 */
add_hook('SendCustomMessage', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $clientId = $vars['clientid'] ?? 0;
        $message = $vars['message'] ?? '';
        $quickReply = $vars['quick_reply'] ?? null; // ✅ NOVO PARÂMETRO
        
        if (!$clientId || !$message) {
            return;
        }
        
        // Carrega configurações
        $settings = zapcel_get_settings();
        
        if (!$settings['zapcel_enabled']) {
            return;
        }
        
        // Carrega cliente
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            return;
        }
        
        // Prepara telefone
        $phoneSource = $settings['zapcel_phone_source'] ?? 'phonenumber';
        $phoneNumber = $client->{$phoneSource} ?? $client->phonenumber ?? '';
        
        if (!$phoneNumber) {
            return;
        }

        $phoneNumber = zapcel_format_phone_number($phoneNumber);
        
        // ✅ Carrega TODAS as variáveis (padrão + personalizadas)
        $variables = zapcel_get_default_variables($clientId, $settings);
        $customVars = zapcel_get_custom_field_variables($clientId);
        $variables = array_merge($variables, $customVars);
        
                
        // Carrega API e Processor
        require_once __DIR__ . '/api/WhatsAppAPI.php';
        require_once __DIR__ . '/api/MessageProcessor.php';
        
        $api = new \WHMCS\Module\Addon\Zapcel\Api\WhatsAppAPI($settings);
        $processor = new \WHMCS\Module\Addon\Zapcel\Api\MessageProcessor($settings);
        
        // Processa template (substitui variáveis)
        $messageParts = $processor->processTemplate($message, $variables, 'custom_message', true);
        
        // Envia mensagem
        $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

        // DEBUG: Verifica se o quick_reply está chegando
        zapcel_log_debug('SendCustomMessage', 'Quick Reply recebido', [
            'client_id' => $clientId,
            'quick_reply' => $quickReply,
            'has_quick_reply' => !empty($quickReply),
            'message_length' => strlen($message)
        ]);
        
        // Log
        if ($result['success']) {
            zapcel_log_message($clientId, 'custom_message_manual', $phoneNumber, ($lang['custom_message_log'] ?? 'custom_message_log'), true, json_encode($result), $quickReply);
        } else {
            zapcel_log_message($clientId, 'custom_message_manual', $phoneNumber, ($lang['custom_message_log'] ?? 'custom_message_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'), $quickReply);
        }
        
    } catch (Exception $e) {
        zapcel_log_error(($lang['send_custom_message_hook_error'] ?? 'send_custom_message_hook_error') . $e->getMessage());
    }
});

/**
 * HOOK: Enviar Mensagem Personalizada ao Cliente
 * Processa e envia mensagem personalizada (usado pelo botão na página do cliente)
 */
/*add_hook('SendCustomMessage', 1, function($vars) {
    $lang = zapcel_load_lang();
    try {
        $clientId = $vars['clientid'] ?? 0;
        $message = $vars['message'] ?? '';
        $quickReply = $vars['quick_reply'] ?? null; // ✅ NOVO PARÂMETRO
        
        if (!$clientId || !$message) {
            return;
        }
        
        // Carrega configurações
        $settings = zapcel_get_settings();
        
        if (!$settings['zapcel_enabled']) {
            return;
        }
        
        // Carrega cliente
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            return;
        }
        
        // Prepara telefone
        $phoneSource = $settings['zapcel_phone_source'] ?? 'phonenumber';
        $phoneNumber = $client->{$phoneSource} ?? $client->phonenumber ?? '';
        
        if (!$phoneNumber) {
            return;
        }

        $phoneNumber = zapcel_format_phone_number($phoneNumber);
        
        // ✅ TODAS AS VARIÁVEIS DO CLIENTE
        $variables = [
            // Dados pessoais
            'cliente' => trim($client->firstname . ' ' . $client->lastname),
            'nome' => $client->firstname,
            'sobrenome' => $client->lastname,
            'email' => $client->email,
            'telefone' => $phoneNumber,
            
            // Endereço
            'endereco' => $client->address1 ?? '',
            'endereco2' => $client->address2 ?? '',
            'cidade' => $client->city ?? '',
            'estado' => $client->state ?? '',
            'cep' => $client->postcode ?? '',
            'pais' => $client->country ?? '',
            
            // Empresa
            'empresa' => $client->companyname ?? '',
            'cpf_cnpj' => $client->tax_id ?? '',
            
            // Sistema
            'provedor' => $settings['company_name'] ?? ($lang['provider'] ?? 'provider'),
            'url_whmcs' => $settings['url_whmcs'] ?? '',
            'assinatura' => $settings['zapcel_signature'] ?? '',
        ];
        
        // ✅ BUSCA PRODUTOS/SERVIÇOS ATIVOS DO CLIENTE
        $services = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->get();
        
        if ($services->count() > 0) {
            $servicesList = [];
            $activeServices = 0;
            $suspendedServices = 0;
            
            foreach ($services as $service) {
                $productName = Capsule::table('tblproducts')
                    ->where('id', $service->packageid)
                    ->value('name');
                
                $servicesList[] = $productName ?? 'Serviço #' . $service->id;
                
                if ($service->domainstatus === 'Active') {
                    $activeServices++;
                } else {
                    $suspendedServices++;
                }
            }
            
            $variables['servicos_ativos'] = $activeServices;
            $variables['servicos_suspensos'] = $suspendedServices;
            $variables['total_servicos'] = $services->count();
            $variables['lista_servicos'] = implode(', ', $servicesList);
        } else {
            $variables['servicos_ativos'] = 0;
            $variables['servicos_suspensos'] = 0;
            $variables['total_servicos'] = 0;
            $variables['lista_servicos'] = ($lang['no_service'] ?? 'no_service');
        }
        
        // ✅ BUSCA ÚLTIMA FATURA DO CLIENTE
        $lastInvoice = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastInvoice) {
            $variables['ultima_fatura_id'] = $lastInvoice->id;
            $variables['ultima_fatura_valor'] = formatCurrency($lastInvoice->total);
            $variables['ultima_fatura_status'] = $lastInvoice->status;
            $variables['ultima_fatura_vencimento'] = date('d/m/Y', strtotime($lastInvoice->duedate));
        } else {
            $variables['ultima_fatura_id'] = '';
            $variables['ultima_fatura_valor'] = '';
            $variables['ultima_fatura_status'] = '';
            $variables['ultima_fatura_vencimento'] = '';
        }
        
        // ✅ BUSCA SALDO DO CLIENTE
        $credit = Capsule::table('tblcredit')
            ->where('clientid', $clientId)
            ->sum('amount');
        
        $variables['saldo_credito'] = formatCurrency($credit);
        
        // ✅ BUSCA DOMÍNIOS DO CLIENTE
        $domains = Capsule::table('tbldomains')
            ->where('userid', $clientId)
            ->whereIn('status', ['Active', 'Pending'])
            ->get();
        
        if ($domains->count() > 0) {
            $domainsList = [];
            foreach ($domains as $domain) {
                $domainsList[] = $domain->domain;
            }
            
            $variables['total_dominios'] = $domains->count();
            $variables['lista_dominios'] = implode(', ', $domainsList);
            $variables['primeiro_dominio'] = $domainsList[0] ?? '';
        } else {
            $variables['total_dominios'] = 0;
            $variables['lista_dominios'] = ($lang['no_domain'] ?? 'no_domain');
            $variables['primeiro_dominio'] = '';
        }
        
        // Carrega API e Processor
        require_once __DIR__ . '/api/WhatsAppAPI.php';
        require_once __DIR__ . '/api/MessageProcessor.php';
        
        $api = new \WHMCS\Module\Addon\Zapcel\Api\WhatsAppAPI($settings);
        $processor = new \WHMCS\Module\Addon\Zapcel\Api\MessageProcessor($settings);
        
        // Processa template (substitui variáveis)
        $messageParts = $processor->processTemplate($message, $variables, 'custom_message', true);
        
        // Envia mensagem
        $result = zapcel_send_message_with_media($api, $phoneNumber, $messageParts, $variables);

        // DEBUG: Verifica se o quick_reply está chegando
        zapcel_log_debug('SendCustomMessage', 'Quick Reply recebido', [
            'client_id' => $clientId,
            'quick_reply' => $quickReply,
            'has_quick_reply' => !empty($quickReply),
            'message_length' => strlen($message)
        ]);
        
        // Log
        if ($result['success']) {
            zapcel_log_message($clientId, 'custom_message_manual', $phoneNumber, ($lang['custom_message_log'] ?? 'custom_message_log'), true, json_encode($result), $quickReply);
        } else {
            zapcel_log_message($clientId, 'custom_message_manual', $phoneNumber, ($lang['custom_message_log'] ?? 'custom_message_log'), false, json_encode($result) ?? ($lang['unknown_error'] ?? 'unknown_error'), $quickReply);
        }
        
    } catch (Exception $e) {
        zapcel_log_error(($lang['send_custom_message_hook_error'] ?? 'send_custom_message_hook_error') . $e->getMessage());
    }
});*/

/**
 * Hook ÚNICO - Validação WhatsApp (Bloqueio + Renderização)
 */
/**
 * Hook ÚNICO - Validação WhatsApp
 */
/**
 * Hook de Validação WhatsApp
 * Redireciona cliente não validado para ?m=zapcel
 */
add_hook("ClientAreaPage", 1, function ($vars) {
    
    // Se não tem usuário logado, não faz nada
    if (!isset($_SESSION["uid"]) || $_SESSION["uid"] <= 0) {
        return NULL;
    }
    
    // SE JÁ ESTÁ NA PÁGINA DO ZAPCEL, NÃO REDIRECIONA (EVITA LOOP)
    if (isset($_GET["m"]) && $_GET["m"] == "zapcel") {
        return NULL;
    }
    
    // Carrega configuração de validação obrigatória
    $validacaoObrigatoria = Capsule::table('tbladdonmodules')
        ->where('module', 'zapcel')
        ->where('setting', 'zapcel_validation')
        ->value('value');
    
    // Se validação não está habilitada, não faz nada
    if ($validacaoObrigatoria != "1") {
        return NULL;
    }
    
    // Verifica se cliente já está validado
    $validacao = Capsule::table('mod_zapcel_validation')
        ->where('client_id', $_SESSION["uid"])
        ->first();
    
    // Se não tem registro OU não está validado, redireciona
    if (!$validacao || $validacao->validated != 1) {
        // Redireciona para ?m=zapcel (função zapcel_clientarea processa)
        redir("m=zapcel", "index.php");
        exit();
    }
    
    return NULL;
});



/**
 * Adiciona menu de validação WhatsApp na área do cliente
 */
add_hook('ClientAreaPrimarySidebar', 1, function($sidebar) {
    $lang = zapcel_load_lang();
    $clientId = $_SESSION['uid'] ?? 0;
    if (!$clientId) {
        return;
    }
    
    // Verifica se validação está habilitada
    $settings = zapcel_get_settings();
    if (!($settings['require_validation'] ?? false)) {
        return;
    }
    
    // Busca status de validação
    $validation = Capsule::table('mod_zapcel_validation')->where('client_id', $clientId)->first();
    
    $icon = 'fa-whatsapp';
    $badge = '';
    
    if ($validation && $validation->validated) {
        $badge = '<span class="badge badge-success">' . ($lang["validated"] ?? "validated") . '</span>';
    } else {
        $badge = '<span class="badge badge-warning">' . ($lang["pending"] ?? "pending") . '</span>';
    }
    
    // Adiciona item no menu
    if (!is_null($sidebar->getChild('Account Details'))) {
        $sidebar->getChild('Account Details')
            ->addChild('WhatsApp Validation', [
                'label' => ($lang['whatsapp_validation_label'] ?? 'whatsapp_validation_label') . $badge,
                'uri' => 'clientarea.php?action=zapcel_validate',
                'icon' => $icon,
                'order' => 100,
            ]);
    }
});

/**
 * Adiciona template da página de validação
 */
add_hook('ClientAreaPageWhatsAppValidation', 1, function($vars) {
    $lang = zapcel_load_lang();
    return [
        'pagetitle' => 'Validação WhatsApp',
        'breadcrumb' => ['index.php?action=zapcel_validate' => 'Validação WhatsApp'],
        'templatefile' => 'zapcel_validation',
    ];
});

/**
 * HOOK: Inclui SweetAlert2 nas páginas administrativas
 */
add_hook('AdminAreaHeaderOutput', 1, function($vars) {
    return zapcel_include_sweetalert2();
});

/**
 * INTEGRAÇÃO AUTO LOGIN NOS HOOKS
 * 
 * Adicionar estas funções no arquivo hooks.php
 * E chamar nos hooks de Invoice e Ticket
 */

/**
 * Gera variáveis de auto login para faturas
 * 
 * @param int $clientId ID do cliente
 * @param int $invoiceId ID da fatura
 * @return array Variáveis adicionais
 */
function zapcel_get_autologin_invoice_variables($clientId, $invoiceId)
{
    try {
        require_once __DIR__ . '/api/AutoLogin.php';
        $autoLogin = new \WHMCS\Module\Addon\Zapcel\Api\AutoLogin();
        $result = $autoLogin->generateInvoiceToken($clientId, $invoiceId);
        
        if ($result['success']) {
            return [
                'link_fatura_autologin' => $result['url'],
                'token_autologin' => $result['token'],
                'token_expiracao' => date('d/m/Y H:i', strtotime($result['expires_at']))
            ];
        }
        
        return [];
        
    } catch (Exception $e) {
        zapcel_log_error('Erro ao gerar token autologin para fatura: ' . $e->getMessage());
        return [];
    }
}

/**
 * Gera variáveis de auto login para tickets
 * 
 * @param int $clientId ID do cliente
 * @param int $ticketId ID do ticket
 * @return array Variáveis adicionais
 */
function zapcel_get_autologin_ticket_variables($clientId, $ticketId)
{
    try {
        require_once __DIR__ . '/api/AutoLogin.php';
        $autoLogin = new \WHMCS\Module\Addon\Zapcel\Api\AutoLogin();
        $result = $autoLogin->generateTicketToken($clientId, $ticketId);
        
        if ($result['success']) {
            return [
                'link_ticket_autologin' => $result['url'],
                'token_autologin' => $result['token'],
                'token_expiracao' => date('d/m/Y H:i', strtotime($result['expires_at']))
            ];
        }
        
        return [];
        
    } catch (Exception $e) {
        zapcel_log_error('Erro ao gerar token autologin para ticket: ' . $e->getMessage());
        return [];
    }
}

/**
 * ════════════════════════════════════════════════════════════════════════
 * HOOK: ADICIONAR VARIÁVEIS DE AUTOLOGIN NOS EMAILS DO WHMCS
 * ════════════════════════════════════════════════════════════════════════
 * 
 * ADICIONAR NO ARQUIVO: modules/addons/zapcel/hooks.php
 * LOCAL: No final do arquivo, antes do último ?>
 * 
 * VARIÁVEIS DISPONÍVEIS NOS TEMPLATES DE EMAIL:
 * - {link_fatura_autologin} - Link de Acesso Direto à Fatura
 * - {link_ticket_autologin} - Link de Acesso Direto ao Ticket
 * - {token_autologin} - Token de Acesso
 * - {token_expiracao} - Expiração do Token
 * ════════════════════════════════════════════════════════════════════════
 */
/**
 * Hook que adiciona variáveis de AutoLogin aos emails do WHMCS
 */
add_hook('EmailPreSend', 1, function($vars) {
    try {
        // Verifica se o módulo Zapcel está ativo
        $zapcelEnabled = Capsule::table('tbladdonmodules')
            ->where('module', 'zapcel')
            ->where('setting', 'zapcel_enabled')
            ->where('value', 'on')
            ->exists();
            
        if (!$zapcelEnabled) {
            return $vars;
        }
        
        // Carrega AutoLogin
        require_once __DIR__ . '/api/AutoLogin.php';
        $autoLogin = new \WHMCS\Module\Addon\Zapcel\Api\AutoLogin();
        
        $clientId = $vars['relid'] ?? null;
        $mergeFields = $vars['mergefields'] ?? [];
        
        // Detecta tipo de email e gera token apropriado
        $autologinVars = [];
        
        // ═══════════════════════════════════════════════════════════════
        // EMAILS DE FATURA
        // ═══════════════════════════════════════════════════════════════
        if (isset($mergeFields['invoice_id']) && $clientId) {
            $invoiceId = $mergeFields['invoice_id'];
            
            $result = $autoLogin->generateInvoiceToken($clientId, $invoiceId);
            
            if ($result['success']) {
                $autologinVars = [
                    'link_fatura_autologin' => $result['url'],
                    'token_autologin' => $result['token'],
                    'token_expiracao' => date('d/m/Y H:i', strtotime($result['expires_at']))
                ];
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // EMAILS DE TICKET
        // ═══════════════════════════════════════════════════════════════
        elseif (isset($mergeFields['ticket_id']) && $clientId) {
            $ticketId = $mergeFields['ticket_id'];
            
            $result = $autoLogin->generateTicketToken($clientId, $ticketId);
            
            if ($result['success']) {
                $autologinVars = [
                    'link_ticket_autologin' => $result['url'],
                    'token_autologin' => $result['token'],
                    'token_expiracao' => date('d/m/Y H:i', strtotime($result['expires_at']))
                ];
            }
        }
        
        // ═══════════════════════════════════════════════════════════════
        // ADICIONA VARIÁVEIS AO EMAIL
        // ═══════════════════════════════════════════════════════════════
        if (!empty($autologinVars)) {
            $vars['mergefields'] = array_merge($mergeFields, $autologinVars);
        }
        
        return $vars;
        
    } catch (\Exception $e) {
        // Em caso de erro, não bloqueia o envio do email
        zapcel_log_error('Erro ao adicionar AutoLogin no email: ' . $e->getMessage());
        return $vars;
    }
});

/**
 * HOOK: Desabilita campo de telefone na página de editar detalhes
 * Impede que cliente edite o telefone
 */
add_hook('ClientAreaFooterOutput', 999, function($vars) {
    try {
        // Só na página de editar detalhes
        if (!isset($_GET['action']) || $_GET['action'] != 'details') {
            return '';
        }
        
        $html = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const phoneInput = document.querySelector(\'input[name="phonenumber"]\');
    if (phoneInput) {
        phoneInput.disabled = true;
        phoneInput.style.backgroundColor = "#f5f5f5";
        phoneInput.style.cursor = "not-allowed";
        phoneInput.title = "O telefone não pode ser alterado. Entre em contato com o suporte.";
    }
});
</script>';
        
        return $html;
        
    } catch (Exception $e) {
        return '';
    }
});

