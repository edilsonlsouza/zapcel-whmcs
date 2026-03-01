<?php
/**
 * Zapcel WHMCS - Auto Login
 * Com página de erro minimalista em cores pastel
 */

require_once __DIR__ . '/init.php';
use WHMCS\Database\Capsule;

/* =======================
 * Carregar idioma do módulo
 * ======================= */
$_LANG = [];
try {
    $langSetting = Capsule::table('tbladdonmodules')
        ->where('module', 'zapcel')
        ->where('setting', 'language')
        ->value('value') ?? 'portuguese';

    $langFile = $langSetting === 'english'
        ? __DIR__ . '/modules/addons/zapcel/langs/en.php'
        : __DIR__ . '/modules/addons/zapcel/langs/pt.php';

    if (file_exists($langFile)) {
        $_LANG = include $langFile;
        if (!is_array($_LANG)) { $_LANG = []; }
    }
} catch (\Throwable $e) {
    $_LANG = [];
}

// helper simples para traduzir aqui (sem dependências externas)
function __t($key, $fallback) {
    global $_LANG;
    return $_LANG[$key] ?? $fallback;
}

/* =======================
 * Captura IP real do cliente
 * ======================= */
/* =======================
 * Captura IP real do cliente (prioriza proxy/CDN)
 * ======================= */
function getClientIp()
{
    // 1) Cloudflare
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    // 2) Akamai / alguns CDNs
    if (!empty($_SERVER['HTTP_TRUE_CLIENT_IP'])) {
        $ip = trim($_SERVER['HTTP_TRUE_CLIENT_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    // 3) X-Forwarded-For (pega o primeiro da cadeia; aceita IP privado também)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        foreach ($parts as $p) {
            $candidate = preg_replace('/:\d+$/', '', $p); // remove porta se vier 1.2.3.4:5678
            if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
        }
    }
    // 4) X-Real-IP (nginx/haproxy)
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = preg_replace('/:\d+$/', '', trim($_SERVER['HTTP_X_REAL_IP']));
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    // 5) REMOTE_ADDR (fallback)
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = preg_replace('/:\d+$/', '', trim($_SERVER['REMOTE_ADDR']));
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return 'unknown';
}

$token = $_GET['token'] ?? null;

if (!$token) {
    showError(__t('auto_token_missing', 'Token não fornecido'));
}

// Buscar token no banco
$tokenData = Capsule::table('mod_zapcel_autologin')
    ->where('token', $token)
    ->first();

if (!$tokenData) {
    showError(__t('auto_token_invalid', 'Token inválido'));
}

// Verificar expiração
$expirationTime = strtotime($tokenData->expires_at);

if (time() > $expirationTime) {
    Capsule::table('mod_zapcel_autologin')
        ->where('id', $tokenData->id)
        ->update(['status' => 'expired']);
    showError(__t('auto_token_expired', 'Token expirado'));
}

// Registrar acesso
$ipAddress = getClientIp();

Capsule::table('mod_zapcel_autologin')
    ->where('id', $tokenData->id)
    ->increment('access_count');

Capsule::table('mod_zapcel_autologin')
    ->where('id', $tokenData->id)
    ->update([
        'last_access_at' => date('Y-m-d H:i:s'),
        'last_ip' => $ipAddress
    ]);

$accessLog = Capsule::table('mod_zapcel_autologin_access')
    ->where('autologin_id', $tokenData->id)
    ->where('ip_address', $ipAddress)
    ->first();

if ($accessLog) {
    Capsule::table('mod_zapcel_autologin_access')
        ->where('id', $accessLog->id)
        ->update([
            'access_count' => $accessLog->access_count + 1,
            'last_access' => date('Y-m-d H:i:s')
        ]);
} else {
    Capsule::table('mod_zapcel_autologin_access')->insert([
        'autologin_id' => $tokenData->id,
        'ip_address' => $ipAddress,
        'access_count' => 1,
        'first_access' => date('Y-m-d H:i:s'),
        'last_access' => date('Y-m-d H:i:s'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

// Preparar parâmetros para CreateSsoToken
$params = ['client_id' => $tokenData->client_id];

if ($tokenData->target_type === 'invoice') {
    $params['destination'] = 'sso:custom_redirect';
    $params['sso_redirect_path'] = "/viewinvoice.php?id={$tokenData->target_id}";
} elseif ($tokenData->target_type === 'ticket') {
    $ticket = Capsule::table('tbltickets')
        ->where('id', $tokenData->target_id)
        ->first();
    
    if ($ticket) {
        $params['destination'] = 'sso:custom_redirect';
        $params['sso_redirect_path'] = "/viewticket.php?tid={$ticket->tid}&c={$ticket->c}";
    }
}

// Chamar API CreateSsoToken
$response = localAPI('CreateSsoToken', $params);

if ($response['result'] == 'success') {
    logActivity(
        "Zapcel Auto Login: Cliente #{$tokenData->client_id} acessou {$tokenData->target_type} #{$tokenData->target_id}",
        $tokenData->client_id
    );
    header("Location: " . $response['redirect_url']);
    exit;
} else {
    error_log("Erro ao gerar SSO token: " . ($response['message'] ?? 'Erro desconhecido'));
    showError(__t('auto_auth_error', 'Erro ao processar autenticação'));
}

/**
 * Página de erro minimalista com cores pastel
 */
function showError($message)
{
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo htmlspecialchars($_GET['lang'] ?? 'pt-BR'); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo __t('auto_error_title', 'Link Inválido'); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #f5f7fa; min-height: 100vh; display: flex; align-items: center; justify-content: center;
                padding: 20px; color: #4a5568;
            }
            .container { background: white; border-radius: 12px; padding: 48px 32px; max-width: 480px; width: 100%;
                box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; }
            .icon { width: 64px; height: 64px; margin: 0 auto 24px; background: #fef3c7; border-radius: 50%;
                display: flex; align-items: center; justify-content: center; font-size: 32px; }
            h1 { font-size: 24px; font-weight: 600; color: #1a202c; margin-bottom: 12px; }
            .message { font-size: 15px; color: #718096; margin-bottom: 24px; line-height: 1.6; }
            .error-detail { background: #fef3c7; border-left: 3px solid #f59e0b; padding: 16px; border-radius: 6px;
                margin-bottom: 32px; text-align: left; }
            .error-detail strong { display: block; color: #92400e; font-size: 13px; font-weight: 600; margin-bottom: 4px;
                text-transform: uppercase; letter-spacing: 0.5px; }
            .error-detail p { color: #78350f; font-size: 14px; margin: 0; }
            .info-list { background: #f9fafb; border-radius: 8px; padding: 20px; margin-bottom: 32px; text-align: left; }
            .info-list p { font-size: 14px; color: #6b7280; margin: 0; padding: 8px 0 8px 24px; position: relative; }
            .info-list p::before { content: ""; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
                width: 6px; height: 6px; background: #d1d5db; border-radius: 50%; }
            .btn { display: inline-block; background: #3b82f6; color: #fff; padding: 12px 32px; border-radius: 8px;
                text-decoration: none; font-weight: 500; font-size: 15px; transition: all .2s; }
            .btn:hover { background: #2563eb; transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(59,130,246,.3); }
            .footer { margin-top: 24px; font-size: 13px; color: #9ca3af; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">🔗</div>
            <h1><?php echo __t('auto_link_invalid_or_expired', 'Link Inválido ou Expirado'); ?></h1>
            <p class="message"><?php echo __t('auto_error_message', 'Não foi possível processar seu link de acesso.'); ?></p>
            <div class="error-detail">
                <strong><?php echo __t('auto_reason', 'Motivo'); ?></strong>
                <p><?= htmlspecialchars($message) ?></p>
            </div>
            <div class="info-list">
                <p><?php echo __t('auto_info_validity', 'Links de acesso são válidos por 72 horas'); ?></p>
                <p><?php echo __t('auto_info_check_link', 'Verifique se o link está completo e correto'); ?></p>
                <p><?php echo __t('auto_info_contact_support', 'Entre em contato com o suporte se necessário'); ?></p>
            </div>
            <a href="<?php echo $params['sso_redirect_path']; ?>/clientarea.php" class="btn">
                <?php echo __t('auto_go_client_area', 'Acessar Área do Cliente'); ?>
            </a>
            <p class="footer">
                Hostcel © <?= date('Y') ?>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
