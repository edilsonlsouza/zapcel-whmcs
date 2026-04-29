<?php
namespace WHMCS\Module\Addon\Zapcel\Admin;

use function \zapcel_trans;

/**
 * Zapcel WHMCS - Painel Administrativo
 * Interface moderna e profissional para gerenciamento do módulo
 * 
 * @package    Zapcel
 * @author     Hostcel
 * @version    2.0.0
 */

// Bloqueia acesso direto
if (!defined('WHMCS')) {
    die(zapcel_trans('access_denied'));
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Zapcel\Api\StatisticsManager;
use WHMCS\Module\Addon\Zapcel\Api\WhatsAppAPI;

// CARREGA AS CLASSES NECESSÁRIAS
require_once __DIR__ . '/../api/StatisticsManager.php';
require_once __DIR__ . '/../api/WhatsAppAPI.php';
require_once __DIR__ . '/../api/NumberValidator.php';

// ✅ ENDPOINT UNIFICADO: Enviar mensagens (lembrete de fatura OU mensagem personalizada)
if (isset($_POST['admin_request']) && $_POST['admin_request'] === 'true') {
    header('Content-Type: application/json');
    
    try {
        // CASO 1: Lembrete de Fatura Manual (invoice_id presente)
        if (isset($_POST['invoice_id'])) {
            $invoiceId = (int)($_POST['invoice_id'] ?? 0);
            
            if (!$invoiceId) {
                echo json_encode([
                    'success' => false,
                    'error' => zapcel_trans('invalid_id') // Usando 'ID inválido' para fatura
                ]);
                exit;
            }
            
            // ✅ Dispara hook InvoicePaymentReminder
            run_hook('InvoicePaymentReminder', ['invoiceid' => $invoiceId]);
            
            echo json_encode([
                'success' => true,
                'message' => zapcel_trans('reminder_sent_successfully_to') // Adaptando de 'Lembrete enviado com sucesso para'
            ]);
            exit;
        }
        
        // CASO 2: Mensagem Personalizada ao Cliente (message presente)
        if (isset($_POST['message'])) {
            $clientId = (int)($_POST['client_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $quickReply = trim($_POST['quick_reply'] ?? ''); 
            
            if (!$clientId || !$message) {
                echo json_encode([
                    'success' => false,
                    'error' => zapcel_trans('invalid_client') // Usando 'Cliente inválido' para o caso de ID ou mensagem inválida
                ]);
                exit;
            }
            
            // ✅ Dispara hook SendCustomMessage
            run_hook('SendCustomMessage', [
                'clientid' => $clientId,
                'message' => $message,
                'quick_reply' => $quickReply 
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => zapcel_trans('message_sent_successfully') // Usando 'Mensagem enviada com sucesso!'
            ]);
            exit;
        }
        
        // Nenhum caso válido
        echo json_encode([
            'success' => false,
            'error' => zapcel_trans('unrecognized_action') // Usando 'Ação não reconhecida'
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            // Mantendo a concatenação da exceção para debug, mas internacionalizando o prefixo.
            'error' => zapcel_trans('exception') . ': ' . $e->getMessage() 
        ]);
        exit;
    }
}

// CARREGA IDIOMA DINÂMICO
$languageSetting = Capsule::table('tbladdonmodules')
    ->where('module', 'zapcel')
    ->where('setting', 'language')
    ->value('value') ?? 'portuguese';

$langFile = $languageSetting === 'english' 
    ? __DIR__ . '/../langs/en.php' 
    : __DIR__ . '/../langs/pt.php';

$LANG = include $langFile;

/**
 * Dispatcher do painel administrativo
 */
class AdminDispatcher
{
    private $vars;
    private $statsManager;
    private $whatsappAPI;
    private $LANG;

    public function __construct($vars)
    {
        $this->vars = $vars;
        
        // Carrega idioma
        global $LANG;
        $this->LANG = $LANG;
        
        // Se for requisição AJAX, não carrega configurações ainda
        // (serão carregadas sob demanda se necessário)
        $action = $_REQUEST['action'] ?? '';
        if ($action === 'ajax') {
            $this->statsManager = null;
            $this->whatsappAPI = null;
            return;
        }
        
        try {
            $this->statsManager = new StatisticsManager();
            $this->whatsappAPI = new WhatsAppAPI($this->getSettings());
        } catch (\Exception $e) {
            // Se der erro ao carregar classes, cria objetos vazios
            $this->statsManager = null;
            $this->whatsappAPI = null;
        }
    }

    /**
     * Obtém configurações do módulo
     */
    private function getSettings()
    {
        return Capsule::table('tbladdonmodules')
            ->where('module', 'zapcel')
            ->pluck('value', 'setting')
            ->toArray();
    }

    /**
     * Dispatch principal para rotas do admin
     */
    public function dispatch($action)
    {
        // Se for AJAX, não adiciona CSS nem HTML
        if ($action === 'ajax') {
            if (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo $this->handleAjax();
            exit;
        }
        
        // Adiciona CSS personalizado para organizar a interface
        echo $this->getCustomCSS();
        
        switch ($action) {
            case 'templates':
                return $this->templatesPage();
                
            case 'edit_template':
                return $this->editTemplatePage();
                
            case 'statistics':
                return $this->statisticsPage();
                
            case 'validation':
                return $this->validationPage();
                
            case 'gateways':
                return $this->gatewaysPage();
                
            case 'campaigns':
                return $this->campaignsPage();
                break;
                
            case 'logs':
                return $this->logsPage();
                
            case 'settings':
                return $this->settingsPage();
                
            case 'test_message':
                return $this->testMessagePage();

            case 'autologin':
                return $this->autologinPage();

            case 'dashboard':
            default:
                return $this->dashboardPage();
        }
    }

    /**
     * CSS personalizado para organizar a interface
     */
    private function getCustomCSS()
    {
        return '
        <meta charset="UTF-8">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <style>
        .zapcel-admin-container {
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .header-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #28a745;
        }
        
        .header-container h2 {
            color: #2c3e50;
            margin: 0;
            font-weight: 600;
        }
        
        .header-container .text-muted {
            color: #6c757d !important;
            margin: 5px 0 0 0;
        }
        
        /* Cards de estatísticas melhorados */
        .info-box {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e3e6f0;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s;
        }
        
        .info-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }
        
        .info-box-icon {
            float: left;
            height: 70px;
            width: 70px;
            text-align: center;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .info-box-icon i {
            font-size: 24px;
            color: white;
        }
        
        .info-box-content {
            margin-left: 85px;
        }
        
        .info-box-text {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .info-box-number {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin: 5px 0;
        }
        
        /* Tabelas organizadas */
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table {
            background: white;
            border-radius: 8px;
        }
        
        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #e3e6f0;
            font-weight: 600;
            color: #2c3e50;
            padding: 12px 15px;
        }
        
        .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-color: #e3e6f0;
        }
        
        /* Badges melhorados */
        .badge {
            font-size: 11px;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        /* Cards modernos */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 25px;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0 !important;
        }
        
        .card-header h3 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
            font-size: 18px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Botões organizados */
        .btn-group .btn {
            margin-right: 5px;
            border-radius: 4px;
        }
        
        .btn-group .btn:last-child {
            margin-right: 0;
        }
        
        /* Formulários organizados */
        #settingsForm .form-group {
            margin-bottom: 20px;
        }

        #settingsForm .form-control {
            border-radius: 4px;
            border: 1px solid #d1d3e2;
            padding: 11px 12px;
            min-height: 42px;
            width: 100%; /* Boa prática para inputs */
            box-sizing: border-box; /* Importante! */
        }

        /* Apenas para selects dentro do form */
        #settingsForm select.form-control {
            /* height: 42px; */ /* Removido - melhor usar padding/min-height */
            appearance: menulist; /* Mantém aparência nativa do select */
        }

        #settingsForm .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none; /* Remove outline padrão do browser */
        }
        
        /* Small boxes para estatísticas */
        .small-box {
            border-radius: 8px;
            position: relative;
            margin-bottom: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .small-box .inner {
            padding: 20px;
            color: white;
        }
        
        .small-box h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            color: white;
        }
        
        .small-box p {
            font-size: 14px;
            margin: 0;
            opacity: 0.9;
        }
        
        .small-box .icon {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 70px;
            opacity: 0.3;
            transition: all 0.3s;
        }
        
        .small-box:hover .icon {
            transform: scale(1.1);
        }
        
        /* Cores dos boxes */
        .bg-primary { background: linear-gradient(45deg, #4e73df, #2e59d9) !important; }
        .bg-success { background: linear-gradient(45deg, #1cc88a, #17a673) !important; }
        .bg-info { background: linear-gradient(45deg, #36b9cc, #258391) !important; }
        .bg-warning { background: linear-gradient(45deg, #f6c23e, #dda20a) !important; }
        
        /* List groups organizados */
        .list-group-item {
            border: 1px solid #e3e6f0;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .list-group-item i {
            margin-right: 8px;
            width: 16px;
        }
        
        /* Grid system melhorado */
        .row {
            margin-left: -10px;
            margin-right: -10px;
        }
        
        .row > [class*="col-"] {
            padding-left: 10px;
            padding-right: 10px;
        }
        
        /* Modal melhorado */
        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
        }
        
        .modal-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
            border-radius: 8px 8px 0 0;
            padding: 15px 20px;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .header-container .text-right {
                text-align: left !important;
                margin-top: 15px;
            }
            
            .info-box-icon {
                float: none;
                margin: 0 auto 15px auto;
            }
            
            .info-box-content {
                margin-left: 0;
                text-align: center;
            }
        }
        </style>
        ';
    }

    /**
     * Página inicial - Dashboard (CORRIGIDA)
     */
    private function dashboardPage()
    {
        try {
            // Estatísticas básicas - CORREÇÃO v2.0.1: Baseado em usage_count dos templates
            $totalMessages = Capsule::table('mod_zapcel_templates')
                ->sum('usage_count');

            // Para cálculo da taxa de sucesso, usamos APENAS os logs (mensagens realmente enviadas)
            $logsForStats = Capsule::table('mod_zapcel_logs')
                ->where(function($query) {
                    $query->where('event_type', 'NOT LIKE', 'debug_%')
                        ->where('event_type', '!=', 'gateway_manager_debug')
                        ->where('event_type', '!=', 'system_log')
                        ->where('message', 'NOT LIKE', '[DEBUG]%');
                })
                ->whereIn('success', [0, 1]);
                        
            $successfulMessages = (clone $logsForStats)->where('success', 1)->count();
            $failedMessages = (clone $logsForStats)->where('success', 0)->count();
            $totalLoggedMessages = $logsForStats->count();
                        
            // Taxa de sucesso baseada APENAS nos logs (mensagens enviadas)
            $successRate = $totalLoggedMessages > 0 ? round(($successfulMessages / $totalLoggedMessages) * 100, 2) : 0;
                        
            $activeTemplates = Capsule::table('mod_zapcel_templates')->where('active', 1)->count();
            $totalTemplates = Capsule::table('mod_zapcel_templates')->count();
            $validatedClients = Capsule::table('mod_zapcel_validation')->where('validated', true)->count();

            // OBTÉM STATUS DO DEBUG LOGGING
            $debugSettings = Capsule::table('tbladdonmodules')
                ->where('module', 'zapcel')
                ->where('setting', 'enable_logging')
                ->first();
            $isDebugEnabled = ($debugSettings && $debugSettings->value == '1');
                        
            // Mensagens de hoje - CORREÇÃO v2.0.1: Filtra logs de debug
            $todayMessages = Capsule::table('mod_zapcel_logs')
                ->whereDate('created_at', date('Y-m-d'))
                ->where(function($query) {
                    $query->where('event_type', 'NOT LIKE', 'debug_%')
                        ->where('event_type', '!=', 'gateway_manager_debug')
                        ->where('event_type', '!=', 'system_log')
                        ->where('message', 'NOT LIKE', '[DEBUG]%');
                })
                ->whereIn('success', [0, 1])
                ->count();

            // Últimas 10 mensagens (APENAS UMA CONSULTA) - FILTRANDO DEBUGS
            $recentMessages = Capsule::table('mod_zapcel_logs as l')
                ->leftJoin('tblclients as c', 'l.client_id', '=', 'c.id')
                ->select('l.*', 'c.firstname', 'c.lastname')
                ->where(function($query) {
                    $query->where('l.event_type', 'NOT LIKE', 'debug_%')
                        ->where('l.event_type', '!=', 'gateway_manager_debug')
                        ->where('l.event_type', '!=', 'system_log')
                        ->where('l.message', 'NOT LIKE', '[DEBUG]%');
                })
                ->orderBy('l.created_at', 'desc')
                ->limit(10)
                ->get();

            } catch (\Exception $e) {
                // Fallback em caso de erro
                $totalMessages = $successfulMessages = $failedMessages = $todayMessages = 0;
                $successRate = 0;
                $activeTemplates = $totalTemplates = $validatedClients = 0;
                $recentMessages = [];
            }

        ob_start();
        ?>
        <div class="zapcel-admin-container">
            <!-- CABEÇALHO ÚNICO -->
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <i class="fab fa-whatsapp text-success"></i> 
                            <?php echo $this->LANG['dashboard_title']; ?>
                        </h2>
                        <p class="text-muted"><?php echo $this->LANG['dashboard_subtitle']; ?></p>
                    </div>
                    <div class="col-md-4 text-right">
                        <div class="btn-group">
                            <a href="addonmodules.php?module=zapcel&action=test_message" class="btn btn-success">
                                <i class="fas fa-paper-plane"></i> <?php echo $this->LANG['test_message']; ?>
                            </a>
                            <a href="addonmodules.php?module=zapcel&action=settings" class="btn btn-outline-secondary">
                                <i class="fas fa-cog"></i> <?php echo $this->LANG['settings']; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARDS DE ESTATÍSTICAS - APENAS UMA VEZ -->
            <div class="row">
                <!-- Total de Mensagens -->
                <div class="col-xl-3 col-md-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['total_messages']; ?></span>
                            <span class="info-box-number"><?= number_format($totalMessages) ?></span>
                            <div class="progress mt-2" style="height: 6px; margin-bottom: 8px;">
                                <div class="progress-bar bg-primary" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block"><?php echo $this->LANG['today']; ?>: <?= number_format($todayMessages) ?></small>
                        </div>
                    </div>
                </div>

                <!-- Taxa de Sucesso -->
                <div class="col-xl-3 col-md-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-<?= $successRate >= 90 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger') ?>">
                            <i class="fas fa-chart-line"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['success_rate']; ?></span>
                            <span class="info-box-number"><?= $successRate ?>%</span>
                            <div class="progress mt-2" style="height: 6px; margin-bottom: 8px;">
                                <div class="progress-bar bg-<?= $successRate >= 90 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger') ?>" style="width: <?= $successRate ?>%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?php echo $this->LANG['successful_messages']; ?>: <?= number_format($successfulMessages) ?> | 
                                <?php echo $this->LANG['failed_messages']; ?>: <?= number_format($failedMessages) ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Templates Ativos -->
                <div class="col-xl-3 col-md-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info">
                            <i class="fas fa-file-alt"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['templates']; ?></span>
                            <span class="info-box-number"><?= $activeTemplates ?>/<?= $totalTemplates ?></span>
                            <div class="progress mt-2" style="height: 6px; margin-bottom: 8px;">
                                <div class="progress-bar bg-info" 
                                     style="width: <?= $totalTemplates > 0 ? ($activeTemplates / $totalTemplates) * 100 : 0 ?>%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block"><?= $activeTemplates ?> <?php echo $this->LANG['active_templates']; ?></small>
                        </div>
                    </div>
                </div>

                <!-- Clientes Validados -->
                <div class="col-xl-3 col-md-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success">
                            <i class="fas fa-users"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['whatsapp_validated']; ?></span>
                            <span class="info-box-number"><?= number_format($validatedClients) ?></span>
                            <div class="progress mt-2" style="height: 6px; margin-bottom: 8px;">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block"><?php echo $this->LANG['clients_with_whatsapp_verified']; ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-lg-8">
                    <!-- ÚLTIMAS MENSAGENS - APENAS UMA VEZ -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history mr-2"></i>
                                <?php echo $this->LANG['recent_messages']; ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($recentMessages) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><?php echo $this->LANG['client']; ?></th>
                                                <th><?php echo $this->LANG['event']; ?></th>
                                                <th><?php echo $this->LANG['status']; ?></th>
                                                <th><?php echo $this->LANG['date']; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentMessages as $message): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($message->client_id): ?>
                                                        <strong><a style="text-decoration: none;" href="clientssummary.php?userid=<?= $message->client_id ?>" target="_blank" title="<?= htmlspecialchars($message->firstname . ' ' . $message->lastname) ?>"><?= htmlspecialchars($message->firstname) ?> <?= htmlspecialchars($message->lastname) ?></strong>
                                                </a>
                                                    <?php else: ?>
                                                        <span class="text-muted"><?php echo $this->LANG['system']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= $this->getEventTypeDisplayName($message->event_type) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($message->success): ?>
                                                        <span class="badge bg-success"><?php echo $this->LANG['success']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><?php echo $this->LANG['error']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y H:i', strtotime($message->created_at)) ?>
                                                    </small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted"><?php echo $this->LANG['no_messages_sent']; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- STATUS DO SISTEMA - APENAS UMA VEZ -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-heartbeat mr-2"></i>
                                <?php echo $this->LANG['system_status']; ?>
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-database text-primary mr-2"></i>
                                        <strong><?php echo $this->LANG['database']; ?></strong>
                                    </div>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle"></i> OK
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fab fa-whatsapp text-success mr-2"></i>
                                        <strong><?php echo $this->LANG['whatsapp_api']; ?></strong>
                                    </div>
                                    <?php
                                    $apiStatus = $this->testAPIConnection();
                                    if ($apiStatus['success']) {
                                        echo '<span class="badge bg-success"><i class="fas fa-check-circle"></i> ' . $this->LANG['connected'] . '</span>';
                                    } else {
                                        echo '<span class="badge bg-danger" style="background-color: #FF0000"><i class="fas fa-times-circle"></i> ' . $this->LANG['disconnected'] . '</span>';
                                    }
                                    ?>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-plug text-info mr-2"></i>
                                        <strong><?php echo $this->LANG['active_hooks']; ?></strong>
                                    </div>
                                    <span class="badge bg-primary"><?= $totalTemplates ?></span>
                                </div>
                                <!-- NOVO ITEM: STATUS DO DEBUG LOGGING -->
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-bug text-<?= $isDebugEnabled ? 'warning' : 'secondary' ?> mr-2"></i>
                                        <strong>
                                            <?php echo $this->LANG['debug_logging']; ?>
                                            <i class="fas fa-info-circle text-muted ml-1" 
                                            data-toggle="tooltip" 
                                            title="<?php echo $this->LANG['debug_logging_tooltip']; ?>"></i>
                                        </strong>
                                    </div>
                                    <?php if ($isDebugEnabled): ?>
                                        <span class="badge bg-warning" data-toggle="tooltip" title="<?php echo $this->LANG['debug_enabled_tooltip']; ?>">
                                            <i class="fas fa-toggle-on"></i> <?php echo $this->LANG['enabled']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" data-toggle="tooltip" title="<?php echo $this->LANG['debug_disabled_tooltip']; ?>">
                                            <i class="fas fa-toggle-off"></i> <?php echo $this->LANG['disabled']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="zapcel-minimal-actions card shadow-sm border-0" style="background: #fff; border-radius: 8px; padding: 20px; margin-top: 20px;">
                        <h4 class="minimal-title" style="margin-top: 0; margin-bottom: 20px; font-size: 14px; font-weight: 700; color: #333; display: flex; align-items: center;">
                            <i class="fas fa-bolt text-warning mr-2" style="margin-right: 10px;"></i> <?php echo $this->LANG['quick_actions']; ?>
                        </h4>
                        
                        <div class="minimal-grid">
                            <a href="addonmodules.php?module=zapcel&action=templates" class="min-link">
                                <i class="fal fa-file-alt"></i> <?php echo $this->LANG['manage_templates']; ?>
                            </a>
                    
                            <a href="addonmodules.php?module=zapcel&action=gateways" class="min-link">
                                <i class="fal fa-exchange-alt"></i> <?php echo $this->LANG['manage_gateways']; ?>
                            </a>
                    
                            <a href="addonmodules.php?module=zapcel&action=validation" class="min-link">
                                <i class="fal fa-mobile-alt"></i> <?php echo $this->LANG['validations']; ?>
                            </a>
                    
                            <a href="addonmodules.php?module=zapcel&action=logs" class="min-link">
                                <i class="fal fa-list-alt"></i> <?php echo $this->LANG['view_logs']; ?>
                            </a>
                    
                            <a href="addonmodules.php?module=zapcel&action=autologin" class="min-link">
                                <i class="fal fa-key"></i> AutoLogin
                            </a>
                    
                            <a href="addonmodules.php?module=zapcel&action=campaigns" class="min-link <?php echo $this->action === 'campaigns' ? 'active' : ''; ?>">
                                <i class="fal fa-bullhorn"></i> <?php echo $this->LANG['menu_campaigns'] ?? 'Campanhas'; ?>
                            </a>
                        </div>
                    </div>
                    
                    <style>
                    /* Grid interno organizado em 2 colunas */
                    .minimal-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 12px;
                    }
                    
                    .min-link {
                        display: flex;
                        align-items: center;
                        padding: 10px 14px;
                        background: #f8f9fa; /* Fundo sutil para os botões internos */
                        border: 1px solid #edf2f7;
                        border-radius: 6px;
                        color: #4a5568 !important;
                        font-size: 13px;
                        font-weight: 500;
                        text-decoration: none !important;
                        transition: all 0.2s ease;
                    }
                    
                    /* Ícones alinhados verticalmente */
                    .min-link i {
                        width: 20px;
                        margin-right: 10px;
                        font-size: 14px;
                        text-align: center;
                        color: #3498db;
                    }
                    
                    .min-link:hover {
                        background: #fff;
                        border-color: #cbd5e0;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                        transform: translateY(-1px);
                    }
                    
                    .min-link.active {
                        border-color: #25d366;
                        background: rgba(37, 211, 102, 0.05);
                        color: #1b8d44 !important;
                    }
                    
                    .min-link.active i {
                        color: #25d366;
                    }
                    
                    /* Ajuste para telas menores */
                    @media (max-width: 480px) {
                        .minimal-grid {
                            grid-template-columns: 1fr;
                        }
                    }
                    </style>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function(){
                $('[data-toggle="tooltip"]').tooltip();
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Página de Gerenciamento de Templates
     */
    private function templatesPage()
    {
        $templates = Capsule::table('mod_zapcel_templates')
            ->orderBy('name', 'asc')
            ->get();
        
        // Busca próximo número de resposta rápida disponível
        $lastQuickReply = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', 'LIKE', 'quick_reply%')
            ->orderBy('trigger_event', 'desc')
            ->value('trigger_event');

        if ($lastQuickReply) {
            // Extrai número: quick_reply_5 → 5
            preg_match('/quick_reply_(\d+)/', $lastQuickReply, $matches);
            $nextQuickReplyNumber = isset($matches[1]) ? (int)$matches[1] + 1 : 1;
        } else {
            $nextQuickReplyNumber = 1;
        }

        $nextQuickReplyEvent = 'quick_reply_' . $nextQuickReplyNumber;
        
        ob_start();
        ?>
        <div class="zapcel-admin-container">
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <i class="fas fa-file-alt mr-2"></i> 
                            <?php echo $this->LANG['manage_message_templates']; ?>
                        </h2>
                        <p class="text-muted"><?php echo $this->LANG['templates_subtitle']; ?></p>
                    </div>
                    <div class="col-md-4 text-right">
                        <a href="addonmodules.php?module=zapcel&action=dashboard" class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-arrow-left mr-1"></i> <?php echo $this->LANG['back']; ?>
                        </a>
                        <button class="btn btn-success" data-toggle="modal" data-target="#newTemplateModal">
                            <i class="fas fa-plus mr-1"></i> <?php echo $this->LANG['new_template']; ?>
                        </button>
                    </div>
                </div>
            </div>
    
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <!-- Filtros de Templates -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-primary filter-templates active" data-filter="all">
                                        <i class="fas fa-list"></i> <?php zapcel_trans('all'); ?> (<?= $templates->count() ?>)
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary filter-templates" data-filter="events">
                                        <i class="fas fa-bolt"></i> <?php zapcel_trans('events'); ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning filter-templates" data-filter="quick_reply">
                                        <i class="fas fa-comments"></i> <?php zapcel_trans('quick_reply_badge'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($templates->count() > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="templatesTable">
                                    <thead>
                                        <tr>
                                            <th><?php echo $this->LANG['template_name']; ?></th>
                                            <th width="100">Tipo</th>
                                            <th><?php echo $this->LANG['trigger_event']; ?></th>
                                            <th><?php echo $this->LANG['status']; ?></th>
                                            <th><?php echo $this->LANG['usage']; ?></th>
                                            <th><?php echo $this->LANG['last_update']; ?></th>
                                            <th width="150" class="text-center"><?php echo $this->LANG['actions']; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($templates as $template): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($template->name) ?></strong>
                                            </td>
                                            <td>
                                                <?php
                                                $isQuickReply = strpos($template->trigger_event, 'quick_reply') === 0;
                                                if ($isQuickReply) {
                                                    echo '<span class="badge bg-warning" style="font-size: 11px;">' . zapcel_trans('quick_reply_badge') . '</span>';
                                                } else {
                                                    echo '<span class="badge bg-info" style="font-size: 11px;">Evento</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                // Detecta se é resposta rápida
                                                $isQuickReply = strpos($template->trigger_event, 'quick_reply') === 0;
                                                
                                                if ($isQuickReply) {
                                                    // Extrai número: quick_reply_5 → #5
                                                    preg_match('/quick_reply_(\d+)/', $template->trigger_event, $matches);
                                                    $number = isset($matches[1]) ? '#' . $matches[1] : '';
                                                    echo '<span class="badge bg-warning" style="font-size: 12px;">⚡ ' . zapcel_trans('quick_reply_badge') . ' ' . $number . '</span>';
                                                } else {
                                                    $eventLabels = [
                                                        'invoice_created' => '📋 ' . zapcel_trans('invoice_created'),
                                                        'invoice_paid' => '✅ ' . zapcel_trans('invoice_paid'),
                                                        'invoice_cancelled' => '❌ ' . zapcel_trans('invoice_cancelled'),
                                                        'invoice_reminder' => '🔔 ' . zapcel_trans('invoice_reminder'),
                                                        'invoice_reminder_1' => '🔔 ' . zapcel_trans('invoice_reminder_1'),
                                                        'invoice_reminder_2' => '🔔 ' . zapcel_trans('invoice_reminder_2'),
                                                        'invoice_reminder_3' => '🔔 ' . zapcel_trans('invoice_reminder_3'),
                                                        'ticket_opened' => '🎫 ' . zapcel_trans('ticket_opened'),
                                                        'ticket_reply' => '💬 ' . zapcel_trans('ticket_reply'),
                                                        'service_activated' => '✅ ' . zapcel_trans('service_activated'),
                                                        'service_suspended' => '⏸️ ' . zapcel_trans('service_suspended'),
                                                        'service_unsuspended' => '▶️ ' . zapcel_trans('service_unsuspended'),
                                                        'service_terminated' => '🚫 ' . zapcel_trans('service_terminated'),
                                                        'cancellation_request' => '📝 ' . zapcel_trans('cancellation_request'),
                                                        'client_added' => '👤 ' . zapcel_trans('client_added'),
                                                        'client_edited' => '✏️ ' . zapcel_trans('client_edited'),
                                                        'password_changed' => '🔑 ' . zapcel_trans('password_changed'),
                                                        'quote_created' => '💰 ' . zapcel_trans('quote_created'),
                                                        'quote_modified' => '📝 ' . zapcel_trans('quote_modified'),
                                                        'quote_accepted' => '✅ ' . zapcel_trans('quote_accepted'),
                                                        'whatsapp_validation' => '📱 ' . zapcel_trans('whatsapp_validation'),
                                                        'email_presend' => '📧 ' . zapcel_trans('email_presend'),
                                                        'test_message' => '📋 ' . zapcel_trans('test_message_event'), // Usando 'test_message_event' dos logs, pois 'test_message' é uma tag de tela
                                                        'custom_message_manual' => '📋 ' . zapcel_trans('custom_message_log'), // Usando 'custom_message_log' do log
                                                        
                                                        // Para os próximos, como não há uma tradução exata no seu array, estou usando as mais próximas, assumindo que são variações do evento principal.
                                                        'service_activated_hosting' => '🌐 ' . zapcel_trans('hosting'), // Ou 'hosting_account', dependendo do contexto.
                                                        'service_activated_other' => '🌐 ' . zapcel_trans('other_services'),
                                                        'service_activated_reseller' => '📋 ' . zapcel_trans('reseller'), // Ou 'reseller_account'
                                                        'service_activated_vps' => '☁️ ' . zapcel_trans('dedicated_vps_server'),
                                                    ];
                                                    
                                                    $eventLabel = $eventLabels[$template->trigger_event] ?? $template->trigger_event;
                                                    echo '<span class="badge bg-secondary" style="font-size: 12px;">' . htmlspecialchars($eventLabel) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($template->active): ?>
                                                    <span class="badge bg-success"><?php echo $this->LANG['active']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo $this->LANG['inactive']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= $template->usage_count ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($template->updated_at)) ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="addonmodules.php?module=zapcel&action=edit_template&id=<?= $template->id ?>" 
                                                            class="btn btn-sm btn-outline-primary" title="<?php echo $this->LANG['edit']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($template->active): ?>
                                                        <button class="btn btn-sm btn-outline-warning deactivate-template" 
                                                                data-id="<?= $template->id ?>" title="<?php echo $this->LANG['deactivate']; ?>">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-success activate-template" 
                                                                data-id="<?= $template->id ?>" title="<?php echo $this->LANG['activate']; ?>">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-danger delete-template" 
                                                            data-id="<?= $template->id ?>" data-name="<?= htmlspecialchars($template->name) ?>" 
                                                            title="<?php echo $this->LANG['delete']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted"><?php echo $this->LANG['no_templates_found']; ?></h4>
                                <p class="text-muted"><?php echo $this->LANG['create_first_template']; ?></p>
                                <button class="btn btn-success" data-toggle="modal" data-target="#newTemplateModal">
                                    <i class="fas fa-plus mr-1"></i> <?php echo $this->LANG['create_first_template']; ?>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Modal Novo Template -->
        <div class="modal fade" id="newTemplateModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title"><?php echo $this->LANG['create_template']; ?></h1>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <form id="createTemplateForm">
                        <div class="modal-body"> 
                            <div class="form-group">
                                <label class="font-weight-bold"><?php echo $this->LANG['template_name']; ?></label>
                                <input type="text" name="name" class="form-control" required placeholder="<?php echo $this->LANG['template_name']; ?>">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold"><?php echo $this->LANG['trigger_event']; ?></label>
                                <select name="trigger_event" id="triggerEventSelect" class="form-control" required>
                                    <option value=""><?php echo $this->LANG['select_event']; ?></option>
                                    
                                    <!-- FATURAS -->
                                    <optgroup label="📋 Faturas">
                                        <option value="invoice_created"><?php echo $this->LANG['invoice_created']; ?></option>
                                        <option value="invoice_paid"><?php echo $this->LANG['invoice_paid']; ?></option>
                                        <option value="invoice_cancelled"><?php echo $this->LANG['invoice_cancelled']; ?></option>
                                        <option value="invoice_reminder"><?php echo $this->LANG['invoice_reminder']; ?></option>
                                        <option value="invoice_reminder_1"><?php echo $this->LANG['invoice_reminder_1']; ?></option>
                                        <option value="invoice_reminder_2"><?php echo $this->LANG['invoice_reminder_2']; ?></option>
                                        <option value="invoice_reminder_3"><?php echo $this->LANG['invoice_reminder_3']; ?></option>
                                    </optgroup>
                                    
                                    <!-- TICKETS -->
                                    <optgroup label="🎫 Suporte">
                                        <option value="ticket_opened"><?php echo $this->LANG['ticket_opened']; ?></option>
                                        <option value="ticket_reply"><?php echo $this->LANG['ticket_reply']; ?></option>
                                    </optgroup>
                                    
                                    <!-- SERVIÇOS -->
                                    <optgroup label="🖥️ Serviços">
                                        <option value="service_activated"><?php echo $this->LANG['service_activated']; ?></option>
                                        <option value="service_suspended"><?php echo $this->LANG['service_suspended']; ?></option>
                                        <option value="service_unsuspended"><?php echo $this->LANG['service_unsuspended']; ?></option>
                                        <option value="service_terminated"><?php echo $this->LANG['service_terminated']; ?></option>
                                        <option value="cancellation_request"><?php echo $this->LANG['cancellation_request']; ?></option>
                                    </optgroup>
                                    
                                    <!-- CLIENTES -->
                                    <optgroup label="👤 Clientes">
                                        <option value="client_added"><?php echo $this->LANG['client_added']; ?></option>
                                        <option value="client_edited"><?php echo $this->LANG['client_edited']; ?></option>
                                        <option value="password_changed"><?php echo $this->LANG['password_changed']; ?></option>
                                    </optgroup>
                                    
                                    <!-- COTAÇÕES -->
                                    <optgroup label="💰 Cotações">
                                        <option value="quote_created"><?php echo $this->LANG['quote_created']; ?></option>
                                        <option value="quote_modified"><?php echo $this->LANG['quote_modified']; ?></option>
                                        <option value="quote_accepted"><?php echo $this->LANG['quote_accepted']; ?></option>
                                    </optgroup>
                                    
                                    <!-- SISTEMA -->
                                    <optgroup label="⚙️ Sistema">
                                        <option value="whatsapp_validation"><?php echo $this->LANG['whatsapp_validation']; ?></option>
                                        <option value="email_presend"><?php echo $this->LANG['email_presend']; ?></option>
                                    </optgroup>

                                    <!-- RESPOSTAS RÁPIDAS -->
                                    <optgroup label="⚡ Respostas Rápidas (Mensagens Personalizadas)">
                                        <option value="<?= $nextQuickReplyEvent ?>" data-type="quick_reply">
                                            Nova Resposta Rápida #<?= $nextQuickReplyNumber ?>
                                        </option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold"><?php echo $this->LANG['message_template']; ?></label>
                                
                                <!-- Barra de Ferramentas de Formatação -->
                                <div class="whatsapp-toolbar" style="background: #f8f9fa; border: 1px solid #dee2e6; border-bottom: none; padding: 8px; border-radius: 4px 4px 0 0;">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary format-btn" data-format="bold" title="<?php echo $this->LANG['bold']; ?>">
                                            <i class="fas fa-bold"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary format-btn" data-format="italic" title="<?php echo $this->LANG['italic']; ?>">
                                            <i class="fas fa-italic"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary format-btn" data-format="strikethrough" title="<?php echo $this->LANG['strikethrough']; ?>">
                                            <i class="fas fa-strikethrough"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary format-btn" data-format="monospace" title="<?php echo $this->LANG['monospace']; ?>">
                                            <i class="fas fa-code"></i>
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm ml-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary" id="emojiPickerBtn" title="<?php echo $this->LANG['emojis']; ?>">
                                            <i class="far fa-smile"></i> <?php echo $this->LANG['emojis']; ?>
                                        </button>
                                    </div>
                                </div>
                                
                                <textarea name="template" id="templateTextarea" class="form-control" rows="10" 
                                        placeholder="<?php echo $this->LANG['enter_message_template']; ?>" 
                                        style="border-radius: 0 0 4px 4px; font-family: 'Segoe UI', sans-serif;"></textarea>
                                <small class="form-text text-muted">
                                    <?php echo $this->LANG['use_variables_in_braces']; ?>
                                </small>
                                
                                <!-- Painel de Emojis -->
                                <div id="emojiPanel" class="card mt-2" style="display: none; max-height: 200px; overflow-y: auto;">
                                    <div class="card-body p-2">
                                        <div class="emoji-grid" style="display: grid; grid-template-columns: repeat(10, 1fr); gap: 5px; font-size: 24px; text-align: center;">
                                            <!-- Status e Ações -->
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Verificado">✅</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Check">✔️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Erro">❌</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="X">✖️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Atenção">⚠️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Importante">❗</span>
                                            
                                            <!-- Setas -->
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Seta Direita">➡️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Seta Esquerda">⬅️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Seta Cima">⬆️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Seta Baixo">⬇️</span>
                                            
                                            <!-- Controles -->
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Play">▶️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Pausa">⏸️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Stop">⏹️</span>
                                            
                                            <!-- Formas Geométricas -->
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Círculo">⭕</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Círculo Preto">●</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Quadrado Preto">◼️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Quadrado Branco">◻️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Quadrado Médio">◾</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Quadrado Pequeno">▫️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Ponto">▪️</span>
                                            
                                            <!-- Símbolos Matemáticos -->
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Mais">➕</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Menos">➖</span>
                                            
                                            <!-- Ícones Gerais -->
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Estrela">⭐</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Email">✉️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Telefone">☎️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Rápido">⚡</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Informação">ℹ️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Configurações">⚙️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Reciclar">♻️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Relógio">⏰</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Marca Registrada">™️</span>
                                            <span class="emoji-item-edit" style="cursor: pointer;" title="Preto">⚫</span>
                                        </div>
                                    </div>
                                </div>
                                <div id="availableVariables" class="mt-2" style="display:none;">
                                    <strong><?php echo $this->LANG['available_variables']; ?>:</strong>
                                    <div id="variablesList" class="mt-1"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $this->LANG['cancel']; ?></button>
                            <button type="submit" class="btn btn-primary"><?php echo $this->LANG['create_template']; ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>                       
        <script>
            $(document).ready(function() {
                // Apenas Desativar Template
                $('.deactivate-template').click(function() {
                    var templateId = $(this).data('id');
                    
                    $.ajax({
                        url: 'addonmodules.php?module=zapcel&action=ajax',
                        type: 'POST',
                        data: {
                            subaction: 'deactivate_template',
                            template_id: templateId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Desativado!',
                                    text: 'Template desativado com sucesso!',
                                    icon: 'warning',
                                    confirmButtonColor: '#ffc107',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erro',
                                    text: response.error,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', xhr.responseText);
                            Swal.fire({
                                title: 'Erro', 
                                text: zapcel_trans('connection_error_prefix') + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });

                // Ativar Template
                $('.activate-template').click(function() {
                    var templateId = $(this).data('id');
                    
                    $.ajax({
                        url: 'addonmodules.php?module=zapcel&action=ajax',
                        type: 'POST',
                        data: {
                            subaction: 'activate_template',
                            template_id: templateId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Ativado!',
                                    text: 'Template ativado com sucesso!',
                                    icon: 'success',
                                    confirmButtonColor: '#28a745',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erro',
                                    text: response.error,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', xhr.responseText);
                            Swal.fire({
                                title: 'Erro',
                                text: zapcel_trans('connection_error_prefix') + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });
        
                // Excluir Template
                $('.delete-template').click(function() {
                    var templateId = $(this).data('id');
                    var templateName = $(this).data('name');
                    
                    Swal.fire({
                        title: '<?php echo $this->LANG['confirm_delete']; ?>',
                        text: '<?php echo $this->LANG['confirm_delete_template']; ?>'.replace('%s', templateName),
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<?php echo $this->LANG['delete']; ?>',
                        cancelButtonText: '<?php echo $this->LANG['cancel']; ?>',
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'addonmodules.php?module=zapcel&action=ajax',
                                type: 'POST',
                                data: {
                                    subaction: 'delete_template',
                                    template_id: templateId
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['error']; ?>',
                                            text: response.error,
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('Erro AJAX:', xhr.responseText);
                                    Swal.fire({
                                        title: '<?php echo $this->LANG['error']; ?>',
                                        text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            });
                        }
                    });
                });
        
                // Carregar variáveis ao selecionar evento
                $('#triggerEventSelect').change(function() {
                    var event = $(this).val();
                    var variables = {
                        // === FATURAS ===
                        'invoice_created': '{cliente}, {numero_fatura}, {titulo}, {valor}, {vencimento}, {data_criacao}, {codigopix}, {linhadigitavel}, {qr_code_url}, {link_fatura}, {link_fatura_autologin}, {itens_fatura}, {provedor}, {assinatura}',
                        'invoice_paid': '{cliente}, {numero_fatura}, {titulo}, {valor}, {data_pagamento}, {metodo_pagamento}, {provedor}, {assinatura}',
                        'invoice_cancelled': '{cliente}, {numero_fatura}, {titulo}, {valor}, {data_cancelamento}, {motivo_cancelamento}, {provedor}, {assinatura}',
                        'invoice_reminder': '{cliente}, {numero_fatura}, {titulo}, {valor}, {vencimento}, {dias_vencimento}, {codigopix}, {linhadigitavel}, {link_fatura}, {link_fatura_autologin}, {provedor}, {assinatura}',
                        'invoice_reminder_1': '{cliente}, {numero_fatura}, {titulo}, {valor}, {vencimento}, {dias_vencimento}, {codigopix}, {linhadigitavel}, {link_fatura}, {link_fatura_autologin}, {provedor}, {assinatura}',
                        'invoice_reminder_2': '{cliente}, {numero_fatura}, {titulo}, {valor}, {vencimento}, {dias_vencimento}, {codigopix}, {linhadigitavel}, {link_fatura}, {link_fatura_autologin}, {provedor}, {assinatura}',
                        'invoice_reminder_3': '{cliente}, {numero_fatura}, {titulo}, {valor}, {vencimento}, {dias_vencimento}, {codigopix}, {linhadigitavel}, {link_fatura}, {link_fatura_autologin}, {provedor}, {assinatura}',

                        // === SERVIÇOS ===
                        'service_activated': '{cliente}, {servico}, {dominio}, {data_ativacao}, {ip_dedicado}, {usuario}, {senha}, {provedor}, {assinatura}',
                        'service_suspended': '{cliente}, {servico}, {dominio}, {data_suspensao}, {motivo}, {provedor}, {assinatura}',
                        'service_unsuspended': '{cliente}, {servico}, {dominio}, {data_reativacao}, {provedor}, {assinatura}',
                        'service_terminated': '{cliente}, {servico}, {dominio}, {data_cancelamento}, {provedor}, {assinatura}',
                        'cancellation_request': '{cliente}, {id_servico}, {nome_servico}, {razao_cancelamento}, {tipo_cancelamento}, {data_solicitacao}, {dominio}, {provedor}, {assinatura}',

                        // === TICKETS ===
                        'ticket_created': '{cliente}, {numero_ticket}, {assunto}, {departamento}, {prioridade}, {link_ticket}, {link_ticket_autologin}, {provedor}, {assinatura}',
                        'ticket_opened': '{cliente}, {numero_ticket}, {assunto}, {departamento}, {prioridade}, {link_ticket}, {link_ticket_autologin}, {provedor}, {assinatura}',
                        'ticket_reply': '{cliente}, {numero_ticket}, {assunto}, {atendente}, {link_ticket}, {link_ticket_autologin}, {provedor}, {assinatura}',
                        'ticket_replied': '{cliente}, {numero_ticket}, {assunto}, {atendente}, {link_ticket}, {link_ticket_autologin}, {provedor}, {assinatura}',

                        // === CLIENTES ===
                        'client_added': '{cliente}, {email}, {telefone}, {data_cadastro}, {provedor}, {assinatura}',
                        'client_edited': '{cliente}, {email}, {telefone}, {endereco}, {cidade}, {estado}, {alteracoes}, {data_alteracao}, {provedor}, {assinatura}',
                        'password_changed': '{cliente}, {data_alteracao}, {nova_senha}, {provedor}, {assinatura}',

                        // === COTAÇÕES ===
                        'quote_created': '{cliente}, {numero_cotacao}, {subject_cotacao}, {valor_cotacao}, {validade_cotacao}, {status_cotacao}, {itens_cotacao}, {provedor}, {assinatura}',
                        'quote_modified': '{cliente}, {numero_cotacao}, {subject_cotacao}, {valor_cotacao}, {validade_cotacao}, {status_cotacao}, {alteracoes}, {itens_cotacao}, {provedor}, {assinatura}',
                        'quote_accepted': '{cliente}, {numero_cotacao}, {subject_cotacao}, {valor_cotacao}, {data_aceitacao}, {status_cotacao}, {itens_cotacao}, {provedor}, {assinatura}',

                        // === EMAIL/SISTEMA ===
                        'email_presend': '{cliente}, {assunto}, {mensagem}, {tipo_servico}, {dominio}, {nome_produto}, {ip_dedicado}, {usuario}, {senha}, {provedor}, {assinatura}',
                        'email_replaced': '{cliente}, {assunto}, {tipo_servico}, {provedor}, {assinatura}',
                        'whatsapp_validation': '{cliente}, {codigo_verificacao}, {provedor}, {assinatura}',

                        // === SISTEMA ===
                        'test_message': '{cliente}, {mensagem_teste}, {provedor}, {assinatura}',
                        'system_error': '{mensagem_erro}, {provedor}'
                    };
                    
                    if (event && variables[event]) {
                        $('#variablesList').html('<code>' + variables[event] + '</code>');
                        $('#availableVariables').show();
                    } else {
                        $('#availableVariables').hide();
                    }
                });
                
                // Criar Template
                $('#createTemplateForm').submit(function(e) {
                    e.preventDefault();
                    var formData = $(this).serializeArray();
                    var dataObj = {};
                    $.each(formData, function(i, field) {
                        dataObj[field.name] = field.value;
                    });
                    
                    $.ajax({
                        url: 'addonmodules.php?module=zapcel&action=ajax',
                        type: 'POST',
                        data: $.extend({subaction: 'create_template'}, dataObj),
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                            location.href = 'addonmodules.php?module=zapcel&action=edit_template&id=' + response.template_id;
                        } else {
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: response.error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', xhr.responseText);
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });
                
                // ========== BARRA DE FERRAMENTAS DE FORMATAÇÃO ==========
                
                // Função para formatar texto selecionado
                function formatText(format, textarea) {
                    var start = textarea.selectionStart;
                    var end = textarea.selectionEnd;
                    var selectedText = textarea.value.substring(start, end);
                    
                    if (!selectedText) {
                        Swal.fire({
                            title: '<?php echo $this->LANG['warning']; ?>',
                            text: '<?php echo $this->LANG['select_text_to_format']; ?>',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                    
                    var formattedText = '';
                    switch(format) {
                        case 'bold':
                            formattedText = '*' + selectedText + '*';
                            break;
                        case 'italic':
                            formattedText = '_' + selectedText + '_';
                            break;
                        case 'strikethrough':
                            formattedText = '~' + selectedText + '~';
                            break;
                        case 'monospace':
                            formattedText = '```' + selectedText + '```';
                            break;
                    }
                    
                    textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
                    textarea.focus();
                    textarea.setSelectionRange(start, start + formattedText.length);
                }
                
                // Botões de formatação - Criar
                $('.format-btn').click(function() {
                    var format = $(this).data('format');
                    var textarea = document.getElementById('templateTextarea');
                    formatText(format, textarea);
                });
                
                // Botão de emojis - Criar
                $('#emojiPickerBtn').click(function() {
                    $('#emojiPanel').slideToggle();
                });
                
                // Inserir emoji ao clicar - Criar
                $('.emoji-item').click(function() {
                    var emoji = $(this).text();
                    var textarea = document.getElementById('templateTextarea');
                    var cursorPos = textarea.selectionStart;
                    var textBefore = textarea.value.substring(0, cursorPos);
                    var textAfter = textarea.value.substring(cursorPos);
                    textarea.value = textBefore + emoji + textAfter;
                    textarea.focus();
                    textarea.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
                });

                // Filtrar templates
                $('.filter-templates').click(function() {
                    const filter = $(this).data('filter');
                    
                    // Remove classes ativas
                    $('.filter-templates').removeClass('btn-primary btn-secondary btn-warning active')
                        .addClass('btn-outline-secondary');
                    
                    // Adiciona classe ativa
                    if (filter === 'quick_reply') {
                        $(this).removeClass('btn-outline-secondary btn-outline-warning').addClass('btn-warning active');
                    } else {
                        $(this).removeClass('btn-outline-secondary btn-outline-primary').addClass('btn-primary active');
                    }
                    
                    // Filtra linhas
                    if (filter === 'all') {
                        $('table tbody tr').show();
                    } else if (filter === 'quick_reply') {
                        $('table tbody tr').each(function() {
                            const type = $(this).find('td:eq(1) .badge').text().trim();
                            if (type === 'Resposta Rápida') {
                                $(this).show();
                            } else {
                                $(this).hide();
                            }
                        });
                    } else if (filter === 'events') {
                        $('table tbody tr').each(function() {
                            const type = $(this).find('td:eq(1) .badge').text().trim();
                            if (type === 'Evento') {
                                $(this).show();
                            } else {
                                $(this).hide();
                            }
                        });
                    }
                    
                    // Atualiza contador
                    const visibleCount = $('table tbody tr:visible').length;
                    $(this).html($(this).html().replace(/\(\d+\)/, '(' + visibleCount + ')'));
                });

                // Buscar templates
                $('#searchTemplates').on('input', function() {
                    const search = $(this).val().toLowerCase();
                    
                    $('table tbody tr').each(function() {
                        const name = $(this).find('td:eq(0)').text().toLowerCase();
                        const event = $(this).find('td:eq(2)').text().toLowerCase();
                        
                        if (name.includes(search) || event.includes(search)) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                });

                // Atualiza contadores ao carregar
                const totalTemplates = $('table tbody tr').length;
                const quickReplyCount = $('table tbody tr').filter(function() {
                    return $(this).find('td:eq(1) .badge').text().trim() === 'Resposta Rápida';
                }).length;
                const eventCount = totalTemplates - quickReplyCount;

                $('.filter-templates[data-filter="all"]').html('<i class="fas fa-list"></i> Todos (' + totalTemplates + ')');
                $('.filter-templates[data-filter="events"]').html('<i class="fas fa-bolt"></i> Eventos (' + eventCount + ')');
                $('.filter-templates[data-filter="quick_reply"]').html('<i class="fas fa-comments"></i> Respostas Rápidas (' + quickReplyCount + ')');
            });
            $(document).ready(function() {
                $('#templatesTable').DataTable({
                    order: [[0, 'asc']], // Ordena por "Evento" crescente
                    pageLength: 25,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                    },
                    columnDefs: [
                        { orderable: false, targets: [3] } // Desabilita ordenação na coluna "Ações"
                    ]
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Página de Edição de Template
     */
    private function editTemplatePage()
    {
        $templateId = (int)($_GET['id'] ?? 0);
        
        if (!$templateId) {
            return '<div class="alert alert-danger">' . $this->LANG['invalid_id'] . '</div>';
        }
    
        $template = Capsule::table('mod_zapcel_templates')
            ->where('id', $templateId)
            ->first();
    
        if (!$template) {
            return '<div class="alert alert-danger">' . $this->LANG['template_not_found'] . '</div>';
        }
    
        ob_start();
        ?>
        <div class="zapcel-admin-container">
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <i class="fas fa-edit mr-2"></i> 
                            <?php echo $this->LANG['edit_template']; ?>: <?= htmlspecialchars($template->name) ?>
                        </h2>
                        <p class="text-muted"><?php echo $this->LANG['edit_template_subtitle']; ?></p>
                    </div>
                    <div class="col-md-4 text-right">
                        <a href="addonmodules.php?module=zapcel&action=templates" class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-arrow-left mr-1"></i> <?php echo $this->LANG['back']; ?>
                        </a>
                        <button class="btn btn-success" id="saveTemplate">
                            <i class="fas fa-save mr-1"></i> <?php echo $this->LANG['save']; ?>
                        </button>
                    </div>
                </div>
            </div>
    
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <form id="editTemplateForm">
                                <input type="hidden" name="template_id" value="<?= $template->id ?>">
                                
                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['template_name']; ?></label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($template->name) ?>" required>
                                </div>
    
                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['trigger_event']; ?></label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($template->trigger_event) ?>" readonly>
                                    <small class="form-text text-muted"><?php echo $this->LANG['event_cannot_be_changed']; ?></small>
                                </div>
    
                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['message_template']; ?></label>
                                    
                                    <!-- Barra de Ferramentas de Formatação -->
                                    <div class="whatsapp-toolbar" style="background: #f8f9fa; border: 1px solid #dee2e6; border-bottom: none; padding: 8px; border-radius: 4px 4px 0 0;">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-secondary format-btn-edit" data-format="bold" title="<?php echo $this->LANG['bold']; ?>">
                                                <i class="fas fa-bold"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary format-btn-edit" data-format="italic" title="<?php echo $this->LANG['italic']; ?>">
                                                <i class="fas fa-italic"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary format-btn-edit" data-format="strikethrough" title="<?php echo $this->LANG['strikethrough']; ?>">
                                                <i class="fas fa-strikethrough"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary format-btn-edit" data-format="monospace" title="<?php echo $this->LANG['monospace']; ?>">
                                                <i class="fas fa-code"></i>
                                            </button>
                                        </div>
                                        <div class="btn-group btn-group-sm ml-2" role="group">
                                            <button type="button" class="btn btn-outline-secondary" id="emojiPickerBtnEdit" title="<?php echo $this->LANG['emojis']; ?>">
                                                <i class="far fa-smile"></i> <?php echo $this->LANG['emojis']; ?>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <textarea name="template" id="templateTextareaEdit" class="form-control" rows="15" 
                                              style="border-radius: 0 0 4px 4px; font-family: 'Segoe UI', sans-serif;" required><?= htmlspecialchars($template->template) ?></textarea>
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['use_variables_in_braces']; ?>
                                    </small>
                                    
                                    <!-- Painel de Emojis -->
                                    <div id="emojiPanelEdit" class="card mt-2" style="display: none; max-height: 200px; overflow-y: auto;">
                                        <div class="card-body p-2">
                                            <div class="emoji-grid" style="display: grid; grid-template-columns: repeat(10, 1fr); gap: 5px; font-size: 24px; text-align: center;">
                                                <!-- Status e Ações -->
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Verificado">✅</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Check">✔️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Erro">❌</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="X">✖️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Atenção">⚠️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Importante">❗</span>
                                                
                                                <!-- Setas -->
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Seta Direita">➡️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Seta Esquerda">⬅️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Seta Cima">⬆️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Seta Baixo">⬇️</span>
                                                
                                                <!-- Controles -->
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Play">▶️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Pausa">⏸️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Stop">⏹️</span>
                                                
                                                <!-- Formas Geométricas -->
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Círculo">⭕</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Círculo Preto">●</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Quadrado Preto">◼️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Quadrado Branco">◻️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Quadrado Médio">◾</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Quadrado Pequeno">▫️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Ponto">▪️</span>
                                                
                                                <!-- Símbolos Matemáticos -->
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Mais">➕</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Menos">➖</span>
                                                
                                                <!-- Ícones Gerais -->
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Estrela">⭐</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Email">✉️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Telefone">☎️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Rápido">⚡</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Informação">ℹ️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Configurações">⚙️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Reciclar">♻️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Relógio">⏰</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Marca Registrada">™️</span>
                                                <span class="emoji-item-edit" style="cursor: pointer;" title="Preto">⚫</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
    
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="active" value="1" <?= $template->active ? 'checked' : '' ?>>
                                            <?php echo $this->LANG['activate']; ?>
                                        </label>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
    
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['available_variables']; ?></h3>
                        </div>
                        <div class="card-body" style="max-height: 452px; overflow-y: auto;">
                            <!-- VARIÁVEIS GLOBAIS -->
                            <h6><?php echo $this->LANG['general']; ?>:</h6>
                            <ul class="list-unstyled">
                                <li><code>{cliente}</code> - <?php echo $this->LANG['client_var']; ?></li>
                                <li><code>{provedor}</code> - <?php echo $this->LANG['provider_var']; ?></li>
                                <li><code>{assinatura}</code> - <?php echo $this->LANG['signature_var']; ?></li>
                                <li><code>{quebrar_mensagem}</code> - <?php echo $this->LANG['break_message_var']; ?></li>
                                <li><code>{url_whmcs}</code> - <?php echo $this->LANG['whmcs_url_var']; ?></li>
                                <li><code>{data_atual}</code> - <?php echo $this->LANG['current_date_var']; ?></li>
                                <li><code>{hora_atual}</code> - <?php echo $this->LANG['current_time_var']; ?></li>
                                <li><code>{data_hora_atual}</code> - <?php echo $this->LANG['current_datetime_var']; ?></li>
                            </ul>

                            <!-- CLIENTES -->
                            <h6><?php echo $this->LANG['clients']; ?>:</h6>
                            <ul class="list-unstyled">
                                <li><code>{cliente_id}</code> - <?php echo $this->LANG['client_id_var']; ?></li>
                                <li><code>{cliente_primeiro_nome}</code> - <?php echo $this->LANG['client_firstname_var']; ?></li>
                                <li><code>{cliente_sobrenome}</code> - <?php echo $this->LANG['client_lastname_var']; ?></li>
                                <li><code>{email}</code> - <?php echo $this->LANG['client_email_var']; ?></li>
                                <li><code>{telefone}</code> - <?php echo $this->LANG['client_phone_var']; ?></li>
                                <li><code>{endereco}</code> - <?php echo $this->LANG['client_address_var']; ?></li>
                                <li><code>{bairro}</code> - <?php echo $this->LANG['client_neighborhood_var']; ?></li>
                                <li><code>{cidade}</code> - <?php echo $this->LANG['client_city_var']; ?></li>
                                <li><code>{estado}</code> - <?php echo $this->LANG['client_state_var']; ?></li>
                                <li><code>{cep}</code> - <?php echo $this->LANG['client_zipcode_var']; ?></li>
                                <li><code>{pais}</code> - <?php echo $this->LANG['client_country_var']; ?></li>
                            </ul>

                            <!-- FATURAS -->
                            <h6><?php echo $this->LANG['invoices']; ?>:</h6>
                            <ul class="list-unstyled">
                                <li><code>{numero_fatura}</code> - <?php echo $this->LANG['invoice_number_var']; ?></li>
                                <li><code>{titulo}</code> - <?php echo $this->LANG['title_var']; ?></li>
                                <li><code>{valor}</code> - <?php echo $this->LANG['value_var']; ?></li>
                                <li><code>{vencimento}</code> - <?php echo $this->LANG['due_date_var']; ?></li>
                                <li><code>{dias_vencimento}</code> - <?php echo $this->LANG['days_until_due_var']; ?></li>
                                <li><code>{data_criacao}</code> - <?php echo $this->LANG['creation_date_var']; ?></li>
                                <li><code>{data_pagamento}</code> - <?php echo $this->LANG['payment_date_var']; ?></li>
                                <li><code>{data_cancelamento}</code> - <?php echo $this->LANG['cancellation_date_var']; ?></li>
                                <li><code>{codigopix}</code> - <?php echo $this->LANG['pix_code_var']; ?></li>
                                <li><code>{qr_code_url}</code> - <?php echo $this->LANG['qr_code_url_var']; ?></li>
                                <li><code>{linhadigitavel}</code> - <?php echo $this->LANG['barcode_var']; ?></li>
                                <li><code>{link_fatura}</code> - <?php echo $this->LANG['invoice_link_var']; ?></li>
                                <li><code>{itens_fatura}</code> - <?php echo $this->LANG['invoice_items_var']; ?></li>
                                <li><code>{metodo_pagamento}</code> - <?php echo $this->LANG['payment_method_var']; ?></li>
                                <li><code>{motivo_cancelamento}</code> - <?php echo $this->LANG['cancellation_reason_var']; ?></li>
                            </ul>

                            <!-- TICKETS -->
                            <h6><?php echo $this->LANG['tickets']; ?>:</h6>
                            <ul class="list-unstyled">
                                <li><code>{numero_ticket}</code> - <?php echo $this->LANG['ticket_number_var']; ?></li>
                                <li><code>{assunto}</code> - <?php echo $this->LANG['subject_var']; ?></li>
                                <li><code>{mensagem}</code> - <?php echo $this->LANG['ticket_message_var']; ?></li>
                                <li><code>{departamento}</code> - <?php echo $this->LANG['department_var']; ?></li>
                                <li><code>{prioridade}</code> - <?php echo $this->LANG['priority_var']; ?></li>
                                <li><code>{atendente}</code> - <?php echo $this->LANG['admin_name_var']; ?></li>
                                <li><code>{link_ticket}</code> - <?php echo $this->LANG['ticket_link_var']; ?></li>
                            </ul>

                            <!-- AUTO LOGIN -->
                            <h6>
                                <a href="#" 
                                    data-toggle="tooltip" 
                                    data-placement="top" 
                                    title='Essas variáveis também podem ser utilizadas nos templates de e-mail, por exemplo: <a href="{link_fatura_autologin}">Acessar fatura diretamente</a>' 
                                    style="color: inherit; text-decoration: none; cursor: help;"
                                >
                                    <?php echo $this->LANG['autologin_title']; ?>
                                    <i class="fas fa-info-circle ml-1" style="font-size: 0.8em; opacity: 0.7;"></i>
                                </a>:
                            </h6>

                            <script>
                            $(function () {
                                $('[data-toggle="tooltip"]').tooltip();
                            });
                            </script>
                            <ul class="list-unstyled">
                                <li><code>{link_fatura_autologin}</code> - <?php echo $this->LANG['link_fatura_autologin']; ?></li>
                                <li><code>{link_ticket_autologin}</code> - <?php echo $this->LANG['link_ticket_autologin']; ?></li>
                                <li><code>{token_autologin}</code> - <?php echo $this->LANG['token_autologin']; ?></li>
                                <li><code>{token_expiracao}</code> - <?php echo $this->LANG['token_expiracao']; ?></li>
                            </ul>

                            <!-- SERVIÇOS -->
                            <h6><?php echo $this->LANG['services']; ?>:</h6>
                            <ul class="list-unstyled">
                                <li><code>{servico}</code> - <?php echo $this->LANG['service_name_var']; ?></li>
                                <li><code>{nome_servico}</code> - <?php echo $this->LANG['service_name_alt_var']; ?></li>
                                <li><code>{id_servico}</code> - <?php echo $this->LANG['service_id_var']; ?></li>
                                <li><code>{dominio}</code> - <?php echo $this->LANG['domain_var']; ?></li>
                                <li><code>{ip_dedicado}</code> - <?php echo $this->LANG['dedicated_ip_var']; ?></li>
                                <li><code>{usuario}</code> - <?php echo $this->LANG['username_var']; ?></li>
                                <li><code>{senha}</code> - <?php echo $this->LANG['password_var']; ?></li>
                                <li><code>{data_ativacao}</code> - <?php echo $this->LANG['activation_date_var']; ?></li>
                                <li><code>{data_suspensao}</code> - <?php echo $this->LANG['suspension_date_var']; ?></li>
                                <li><code>{data_reativacao}</code> - <?php echo $this->LANG['unsuspension_date_var']; ?></li>
                                <li><code>{data_cancelamento}</code> - <?php echo $this->LANG['termination_date_var']; ?></li>
                                <li><code>{motivo}</code> - <?php echo $this->LANG['suspension_reason_var']; ?></li>
                            </ul>

                            <!-- CANCELAMENTOS -->
                            <h6><?php echo $this->LANG['cancellations']; ?>:</h6>
                            <ul class="list-unstyled">
                                <li><code>{razao_cancelamento}</code> - <?php echo $this->LANG['cancellation_reason_var']; ?></li>
                                <li><code>{tipo_cancelamento}</code> - <?php echo $this->LANG['cancellation_type_var']; ?></li>
                                <li><code>{data_solicitacao}</code> - <?php echo $this->LANG['request_date_var']; ?></li>
                            </ul>

                            <!-- COTAÇÕES -->
                            <h6><?php echo $this->LANG['quotes']; ?>:</h6>
                            <ul class="list-unstyled">
                                <li><code>{numero_cotacao}</code> - <?php echo $this->LANG['quote_number_var']; ?></li>
                                <li><code>{subject_cotacao}</code> - <?php echo $this->LANG['quote_subject_var']; ?></li>
                                <li><code>{valor_cotacao}</code> - <?php echo $this->LANG['quote_value_var']; ?></li>
                                <li><code>{validade_cotacao}</code> - <?php echo $this->LANG['quote_validity_var']; ?></li>
                                <li><code>{status_cotacao}</code> - <?php echo $this->LANG['quote_status_var']; ?></li>
                                <li><code>{itens_cotacao}</code> - <?php echo $this->LANG['quote_items_var']; ?></li>
                                <li><code>{data_aceitacao}</code> - <?php echo $this->LANG['acceptance_date_var']; ?></li>
                                <li><code>{alteracoes}</code> - <?php echo $this->LANG['changes_var']; ?></li>
                            </ul>

                            <!-- SISTEMA -->
                            <h6><?php echo $this->LANG['system']; ?>:</h6>
                            <ul class="list-unstyled">
                                <li><code>{codigo_verificacao}</code> - <?php echo $this->LANG['verification_code_var']; ?></li>
                                <li><code>{data_cadastro}</code> - <?php echo $this->LANG['registration_date_var']; ?></li>
                                <li><code>{data_alteracao}</code> - <?php echo $this->LANG['modification_date_var']; ?></li>
                                <li><code>{nova_senha}</code> - <?php echo $this->LANG['new_password_var']; ?></li>
                                <li><code>{alteracoes}</code> - <?php echo $this->LANG['changes_var']; ?></li>
                                <li><code>{tipo_servico}</code> - <?php echo $this->LANG['service_type_var']; ?></li>
                                <li><code>{nome_produto}</code> - <?php echo $this->LANG['product_name_var']; ?></li>
                            </ul>
                        </div>
                    </div>
    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['template_statistics']; ?></h3>
                        </div>
                        <div class="card-body">
                            <p><strong><?php echo $this->LANG['usage']; ?>:</strong> <?= $template->usage_count ?> <?php echo $this->LANG['times']; ?></p>
                            <p><strong><?php echo $this->LANG['created_at']; ?>:</strong> <?= date('d/m/Y H:i', strtotime($template->created_at)) ?></p>
                            <p><strong><?php echo $this->LANG['updated_at']; ?>:</strong> <?= date('d/m/Y H:i', strtotime($template->updated_at)) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            $(document).ready(function() {
                $('#saveTemplate').click(function() {
                    var formData = $('#editTemplateForm').serializeArray();
                    var dataObj = {};
                    $.each(formData, function(i, field) {
                        dataObj[field.name] = field.value;
                    });
                    
                    $.ajax({
                        url: 'addonmodules.php?module=zapcel&action=ajax',
                        type: 'POST',
                        data: $.extend({subaction: 'update_template'}, dataObj),
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: '<?php echo $this->LANG['success']; ?>!',
                                    text: '<?php echo $this->LANG['template_updated']; ?>!',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: '<?php echo $this->LANG['error']; ?>',
                                    text: response.error || '<?php echo $this->LANG['unknown_error']; ?>',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', xhr.responseText);
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: '<?php echo $this->LANG['error_saving_template']; ?>: ' + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });
                
                // ========== BARRA DE FERRAMENTAS DE FORMATO - EDIÇÃO ==========
                
                // Botões de formatação - Editar
                $('.format-btn-edit').click(function() {
                    var format = $(this).data('format');
                    var textarea = document.getElementById('templateTextareaEdit');
                    formatText(format, textarea);
                });
                
                // Botão de emojis - Editar
                $('#emojiPickerBtnEdit').click(function() {
                    $('#emojiPanelEdit').slideToggle();
                });
                
                // Inserir emoji ao clicar - Editar
                $('.emoji-item-edit').click(function() {
                    var emoji = $(this).text();
                    var textarea = document.getElementById('templateTextareaEdit');
                    var cursorPos = textarea.selectionStart;
                    var textBefore = textarea.value.substring(0, cursorPos);
                    var textAfter = textarea.value.substring(cursorPos);
                    textarea.value = textBefore + emoji + textAfter;
                    textarea.focus();
                    textarea.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
                });
                
                // Função compartilhada de formatação
                function formatText(format, textarea) {
                    var start = textarea.selectionStart;
                    var end = textarea.selectionEnd;
                    var selectedText = textarea.value.substring(start, end);
                    
                    if (!selectedText) {
                        Swal.fire({
                            title: '<?php echo $this->LANG['warning']; ?>',
                            text: '<?php echo $this->LANG['select_text_to_format']; ?>',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                    
                    var formattedText = '';
                    switch(format) {
                        case 'bold':
                            formattedText = '*' + selectedText + '*';
                            break;
                        case 'italic':
                            formattedText = '_' + selectedText + '_';
                            break;
                        case 'strikethrough':
                            formattedText = '~' + selectedText + '~';
                            break;
                        case 'monospace':
                            formattedText = '```' + selectedText + '```';
                            break;
                    }
                    
                    textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
                    textarea.focus();
                    textarea.setSelectionRange(start, start + formattedText.length);
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Página de Estatísticas Detalhadas
     */
    private function statisticsPage()
    {
        $period = $_GET['period'] ?? 'month';
        
        // Estatísticas básicas - CORREÇÃO v2.0.1: Filtra logs de debug
        $totalMessages = Capsule::table('mod_zapcel_logs')
            ->where(function($query) {
                $query->where('event_type', 'NOT LIKE', 'debug_%')
                    ->where('event_type', '!=', 'gateway_manager_debug')
                    ->where('event_type', '!=', 'system_log');
            })
            ->whereIn('success', [0, 1])
            ->count();
        
        $successfulMessages = Capsule::table('mod_zapcel_logs')
            ->where('success', 1)
            ->where(function($query) {
                $query->where('event_type', 'NOT LIKE', 'debug_%')
                    ->where('event_type', '!=', 'gateway_manager_debug')
                    ->where('event_type', '!=', 'system_log');
            })
            ->count();
        
        $failedMessages = Capsule::table('mod_zapcel_logs')
            ->where('success', 0)
            ->where(function($query) {
                $query->where('event_type', 'NOT LIKE', 'debug_%')
                    ->where('event_type', '!=', 'gateway_manager_debug')
                    ->where('event_type', '!=', 'system_log');
            })
            ->count();
        
        $successRate = $totalMessages > 0 ? round(($successfulMessages / $totalMessages) * 100, 2) : 0;
    
        // Estatísticas por evento - CORREÇÃO v2.0.1: Filtra logs de debug
        $eventStats = Capsule::table('mod_zapcel_logs')
            ->select('event_type', 
                    Capsule::raw('COUNT(*) as total_messages'),
                    Capsule::raw('SUM(success) as successful_messages'),
                    Capsule::raw('SUM(1 - success) as failed_messages'))
            ->where(function($query) {
                $query->where('event_type', 'NOT LIKE', 'debug_%')
                    ->where('event_type', '!=', 'gateway_manager_debug')
                    ->where('event_type', '!=', 'system_log');
            })
            ->groupBy('event_type')
            ->get();
    
        ob_start();
        ?>
        <div class="zapcel-admin-container">
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2>
                            <i class="fas fa-chart-pie mr-2"></i> 
                            <?php echo $this->LANG['detailed_statistics']; ?>
                        </h2>
                        <p class="text-muted"><?php echo $this->LANG['statistics_subtitle']; ?></p>
                    </div>
                    <div class="col-md-6 text-right">
                        <div class="btn-group">
                            <a href="addonmodules.php?module=zapcel&action=dashboard" class="btn btn-outline-secondary mr-2">
                                <i class="fas fa-arrow-left mr-1"></i> <?php echo $this->LANG['back']; ?>
                            </a>
                            <div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown">
                                    <?php echo $this->LANG['period']; ?>: <?= ucfirst($period) ?> <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a href="?module=zapcel&action=statistics&period=day"><?php echo $this->LANG['day']; ?></a></li>
                                    <li><a href="?module=zapcel&action=statistics&period=week"><?php echo $this->LANG['week']; ?></a></li>
                                    <li><a href="?module=zapcel&action=statistics&period=month"><?php echo $this->LANG['month']; ?></a></li>
                                    <li><a href="?module=zapcel&action=statistics&period=year"><?php echo $this->LANG['year']; ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    
            <!-- Resumo -->
            <div class="row">
                <div class="col-md-3">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?= number_format($totalMessages) ?></h3>
                            <p><?php echo $this->LANG['total_messages']; ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= $successRate ?>%</h3>
                            <p><?php echo $this->LANG['success_rate']; ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($successfulMessages) ?></h3>
                            <p><?php echo $this->LANG['successful_messages']; ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($failedMessages) ?></h3>
                            <p><?php echo $this->LANG['failed_messages']; ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
    
            <!-- Estatísticas por Evento -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['statistics_by_event_type']; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th><?php echo $this->LANG['event']; ?></th>
                                            <th><?php echo $this->LANG['total']; ?></th>
                                            <th><?php echo $this->LANG['successful_messages']; ?></th>
                                            <th><?php echo $this->LANG['failed_messages']; ?></th>
                                            <th><?php echo $this->LANG['rate']; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($eventStats as $event): ?>
                                        <tr>
                                            <td><?= $this->getEventTypeDisplayName($event->event_type) ?></td>
                                            <td><?= $event->total_messages ?></td>
                                            <td><?= $event->successful_messages ?></td>
                                            <td><?= $event->failed_messages ?></td>
                                            <td>
                                                <?php 
                                                $eventRate = $event->total_messages > 0 ? 
                                                    round(($event->successful_messages / $event->total_messages) * 100, 2) : 0;
                                                ?>
                                                <span class="badge bg-<?= $eventRate >= 90 ? 'success' : ($eventRate >= 80 ? 'warning' : 'danger') ?>">
                                                    <?= $eventRate ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Página de Validação WhatsApp
     */
    private function validationPage()
    {
        $validations = Capsule::table('mod_zapcel_validation as v')
            ->join('tblclients as c', 'v.client_id', '=', 'c.id')
            ->select('v.*', 'c.firstname', 'c.lastname', 'c.email', 'c.phonenumber')
            ->orderBy('v.updated_at', 'desc')
            ->get();

        // Conta pendentes baseado no STATUS
        $pendingCount = Capsule::table('mod_zapcel_validation')
            ->where('status', 'pending')
            ->count();

        $today = date('Y-m-d');

        // Total de clientes hoje (usa validações criadas hoje – evita depender de coluna desconhecida em tblclients)
        $todayClients = Capsule::table('mod_zapcel_validation')
            ->whereDate('created_at', $today)
            ->distinct('client_id')
            ->count('client_id');

        // Validados hoje (status validated, marcados hoje em updated_at)
        $todayValidated = Capsule::table('mod_zapcel_validation')
            ->where('status', 'validated')
            ->whereDate('updated_at', $today)
            ->count();

        // Pendentes hoje (novos pendentes criados hoje)
        $todayPending = Capsule::table('mod_zapcel_validation')
            ->where('status', 'pending')
            ->whereDate('created_at', $today)
            ->count();

        // Invalidado hoje (blocked/expired/invalid, alterados hoje)
        $todayInvalidated = Capsule::table('mod_zapcel_validation')
            ->whereIn('status', ['blocked', 'expired', 'invalid'])
            ->whereDate('updated_at', $today)
            ->count();

        ob_start();
        ?>
        <div class="zapcel-admin-container">
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <i class="fas fa-mobile-alt mr-2"></i> 
                            <?php echo $this->LANG['whatsapp_validation_title']; ?>
                        </h2>
                        <p class="text-muted"><?php echo $this->LANG['validation_subtitle']; ?></p>
                    </div>
                    <div class="col-md-4 text-right">
                        <a href="addonmodules.php?module=zapcel&action=dashboard" class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-arrow-left mr-1"></i> <?php echo $this->LANG['back']; ?>
                        </a>
                        <?php if ($pendingCount > 0): ?>
                        <button class="btn btn-warning" id="sendPendingValidations">
                            <i class="fas fa-paper-plane mr-1"></i> <?php echo $this->LANG['send_pending']; ?> (<?= $pendingCount ?>)
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Cards de Status -->
            <div class="row">
                <!-- Total de Clientes -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary">
                            <i class="fas fa-users"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['total_clients'] ?? 'Total de Clientes'; ?></span>
                            <span class="info-box-number"><?= number_format(Capsule::table('tblclients')->count()) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-primary" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?= $this->LANG['today'] ?? 'Hoje' ?>: <?= number_format($todayClients) ?>
                            </small>
                            <small class="text-muted"><?= $this->LANG['total_clients_desc'] ?? 'Clientes com tentativa de validação.' ?></small>
                        </div>
                    </div>
                </div>

                <!-- Validados -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-success">
                            <i class="fas fa-check-circle"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['validated'] ?? 'Validados'; ?></span>
                            <span class="info-box-number"><?= number_format(Capsule::table('mod_zapcel_validation')->where('status', 'validated')->count()) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?= $this->LANG['today'] ?? 'Hoje' ?>: <?= number_format($todayValidated) ?>
                            </small>
                            <small class="text-muted"><?= $this->LANG['validated_desc'] ?? 'WhatsApp confirmado com sucesso.' ?></small>
                        </div>
                    </div>
                </div>

                <!-- Pendentes -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning">
                            <i class="fas fa-clock"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['pending'] ?? 'Pendentes'; ?></span>
                            <span class="info-box-number"><?= number_format(Capsule::table('mod_zapcel_validation')->where('status', 'pending')->count()) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-warning" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?= $this->LANG['today'] ?? 'Hoje' ?>: <?= number_format($todayPending) ?>
                            </small>
                            <small class="text-muted"><?= $this->LANG['pending_desc'] ?? 'Aguardando confirmação do código.' ?></small>
                        </div>
                    </div>
                </div>

                <!-- Invalidado (bloqueado / expirado / inválido) -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon btn-danger">
                            <i class="fas fa-times-circle"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['invalidated'] ?? 'Invalidado'; ?></span>
                            <span class="info-box-number"><?= number_format(Capsule::table('mod_zapcel_validation')->whereIn('status', ['blocked', 'expired', 'invalid'])->count()) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar btn-danger" style="width: 100%; background-color: #d9534f;"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?= $this->LANG['today'] ?? 'Hoje' ?>: <?= number_format($todayInvalidated) ?>
                            </small>
                            <small class="text-muted"><?= $this->LANG['invalidated_desc'] ?? 'Códigos expirados, inválidos ou bloqueados.' ?></small>
                        </div>
                    </div>
                </div>
            </div>


            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="validationTable">
                                    <thead>
                                        <tr>
                                            <th><?php echo $this->LANG['client']; ?></th>
                                            <th><?php echo $this->LANG['phone']; ?></th>
                                            <th><?php echo $this->LANG['status']; ?></th>
                                            <th><?php echo $this->LANG['code']; ?></th>
                                            <th><?php echo $this->LANG['attempts']; ?></th>
                                            <th><?php echo $this->LANG['last_attempt']; ?></th>
                                            <th width="120"><?php echo $this->LANG['actions']; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($validations as $validation): ?>
                                        <tr>
                                            <td>
                                                <strong><a style="text-decoration: none;" href="clientssummary.php?userid=<?= $validation->client_id ?>" target="_blank" title="<?= htmlspecialchars($validation->firstname . ' ' . $validation->lastname) ?>"><?= htmlspecialchars($validation->firstname) ?> <?= htmlspecialchars($validation->lastname) ?></strong>  

                                                <small class="text-muted"><?= htmlspecialchars($validation->email) ?></small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($validation->phone_number) ?>
                                            </td>
                                            <td>
                                                <?php switch($validation->status):
                                                    case 'validated': ?>
                                                        <span class="badge bg-success"><?php echo $this->LANG['validated']; ?></span>
                                                        <?php break;
                                                    case 'pending': ?>
                                                        <span class="badge bg-warning"><?php echo $this->LANG['pending']; ?></span>
                                                        <?php break;
                                                    case 'blocked': ?>
                                                        <span class="badge bg-danger"><?php echo $this->LANG['blocked']; ?></span>
                                                        <?php break;
                                                    case 'expired': ?>
                                                        <span class="badge bg-secondary"><?php echo $this->LANG['expired']; ?></span>
                                                        <?php break;
                                                    case 'invalid': ?>
                                                        <span class="badge bg-danger"><?php echo $this->LANG['invalidated']; ?></span>
                                                        <?php break;
                                                    default: ?>
                                                        <span class="badge bg-secondary"><?php echo $this->LANG['not_started']; ?></span>
                                                <?php endswitch; ?>
                                            </td>
                                            <td>
                                                <?php if ($validation->verification_code): ?>
                                                    <code><?= htmlspecialchars($validation->verification_code) ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $validation->attempts ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= $validation->updated_at ? date('d/m/Y H:i', strtotime($validation->updated_at)) : '-' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($validation->status == 'pending'): ?>
                                                    <button class="btn btn-success resend-validation" 
                                                            data-clientid="<?= $validation->client_id ?>" 
                                                            title="<?php echo $this->LANG['resend_code']; ?>">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-info view-validation" 
                                                            data-clientid="<?= $validation->client_id ?>" 
                                                            title="<?php echo $this->LANG['view_details']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-danger reset-validation" 
                                                            data-clientid="<?= $validation->client_id ?>"
                                                            data-name="<?= htmlspecialchars($validation->firstname) ?> <?= htmlspecialchars($validation->lastname) ?>"
                                                            title="<?php echo $this->LANG['reset_validation']; ?>">
                                                        <i class="fas fa-sync"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Detalhes Validação -->
        <div class="modal fade" id="validationDetailsModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title"><?php echo $this->LANG['validation_details']; ?></h1>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body" id="validationDetailsContent">
                        <!-- Conteúdo carregado via AJAX -->
                        <div class="text-center py-5">
                            <i class="fas fa-spinner fa-spin fa-3x text-primary"></i>
                            <p class="mt-3 text-muted"><?php echo $this->LANG['loading']; ?>...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>
                            <?php echo $this->LANG['close']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <style>

        #validationDetailsModal .modal-content {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        #validationDetailsModal .validation-info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }

        #validationDetailsModal .validation-info-box.success {
            border-left-color: #28a745;
            background: #d4edda;
        }

        #validationDetailsModal .validation-info-box.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }

        #validationDetailsModal .validation-info-box.danger {
            border-left-color: #dc3545;
            background: #f8d7da;
        }

        #validationDetailsModal .validation-label {
            font-weight: 600;
            color: #495057;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        #validationDetailsModal .validation-value {
            font-size: 16px;
            color: #212529;
            font-weight: 500;
        }

        #validationDetailsModal .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        #validationDetailsModal .status-badge.validated {
            background: #28a745;
            color: white;
        }

        #validationDetailsModal .status-badge.pending {
            background: #ffc107;
            color: #212529;
        }

        #validationDetailsModal .status-badge.blocked {
            background: #dc3545;
            color: white;
        }

        #validationDetailsModal .status-badge.expired {
            background: #6c757d;
            color: white;
        }
        </style>

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            $(document ).ready(function() {
                // DataTable
                $('#validationTable').DataTable({
                    order: [[5, 'desc']], // Ordena por "Última Tentativa" decrescente
                    pageLength: 25,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                    }
                });
                // Reenviar código de validação
                $('.resend-validation').click(function() {
                    var clientId = $(this).data('clientid');
                    
                    $.ajax({
                        url: 'addonmodules.php?module=zapcel&action=ajax',
                        type: 'POST',
                        data: {
                            subaction: 'resend_validation',
                            client_id: clientId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: '<?php echo $this->LANG['code_resent']; ?>',
                                    text: '<?php echo $this->LANG['code_resent_success']; ?>!',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                });
                                location.reload();
                            } else {
                                Swal.fire({
                                    title: '<?php echo $this->LANG['error']; ?>',
                                    text: response.error,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', xhr.responseText);
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });

                // Ver detalhes da validação
                $('.view-validation').click(function() {
                    var clientId = $(this).data('clientid');
                    
                    $.ajax({
                        url: 'addonmodules.php?module=zapcel&action=ajax',
                        type: 'POST',
                        data: {
                            subaction: 'get_validation_details',
                            client_id: clientId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $('#validationDetailsContent').html(response.html);
                                $('#validationDetailsModal').modal('show');
                            } else {
                                Swal.fire({
                                    title: '<?php echo $this->LANG['error']; ?>',
                                    text: response.error,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', xhr.responseText);
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });

                // Resetar validação
                $('.reset-validation').click(function() {
                    var clientId = $(this).data('clientid');
                    var clientName = $(this).data('name');
                    
                    Swal.fire({
                        title: '<?php echo $this->LANG['confirm_reset_validation']; ?>'.replace('%s', clientName),
                        text: '<?php echo $this->LANG['reset_validation_warning']; ?>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<?php echo $this->LANG['reset_validation']; ?>',
                        cancelButtonText: '<?php echo $this->LANG['cancel']; ?>',
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'addonmodules.php?module=zapcel&action=ajax',
                                type: 'POST',
                                data: {
                                    subaction: 'reset_validation',
                                    client_id: clientId
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['validation_reset']; ?>',
                                            text: '<?php echo $this->LANG['validation_reset_success']; ?>!',
                                            icon: 'success',
                                            confirmButtonText: 'OK'
                                        });
                                        location.reload();
                                    } else {
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['error']; ?>',
                                            text: response.error,
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('Erro AJAX:', xhr.responseText);
                                    Swal.fire({
                                        title: '<?php echo $this->LANG['error']; ?>',
                                        text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            });
                        }
                    });
                });

                // Enviar validações pendentes
                $('#sendPendingValidations').click(function() {
                    Swal.fire({
                        title: '<?php echo $this->LANG['confirm_send_pending_validations']; ?>',
                        text: '<?php echo $this->LANG['send_pending_validations_warning']; ?>',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: '<?php echo $this->LANG['send_pending']; ?>',
                        cancelButtonText: '<?php echo $this->LANG['cancel']; ?>',
                        confirmButtonColor: '#f0ad4e',
                        cancelButtonColor: '#3085d6',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Desabilita o botão e mostra loading
                            $('#sendPendingValidations').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> <?php echo $this->LANG['sending']; ?>...');
                            
                            $.ajax({
                                url: 'addonmodules.php?module=zapcel&action=ajax',
                                type: 'POST',
                                data: {
                                    subaction: 'send_pending_validations'
                                },
                                dataType: 'json',
                                success: function(response) {
                                    // Reabilita o botão
                                    $('#sendPendingValidations').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> <?php echo $this->LANG['send_pending']; ?>');
                                    
                                    if (response.success) {
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['validations_sent']; ?>',
                                            text: response.message,
                                            icon: 'success',
                                            confirmButtonText: 'OK'
                                        });
                                        location.reload();
                                    } else {
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['error']; ?>',
                                            text: response.error,
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                },
                                error: function(xhr, status, error) {
                                    // Reabilita o botão
                                    $('#sendPendingValidations').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> <?php echo $this->LANG['send_pending']; ?>');
                                    
                                    console.error('Erro AJAX:', xhr.responseText);
                                    Swal.fire({
                                        title: '<?php echo $this->LANG['error']; ?>',
                                        text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            });
                        }
                    });
                });

            });
            // Filtro de busca na tabela
            $('#searchValidation').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('.table tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Página de Gateways de Pagamento - VERSÃO FINAL COM MODAL E LOGOS
     */
    private function gatewaysPage()
    {
        // Busca gateway ativo configurado
        $activeGateway = Capsule::table('tbladdonmodules')
            ->where('module', 'zapcel')
            ->where('setting', 'zapcel_active_gateway')
            ->value('value') ?? 'none';
        
        // Escaneia pasta gateways/ para encontrar gateways disponíveis
        $gatewayPath = __DIR__ . '/../gateways/';
        $availableGateways = [];
        
        // Logos dos gateways (usando Font Awesome ou URLs)
        $gatewayLogos = [
            'iugupix' => 'fas fa-credit-card',
            'mercadopago' => 'fas fa-shopping-cart',
            'paghiper' => 'fas fa-barcode',
            'pagseguro' => 'fas fa-shield-alt',
            'inter' => 'fas fa-university',
            'bs2' => 'fas fa-building',
            'asaas' => 'fas fa-money-check-alt',
        ];
        
        // Cores dos gateways
        $gatewayColors = [
            'iugupix' => '#6C5CE7',
            'mercadopago' => '#00AEEF',
            'paghiper' => '#FF6B35',
            'pagseguro' => '#FFC700',
            'inter' => '#FF6600',
            'bs2' => '#00A859',
            'asaas' => '#1E88E5',
        ];
        
        if (is_dir($gatewayPath)) {
            $files = glob($gatewayPath . '*Gateway.php');
            
            foreach ($files as $file) {
                $filename = basename($file);
                
                // Ignora arquivos de interface/abstract
                if (in_array($filename, ['GatewayInterface.php', 'AbstractGateway.php'])) {
                    continue;
                }
                
                // Extrai informações do gateway
                $className = str_replace('.php', '', $filename);
                $gatewayId = strtolower(str_replace('Gateway', '', $className));
                $displayName = str_replace('Gateway', '', $className);
                
                // Define logo e cor
                $logo = $gatewayLogos[$gatewayId] ?? 'fas fa-plug';
                $color = $gatewayColors[$gatewayId] ?? '#6c757d';
                
                $availableGateways[$gatewayId] = [
                    'id' => $gatewayId,
                    'name' => $displayName,
                    'file' => $filename,
                    'logo' => $logo,
                    'color' => $color,
                    'active' => ($activeGateway === $gatewayId)
                ];
            }
        }
        
        // Ordena por nome
        uasort($availableGateways, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        ob_start();
        ?>
        <style>
        .gateway-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border-radius: 12px;
            overflow: hidden;
        }
        .gateway-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15) !important;
        }
        .gateway-card.active {
            border: 3px solid #28a745 !important;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.3) !important;
        }
        .gateway-card-header {
            padding: 20px;
            background: var(--gateway-color);
            color: white;
            border: none !important;
        }
        .gateway-logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
            backdrop-filter: blur(10px);
        }
        .gateway-name {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: white;
        }
        .gateway-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #28a745;
            color: white;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.4);
        }
        .custom-radio-gateway {
            transform: scale(1.3);
            cursor: pointer;
        }
        .alert-gateway-status {
            border-radius: 12px;
            border-left: 4px solid;
            padding: 20px;
            margin-bottom: 30px;
        }
        .tutorial-modal .modal-content {
            border-radius: 15px;
            border: none;
        }
        .tutorial-modal .modal-header {
            //background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 25px 30px;
        }
        .tutorial-modal .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .tutorial-step {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .tutorial-step h4 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            white-space: pre;      /* Preserva quebras de linha e espaços */
            word-wrap: normal;     /* Não quebra palavras */
        }
        </style>

        <div class="zapcel-admin-container">
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <i class="fas fa-credit-card mr-2"></i> 
                            <?php echo $this->LANG['payment_gateways']; ?>
                        </h2>
                        <p class="text-muted">
                            <?php echo zapcel_trans('gateways_subtitle_new'); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-right">
                        <button class="btn btn-primary mr-2" data-toggle="modal" data-target="#tutorialModal">
                            <i class="fas fa-book mr-1"></i> <?php echo zapcel_trans('how_to_create_gateway'); ?>
                        </button>
                        <a href="addonmodules.php?module=zapcel&action=dashboard" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> <?php echo $this->LANG['back']; ?>
                        </a>
                    </div>
                </div>
            </div>

            <?php if (empty($availableGateways)): ?>
            <!-- Nenhum gateway encontrado -->
            <div class="alert alert-info alert-gateway-status" style="border-left-color: #17a2b8;">
                <i class="fas fa-info-circle mr-2" style="font-size: 24px;"></i>
                <strong><?php echo zapcel_trans('no_gateways_found'); ?></strong><br>
                <?php echo zapcel_trans('no_gateways_description'); ?>
                <button class="btn btn-sm btn-info mt-2" data-toggle="modal" data-target="#tutorialModal">
                    <i class="fas fa-graduation-cap mr-1"></i> <?php echo zapcel_trans('learn_how_to_create'); ?>
                </button>
            </div>
            <?php else: ?>
            
            <!-- Gateway Ativo Atual -->
            <div class="alert alert-gateway-status <?php echo ($activeGateway !== 'none') ? 'alert-success' : 'alert-warning'; ?>" 
                    style="border-left-color: <?php echo ($activeGateway !== 'none') ? '#28a745' : '#ffc107'; ?>;">
                <i class="fas fa-<?php echo ($activeGateway !== 'none') ? 'check-circle' : 'exclamation-triangle'; ?> mr-2" style="font-size: 28px;"></i>
                <span style="font-size: 28px; font-weight: 400;">  <?php echo zapcel_trans('active_gateway'); ?>:</span>
                <?php if ($activeGateway !== 'none'): ?>
                    <span style="font-size: 28px; font-weight: 600;">
                        <?php echo isset($availableGateways[$activeGateway]) ? $availableGateways[$activeGateway]['name'] : $activeGateway; ?>
                    </span>
                <?php else: ?>
                    <span style="font-size: 28px; font-weight: 600;"><?php echo zapcel_trans('none_selected'); ?></span>
                <?php endif; ?>
            </div>

            <!-- Lista de Gateways -->
            <div class="row">
                <!-- Opção: Nenhum (desativado) -->
                <div class="col-md-4 mb-4">
                    <div class="card gateway-card <?php echo ($activeGateway === 'none') ? 'active' : ''; ?>" 
                            style="--gateway-color: #6c757d; --gateway-color-dark: #5a6268;">
                        <div class="gateway-card-header position-relative">
                            <?php if ($activeGateway === 'none'): ?>
                            <span class="gateway-badge">
                                <i class="fas fa-check mr-1"></i> <?php echo zapcel_trans('active'); ?>
                            </span>
                            <?php endif; ?>
                            <div class="gateway-logo">
                                <i class="fas fa-ban"></i>
                            </div>
                            <h5 class="gateway-name"><?php echo zapcel_trans('none_disabled'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="custom-control custom-radio">
                                <input type="radio" 
                                        id="gateway_none" 
                                        name="active_gateway" 
                                        value="none" 
                                        class="custom-control-input custom-radio-gateway gateway-radio"
                                        <?php echo ($activeGateway === 'none') ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="gateway_none">
                                    <strong><?php echo zapcel_trans('no_gateway_description'); ?></strong>
                                </label>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-file-code mr-1"></i> <?php echo zapcel_trans('file_none'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gateways Disponíveis -->
                <?php foreach ($availableGateways as $gateway): ?>
                <div class="col-md-4 mb-4">
                    <div class="card gateway-card <?php echo $gateway['active'] ? 'active' : ''; ?>" 
                            style="--gateway-color: <?php echo $gateway['color']; ?>;">
                        <div class="gateway-card-header position-relative">
                            <?php if ($gateway['active']): ?>
                            <span class="gateway-badge">
                                <i class="fas fa-check mr-1"></i> <?php echo zapcel_trans('active'); ?>
                            </span>
                            <?php endif; ?>
                            <div class="gateway-logo">
                                <i class="<?php echo $gateway['logo']; ?>"></i>
                            </div>
                            <h5 class="gateway-name"><?php echo $gateway['name']; ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="custom-control custom-radio mb-3">
                                <input type="radio" 
                                        id="gateway_<?php echo $gateway['id']; ?>" 
                                        name="active_gateway" 
                                        value="<?php echo $gateway['id']; ?>" 
                                        class="custom-control-input custom-radio-gateway gateway-radio"
                                        <?php echo $gateway['active'] ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="gateway_<?php echo $gateway['id']; ?>">
                                    <strong><?php echo zapcel_trans('select_this_gateway'); ?></strong>
                                </label>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-file-code mr-1"></i> <?php echo $gateway['file']; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Informações de Variáveis -->
            <div class="card mt-4" style="border-radius: 12px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <div class="card-header" style="background-color: #667eea; color: white; border-radius: 12px 12px 0 0;">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-code mr-2"></i>
                        <?php echo zapcel_trans('available_variables'); ?>
                    </h3>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3"><i class="fas fa-qrcode mr-2 text-primary"></i> PIX</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px;">{codigopix}</code>
                                    <span class="ml-2 text-muted"><?php echo zapcel_trans('var_codigopix'); ?></span>
                                </li>
                                <li class="mb-2">
                                    <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px;">{qr_code_url}</code>
                                    <span class="ml-2 text-muted"><?php echo zapcel_trans('var_qr_code_url'); ?></span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3"><i class="fas fa-barcode mr-2 text-success"></i> Boleto</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px;">{linhadigitavel}</code>
                                    <span class="ml-2 text-muted"><?php echo zapcel_trans('var_linhadigitavel'); ?></span>
                                </li>
                                <li class="mb-2">
                                    <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px;">{link_fatura}</code>
                                    <span class="ml-2 text-muted"><?php echo zapcel_trans('var_link_fatura'); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Tutorial INTERNACIONALIZADO -->
        <div class="modal fade tutorial-modal" id="tutorialModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title">
                            <?php echo zapcel_trans('tutorial_title'); ?>
                        </h1>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <!-- Passo 1 -->
                        <div class="tutorial-step">
                            <h4><i class="fas fa-file-code mr-2"></i> <?php echo zapcel_trans('tutorial_step1_title'); ?></h4>
                            <p><?php echo zapcel_trans('tutorial_step1_desc'); ?></p>
                            <ul>
                                <li><?php echo zapcel_trans('tutorial_step1_ex1'); ?></li>
                                <li><?php echo zapcel_trans('tutorial_step1_ex2'); ?></li>
                                <li><?php echo zapcel_trans('tutorial_step1_ex3'); ?></li>
                            </ul>
                        </div>

                        <!-- Passo 2 -->
                        <div class="tutorial-step">
                            <h4><i class="fas fa-code mr-2"></i> <?php echo zapcel_trans('tutorial_step2_title'); ?></h4>
                            <p><?php echo zapcel_trans('tutorial_step2_desc'); ?></p>
                            <div class="code-block">
&lt;?php

namespace WHMCS\Module\Addon\Zapcel\Gateways;

use Illuminate\Database\Capsule\Manager as Capsule;

class <?php echo zapcel_trans('tutorial_code_classname'); ?> extends AbstractGateway
{
    protected $gatewayName = '<?php echo zapcel_trans('tutorial_code_gatewayname'); ?>';

    public function extractPixData($invoiceId)
    {
        // <?php echo zapcel_trans('tutorial_code_comment_pix'); ?>
        
        $pixData = Capsule::table('<?php echo zapcel_trans('tutorial_code_table_pix'); ?>')
            ->where('invoice_id', $invoiceId)
            ->first();

        if (!$pixData) {
            return null;
        }

        return [
            'qrcode' => $pixData->qr_code_url,
            'copiaecola' => $pixData->pix_code,
        ];
    }

    public function extractBoletoData($invoiceId)
    {
        // <?php echo zapcel_trans('tutorial_code_comment_boleto'); ?>
        
        $boletoData = Capsule::table('<?php echo zapcel_trans('tutorial_code_table_boleto'); ?>')
            ->where('invoice_id', $invoiceId)
            ->first();

        if (!$boletoData) {
            return null;
        }

        return [
            'linha_digitavel' => $boletoData->linha_digitavel,
            'pdf_url' => $boletoData->pdf_url,
        ];
    }
}
                            </div>
                        </div>

                        <!-- Passo 3 -->
                        <div class="tutorial-step">
                            <h4><i class="fas fa-database mr-2"></i> <?php echo zapcel_trans('tutorial_step3_title'); ?></h4>
                            <p><?php echo zapcel_trans('tutorial_step3_desc'); ?></p>
                            <div class="code-block">
-- <?php echo zapcel_trans('tutorial_sql_comment1'); ?>
SHOW TABLES LIKE '%<?php echo zapcel_trans('tutorial_code_gatewayname'); ?>%';

-- <?php echo zapcel_trans('tutorial_sql_comment2'); ?>
DESCRIBE mod_<?php echo zapcel_trans('tutorial_code_gatewayname'); ?>_pix;

-- <?php echo zapcel_trans('tutorial_sql_comment3'); ?>
SELECT * FROM mod_<?php echo zapcel_trans('tutorial_code_gatewayname'); ?>_pix WHERE invoice_id = 123;
                            </div>
                        </div>

                        <!-- Passo 4 -->
                        <div class="tutorial-step">
                            <h4><i class="fas fa-check-circle mr-2"></i> <?php echo zapcel_trans('tutorial_step4_title'); ?></h4>
                            <p><?php echo zapcel_trans('tutorial_step4_desc'); ?></p>
                            <ol>
                                <li><?php echo zapcel_trans('tutorial_step4_item1'); ?></li>
                                <li><?php echo zapcel_trans('tutorial_step4_item2'); ?></li>
                                <li><?php echo zapcel_trans('tutorial_step4_item3'); ?></li>
                                <li><?php echo zapcel_trans('tutorial_step4_item4'); ?></li>
                            </ol>
                        </div>

                        <!-- Dicas -->
                        <div class="alert alert-info">
                            <h5><i class="fas fa-lightbulb mr-2"></i> <?php echo zapcel_trans('tutorial_tips_title'); ?></h5>
                            <ul class="mb-0">
                                <li><?php echo zapcel_trans('tutorial_tip1'); ?></li>
                                <li><?php echo zapcel_trans('tutorial_tip2'); ?></li>
                                <li><?php echo zapcel_trans('tutorial_tip3'); ?></li>
                                <li><?php echo zapcel_trans('tutorial_tip4'); ?></li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i> <?php echo zapcel_trans('close'); ?>
                        </button>
                        <a href="https://www.hostcel.com.br/tutoriais/como-criar-seu-gateway-personalizado-para-o-zapcel-whmcs/" target="_blank" class="btn btn-primary">
                            <i class="fas fa-book mr-1"></i> <?php echo zapcel_trans('tutorial_full_docs'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Ao selecionar um gateway
            $('.gateway-radio').on('change', function() {
                const selectedGateway = $(this).val();
                const $radio = $(this);
                
                // Confirmação
                Swal.fire({
                    title: '<?php echo zapcel_trans('confirm_change_gateway'); ?>',
                    text: '<?php echo zapcel_trans('confirm_change_gateway_text'); ?>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<?php echo zapcel_trans('yes_change'); ?>',
                    cancelButtonText: '<?php echo zapcel_trans('cancel'); ?>',
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Salvar via AJAX
                        $.ajax({
                            url: 'addonmodules.php?module=zapcel&action=ajax',
                            method: 'POST',
                            data: {
                                subaction: 'set_active_gateway',
                                gateway: selectedGateway
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '<?php echo zapcel_trans('success'); ?>',
                                        text: response.message,
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: '<?php echo zapcel_trans('error'); ?>',
                                        text: response.error || '<?php echo zapcel_trans('unknown_error'); ?>'
                                    });
                                    $radio.prop('checked', false);
                                    $('input[name="active_gateway"][value="<?php echo $activeGateway; ?>"]').prop('checked', true);
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: '<?php echo zapcel_trans('error'); ?>',
                                    text: '<?php echo zapcel_trans('connection_error'); ?>'
                                });
                                $radio.prop('checked', false);
                                $('input[name="active_gateway"][value="<?php echo $activeGateway; ?>"]').prop('checked', true);
                            }
                        });
                    } else {
                        $radio.prop('checked', false);
                        $('input[name="active_gateway"][value="<?php echo $activeGateway; ?>"]').prop('checked', true);
                    }
                });
            });

            // Click no card seleciona o radio
            $('.gateway-card').on('click', function() {
                $(this).find('input[type="radio"]').trigger('click');
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Página de Logs do Sistema - CORRIGIDA
     */
    private function logsPage()
    {
        // Filtros
        $eventType = $_GET['type'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $status = $_GET['status'] ?? '';

        // Query base com filtros
        $query = Capsule::table('mod_zapcel_logs as l')
            ->leftJoin('tblclients as c', 'l.client_id', '=', 'c.id')
            ->select('l.*', 'c.firstname', 'c.lastname', 'c.email')
            ->orderBy('l.created_at', 'desc');

        // Aplica filtros
        if ($eventType) {
            $query->where('l.event_type', $eventType);
        }

        if ($dateFrom) {
            $query->whereDate('l.created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('l.created_at', '<=', $dateTo);
        }

        if ($status !== '') {
            $query->where('l.success', $status);
        }

        // Obtém TODOS os logs (DataTables faz a paginação no frontend)
        $logs = $query->limit(1000)->get();

        // Tipos de eventos para filtro
        $eventTypes = Capsule::table('mod_zapcel_logs')
            ->distinct()
            ->pluck('event_type')
            ->toArray();

        ob_start();
    ?>
        <div class="zapcel-admin-container">
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <i class="fas fa-list-alt mr-2"></i> 
                            <?php echo $this->LANG['system_logs']; ?>
                        </h2>
                        <p class="text-muted"><?php echo $this->LANG['logs_subtitle']; ?></p>
                    </div>
                    <div class="col-md-4 text-right">
                        <a href="addonmodules.php?module=zapcel&action=dashboard" class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-arrow-left mr-1"></i> <?php echo $this->LANG['back']; ?>
                        </a>
                        <div class="btn-group">
                            <button class="btn btn-info" id="refreshLogs">
                                <i class="fas fa-sync mr-1"></i> <?php echo $this->LANG['refresh']; ?>
                            </button>
                            <button class="btn btn-danger" id="clearLogs">
                                <i class="fas fa-trash mr-1"></i> <?php echo $this->LANG['clear_logs']; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['filters']; ?></h3>
                        </div>
                        <div class="card-body">
                            <form id="logsFilterForm" method="get" action="addonmodules.php">
                                <input type="hidden" name="module" value="zapcel">
                                <input type="hidden" name="action" value="logs">
                                
                                <div class="row align-items-end">
                                    <div class="col-md-3 mb-3">
                                        <label for="type"><?php echo $this->LANG['event_type']; ?>:</label>
                                        <select name="type" id="type" class="form-control">
                                            <option value=""><?php echo $this->LANG['all_types']; ?></option>
                                            <?php foreach ($eventTypes as $type): ?>
                                            <option value="<?= $type ?>" <?= $eventType === $type ? 'selected' : '' ?>>
                                                <?= $this->getEventTypeDisplayName($type) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2 mb-3">
                                        <label for="status"><?php echo $this->LANG['status']; ?>:</label>
                                        <select name="status" id="status" class="form-control">
                                            <option value=""><?php echo $this->LANG['all_statuses']; ?></option>
                                            <option value="1" <?= $status === '1' ? 'selected' : '' ?>><?php echo $this->LANG['success']; ?></option>
                                            <option value="0" <?= $status === '0' ? 'selected' : '' ?>><?php echo $this->LANG['error']; ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2 mb-3">
                                        <label for="date_from"><?php echo $this->LANG['date_from']; ?>:</label>
                                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?= $dateFrom ?>">
                                    </div>
                                    
                                    <div class="col-md-2 mb-3">
                                        <label for="date_to"><?php echo $this->LANG['date_to']; ?>:</label>
                                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?= $dateTo ?>">
                                    </div>
                                    
                                    <div class="col-md-3 mb-3" style="padding-top: 28px;">
                                        <div class="d-flex flex-column">
                                            <button type="submit" class="btn btn-primary mb-2">
                                                <i class="fas fa-filter mr-1"></i> <?php echo $this->LANG['filter']; ?>
                                            </button>
                                            
                                            <?php if ($eventType || $dateFrom || $dateTo || $status !== ''): ?>
                                            <a href="addonmodules.php?module=zapcel&action=logs" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-times mr-1"></i> <?php echo $this->LANG['clear_filters']; ?>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Logs -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <?php if ($logs->count() > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="logsTable">
                                    <thead>
                                        <tr>
                                            <th width="150"><?php echo $this->LANG['date_time']; ?></th>
                                            <th width="180"><?php echo $this->LANG['event_type']; ?></th>
                                            <th><?php echo $this->LANG['message']; ?></th>
                                            <th width="130"><?php echo $this->LANG['client']; ?></th>
                                            <th width="80"><?php echo $this->LANG['status']; ?></th>
                                            <th width="100"><?php echo $this->LANG['actions']; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($log->created_at)) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $this->getLogTypeBadge($log->event_type) ?>">
                                                    <?= $this->getEventTypeDisplayName($log->event_type) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="log-message">
                                                    <?= htmlspecialchars(substr($log->message, 0, 100)) ?>
                                                    <?php if (strlen($log->message) > 100): ?>
                                                    ... <a href="#" class="view-full-log" data-logid="<?= $log->id ?>"><?php echo $this->LANG['view_more']; ?></a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($log->response): ?>
                                                    <?php
                                                        $responseData = json_decode($log->response, true);
                                                        // Se tiver api_response dentro, usa ele
                                                        if (isset($responseData['api_response'])) {
                                                            $responseData = $responseData['api_response'];
                                                        }
                                                        $responseSummary = '';
                                                        if (is_array($responseData)) {
                                                            if (isset($responseData['success'])) {
                                                                $responseSummary .= ' Sucesso: ' . $responseData['success'] ? 'Sucesso' : 'Erro';
                                                            }
                                                            if (isset($responseData['message_id'])) {
                                                                $responseSummary .= ' ID: ' . substr($responseData['message_id'], 0, 8) . '...';
                                                            }
                                                            if (isset($responseData['error'])) {
                                                                $responseSummary .= ' Erro: ' . substr($responseData['error'], 0, 50);
                                                            }
                                                        }
                                                    ?>
                                                    <?php if ($responseSummary): ?>
                                                        <small class="text-muted d-block mt-1">
                                                            <?php echo $this->LANG['response_log']; ?>: <?= htmlspecialchars($responseSummary) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($log->client_id): ?>
                                                <a href="clientssummary.php?userid=<?= $log->client_id ?>" target="_blank" title="<?= htmlspecialchars($log->firstname . ' ' . $log->lastname) ?>">
                                                    #<?= $log->client_id ?>
                                                </a>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($log->phone_number) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int)$log->success === 1): ?>
                                                    <span class="badge bg-success"><?php echo $this->LANG['success']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><?php echo $this->LANG['error']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary view-log-details" 
                                                            data-logid="<?= $log->id ?>" title="<?php echo $this->LANG['view_details']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger delete-log" 
                                                            data-logid="<?= $log->id ?>" title="<?php echo $this->LANG['delete']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginação -->
                            
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-list-alt fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted"><?php echo $this->LANG['no_logs_found']; ?></h4>
                                <p class="text-muted"><?php echo $this->LANG['no_logs_description']; ?></p>
                                
                                <?php if ($eventType || $dateFrom || $dateTo || $status !== ''): ?>
                                <a href="addonmodules.php?module=zapcel&action=logs" class="btn btn-primary">
                                    <i class="fas fa-times mr-1"></i> <?php echo $this->LANG['clear_filters']; ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Detalhes do Log -->
        <div class="modal fade" id="logDetailsModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title"><?php echo $this->LANG['log_details']; ?></h1>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body" id="logDetailsContent">
                        <!-- Conteúdo carregado via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">&times;</button>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            $(document).ready(function() {
                // Ver detalhes completos do log
                $('.view-full-log, .view-log-details').click(function(e) {
                    e.preventDefault();
                    var logId = $(this).data('logid');
                    
                    $.ajax({
                        url: 'addonmodules.php?module=zapcel&action=ajax',
                        type: 'POST',
                        data: {
                            subaction: 'get_log_details',
                            log_id: logId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $('#logDetailsContent').html(response.html);
                                $('#logDetailsModal').modal('show');
                            } else {
                                Swal.fire({
                                    title: '<?php echo $this->LANG['error']; ?>',
                                    text: response.error,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', xhr.responseText);
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });

                // Excluir log individual
                $('.delete-log').click(function() {
                    var logId = $(this).data('logid');
                    
                    Swal.fire({
                        title: '<?php echo $this->LANG['confirm_delete_log']; ?>',
                        text: '<?php echo $this->LANG['delete_log_warning']; ?>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<?php echo $this->LANG['delete']; ?>',
                        cancelButtonText: '<?php echo $this->LANG['cancel']; ?>',
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'addonmodules.php?module=zapcel&action=ajax',
                                type: 'POST',
                                data: {
                                    subaction: 'delete_log',
                                    log_id: logId
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['success']; ?>!',
                                            text: '<?php echo $this->LANG['log_deleted_success']; ?>',
                                            icon: 'success',
                                            confirmButtonText: 'OK'
                                        }).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['error']; ?>',
                                            text: response.error,
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('Erro AJAX:', xhr.responseText);
                                    Swal.fire({
                                        title: '<?php echo $this->LANG['error']; ?>',
                                        text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            });
                        }
                    });
                });

                // Limpar todos os logs
                $('#clearLogs').click(function() {
                    Swal.fire({
                        title: '<?php echo $this->LANG['confirm_clear_logs']; ?>',
                        text: '<?php echo $this->LANG['clear_logs_warning']; ?>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<?php echo $this->LANG['clear_logs']; ?>',
                        cancelButtonText: '<?php echo $this->LANG['cancel']; ?>',
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'addonmodules.php?module=zapcel&action=ajax',
                                type: 'POST',
                                data: {
                                    subaction: 'clear_logs'
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['logs_cleared']; ?>',
                                            text: '<?php echo $this->LANG['logs_cleared_success']; ?>!',
                                            icon: 'success',
                                            confirmButtonText: 'OK'
                                        }).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['error']; ?>',
                                            text: response.error,
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('Erro AJAX:', xhr.responseText);
                                    Swal.fire({
                                        title: '<?php echo $this->LANG['error']; ?>',
                                        text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            });
                        }
                    });
                });

                // Atualizar logs
                $('#refreshLogs').click(function() {
                    location.reload();
                });
            });
            $(document).ready(function() {
                $('#logsTable').DataTable({
                    order: [[5, 'desc']], // Ordena por data (última coluna) decrescente
                    pageLength: 25,
                    deferRender: true,  // ← ADICIONA RENDERIZAÇÃO LAZY
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                    },
                    columnDefs: [
                        { orderable: false, targets: [3] } // Desabilita ordenação na coluna "Mensagem"
                    ]
                });
            });
            </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Constrói URL de paginação com filtros
     */
    private function buildPaginationUrl($page)
    {
        $params = [
            'module' => 'zapcel',
            'action' => 'logs',
            'page' => $page
        ];

        // Adiciona filtros atuais
        $filters = ['type', 'date_from', 'date_to', 'status'];
        foreach ($filters as $filter) {
            if (!empty($_GET[$filter])) {
                $params[$filter] = $_GET[$filter];
            }
        }

        return 'addonmodules.php?' . http_build_query($params);
    }

    /**
     * Página de Configurações
     */
    private function settingsPage()
    {
        $settings = $this->getSettings();

        ob_start();
        ?>
        <div class="zapcel-admin-container">
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <i class="fas fa-cog mr-2"></i> 
                            <?php echo $this->LANG['zapcel_settings']; ?>
                        </h2>
                        <p class="text-muted"><?php echo $this->LANG['settings_subtitle']; ?></p>
                    </div>
                    <div class="col-md-4 text-right">
                        <a href="addonmodules.php?module=zapcel&action=dashboard" class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-arrow-left mr-1"></i> <?php echo $this->LANG['back']; ?>
                        </a>
                        <button class="btn btn-success" id="saveSettings">
                            <i class="fas fa-save mr-1"></i> <?php echo $this->LANG['save_settings']; ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['main_settings']; ?></h3>
                        </div>
                        <div class="card-body">
                            <form id="settingsForm">
                                <!-- Status do Módulo -->
                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['module_status']; ?></label>
                                    <select name="status" class="form-control">
                                        <option value="active" <?= ($settings['status'] ?? '') == 'active' ? 'selected' : '' ?>><?php echo $this->LANG['active']; ?></option>
                                        <option value="inactive" <?= ($settings['status'] ?? '') == 'inactive' ? 'selected' : '' ?>><?php echo $this->LANG['inactive']; ?></option>
                                    </select>
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['activate_deactivate_module']; ?>
                                    </small>
                                </div>

                                <!-- Credenciais API -->
                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['instance_id']; ?></label>
                                    <input type="text" name="zapcel_instance_id" class="form-control" 
                                           value="<?= $settings['zapcel_instance_id'] ?? '' ?>" 
                                           placeholder="<?php echo $this->LANG['enter_instance_id']; ?>">
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['instance_id_help']; ?>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['access_token']; ?></label>
                                    <input type="text" name="zapcel_access_token" class="form-control" 
                                           value="<?= $settings['zapcel_access_token'] ?? '' ?>" 
                                           placeholder="<?php echo $this->LANG['enter_access_token']; ?>">
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['access_token_help']; ?>
                                    </small>
                                </div>

                                <!-- Configurações de Envio -->
                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['message_delay']; ?></label>
                                    <input type="number" name="message_delay" class="form-control" 
                                           value="<?= $settings['message_delay'] ?? 2 ?>" min="1" max="10">
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['message_delay_help']; ?>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['max_attempts']; ?></label>
                                    <input type="number" name="max_attempts" class="form-control" 
                                           value="<?= $settings['max_attempts'] ?? 3 ?>" min="1" max="5">
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['max_attempts_help']; ?>
                                    </small>
                                </div>

                                <!-- Validação WhatsApp Obrigatória -->
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="zapcel_validation" value="1" 
                                                <?= ($settings['zapcel_validation'] ?? 0) ? 'checked' : '' ?>>
                                            <?php echo $this->LANG['enable_validation_system']; ?>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['enable_validation_system_help']; ?>
                                    </small>
                                </div>

                                <!-- Enviar apenas para validados -->
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="require_validation" value="1" 
                                                <?= ($settings['require_validation'] ?? 0) ? 'checked' : '' ?>>
                                            <?php echo $this->LANG['require_validation']; ?>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['require_validation_help']; ?>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['validation_template']; ?></label>
                                    <select name="validation_template" class="form-control">
                                        <option value=""><?php echo $this->LANG['select_template']; ?></option>
                                        <?php
                                        $templates = Capsule::table('mod_zapcel_templates')
                                            ->where('trigger_event', 'whatsapp_validation')
                                            ->get();
                                        foreach ($templates as $template):
                                        ?>
                                        <option value="<?= $template->id ?>" 
                                                <?= ($settings['validation_template'] ?? '') == $template->id ? 'selected' : '' ?>>
                                            <?= $template->name ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Configurações de Idioma -->
                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['module_language']; ?></label>
                                    <select name="language" class="form-control">
                                        <option value="portuguese" <?= ($settings['language'] ?? 'portuguese') == 'portuguese' ? 'selected' : '' ?>><?php echo $this->LANG['portuguese_brazil']; ?></option>
                                        <option value="english" <?= ($settings['language'] ?? '') == 'english' ? 'selected' : '' ?>><?php echo $this->LANG['english']; ?></option>
                                    </select>
                                </div>

                                <!-- Configurações de Log -->
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="enable_logging" value="1" <?= ($settings['enable_logging'] ?? 1) ? 'checked' : '' ?>>
                                            <?php echo $this->LANG['enable_logging']; ?>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['enable_logging_help']; ?>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['log_retention_days']; ?></label>
                                    <input type="number" name="log_retention_days" class="form-control" value="<?= $settings['log_retention_days'] ?? 30 ?>" min="1" max="365">
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['log_retention_days_help']; ?>
                                    </small>
                                </div>

                                <!-- Botão Flutuante WhatsApp -->
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="zapcel_floating_button" value="1" 
                                                <?= ($settings['zapcel_floating_button'] ?? 0) ? 'checked' : '' ?>>
                                            <?php echo $this->LANG['enable_floating_button']; ?>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['enable_floating_button_help']; ?>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['company_phone_number']; ?></label>
                                    <input type="text" name="zapcel_company_phone_full" class="form-control" 
                                        value="<?= $settings['zapcel_company_phone_full'] ?? '' ?>" 
                                        placeholder="Ex: 5511999999999"
                                        data-phone-number>
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['company_phone_number_help']; ?>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="zapcel_hide_mobile" value="1" 
                                                <?= ($settings['zapcel_hide_mobile'] ?? '0') == '1' ? 'checked' : '' ?>>
                                            <?php echo $this->LANG['hide_mobile_button']; ?>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['hide_mobile_button_help']; ?>
                                    </small>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Status da Conexão -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['connection_status']; ?></h3>
                        </div>
                        <div class="card-body text-center">
                            <?php
                                $connectionStatus = $this->testAPIConnection();
                                // Obtém o email do admin logado
                                $adminEmail = $_SESSION['adminid'] ? Capsule::table('tbladmins')
                                    ->where('id', $_SESSION['adminid'])
                                    ->value('email') : 'admin@localhost';
                            ?>
                            <div class="connection-status <?= $connectionStatus['success'] ? 'connected' : 'disconnected' ?>">
                                <i class="fas fa-<?= $connectionStatus['success'] ? 'check-circle' : 'times-circle' ?> fa-3x mb-3"></i>
                                <h4><?= $connectionStatus['success'] ? $this->LANG['connected'] : $this->LANG['disconnected'] ?></h4>
                                <p><?= $connectionStatus['message'] ?></p>
                            </div>
                            <div class="btn-group" role="group">
                                <button class="btn btn-info btn-sm" id="testConnection">
                                    <i class="fas fa-sync mr-1"></i> <?php echo $this->LANG['test_connection']; ?>
                                </button>
                                <a href="https://zap.hostcel.com.br/autologin.php?email=<?= urlencode($adminEmail) ?>" 
                                    class="btn btn-success btn-sm" 
                                    target="_blank"
                                    title="Zapcel Panel">
                                    <i class="fab fa-whatsapp mr-1"></i> Login Zapcel
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Informações da Conta -->

                    <!-- Ações Rápidas -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['quick_actions_settings']; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-warning" id="clearCache">
                                    <i class="fas fa-broom"></i> <?php echo $this->LANG['clear_cache']; ?>
                                </button>
                                <button class="btn btn-sm btn-outline-info" id="syncTemplates">
                                    <i class="fas fa-sync"></i> <?php echo $this->LANG['sync_templates']; ?>
                                </button>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-success" id="exportSettings">
                                        <i class="fas fa-download"></i> <?php echo $this->LANG['export']; ?>
                                    </button>
                                    <button class="btn btn-outline-primary" id="importSettings">
                                        <i class="fas fa-upload"></i> <?php echo $this->LANG['import']; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            $(document).ready(function() {
                // Salvar configurações
                $('#saveSettings').click(function() {
                    var formData = $('#settingsForm').serializeArray();
                    var dataObj = {};
                    $.each(formData, function(i, field) {
                        dataObj[field.name] = field.value;
                    });
                    
                    $.ajax({
                        url: 'addonmodules.php?module=zapcel&action=ajax',
                        type: 'POST',
                        data: $.extend({subaction: 'save_settings'}, dataObj),
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: '<?php echo $this->LANG['success']; ?>!',
                                    text: '<?php echo $this->LANG['settings_saved']; ?>!',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: '<?php echo $this->LANG['error']; ?>',
                                    text: response.error,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    location.reload();
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', xhr.responseText);
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });

                // Testar conexão
                $('#testConnection').click(function() {

                    Swal.fire({
                        title: 'Testando conexão...',
                        text: 'Aguarde...',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => Swal.showLoading()
                    });

                    $.ajax({
                        url: 'addonmodules.php?module=zapcel&action=ajax',
                        type: 'POST',
                        data: { subaction: 'test_connection' },
                        dataType: 'json',
                        success: function(response) {

                            Swal.fire({
                                title: response.success 
                                    ? '<?php echo $this->LANG['success']; ?>!' 
                                    : '<?php echo $this->LANG['warning']; ?>',
                                
                                text: response.success 
                                    ? (response.message ?? 'Conexão bem-sucedida.') 
                                    : (response.error ?? 'Falha ao testar a conexão.'),

                                icon: response.success ? 'success' : 'warning',
                                confirmButtonText: 'OK'
                            }).then(() => location.reload());
                        },

                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', xhr.responseText);
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });

                // Ações rápidas - Limpar Cache
                $('#clearCache').click(function() {
                    Swal.fire({
                        title: '<?php echo $this->LANG['confirm_clear_cache']; ?>?',
                        text: '<?php echo $this->LANG['clear_cache_warning']; ?>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<?php echo $this->LANG['clear_cache_templates']; ?>',
                        cancelButtonText: '<?php echo $this->LANG['cancel']; ?>',
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'addonmodules.php?module=zapcel&action=ajax',
                                type: 'POST',
                                data: {
                                    subaction: 'clear_templates_cache'
                                },
                                dataType: 'json',
                                success: function(response) {
                                    Swal.fire({
                                        title: response.success ? '<?php echo $this->LANG['success']; ?>!' : '<?php echo $this->LANG['warning']; ?>',
                                        text: response.message,
                                        icon: response.success ? 'success' : 'warning',
                                        confirmButtonText: 'OK'
                                    });
                                },
                                error: function(xhr, status, error) {
                                    console.error('Erro AJAX:', xhr.responseText);
                                    Swal.fire({
                                        title: '<?php echo $this->LANG['error']; ?>',
                                        text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            });
                        }
                    });
                });

                // Sincronizar Templates
                $('#syncTemplates').click(function() {
                    $.ajax({
                        url: 'addonmodules.php?module=zapcel&action=ajax',
                        type: 'POST',
                        data: {
                            subaction: 'sync_templates'
                        },
                        dataType: 'json',
                        success: function(response) {
                            Swal.fire({
                                title: response.success ? '<?php echo $this->LANG['success']; ?>!' : '<?php echo $this->LANG['warning']; ?>',
                                text: response.message,
                                icon: response.success ? 'success' : 'warning',
                                confirmButtonText: 'OK'
                            });
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro AJAX:', xhr.responseText);
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });

                // Exportar Configurações
                $('#exportSettings').click(function() {
                    Swal.fire({
                        title: '<?php echo $this->LANG['export_settings']; ?>?',
                        text: '<?php echo $this->LANG['export_settings_warning']; ?>',
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: '<?php echo $this->LANG['export']; ?>',
                        cancelButtonText: '<?php echo $this->LANG['cancel']; ?>',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'addonmodules.php?module=zapcel&action=ajax',
                                type: 'POST',
                                data: { 
                                    subaction: 'export_settings' 
                                },
                                dataType: 'json',
                                success: function(response) {
                                    console.log('Resposta export:', response);
                                    
                                    if (response.success && response.data) {
                                        const dataStr = JSON.stringify(response.data, null, 2);
                                        const blob = new Blob([dataStr], {type: 'application/json;charset=utf-8'});
                                        const url = URL.createObjectURL(blob);
                                        const a = document.createElement('a');
                                        a.href = url;
                                        a.download = response.filename || 'zapcel_backup.json';
                                        document.body.appendChild(a);
                                        a.click();
                                        document.body.removeChild(a);
                                        URL.revokeObjectURL(url);
                                        
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['success']; ?>!',
                                            text: '<?php echo $this->LANG['export_success']; ?>',
                                            icon: 'success',
                                            confirmButtonText: 'OK'
                                        });
                                    } else {
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['error']; ?>',
                                            text: response.error || zapcel_trans('export_settings_error'),
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('Erro AJAX:', xhr.responseText);
                                    Swal.fire({
                                        title: '<?php echo $this->LANG['error']; ?>',
                                        text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            });
                        }
                    });
                });

                // Botão Importar Configurações
                $('#importSettings').click(function() {
                    // Cria input file dinamicamente
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.accept = '.json';
                    fileInput.style.display = 'none';
                    
                    fileInput.onchange = function(e) {
                        const file = e.target.files[0];
                        if (!file) return;
                        
                        // Verifica se é arquivo JSON
                        if (!file.name.endsWith('.json')) {
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: '<?php echo $this->LANG['invalid_file_format']; ?>',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                            return;
                        }
                        
                        Swal.fire({
                            title: '<?php echo $this->LANG['import_settings']; ?>?',
                            text: '<?php echo $this->LANG['import_settings_warning']; ?>',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: '<?php echo $this->LANG['import']; ?>',
                            cancelButtonText: '<?php echo $this->LANG['cancel']; ?>',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const formData = new FormData();
                                formData.append('import_file', file);
                                formData.append('subaction', 'import_settings');
                                
                                $.ajax({
                                    url: 'addonmodules.php?module=zapcel&action=ajax',
                                    type: 'POST',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    dataType: 'json',
                                    success: function(response) {
                                        if (response.success) {
                                            Swal.fire({
                                                title: '<?php echo $this->LANG['success']; ?>!',
                                                text: response.message,
                                                icon: 'success',
                                                confirmButtonText: 'OK'
                                            }).then(() => {
                                                location.reload();
                                            });
                                        } else {
                                            Swal.fire({
                                                title: '<?php echo $this->LANG['error']; ?>',
                                                text: response.error,
                                                icon: 'error',
                                                confirmButtonText: 'OK'
                                            });
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('Erro AJAX:', xhr.responseText);
                                        Swal.fire({
                                            title: '<?php echo $this->LANG['error']; ?>',
                                            text: '<?php echo $this->LANG['connection_error']; ?>: ' + error,
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                });
                            }
                        });
                    };
                    
                    document.body.appendChild(fileInput);
                    fileInput.click();
                    document.body.removeChild(fileInput);
                });
            });
        </script>

        <style>
        .connection-status.connected {
            color: #28a745;
        }
        .connection-status.disconnected {
            color: #dc3545;
        }
        .account-info p {
            margin-bottom: 5px;
            font-size: 14px;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Página de Teste de Mensagem
     */
    private function testMessagePage()
    {
        $clients = Capsule::table('tblclients')
            ->select('id', 'firstname', 'lastname', 'phonenumber')
            ->orderBy('firstname', 'asc')
            ->get();

        ob_start();
        ?>
        <div class="zapcel-admin-container">
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <i class="fas fa-paper-plane mr-2"></i> 
                            <?php echo $this->LANG['test_whatsapp_message']; ?>
                        </h2>
                        <p class="text-muted"><?php echo $this->LANG['test_message_subtitle']; ?></p>
                    </div>
                    <div class="col-md-4 text-right">
                        <a href="addonmodules.php?module=zapcel&action=dashboard" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> <?php echo $this->LANG['back']; ?>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['configure_test_message']; ?></h3>
                        </div>
                        <div class="card-body">
                            <form id="testMessageForm">
                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['destination_client']; ?></label>
                                    <select name="client_id" class="form-control" required>
                                        <option value=""><?php echo $this->LANG['select_client']; ?></option>
                                        <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client->id ?>">
                                            <?= htmlspecialchars($client->firstname) ?> <?= htmlspecialchars($client->lastname) ?> 
                                            (<?= htmlspecialchars($client->phonenumber) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold"><?php echo $this->LANG['test_message']; ?></label>
                                    <textarea name="custom_message" class="form-control" rows="8" placeholder="<?php echo $this->LANG['enter_test_message']; ?>" id="customMessage" required></textarea>
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['available_variables_test']; ?>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="simulate_only" value="1" checked>
                                            <?php echo $this->LANG['simulate_only']; ?>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <?php echo $this->LANG['simulate_only_help']; ?>
                                    </small>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane mr-1"></i> <?php echo $this->LANG['send_test_message']; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Preview da Mensagem -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['message_preview']; ?></h3>
                        </div>
                        <div class="card-body">
                            <div id="messagePreview" class="message-preview">
                                <p class="text-muted text-center">
                                    <?php echo $this->LANG['select_template_or_type']; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Variáveis Disponíveis -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['available_variables_list']; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="variables-list">
                                <p class="text-muted mb-3"><?php echo $this->LANG['use_variables_below']; ?></p>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <code>{cliente}</code><br>
                                        <small class="text-muted"><?php echo $this->LANG['client_full_name']; ?></small>
                                    </li>
                                    <li class="mb-2">
                                        <code>{provedor}</code><br>
                                        <small class="text-muted"><?php echo $this->LANG['company_name']; ?></small>
                                    </li>
                                </ul>
                                <div class="alert alert-info mt-3">
                                    <small><i class="fas fa-info-circle"></i> <strong><?php echo $this->LANG['tip']; ?>:</strong> <?php echo $this->LANG['test_variables_tip']; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resultado do Teste -->
            <div class="row mt-4" id="testResult" style="display: none;">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $this->LANG['test_result']; ?></h3>
                        </div>
                        <div class="card-body" id="testResultContent">
                            <!-- Conteúdo carregado via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Atualizar preview quando mensagem for alterada
            $('#customMessage').on('input', function() {
                updateMessagePreview($(this).val());
            });

            // Enviar mensagem de teste
            $('#testMessageForm').submit(function(e) {
                e.preventDefault();
                var formData = $(this).serializeArray();
                var dataObj = {};
                $.each(formData, function(i, field) {
                    dataObj[field.name] = field.value;
                });
                
                $.ajax({
                    url: 'addonmodules.php?module=zapcel&action=ajax',
                    type: 'POST',
                    data: $.extend({subaction: 'send_test_message'}, dataObj),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#testResultContent').html(response.html);
                            $('#testResult').show();
                            $('html, body').animate({
                                scrollTop: $('#testResult').offset().top
                            }, 500);
                        } else {
                            Swal.fire({
                                title: '<?php echo $this->LANG['error']; ?>',
                                text: response.error || '<?php echo $this->LANG['unknown_error']; ?>',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro AJAX:', xhr.responseText);
                        Swal.fire({
                            title: '<?php echo $this->LANG['error']; ?>',
                            text: '<?php echo $this->LANG['error_sending_test_message']; ?>: ' + error,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            });

            function updateMessagePreview(content) {
                if (!content.trim()) {
                    $('#messagePreview').html('<p class="text-muted text-center"><?php echo $this->LANG['enter_message_to_preview']; ?></p>');
                    return;
                }

                // Simular processamento básico da mensagem
                var preview = content.replace(/\n/g, '<br>');

                $('#messagePreview').html('<div class="whatsapp-message">' + preview + '</div>');
            }
        });
        </script>

        <style>
        .message-preview {
            min-height: 200px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fa;
        }
        .whatsapp-message {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.5;
        }
        .variables-list h6 {
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: bold;
            color: #495057;
        }
        .variables-list code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Página de Auto Login - VERSÃO CORRIGIDA
     * Copiar esta função inteira para modules/addons/zapcel/admin/index.php
     */
    private function autologinPage()
    {
        require_once __DIR__ . '/../api/AutoLogin.php';
        
        $autoLogin = new \WHMCS\Module\Addon\Zapcel\Api\AutoLogin();
        $stats = $autoLogin->getStatistics();
        
        // Busca tokens
        $tokens = Capsule::table('mod_zapcel_autologin as a')
            ->join('tblclients as c', 'a.client_id', '=', 'c.id')
            ->select(
                'a.*',
                'c.firstname',
                'c.lastname',
                'c.email'
            )
            ->orderBy('a.created_at', 'desc')
            ->limit(100)
            ->get();
        
        $today = date('Y-m-d');

        $todayActive = Capsule::table('mod_zapcel_autologin')
            ->whereDate('created_at', $today)
            ->where('status', 'active')
            ->count();

        $todayExpired = Capsule::table('mod_zapcel_autologin')
            ->whereDate('expires_at', $today)
            ->count();

        $todayClicks = (int) (Capsule::table('mod_zapcel_autologin_access')
            ->whereDate('last_access', $today)
            ->sum('access_count') ?? 0);

        $todayTotal = Capsule::table('mod_zapcel_autologin')
            ->whereDate('created_at', $today)
            ->count();

        ob_start();
        ?>
        <div class="zapcel-admin-container">
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <i class="fas fa-key mr-2"></i> 
                            <?php echo $this->LANG['autologin_title'] ?? 'Auto Login'; ?>
                        </h2>
                        <p class="text-muted"><?php echo $this->LANG['autologin_subtitle'] ?? 'Gerencie tokens de acesso direto para faturas e tickets'; ?></p>
                    </div>
                    <div class="col-md-4 text-right">
                        <a href="addonmodules.php?module=zapcel&action=dashboard" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> <?php echo $this->LANG['back'] ?? 'Voltar'; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Cards de Estatísticas -->
            <div class="row">
                <!-- Tokens Ativos -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-success">
                            <i class="fas fa-check-circle"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['active_tokens'] ?? 'Tokens Ativos'; ?></span>
                            <span class="info-box-number"><?= number_format($stats['active']) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?= $this->LANG['today'] ?? 'Hoje' ?>: <?= number_format($todayActive) ?>
                            </small>
                            <small class="text-muted"><?= $this->LANG['active_tokens_desc'] ?? 'Tokens válidos e utilizáveis.' ?></small>
                        </div>
                    </div>
                </div>

                <!-- Tokens Expirados -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning">
                            <i class="fas fa-clock"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['expired_tokens'] ?? 'Tokens Expirados'; ?></span>
                            <span class="info-box-number"><?= number_format($stats['expired']) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-warning" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?= $this->LANG['today'] ?? 'Hoje' ?>: <?= number_format($todayExpired) ?>
                            </small>
                            <small class="text-muted"><?= $this->LANG['expired_tokens_desc'] ?? 'Tokens que perderam validade.' ?></small>
                        </div>
                    </div>
                </div>

                <!-- Total de Cliques -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-info">
                            <i class="fas fa-mouse-pointer"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['total_clicks'] ?? 'Total de Cliques'; ?></span>
                            <span class="info-box-number"><?= number_format($stats['total_accesses']) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-info" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?= $this->LANG['today'] ?? 'Hoje' ?>: <?= number_format($todayClicks) ?>
                            </small>
                            <small class="text-muted"><?= $this->LANG['total_clicks_desc'] ?? 'Acessos gerados pelos tokens.' ?></small>
                        </div>
                    </div>
                </div>

                <!-- Total de Tokens -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary">
                            <i class="fas fa-link"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['total_tokens'] ?? 'Total de Tokens'; ?></span>
                            <span class="info-box-number"><?= number_format($stats['total']) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-primary" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?= $this->LANG['today'] ?? 'Hoje' ?>: <?= number_format($todayTotal) ?>
                            </small>
                            <small class="text-muted"><?= $this->LANG['total_tokens_desc'] ?? 'Quantidade total gerada.' ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Tokens -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title mb-0"><?php echo $this->LANG['tokens_history'] ?? 'Histórico de Tokens'; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="autologinTable">
                                    <thead>
                                        <tr>
                                            <th><?php echo $this->LANG['client'] ?? 'Cliente'; ?></th>
                                            <th><?php echo $this->LANG['type'] ?? 'Tipo'; ?></th>
                                            <th><?php echo $this->LANG['target'] ?? 'Alvo'; ?></th>
                                            <th><?php echo $this->LANG['token'] ?? 'Token'; ?></th>
                                            <th><?php echo $this->LANG['created_at'] ?? 'Criado em'; ?></th>
                                            <th><?php echo $this->LANG['expires_at'] ?? 'Expira em'; ?></th>
                                            <th><?php echo $this->LANG['clicks'] ?? 'Cliques'; ?></th>
                                            <th><?php echo $this->LANG['last_access'] ?? 'Último Acesso'; ?></th>
                                            <th><?php echo $this->LANG['ip_address'] ?? 'IP'; ?></th>
                                            <th><?php echo $this->LANG['status'] ?? 'Status'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tokens as $token): ?>
                                        <?php
                                            // Determina status baseado apenas na expiração
                                            if (strtotime($token->expires_at) < time()) {
                                                $status = 'expired';
                                                $statusLabel = $this->LANG['expired'] ?? 'Expirado';
                                                $statusClass = 'secondary';
                                            } else {
                                                $status = 'active';
                                                $statusLabel = $this->LANG['active'] ?? 'Ativo';
                                                $statusClass = 'success';
                                            }
                                            
                                            // Tipo formatado
                                            $typeLabel = $token->target_type === 'invoice' 
                                                ? ($this->LANG['invoice'] ?? 'Fatura')
                                                : ($this->LANG['ticket'] ?? 'Ticket');
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><a style="text-decoration: none;" href="clientssummary.php?userid=<?= $token->client_id ?>" target="_blank" title="<?= htmlspecialchars($token->firstname . ' ' . $token->lastname) ?>"><?= htmlspecialchars($token->firstname) ?> <?= htmlspecialchars($token->lastname) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($token->email) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $token->target_type === 'invoice' ? 'info' : 'warning' ?>">
                                                    <i class="fas fa-<?= $token->target_type === 'invoice' ? 'file-invoice' : 'ticket-alt' ?>"></i>
                                                    <?= $typeLabel ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong>#<?= $token->target_id ?></strong>
                                            </td>
                                            <td>
                                                <code class="token-display" title="<?= htmlspecialchars($token->token) ?>">
                                                    <?= substr($token->token, 0, 12) ?>...
                                                </code>
                                                <button class="btn btn-xs btn-link copy-token" data-token="<?= htmlspecialchars($token->token) ?>" title="Copiar token completo">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <small><?= date('d/m/Y H:i', strtotime($token->created_at)) ?></small>
                                            </td>
                                            <td>
                                                <small class="<?= $status === 'expired' ? 'text-danger' : '' ?>">
                                                    <?= date('d/m/Y H:i', strtotime($token->expires_at)) ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $token->access_count > 0 ? 'success' : 'secondary' ?>">
                                                    <?= number_format($token->access_count) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($token->last_access_at): ?>
                                                    <small class="text-success">
                                                        <?= date('d/m/Y H:i', strtotime($token->last_access_at)) ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($token->last_ip): ?>
                                                    <small><code><?= htmlspecialchars($token->last_ip) ?></code></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <?= $statusLabel ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // DataTable
            $('#autologinTable').DataTable({
                order: [[4, 'desc']],
                pageLength: 25,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                }
            });

            // Copiar token
            $('.copy-token').on('click', function() {
                var token = $(this).data('token');
                var tempInput = $('<input>');
                $('body').append(tempInput);
                tempInput.val(token).select();
                document.execCommand('copy');
                tempInput.remove();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Token copiado!',
                    text: zapcel_trans('token_copied_to_clipboard'),
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * FUNÇÃO campaignsPage() CORRETA
     * 
     * SEGUINDO FIELMENTE O PADRÃO DO AUTOLOGIN:
     * - Header-container igual
     * - Info-boxes com estatísticas
     * - Tabela com nome + quantidade abaixo
     * - Gráfico simples (Chart.js inline)
     * - Botões de ação
     */
    
    private function campaignsPage()
    {
        // Busca estatísticas gerais
        $stats = [
            'total' => Capsule::table('mod_zapcel_campaigns')->count(),
            'active' => Capsule::table('mod_zapcel_campaigns')->where('status', 'active')->count(),
            'paused' => Capsule::table('mod_zapcel_campaigns')->where('status', 'paused')->count(),
            'finished' => Capsule::table('mod_zapcel_campaigns')->where('status', 'finished')->count(),
        ];
        
        // Busca campanhas
        $campaigns = Capsule::table('mod_zapcel_campaigns')
            ->orderBy('id', 'desc')
            ->get();
        
        $today = date('Y-m-d');
        
        $todayActive = Capsule::table('mod_zapcel_campaigns')
            ->whereDate('created_at', $today)
            ->where('status', 'active')
            ->count();
        
        $todaySent = Capsule::table('mod_zapcel_campaign_queue')
            ->whereDate('sent_at', $today)
            ->where('status', 'sent')
            ->count();
        
        $todayTotal = Capsule::table('mod_zapcel_campaigns')
            ->whereDate('created_at', $today)
            ->count();
        
        ob_start();
        ?>
        <div class="zapcel-admin-container">
            <div class="header-container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <i class="fas fa-bullhorn mr-2"></i> 
                            <?php echo $this->LANG['campaigns_title'] ?? 'Campanhas de Marketing'; ?>
                        </h2>
                        <p class="text-muted"><?php echo $this->LANG['campaigns_subtitle'] ?? 'Gerencie campanhas de WhatsApp para clientes do WHMCS'; ?></p>
                    </div>
                    <div class="col-md-4 text-right">
                        <a href="addonmodules.php?module=zapcel&action=dashboard" class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-arrow-left mr-1"></i> <?php echo $this->LANG['back'] ?? 'Voltar'; ?>
                        </a>
                        <button class="btn btn-success" onclick="openCampaignForm(0)">
                            <i class="fas fa-plus mr-1"></i> <?php echo $this->LANG['new_campaign'] ?? 'Nova Campanha'; ?>
                        </button>
                    </div>
                </div>
            </div>
    
            <!-- Cards de Estatísticas (IGUAL AO AUTOLOGIN) -->
            <div class="row">
                <!-- Campanhas Ativas -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-success">
                            <i class="fas fa-play-circle"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['active_campaigns'] ?? 'Campanhas Ativas'; ?></span>
                            <span class="info-box-number"><?= number_format($stats['active']) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?= $this->LANG['today'] ?? 'Hoje' ?>: <?= number_format($todayActive) ?>
                            </small>
                            <small class="text-muted"><?= $this->LANG['active_campaigns_desc'] ?? 'Campanhas em execução.' ?></small>
                        </div>
                    </div>
                </div>
    
                <!-- Campanhas Pausadas -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning">
                            <i class="fas fa-pause-circle"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['paused_campaigns'] ?? 'Campanhas Pausadas'; ?></span>
                            <span class="info-box-number"><?= number_format($stats['paused']) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-warning" style="width: 100%"></div>
                            </div>
                            <small class="text-muted"><?= $this->LANG['paused_campaigns_desc'] ?? 'Campanhas temporariamente pausadas.' ?></small>
                        </div>
                    </div>
                </div>
    
                <!-- Mensagens Enviadas Hoje -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-info">
                            <i class="fas fa-paper-plane"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['sent_today'] ?? 'Enviadas Hoje'; ?></span>
                            <span class="info-box-number"><?= number_format($todaySent) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-info" style="width: 100%"></div>
                            </div>
                            <small class="text-muted"><?= $this->LANG['sent_today_desc'] ?? 'Mensagens enviadas hoje.' ?></small>
                        </div>
                    </div>
                </div>
    
                <!-- Total de Campanhas -->
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary">
                            <i class="fas fa-list"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo $this->LANG['total_campaigns'] ?? 'Total de Campanhas'; ?></span>
                            <span class="info-box-number"><?= number_format($stats['total']) ?></span>
                            <div class="progress mt-2" style="height: 4px; margin-bottom: 8px;">
                                <div class="progress-bar bg-primary" style="width: 100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <?= $this->LANG['today'] ?? 'Hoje' ?>: <?= number_format($todayTotal) ?>
                            </small>
                            <small class="text-muted"><?= $this->LANG['total_campaigns_desc'] ?? 'Todas as campanhas criadas.' ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <style>
                /* ===== ZAPCEL – CAMPANHAS ===== */
                .zapcel-campaigns .badge-status-draft {
                    background-color: #6c757d;
                }
                
                .zapcel-campaigns .badge-status-active {
                    background-color: #28a745;
                }
                
                .zapcel-campaigns .badge-status-paused {
                    background-color: #ffc107;
                    color: #000;
                }
                
                .zapcel-campaigns .badge-status-finished {
                    background-color: #17a2b8;
                }
                
                .zapcel-campaigns .badge-status-scheduled {
                    background-color: #007bff;
                }
                
                /* contadores */
                .zapcel-campaigns .badge-sent {
                    background-color: #28a745;
                }
                
                .zapcel-campaigns .badge-pending {
                    background-color: #ffc107;
                    color: #000;
                }
                
                .zapcel-campaigns .badge-failed {
                    background-color: #dc3545;
                }
                .zapcel-campaigns .zapcel-progress{
                  width: 150px;
                  height: 12px;
                  background: #e9ecef;
                  border-radius: 999px;
                  overflow: hidden;
                  display: flex;
                  box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);
                }
                
                .zapcel-campaigns .zapcel-progress-sent{
                  height: 100%;
                  background: #28a745;
                }
                
                .zapcel-campaigns .zapcel-progress-failed{
                  height: 100%;
                  background: #dc3545;
                }
                
                .zapcel-campaigns .zapcel-progress-label{
                  font-size: 11px;
                  color: #6c757d;
                  margin-top: 4px;
                }
                
                /* ===== STATUS DAS CAMPANHAS (ISOLADO ZAPCEL) ===== */
                .badge-status-secondary {
                    background-color: #6c757d;
                    color: #fff;
                }
                
                .badge-status-success {
                    background-color: #28a745;
                    color: #fff;
                }
                
                .badge-status-warning {
                    background-color: #ffc107;
                    color: #212529;
                }
                
                .badge-status-info {
                    background-color: #17a2b8;
                    color: #fff;
                }
                
                .badge-status-primary {
                    background-color: #007bff;
                    color: #fff;
                }
                
                /* opcional: ajuste visual */
                .badge-status-secondary,
                .badge-status-success,
                .badge-status-warning,
                .badge-status-info,
                .badge-status-primary {
                    font-size: 12px;
                    padding: 5px 9px;
                    border-radius: 6px;
                    font-weight: 600;
                }
            </style>
            <!-- Tabela de Campanhas (IGUAL AO AUTOLOGIN) -->
            <div class="card zapcel-campaigns">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list mr-2"></i> <?php echo $this->LANG['campaigns_list'] ?? 'Lista de Campanhas'; ?>
                    </h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <?php if ($campaigns->isEmpty()): ?>
                        <div class="alert alert-info text-center m-3">
                            <i class="fas fa-info-circle mr-2"></i>
                            <?php echo $this->LANG['no_campaigns_found'] ?? 'Nenhuma campanha encontrada.'; ?>
                            <button class="btn btn-sm btn-info ml-3" onclick="openCampaignForm(0)">
                                <i class="fas fa-plus mr-1"></i> <?php echo $this->LANG['create_first_campaign'] ?? 'Criar primeira campanha'; ?>
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?php echo $this->LANG['campaign_name'] ?? 'Nome da Campanha'; ?></th>
                                    <th class="text-center"><?php echo $this->LANG['status'] ?? 'Status'; ?></th>
                                    <th class="text-center"><?php echo $this->LANG['proxima_acao'] ?? 'Próxima Ação'; ?></th>
                                    <th class="text-center"><?php echo $this->LANG['sent'] ?? 'Enviadas'; ?></th>
                                    <th class="text-center"><?php echo $this->LANG['pending'] ?? 'Pendentes'; ?></th>
                                    <th class="text-center"><?php echo $this->LANG['failed'] ?? 'Fracassadas'; ?></th>
                                    <th class="text-center"><?php echo $this->LANG['progress'] ?? 'Progresso'; ?></th>
                                    <th class="text-center"><?php echo $this->LANG['actions'] ?? 'Ações'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): 
                                    $successRate = $campaign->total_contacts > 0 
                                        ? round(($campaign->sent_count / $campaign->total_contacts) * 100, 1) 
                                        : 0;
                                    
                                    $statusColors = [
                                        'draft' => 'secondary',
                                        'active' => 'success',
                                        'paused' => 'warning',
                                        'finished' => 'info',
                                        'scheduled' => 'primary'
                                    ];
                                    $statusColor = $statusColors[$campaign->status] ?? 'secondary';
                                    $nowTs = time();
                                    $scheduleTs = !empty($campaign->schedule_start) ? strtotime($campaign->schedule_start) : 0;
                                    $isFuture = ($scheduleTs && $scheduleTs > $nowTs);
                                    
                                    $isFinished = ($campaign->status === 'finished');
                                    
                                    // Botão principal (toggle)
                                    $toggleIcon  = 'fa-play';
                                    $toggleClass = 'btn-success';
                                    $toggleTitle = $this->LANG['start'] ?? 'Iniciar';
                                    
                                    if ($campaign->status === 'active') {
                                        $toggleIcon  = 'fa-pause';
                                        $toggleClass = 'btn-warning';
                                        $toggleTitle = $this->LANG['pause'] ?? 'Pausar';
                                    } elseif ($campaign->status === 'paused') {
                                        $toggleIcon  = $isFuture ? 'fa-clock' : 'fa-play';
                                        $toggleClass = $isFuture ? 'btn-primary' : 'btn-success';
                                        $toggleTitle = $isFuture ? ($this->LANG['schedule'] ?? 'Agendar') : ($this->LANG['resume'] ?? 'Retomar');
                                    } elseif ($campaign->status === 'scheduled') {
                                        $toggleIcon  = 'fa-ban';
                                        $toggleClass = 'btn-secondary';
                                        $toggleTitle = $this->LANG['cancel_schedule'] ?? 'Cancelar agendamento';
                                    } else { // draft (ou qualquer outro)
                                        $toggleIcon  = $isFuture ? 'fa-clock' : 'fa-play';
                                        $toggleClass = $isFuture ? 'btn-primary' : 'btn-success';
                                        $toggleTitle = $isFuture ? ($this->LANG['schedule'] ?? 'Agendar') : ($this->LANG['start'] ?? 'Iniciar');
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($campaign->name) ?></strong>
                                        <small class="text-muted ml-2 align-middle">
                                            <i class="fas fa-users mr-1"></i>
                                            <?= number_format($campaign->total_contacts) ?> <?php echo $this->LANG['contacts'] ?? 'contatos'; ?>
                                        </small>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge badge-status-<?= $statusColor ?>">
                                            <?= $this->LANG['status_' . $campaign->status] ?? ucfirst($campaign->status) ?>
                                        </span>
                                    </td>
                                    <td class="text-center" style="width: 140px;">
                                        <?php if ($campaign->status === 'scheduled' && !empty($campaign->schedule_start)): ?>
                                            <span class="zapcel-schedule-date">
                                                <?= date('d/m/Y H:i', strtotime($campaign->schedule_start)) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="width: 80px;">
                                        <span class="badge badge-sent"><?= number_format($campaign->sent_count) ?></span>
                                    </td>
                                    <td class="text-center" style="width: 80px;">
                                        <span class="badge badge-pending"><?= number_format($campaign->pending_count) ?></span>
                                    </td>
                                    <td class="text-center" style="width: 80px;">
                                        <span class="badge badge-failed"><?= number_format($campaign->failed_count) ?></span>
                                    </td>
                                    
                                    <td class="text-center" style="width: 150px;">
                                        <?php
                                            $sent   = (int)$campaign->sent_count;
                                            $failed = (int)$campaign->failed_count;
                                            $processed = $sent + $failed;
                                            
                                            $sentPct = $processed > 0 ? round(($sent / $processed) * 100, 1) : 0;
                                            $failPct = $processed > 0 ? round(($failed / $processed) * 100, 1) : 0;
                                        ?>
                                        
                                        <div class="zapcel-progress"
                                             title="Enviadas: <?= $sent ?> | Falhas: <?= $failed ?> | Processadas: <?= $processed ?>">
                                          <div class="zapcel-progress-sent" style="width: <?= $sentPct ?>%"></div>
                                          <div class="zapcel-progress-failed" style="width: <?= $failPct ?>%"></div>
                                        </div>
                                        
                                        <div class="zapcel-progress-label">
                                          <?= $processed > 0 ? ($sentPct . '% enviadas / ' . $failPct . '% falhas') : '0% (sem envios)' ?>
                                        </div>
                                    </td>
                                    <td class="text-center" style="width: 220px;">
                                      <div class="btn-group">
                                    
                                        <!-- BOTÃO PRINCIPAL: iniciar/pausar/agendar/cancelar agendamento -->
                                        <?php if ($isFinished): ?>
                                            <button class="btn btn-sm btn-info"
                                                    onclick="resetCampaign(<?= (int)$campaign->id ?>)"
                                                    title="<?php echo $this->LANG['reset'] ?? 'Reiniciar'; ?>">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm <?= $toggleClass ?>"
                                                    onclick="toggleCampaign(<?= (int)$campaign->id ?>)"
                                                    title="<?= htmlspecialchars($toggleTitle) ?>">
                                                <i class="fas <?= $toggleIcon ?>"></i>
                                            </button>
                                        <?php endif; ?>
                                    
                                        <!-- EDITAR -->
                                        <button class="btn btn-sm btn-primary"
                                                onclick="openCampaignForm(<?= (int)$campaign->id ?>)"
                                                title="<?php echo $this->LANG['edit'] ?? 'Editar'; ?>">
                                          <i class="fas fa-edit"></i>
                                        </button>
                                    
                                        <!-- ATIVIDADE -->
                                        <button class="btn btn-sm btn-success"
                                                onclick="downloadActivity(<?= (int)$campaign->id ?>)"
                                                title="<?php echo $this->LANG['activity'] ?? 'Atividade'; ?>">
                                          <i class="fas fa-chart-line"></i>
                                        </button>
                                    
                                        <!-- DELETAR -->
                                        <button class="btn btn-sm btn-danger"
                                                onclick="deleteCampaign(<?= (int)$campaign->id ?>)"
                                                title="<?php echo $this->LANG['delete'] ?? 'Deletar'; ?>">
                                          <i class="fas fa-trash"></i>
                                        </button>
                                    
                                      </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    
        <!-- Modal para Formulário (será carregado via AJAX) -->
        <div class="modal fade" id="campaignFormModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title" id="campaignFormModalLabel"><?php echo $this->LANG['campaign_form_title'] ?? 'Formulário de Campanha'; ?></h1>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" id="campaignFormContent">
                        <!-- Conteúdo carregado via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $this->LANG['cancel'] ?? 'Cancelar'; ?></button>
                        <button type="button" class="btn btn-primary" onclick="saveCampaign()"><?php echo $this->LANG['save'] ?? 'Salvar'; ?></button>
                    </div>
                </div>
            </div>
        </div>
    

<script>
function openCampaignForm(campaignId) {
    campaignId = campaignId || 0;
    
    $.ajax({
        url: 'addonmodules.php?module=zapcel&action=ajax',
        type: 'POST',
        data: {
            subaction: 'get_campaign_form',
            campaign_id: campaignId
        },
        dataType: 'json',
        success: function(response) {
            console.log('Response:', response);
            
            if (response.status === 'success') {
                $('#campaignFormContent').html(response.html);
                $('#campaignFormModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: response.message || 'Erro ao carregar formulário'
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.responseText);
            Swal.fire({
                icon: 'error',
                title: 'Erro de Comunicação',
                text: 'Erro ao conectar com o servidor: ' + error,
                footer: '<pre>' + xhr.responseText.substring(0, 500) + '</pre>'
            });
        }
    });
}

function saveCampaign() {
    const formData = $('#campaignForm').serializeArray();
    formData.push({ name: 'subaction', value: 'save_campaign' });

    $.ajax({
        url: 'addonmodules.php?module=zapcel&action=ajax',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function (response) {
            const ok = (response && (response.success === true || response.status === 'success'));
            const msg = (response && (response.message || response.error)) ? (response.message || response.error) : 'Resposta inválida do servidor';

            if (ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: msg
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: msg || 'Erro ao salvar campanha'
                });
            }
        },
        error: function (xhr, status, error) {
            console.error('Erro AJAX:', xhr.responseText);

            // mostra o início da resposta REAL (isso revela o lixo que está quebrando o JSON)
            let raw = (xhr && xhr.responseText) ? xhr.responseText.trim() : '';
            raw = raw.substring(0, 300);

            Swal.fire({
                icon: 'error',
                title: 'Erro de Comunicação',
                text: 'Erro ao salvar: ' + error + (raw ? (' | Resp: ' + raw) : '')
            });
        }
    });
}


function resetCampaign(campaignId) {
    Swal.fire({
        title: 'Confirmar Reset',
        text: 'Isso irá zerar os contadores e re-enfileirar a campanha. Continuar?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, resetar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'addonmodules.php?module=zapcel&action=ajax',
                type: 'POST',
                data: {
                    subaction: 'reset_campaign',
                    campaign_id: campaignId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success === true) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: response.message
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: response.message
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao resetar: ' + error
                    });
                }
            });
        }
    });
}

function deleteCampaign(campaignId) {
    Swal.fire({
        title: 'Confirmar Exclusão',
        text: 'Isso irá deletar a campanha e todos os registros relacionados. Continuar?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, deletar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'addonmodules.php?module=zapcel&action=ajax',
                type: 'POST',
                data: {
                    subaction: 'delete_campaign',
                    campaign_id: campaignId
                },
                dataType: 'json',
                success: function(response) {
                    const ok = (response && (response.success === true || response.success === 'success'));
                    const msg = (response && (response.message || response.error)) ? (response.message || response.error) : 'Resposta inválida do servidor.';

                    if (ok) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: msg
                        }).then(() => {
                            // Remove a linha da campanha sem recarregar a página
                            const $btn = $('button[onclick*="deleteCampaign(' + campaignId + '"]');
                            const $row = $btn.closest('tr');

                            if ($row.length) {
                                $row.fadeOut(200, function () { $(this).remove(); });
                            } else {
                                // fallback caso não encontre a linha
                                window.location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: msg
                        });
                    }
                },
                error: function(xhr) {
                    const msg =
                        (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ||
                        (xhr && xhr.responseText) ||
                        'Erro ao deletar.';
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: msg
                    });
                }
            });
        }
    });
}


function toggleCampaign(campaignId) {
    $.ajax({
        url: 'addonmodules.php?module=zapcel&action=ajax',
        type: 'POST',
        data: {
            subaction: 'toggle_campaign',
            campaign_id: campaignId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                window.location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: response.message
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao alterar status: ' + error
            });
        }
    });
}

function downloadActivity(campaignId) {
    $.ajax({
        url: 'addonmodules.php?module=zapcel&action=ajax',
        type: 'POST',
        data: {
            subaction: 'get_campaign_activity',
            campaign_id: campaignId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Cria blob e faz download
                const blob = new Blob([response.content], { type: 'text/plain' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = response.filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: response.message
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao gerar relatório: ' + error
            });
        }
    });
}
</script>
    
        <?php
        return ob_get_clean();
    }

    /**
     * Manipula requisições AJAX
     */
    private function handleAjax()
    {
        // Limpa qualquer output anterior
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json');
        $subaction = $_POST['subaction'] ?? '';
        
        switch ($subaction) {
            case 'deactivate_template':
                return $this->ajaxDeactivateTemplate();
            case 'activate_template':
                return $this->ajaxActivateTemplate();
            case 'delete_template':
                return $this->ajaxDeleteTemplate();
            case 'create_template':
                return $this->ajaxCreateTemplate();
            case 'resend_validation':
                return $this->ajaxResendValidation();
            case 'get_validation_details':
                return $this->ajaxGetValidationDetails();
            case 'reset_validation':
                return $this->ajaxResetValidation();
            case 'send_pending_validations':
                return $this->ajaxSendPendingValidations();
            case 'install_gateway':
                return $this->ajaxInstallGateway();
            case 'toggle_gateway':
                return $this->ajaxToggleGateway();
            case 'remove_gateway':
                return $this->ajaxRemoveGateway();
            case 'get_log_details':
                return $this->ajaxGetLogDetails();
            case 'delete_log':
                return $this->ajaxDeleteLog();
            case 'clear_logs':
                return $this->ajaxClearLogs();
            case 'save_settings':
                return $this->ajaxSaveSettings();
            case 'test_connection':
                return $this->ajaxTestConnection();
            case 'clear_cache':
                return $this->ajaxClearCache();
            case 'sync_templates':
                return $this->ajaxSyncTemplates();
            case 'export_settings':
                return $this->ajaxExportSettings();
            case 'import_settings':
                return $this->ajaxImportSettings();
            case 'send_test_message':
                return $this->ajaxSendTestMessage();
            case 'update_template':
                return $this->ajaxUpdateTemplate();
            case 'send_invoice_reminder':
                return $this->ajaxSendInvoiceReminder();
            case 'clear_templates_cache':
                return $this->ajaxClearTemplatesCache();
            case 'set_active_gateway':
                return $this->ajaxSetActiveGateway(); 
            case 'get_campaign_form':
                return $this->ajaxGetCampaignForm();
            case 'save_campaign':
                return $this->ajaxSaveCampaign();
            case 'reset_campaign':
                return $this->ajaxResetCampaign();
            case 'delete_campaign':
                return $this->ajaxDeleteCampaign();
            case 'toggle_campaign':
                return $this->ajaxToggleCampaign();
            case 'get_campaign_activity':
                return $this->ajaxGetCampaignActivity();
            default:
                return json_encode(['success' => false, 'error' => $this->LANG['unrecognized_action']]);
        }
    }

    private function ajaxExportSettings() {
        try {
            $settings = $this->getSettings();
            
            // Remove dados sensíveis
            unset($settings['zapcel_access_token']);
            unset($settings['zapcel_instance_id']);
            
            $exportData = [
                'module' => 'zapcel',
                'version' => '2.1.0',
                'export_date' => date('Y-m-d H:i:s'),
                'settings' => $settings,
                'templates_count' => Capsule::table('mod_zapcel_templates')->count(),
                'statistics' => [
                    'total_messages' => Capsule::table('mod_zapcel_logs')
                        ->where(function($query) {
                            $query->where('event_type', 'NOT LIKE', 'debug_%')
                                  ->where('event_type', '!=', 'gateway_manager_debug')
                                  ->where('event_type', '!=', 'system_log');
                        })
                        ->whereIn('success', [0, 1])
                        ->count(),
                    'active_templates' => Capsule::table('mod_zapcel_templates')->where('active', 1)->count(),
                ]
            ];
            
            // Log da ação
            $adminName = $_SESSION['adminid'] ? Capsule::table('tbladmins')->where('id', $_SESSION['adminid'])->value('username') : 'Unknown';
            logActivity("Zapcel: " . $this->LANG['settings_exported_by_admin'] . " ({$adminName})");
            
            return json_encode([
                'success' => true,
                'filename' => 'zapcel_backup_' . date('Y-m-d_H-i-s') . '.json',
                'data' => $exportData
            ]);
            
        } catch (\Throwable $e) {
            return json_encode([
                'success' => false, 
                'error' => $e->getMessage()
            ]);
        }
    }

    private function ajaxImportSettings() {
        try {
            if (!isset($_FILES['import_file'])) {
                return json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado']);
            }
            
            $fileContent = file_get_contents($_FILES['import_file']['tmp_name']);
            $importData = json_decode($fileContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return json_encode(['success' => false, 'error' => 'Arquivo JSON inválido']);
            }
            
            // Valida e importa configurações
            foreach ($importData['settings'] as $key => $value) {
                if (!in_array($key, ['zapcel_access_token', 'zapcel_instance_id'])) {
                    Capsule::table('tbladdonmodules')->updateOrInsert(
                        ['module' => 'zapcel', 'setting' => $key],
                        ['value' => $value]
                    );
                }
            }
            
            logActivity("Zapcel: " . $this->LANG['settings_imported_by_admin']);
            
            return json_encode(['success' => true, 'message' => $this->LANG['import_success']]);
            
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxClearTemplatesCache() {
        try {
            // Limpa APENAS cache de templates, sem mexer em estatísticas
            $this->clearTemplatesCacheOnly();
            
            return json_encode([
                'success' => true, 
                'message' => $this->LANG['cache_templates_cleared']
            ]);
            
        } catch (\Throwable $e) {
            return json_encode([
                'success' => false, 
                'error' => $this->LANG['cache_clear_error'] . ': ' . $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX: Define gateway ativo
     */
    private function ajaxSetActiveGateway()
    {
        try {
            $gateway = $_POST['gateway'] ?? '';
            
            // Valida gateway
            if (empty($gateway)) {
                return json_encode([
                    'success' => false,
                    'error' => zapcel_trans('invalid_gateway')
                ]);
            }
            
            // Se não for 'none', verifica se o arquivo existe
            if ($gateway !== 'none') {
                $gatewayFile = __DIR__ . '/../gateways/' . ucfirst($gateway) . 'Gateway.php';
                
                if (!file_exists($gatewayFile)) {
                    return json_encode([
                        'success' => false,
                        'error' => zapcel_trans('gateway_file_not_found')
                    ]);
                }
            }
            
            // Verifica se já existe configuração
            $exists = Capsule::table('tbladdonmodules')
                ->where('module', 'zapcel')
                ->where('setting', 'zapcel_active_gateway')
                ->exists();
            
            if ($exists) {
                // Atualiza
                Capsule::table('tbladdonmodules')
                    ->where('module', 'zapcel')
                    ->where('setting', 'zapcel_active_gateway')
                    ->update(['value' => $gateway]);
            } else {
                // Insere
                Capsule::table('tbladdonmodules')->insert([
                    'module' => 'zapcel',
                    'setting' => 'zapcel_active_gateway',
                    'value' => $gateway
                ]);
            }
            
            // Mensagem de sucesso
            $message = ($gateway === 'none') 
                ? zapcel_trans('gateway_deactivated_success')
                : zapcel_trans('gateway_activated_success') . ' ' . ucfirst($gateway);
            
            return json_encode([
                'success' => true,
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => zapcel_trans('error_saving_gateway') . ': ' . $e->getMessage()
            ]);
        }
    }

    private function clearTemplatesCacheOnly() {
        // Limpa APENAS cache relacionado a templates
        // Exemplo: arquivos temporários de templates compilados
        
        $cacheDirs = [
            // Diretórios específicos de cache de templates
            __DIR__ . '/../templates_cache/',
            __DIR__ . '/../compiled_templates/',
        ];
        
        foreach ($cacheDirs as $dir) {
            if (is_dir($dir)) {
                $this->clearDirectory($dir, 'tpl'); // Limpa apenas arquivos .tpl
            }
        }
        
        logActivity("Zapcel: " . $this->LANG['cache_cleared_by_admin']);
    }

    private function clearDirectory($dir, $extension = '') {
        // Limpa apenas arquivos com extensão específica
        $files = glob($dir . '*' . $extension);
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    private function ajaxUpdateTemplate()
    {
        try {
            // Recebe dados diretamente do POST
            $id = (int)($_POST['template_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $content = trim($_POST['template'] ?? '');
            $active = isset($_POST['active']) && $_POST['active'] == '1' ? 1 : 0;
            
            // Validação mais detalhada
            if (!$id) {
                return json_encode(['success' => false, 'error' => $this->LANG['invalid_id']]);
            }
            
            if ($name === '') {
                return json_encode(['success' => false, 'error' => $this->LANG['template_name_required']]);
            }
            
            if ($content === '') {
                return json_encode(['success' => false, 'error' => $this->LANG['template_content_required']]);
            }
            
            Capsule::table('mod_zapcel_templates')
                ->where('id', $id)
                ->update([
                    'name' => $name,
                    'template' => $content,
                    'active' => $active,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            return json_encode(['success' => true, 'message' => $this->LANG['template_updated']]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /* ═══════════════════════════════════════════════════════════════════
     * FUNÇÕES DE CAMPANHAS - ADICIONAR ANTES DO FECHAMENTO DA CLASSE
     * ═══════════════════════════════════════════════════════════════════ */
    
    /**
     * Retorna o formulário de criação/edição de campanha
    */
    private function ajaxGetCampaignForm()
    {
        try {
            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
            
            $campaign = null;
            if ($campaignId > 0) {
                $campaign = Capsule::table('mod_zapcel_campaigns')->find($campaignId);
            }
            
            // Busca produtos/serviços do WHMCS
            $products = Capsule::table('tblproducts')
                ->where('hidden', 0)
                ->orderBy('name')
                ->get();
            
            ob_start();
            ?>
            <form id="campaignForm">
                <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                
                <!-- Nome da Campanha e Idioma na mesma linha -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label class="font-weight-bold"><?php echo $this->LANG['campaign_name'] ?? 'Nome da Campanha'; ?></label>
                            <input type="text" name="name" class="form-control" value="<?= $campaign->name ?? '' ?>" required placeholder="<?php echo $this->LANG['campaign_name'] ?? 'Nome da Campanha'; ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="font-weight-bold"><?php echo $this->LANG['language'] ?? 'Idioma'; ?></label>
                            <select name="language" class="form-control">
                                <option value="pt" <?= ($campaign->language ?? 'pt') === 'pt' ? 'selected' : '' ?>>Português</option>
                                <option value="en" <?= ($campaign->language ?? 'pt') === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros: Produto em uma linha; Status Serviço + Status Cliente na linha abaixo -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="font-weight-bold"><?php echo $this->LANG['target_products'] ?? 'Tipo'; ?></label>
                            <select name="product_ids[]" class="form-control select2" multiple style="width: 100%;">
                                <?php 
                                $selectedProducts = $campaign ? json_decode($campaign->filters, true)['product_ids'] ?? [] : [];
                                foreach ($products as $product): 
                                ?>
                                <option value="<?= $product->id ?>" <?= in_array($product->id, $selectedProducts) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($product->name) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold"><?php echo $this->LANG['service_status'] ?? 'Status'; ?></label>
                            <select name="service_status[]" class="form-control select2" multiple style="width: 100%;">
                                <?php 
                                $selectedServiceStatus = $campaign ? json_decode($campaign->filters, true)['service_status'] ?? [] : [];
                                $serviceStatuses = ['Active' => 'Ativo', 'Suspended' => 'Suspenso', 'Terminated' => 'Encerrado', 'Cancelled' => 'Cancelado'];
                                foreach ($serviceStatuses as $key => $label): 
                                ?>
                                <option value="<?= $key ?>" <?= in_array($key, $selectedServiceStatus) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold"><?php echo $this->LANG['client_status'] ?? 'Status Cliente'; ?></label>
                            <select name="client_status[]" class="form-control select2" multiple style="width: 100%;">
                                <?php 
                                $selectedClientStatus = $campaign ? json_decode($campaign->filters, true)['client_status'] ?? [] : [];
                                $clientStatuses = ['Active' => 'Ativo', 'Inactive' => 'Inativo', 'Closed' => 'Fechado'];
                                foreach ($clientStatuses as $key => $label): 
                                ?>
                                <option value="<?= $key ?>" <?= in_array($key, $selectedClientStatus) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Template da Mensagem -->
                <div class="form-group">
                    <label class="font-weight-bold"><?php echo $this->LANG['message_template'] ?? 'Template da Mensagem'; ?></label>
                    
                    <!-- Barra de Ferramentas (IGUAL À IMAGEM) -->
                    <div class="whatsapp-toolbar" style="background: #f8f9fa; border: 1px solid #dee2e6; border-bottom: none; padding: 8px; border-radius: 4px 4px 0 0;">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary format-btn-campaign" data-format="bold" title="Negrito">
                                <i class="fas fa-bold"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary format-btn-campaign" data-format="italic" title="Itálico">
                                <i class="fas fa-italic"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary format-btn-campaign" data-format="strikethrough" title="Riscado">
                                <i class="fas fa-strikethrough"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary format-btn-campaign" data-format="monospace" title="Código">
                                <i class="fas fa-code"></i>
                            </button>
                        </div>
                        <div class="btn-group btn-group-sm ml-2" role="group">
                            <button type="button" class="btn btn-outline-secondary" id="emojiPickerBtnCampaign" title="Emojis">
                                <i class="far fa-smile"></i> Emojis
                            </button>
                        </div>
                    </div>
                    
                    <textarea name="message_template" id="campaignMessageTextarea" class="form-control" rows="10" 
                            placeholder="<?php echo $this->LANG['enter_message_template'] ?? 'Digite a mensagem da campanha...'; ?>" 
                            style="border-radius: 0 0 4px 4px; font-family: 'Segoe UI', sans-serif;"><?= $campaign->message_template ?? '' ?></textarea>
                    
                    <small class="form-text text-muted">
                        <?php echo $this->LANG['use_variables_in_braces'] ?? 'Use variáveis entre chaves {assinatura}. Selecione o texto e clique nos botões para formatar.'; ?>
                    </small>
                    
                    <!-- Painel de Emojis COMPLETO (TODOS OS EMOJIS COMO NA IMAGEM) -->
                    <div id="emojiPanelCampaign" class="card mt-2" style="display: none; max-height: 300px; overflow-y: auto;">
                        <div class="card-body p-2">
                            <div class="emoji-grid" style="display: grid; grid-template-columns: repeat(10, 1fr); gap: 5px; font-size: 24px; text-align: center;">
                                <!-- Status e Ações -->
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Verificado">✅</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Check">✔️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Erro">❌</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="X">✖️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Atenção">⚠️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Importante">❗</span>
                                
                                <!-- Setas -->
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Seta Direita">➡️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Seta Esquerda">⬅️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Seta Cima">⬆️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Seta Baixo">⬇️</span>
                                
                                <!-- Controles -->
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Play">▶️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Pausa">⏸️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Stop">⏹️</span>
                                
                                <!-- Formas Geométricas -->
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Círculo">⭕</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Círculo Preto">●</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Quadrado Preto">◼️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Quadrado Branco">◻️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Quadrado Médio">◾</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Quadrado Pequeno">▫️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Ponto">▪️</span>
                                
                                <!-- Símbolos Matemáticos -->
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Mais">➕</span>
                                <span class="emoji-item-edit" style="cursor: pointer;" title="Menos">➖</span>
                                
                                <!-- Ícones Gerais -->
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Estrela">⭐</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Email">✉️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Telefone">☎️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Rápido">⚡</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Informação">ℹ️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Configurações">⚙️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Reciclar">♻️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Relógio">⏰</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Marca Registrada">™️</span>
                                <span class="emoji-item-campaign" style="cursor: pointer;" title="Preto">⚫</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Variáveis ABAIXO do campo mensagem -->
                    <div class="mt-2">
                        <div class="mt-1">
                            <span class="badge badge-info variable-item-campaign" style="cursor: pointer; margin: 2px;">{cliente}</span>
                            <span class="badge badge-info variable-item-campaign" style="cursor: pointer; margin: 2px;">{email}</span>
                            <span class="badge badge-info variable-item-campaign" style="cursor: pointer; margin: 2px;">{telefone}</span>
                            <span class="badge badge-info variable-item-campaign" style="cursor: pointer; margin: 2px;">{dominio}</span>
                            <span class="badge badge-info variable-item-campaign" style="cursor: pointer; margin: 2px;">{empresa}</span>
                            <span class="badge badge-info variable-item-campaign" style="cursor: pointer; margin: 2px;">{url_whmcs}</span>
                            <span class="badge badge-info variable-item-campaign" style="cursor: pointer; margin: 2px;">{assinatura}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Data/Hora de Início e Modo de Envio na mesma linha -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold"><?php echo $this->LANG['schedule_start'] ?? 'Data/Hora de Início'; ?></label>
                            <input type="datetime-local" name="schedule_start" class="form-control" 
                                   value="<?= $campaign && isset($campaign->schedule_start) ? date('Y-m-d\TH:i', strtotime($campaign->schedule_start)) : '' ?>">
                            <small class="form-text text-muted">
                                <?php echo $this->LANG['schedule_help'] ?? 'Deixe em branco para iniciar imediatamente ao ativar.'; ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold"><?php echo $this->LANG['send_mode'] ?? 'Modo de Envio'; ?></label>
                            <select name="send_mode" class="form-control">
                                <option value="all_day" <?= (isset($campaign->send_mode) && $campaign->send_mode === 'all_day') ? 'selected' : '' ?>>
                                    <?php echo $this->LANG['all_day'] ?? 'Dia Todo (24h)'; ?>
                                </option>
                                <option value="business_hours" <?= (!isset($campaign->send_mode) || $campaign->send_mode === 'business_hours') ? 'selected' : '' ?>>
                                    <?php echo $this->LANG['business_hours'] ?? 'Horário Comercial (07:00 às 18:00)'; ?>
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Delay -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold"><?php echo $this->LANG['delay_min'] ?? 'Delay Mínimo (segundos)'; ?></label>
                            <input type="number" name="delay_min" class="form-control" min="7" max="60" 
                                   value="<?= isset($campaign->delay_min) ? $campaign->delay_min : 7 ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold"><?php echo $this->LANG['delay_max'] ?? 'Delay Máximo (segundos)'; ?></label>
                            <input type="number" name="delay_max" class="form-control" min="7" max="60" 
                                   value="<?= isset($campaign->delay_max) ? $campaign->delay_max : 13 ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i>
                    <?php echo $this->LANG['delay_info'] ?? 'O sistema aplica, além do delay do Zapcel, um atraso aleatório entre 7 e 13 segundos em cada envio.'; ?>
                </div>
            </form>
            
            <script>
            $(document).ready(function() {
                // Formatação
                $('.format-btn-campaign').click(function() {
                    const format = $(this).data('format');
                    const textarea = document.getElementById('campaignMessageTextarea');
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const text = textarea.value;
                    const selectedText = text.substring(start, end);
                    
                    let before = '', after = '';
                    switch(format) {
                        case 'bold': before = after = '*'; break;
                        case 'italic': before = after = '_'; break;
                        case 'strikethrough': before = after = '~'; break;
                        case 'monospace': before = after = '```'; break;
                    }
                    
                    const newText = text.substring(0, start) + before + selectedText + after + text.substring(end);
                    textarea.value = newText;
                    textarea.focus();
                    textarea.setSelectionRange(start + before.length, end + before.length);
                });
                
                // Emojis
                $('#emojiPickerBtnCampaign').click(function() {
                    $('#emojiPanelCampaign').toggle();
                });
                
                $('.emoji-item-campaign').click(function() {
                    const emoji = $(this).text();
                    const textarea = document.getElementById('campaignMessageTextarea');
                    const start = textarea.selectionStart;
                    const text = textarea.value;
                    textarea.value = text.substring(0, start) + emoji + text.substring(start);
                    textarea.focus();
                    textarea.setSelectionRange(start + emoji.length, start + emoji.length);
                });
                
                // Variáveis
                $('.variable-item-campaign').click(function() {
                    const variable = $(this).text();
                    const textarea = document.getElementById('campaignMessageTextarea');
                    const start = textarea.selectionStart;
                    const text = textarea.value;
                    textarea.value = text.substring(0, start) + variable + text.substring(start);
                    textarea.focus();
                    textarea.setSelectionRange(start + variable.length, start + variable.length);
                });
                
                // Inicializa Select2
                setTimeout(function() {
                    $('.select2').select2({
                        dropdownParent: $('#campaignFormModal')
                    });
                }, 100);
            });
            </script>
            <?php
            
            $html = ob_get_clean();
            
            return json_encode([
                'status' => 'success',
                'html' => $html
            ]);
            
        } catch (Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Erro ao carregar formulário: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Altera o status de uma campanha (ativar/pausar)
     * ADICIONAR ESTA FUNÇÃO NO index.php JUNTO COM AS OUTRAS FUNÇÕES DE CAMPANHAS
     */
    /**
    /*private function ajaxToggleCampaign()
    {
        try {
            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
            $newStatus  = $_POST['status'] ?? '';
    
            if ($campaignId <= 0) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Campanha inválida'
                ]);
            }
    
            if (!in_array($newStatus, ['active', 'paused', 'draft'])) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Status inválido'
                ]);
            }
    
            $campaign = Capsule::table('mod_zapcel_campaigns')->find($campaignId);
            if (!$campaign) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Campanha não encontrada'
                ]);
            }
    
            // ===== ATIVAÇÃO REAL DA CAMPANHA =====
            if ($newStatus === 'active') {
    
                $filters = json_decode($campaign->filters, true);
                if (!is_array($filters)) {
                    return json_encode([
                        'status' => 'error',
                        'message' => 'Filtros da campanha inválidos'
                    ]);
                }
    
                // Enfileira destinatários (materializa campanha)
                $this->enqueueCampaign($campaignId);
                $total = (int) Capsule::table('mod_zapcel_campaign_queue')
                    ->where('campaign_id', $campaignId)
                    ->count();
    
                Capsule::table('mod_zapcel_campaigns')
                    ->where('id', $campaignId)
                    ->update([
                        'status'         => 'active',
                        'total_contacts' => $total,
                        'pending_count'  => $total,
                        'sent_count'     => 0,
                        'failed_count'   => 0,
                        'updated_at'     => date('Y-m-d H:i:s'),
                    ]);
    
                return json_encode([
                    'status'  => 'success',
                    'message' => "Campanha ativada com sucesso. $total contatos enfileirados."
                ]);
            }
    
            // ===== PAUSE / DRAFT (COMPORTAMENTO ANTIGO) =====
            Capsule::table('mod_zapcel_campaigns')
                ->where('id', $campaignId)
                ->update([
                    'status'     => $newStatus,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
    
            return json_encode([
                'status'  => 'success',
                'message' => 'Status alterado com sucesso!'
            ]);
    
        } catch (Exception $e) {
            return json_encode([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }*/
    
    /**
     * Toggle inteligente de campanha
     * Regras:
     * - draft  -> (schedule_start futuro ? scheduled : active) + ENFILEIRA + zera contadores
     * - paused -> (schedule_start futuro ? scheduled : active) + NÃO enfileira (só retoma)
     * - active -> paused
     * - scheduled -> paused (cancela agendamento)
     * - finished -> não toggla (usa reset)
     */
    private function ajaxToggleCampaign()
    {
        try {
            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
    
            if ($campaignId <= 0) {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Campanha inválida'
                ]);
            }
    
            $campaign = Capsule::table('mod_zapcel_campaigns')->find($campaignId);
            if (!$campaign) {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Campanha não encontrada'
                ]);
            }
    
            $current = (string) ($campaign->status ?? 'draft');
    
            if ($current === 'finished') {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Campanha finalizada. Use Reset para reenfileirar.'
                ]);
            }
    
            $nowTs = time();
            $isFuture = (!empty($campaign->schedule_start) && strtotime($campaign->schedule_start) > $nowTs);
    
            // Decide o próximo status
            $target = $current;
    
            if ($current === 'active') {
                $target = 'paused';
            } elseif ($current === 'scheduled') {
                // cancela agendamento
                $target = 'paused';
            } elseif ($current === 'paused') {
                // retoma
                $target = $isFuture ? 'scheduled' : 'active';
            } else {
                // draft (ou qualquer outro)
                $target = $isFuture ? 'scheduled' : 'active';
            }
    
            // ===== 1) Se saiu de draft -> (active/scheduled): ENFILEIRA + zera contadores =====
            $fromDraft = ($current === 'draft');
    
            if ($fromDraft && in_array($target, ['active', 'scheduled'], true)) {
                // Enfileira destinatários (materializa)
                $this->enqueueCampaign($campaignId);
    
                // Total real da fila
                $total = (int) Capsule::table('mod_zapcel_campaign_queue')
                    ->where('campaign_id', $campaignId)
                    ->count();
    
                Capsule::table('mod_zapcel_campaigns')
                    ->where('id', $campaignId)
                    ->update([
                        'status'         => $target,
                        'total_contacts' => $total,
                        'pending_count'  => $total,
                        'sent_count'     => 0,
                        'failed_count'   => 0,
                        'updated_at'     => date('Y-m-d H:i:s'),
                    ]);
    
                return json_encode([
                    'status'  => 'success',
                    'message' => ($target === 'scheduled'
                        ? "Campanha agendada com sucesso. $total contatos enfileirados."
                        : "Campanha iniciada com sucesso. $total contatos enfileirados."
                    )
                ]);
            }
    
            // ===== 2) Se foi resume (paused -> active/scheduled): NÃO enfileira, só muda status =====
            if ($current === 'paused' && in_array($target, ['active', 'scheduled'], true)) {
    
                // Recalcula pending_count real (pending + processing), sem zerar histórico
                $pendingReal = (int) Capsule::table('mod_zapcel_campaign_queue')
                    ->where('campaign_id', $campaignId)
                    ->whereIn('status', ['pending', 'processing'])
                    ->count();
    
                Capsule::table('mod_zapcel_campaigns')
                    ->where('id', $campaignId)
                    ->update([
                        'status'        => $target,
                        'pending_count' => $pendingReal,
                        'updated_at'    => date('Y-m-d H:i:s'),
                    ]);
    
                return json_encode([
                    'status'  => 'success',
                    'message' => ($target === 'scheduled'
                        ? 'Campanha reagendada com sucesso!'
                        : 'Campanha retomada com sucesso!'
                    )
                ]);
            }
    
            // ===== 3) Pausar / cancelar agendamento: só muda status =====
            Capsule::table('mod_zapcel_campaigns')
                ->where('id', $campaignId)
                ->update([
                    'status'     => $target,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
    
            return json_encode([
                'status'  => 'success',
                'message' => ($target === 'paused' ? 'Campanha pausada com sucesso!' : 'Status alterado com sucesso!')
            ]);
    
        } catch (Exception $e) {
            return json_encode([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Salva uma campanha (criar ou editar)
     */
        private function ajaxSaveCampaign()
    {   
        try {
            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
            $name = $_POST['name'] ?? '';
            $language = $_POST['language'] ?? 'pt';
            $messageTemplate = $_POST['message_template'] ?? '';
            $scheduleStart = $_POST['schedule_start'] ?? null;
            $sendMode = $_POST['send_mode'] ?? 'business_hours';
            $delayMin = max(7, (int) ($_POST['delay_min'] ?? 7));
            $delayMax = max($delayMin, (int) ($_POST['delay_max'] ?? 13));
            
            // Validações
            if (empty($name)) {
                ob_end_clean();
                return json_encode([
                    'status' => 'error',
                    'message' => 'Nome da campanha é obrigatório'
                ]);
            }
            
            if (empty($messageTemplate)) {
                ob_end_clean();
                return json_encode([
                    'status' => 'error',
                    'message' => 'Mensagem da campanha é obrigatória'
                ]);
            }
            
            if ($delayMin < 7 || $delayMax > 60 || $delayMin > $delayMax) {
                ob_end_clean();
                return json_encode([
                    'status' => 'error',
                    'message' => 'Delays inválidos. Mínimo: 7s, Máximo: 60s'
                ]);
            }
            
            // Filtros
            $filtersArr = [
                'product_ids' => $_POST['product_ids'] ?? [],
                'service_status' => $_POST['service_status'] ?? [],
                'client_status' => $_POST['client_status'] ?? [],
            ];
            
            // Calcula lote seguro: X = floor(T / Dmax) + 1
            // T = 300s (5 min) - 20s (margem) = 280s
            $T = 280;
            $batchSize = floor($T / $delayMax) + 1;
            
            $totalContacts = $this->countCampaignRecipients($filtersArr);
            
            $data = [
                'name' => $name,
                'message_template' => $messageTemplate,
                'language' => $language,
                'filters' => json_encode($filtersArr),
                'schedule_start' => $scheduleStart ? date('Y-m-d H:i:s', strtotime($scheduleStart)) : null,
                'send_mode' => $sendMode,
                'delay_min' => $delayMin,
                'delay_max' => $delayMax,
                'batch_size' => $batchSize,
                'total_contacts' => $totalContacts,
                'pending_count'  => $totalContacts,
                'sent_count'     => 0,
                'failed_count'   => 0,
                'status' => 'draft',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            
            if ($campaignId > 0) {
                
                // Atualiza campanha existente
                Capsule::table('mod_zapcel_campaigns')
                    ->where('id', $campaignId)
                    ->update($data);
                    
                $message = 'Campanha atualizada com sucesso!';
                
            } else {
                
                // Cria nova campanha
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['sent_count'] = 0;
                $data['failed_count'] = 0;
                $data['total_contacts'] = 0;
                
                $campaignId = Capsule::table('mod_zapcel_campaigns')->insertGetId($data);
                
                $message = 'Campanha criada com sucesso!';
            }
            
            // Enfileira destinatários
            $this->enqueueCampaign($campaignId);
            
            return json_encode([
                'success' => true,
                'message' => $message // $this->LANG['campaign_saved']
            ]);
            
        } catch (Exception $e) {
            
            return json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    // Contar destinatários (usado pra mostrar quantos vai enfileirar)
    private function countCampaignRecipients(array $filters): int
    {
        $query = Capsule::table('tblhosting as h')
            ->join('tblclients as c', 'h.userid', '=', 'c.id');
    
        if (!empty($filters['client_status'])) {
            $query->whereIn('c.status', (array)$filters['client_status']);
        }
        if (!empty($filters['service_status'])) {
            $query->whereIn('h.domainstatus', (array)$filters['service_status']);
        }
        if (!empty($filters['product_ids'])) {
            $query->whereIn('h.packageid', (array)$filters['product_ids']);
        }
    
        // conta quantos destinatários seriam enfileirados (um por serviço)
        return (int)$query->count('h.id');
    }

    // Enfileirar destinatários (materializa e impede duplicidade)
    private function enqueueCampaignRecipients(int $campaignId, array $filters): int
    {
        // limpa fila antiga
        Capsule::table('mod_zapcel_campaign_queue')->where('campaign_id', $campaignId)->delete();
    
        $q = Capsule::table('tblclients as c')
            ->join('tblhosting as h', 'h.userid', '=', 'c.id');
    
        if (!empty($filters['client_status'])) {
            $q->whereIn('c.status', $filters['client_status']);
        }
        if (!empty($filters['service_status'])) {
            $q->whereIn('h.domainstatus', $filters['service_status']);
        }
        if (!empty($filters['product_ids'])) {
            $q->whereIn('h.packageid', $filters['product_ids']);
        }
    
        // selecione o mínimo necessário
        $rows = $q->select([
            'c.id as client_id',
            'h.id as service_id',
            'c.phonenumber as phone'
        ])->get();
    
        $now = date('Y-m-d H:i:s');
        $inserted = 0;
    
        foreach ($rows as $r) {
            $phone = trim((string)$r->phone);
            if ($phone === '') continue;
    
            // insert ignorando duplicados (se você criar UNIQUE)
            try {
                Capsule::table('mod_zapcel_campaign_queue')->insertOrIgnore([
                    'campaign_id'   => $campaignId,
                    'client_id'     => (int)$r->client_id,
                    'service_id'    => (int)$r->service_id,
                    'phone_number'  => $phone,
                    'message'       => '',
                    'status'        => 'pending',
                    'next_send_at'  => $now,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
                $inserted++;
            } catch (\Exception $e) {
                // se for duplicado por UNIQUE, ignora
            }
        }
    
        return $inserted;
    }

    /**
     * Reseta uma campanha (zera contadores e re-enfileira)
     */
    private function ajaxResetCampaign()
    {
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        if ($campaignId <= 0) {
            return json_encode(['success' => false, 'error' => 'Campanha inválida']);
        }
    
        $campaign = Capsule::table('mod_zapcel_campaigns')->find($campaignId);
        if (!$campaign) {
            return json_encode(['success' => false, 'error' => 'Campanha não encontrada']);
        }
    
        $filters = json_decode($campaign->filters, true) ?: [];
    
        // recria fila
        $this->enqueueCampaign($campaignId);
        $total = (int) Capsule::table('mod_zapcel_campaign_queue')
            ->where('campaign_id', $campaignId)
            ->count();
    
        Capsule::table('mod_zapcel_campaigns')
            ->where('id', $campaignId)
            ->update([
                'status'         => 'active',
                'total_contacts' => $total,
                'pending_count'  => $total,
                'sent_count'     => 0,
                'failed_count'   => 0,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
    
        return json_encode([
            'success' => true,
            'message' => "Campanha resetada. $total contatos reenfileirados.",
            'icon'    => 'success', // Altera para 'success'
            'color'   => 'green'   // Cor verde para o aviso
        ]);
    }

    /**
     * Deleta uma campanha
     */
    private function ajaxDeleteCampaign()
    {
        try {
            
            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            if (!$campaignId) { return json_encode(['success' => false, 'error' => $this->LANG['invalid_id']]); }
            
            // Deleta campanha (CASCADE deleta fila automaticamente)
            Capsule::table('mod_zapcel_campaigns')
                ->where('id', $campaignId)->delete();
            
            return json_encode([
                'success' => true,
                'message' => $this->LANG['campaign_deleted']
            ]);
            
        } catch (Exception $e) {
            return json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gera relatório de atividade da campanha (TXT)
     */
    private function ajaxGetCampaignActivity()
    {
        try {
            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
    
            $campaign = Capsule::table('mod_zapcel_campaigns')->find($campaignId);
            if (!$campaign) {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Campanha não encontrada'
                ]);
            }
    
            // Filtros
            $filters = json_decode($campaign->filters, true) ?: [];
    
            // Produtos
            $productNames = [];
            if (!empty($filters['product_ids'])) {
                $productNames = Capsule::table('tblproducts')
                    ->whereIn('id', (array)$filters['product_ids'])
                    ->pluck('name')
                    ->toArray();
            }
    
            // Status mapeados
            $statusMap = [
                'Active'     => 'Ativo',
                'Suspended'  => 'Suspenso',
                'Terminated' => 'Encerrado',
                'Cancelled'  => 'Cancelado',
                'Inactive'   => 'Inativo',
                'Closed'     => 'Fechado',
            ];
    
            // Texto dos filtros
            $filtrosTxt  = "- Serviço/Produto: " . (!empty($productNames) ? implode(', ', $productNames) : 'Todos') . "\n";
            $filtrosTxt .= "- Status do Serviço: " . (!empty($filters['service_status'])
                ? implode(', ', array_map(function ($s) use ($statusMap) { return $statusMap[$s] ?? $s; }, (array)$filters['service_status']))
                : 'Todos') . "\n";
            $filtrosTxt .= "- Status do Cliente: " . (!empty($filters['client_status'])
                ? implode(', ', array_map(function ($s) use ($statusMap) { return $statusMap[$s] ?? $s; }, (array)$filters['client_status']))
                : 'Todos') . "\n";
    
            $queue = Capsule::table('mod_zapcel_campaign_queue')
                ->where('campaign_id', $campaignId)
                ->whereIn('status', ['sent', 'failed'])
                ->orderBy('sent_at')
                ->get();
    
            // Datas BR
            $periodo = $campaign->schedule_start
                ? date('d/m/Y H:i:s', strtotime($campaign->schedule_start))
                : 'Imediato';
    
            // Gera TXT
            $content  = "==============================================\n";
            $content .= "RELATÓRIO DE ATIVIDADE DA CAMPANHA\n";
            $content .= "==============================================\n\n";
            $content .= "Nome: " . $campaign->name . "\n\n";
            $content .= "Filtros Aplicados:\n";
            $content .= $filtrosTxt . "\n";
            $content .= "Quantidade Total: " . (int)$campaign->total_contacts . "\n";
            $content .= "Período: " . $periodo . "\n";
            $content .= "Janela de Envio: " . ($campaign->send_mode === 'business_hours' ? '07:00 às 18:00' : '24h') . "\n";
            $content .= "Delay Min/Max: " . (int)$campaign->delay_min . "s / " . (int)$campaign->delay_max . "s\n";
            $content .= "\n==============================================\n";
            $content .= "NÚMEROS PROCESSADOS\n";
            $content .= "==============================================\n\n";
    
            foreach ($queue as $item) {
                $status = ($item->status === 'sent') ? 'SUCESSO' : 'FALHA';
                $delay  = isset($item->delay_used) ? (int)$item->delay_used : 'N/A';
    
                // telefone somente números (558199...)
                $phone = preg_replace('/\D+/', '', (string)$item->phone_number);
    
                $dataEnvio = $item->sent_at
                    ? date('d/m/Y H:i:s', strtotime($item->sent_at))
                    : 'N/A';
    
                $content .= sprintf(
                    "%s | %s | Delay: %s%s | %s\n",
                    $phone,
                    $status,
                    $delay,
                    ($delay === 'N/A' ? '' : 's'),
                    $dataEnvio
                );
    
                if ($item->status === 'failed' && !empty($item->error_message)) {
                    $content .= "   Erro: " . $item->error_message . "\n";
                }
            }
    
            $filename = 'relatorio_campanha_' . $campaignId . '_' . date('YmdHis') . '.txt';
    
            return json_encode([
                'status'   => 'success',
                'content'  => $content,
                'filename' => $filename
            ]);
    
        } catch (Exception $e) {
            return json_encode([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Enfileira destinatários de uma campanha
     */
    private function enqueueCampaign($campaignId)
    {
        $campaign = Capsule::table('mod_zapcel_campaigns')->find($campaignId);
        if (!$campaign) return;
    
        $filters = json_decode($campaign->filters, true);
        if (!is_array($filters)) $filters = [];
    
        $query = Capsule::table('tblhosting as h')
            ->join('tblclients as c', 'h.userid', '=', 'c.id');
    
        // filtros
        if (!empty($filters['client_status'])) {
            $query->whereIn('c.status', (array)$filters['client_status']);
        }
        if (!empty($filters['service_status'])) {
            $query->whereIn('h.domainstatus', (array)$filters['service_status']);
        }
        if (!empty($filters['product_ids'])) {
            $query->whereIn('h.packageid', (array)$filters['product_ids']);
        }
    
        // dados do contato (aliases usados no processMessageVariables)
        $contacts = $query->select([
            'c.id as client_id',
            'h.id as service_id',
            'c.firstname',
            'c.lastname',
            'c.email',
            'c.phonenumber as phone',
            'h.domain',
        ])->get();
    
        // limpa fila anterior
        Capsule::table('mod_zapcel_campaign_queue')
            ->where('campaign_id', $campaignId)
            ->delete();
    
        $nextSendAt = $campaign->schedule_start ? date('Y-m-d H:i:s', strtotime($campaign->schedule_start)) : date('Y-m-d H:i:s');
    
        $inserted = 0;
    
        foreach ($contacts as $contact) {
            $phone = trim((string)($contact->phone ?? ''));
            if ($phone === '') continue;
    
            $message = $this->processMessageVariables($campaign->message_template, $contact, $campaign->language);
    
            Capsule::table('mod_zapcel_campaign_queue')->insertOrIgnore([
                'campaign_id'  => (int)$campaignId,
                'client_id'    => (int)$contact->client_id,
                'service_id'   => (int)$contact->service_id,
                'phone_number' => $phone,
                'message'      => (string)$message, // NOT NULL
                'status'       => 'pending',
                'next_send_at' => $nextSendAt,
            ]);
    
            $inserted++;
        }
    
        // atualiza contadores reais da fila
        Capsule::table('mod_zapcel_campaigns')
            ->where('id', $campaignId)
            ->update([
                'total_contacts' => $inserted,
                'pending_count'  => $inserted,
                'sent_count'     => 0,
                'failed_count'   => 0,
            ]);
    }
    
    /**
     * Processa variáveis na mensagem da campanha
     */
    private function processMessageVariables($template, $contact, $language)
    {
        $variables = [
            '{cliente}' => $contact->firstname . ' ' . $contact->lastname,
            '{email}' => $contact->email,
            '{telefone}' => $contact->phone,
            '{empresa}' => Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value') ?? '',
            '{url_whmcs}' => Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value') ?? '',
            '{assinatura}' => Capsule::table('tbladdonmodules')->where('module', 'zapcel')->where('setting', 'zapcel_signature')->value('value') ?? ''
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $template);
    }
    
    /* FIM DAS FUNÇÕES DE CAMPANHA */
    /**
     * Testa conexão com a API
     */
    private function testAPIConnection()
    {
        try {
            $settings = $this->getSettings();
            
            if (empty($settings['zapcel_instance_id']) || empty($settings['zapcel_access_token'])) {
                return [
                    'success' => false,
                    'message' => $this->LANG['api_credentials_not_configured']
                ];
            }

            $api = new WhatsAppAPI($settings);
            $result = $api->checkInstanceStatus();

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => $this->LANG['connection_established'],
                    'account_info' => $result['data'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['error'] ?? $this->LANG['connection_error'],
                    'account_info' => null
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $this->LANG['connection_error'] . ': ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém badge CSS para tipo de log - ATUALIZADO
     */
    private function getLogTypeBadge($type)
    {
        $badges = [
            // FATURAS - Verde/Azul (Financeiro)
            'invoice_created' => 'primary',      // Azul - Nova fatura
            'invoice_paid' => 'success',         // Verde - Pagamento confirmado
            'invoice_cancelled' => 'danger',     // Vermelho - Cancelamento
            'invoice_reminder' => 'warning',     // Amarelo - Aviso geral
            'invoice_reminder_1' => 'warning',   // Amarelo - 1º aviso
            'invoice_reminder_2' => 'warning',   // Amarelo - 2º aviso  
            'invoice_reminder_3' => 'danger',    // Vermelho - 3º aviso (urgente)

            // SERVIÇOS - Roxo/Ciano (Infraestrutura)
            'service_activated' => 'success',    // Verde - Ativação
            'service_suspended' => 'warning',    // Amarelo - Suspensão
            'service_unsuspended' => 'info',     // Ciano - Reativação
            'service_terminated' => 'danger',    // Vermelho - Término
            'cancellation_request' => 'warning', // Amarelo - Solicitação cancelamento

            // TICKETS - Azul (Suporte)
            'ticket_opened' => 'info',           // Ciano - Novo ticket
            'ticket_reply' => 'primary',         // Azul - Resposta
            'ticket_created' => 'info',          // Ciano - Compatibilidade
            'ticket_replied' => 'primary',       // Azul - Compatibilidade

            // CLIENTES - Roxo (Gestão)
            'client_added' => 'primary',         // Azul - Novo cliente
            'client_edited' => 'info',           // Ciano - Edição
            'password_changed' => 'secondary',   // Cinza - Segurança

            // COTAÇÕES - Verde (Vendas)
            'quote_created' => 'success',        // Verde - Nova cotação
            'quote_modified' => 'warning',       // Amarelo - Modificação
            'quote_accepted' => 'success',       // Verde - Aceitação

            // EMAIL - Cinza (Comunicação)
            'email_presend' => 'secondary',      // Cinza - Pré-envio
            'email_replaced' => 'light',         // Cinza claro - Substituição

            // SISTEMA - Laranja/Cinza (Técnico)
            'whatsapp_validation' => 'info',     // Ciano - Validação
            'test_message' => 'secondary',       // Cinza - Teste
            'system_error' => 'danger',          // Vermelho - Erro
            'system_cleanup' => 'light',         // Cinza claro - Manutenção

            // DEBUG - Cinza claro (Desenvolvimento)
            'debug_aftermodulesuspend' => 'light',
            'debug_aftermoduleunsuspend' => 'light', 
            'debug_aftermoduleterminate' => 'light',
            'debug_invoicecreated' => 'light',
            'debug_ticketopened' => 'light',
            'debug_default_variables' => 'light',
            'debug_validationtest' => 'light'
        ];

        // Para tipos de debug genéricos
        if (strpos($type, 'debug_') === 0) {
            return 'light';
        }

        return $badges[$type] ?? 'secondary';
    }

    /**
     * Obtém nome amigável para tipo de evento
     */
    private function getEventTypeDisplayName($eventType)
    {
        $names = [
            // FATURAS
            'invoice_created' => '📋 ' . $this->LANG['invoice_created'],
            'invoice_reminder' => '🔔 ' . $this->LANG['invoice_reminder'],
            'invoice_paid' => '✅ ' . $this->LANG['invoice_paid'],
            'invoice_cancelled' => '❌ ' . $this->LANG['invoice_cancelled'],
            'invoice_reminder_1' => '🔔 ' . $this->LANG['invoice_reminder_1'],
            'invoice_reminder_2' => '🔔 ' . $this->LANG['invoice_reminder_2'],
            'invoice_reminder_3' => '🔔 ' . $this->LANG['invoice_reminder_3'],
            
            // TICKETS
            'ticket_created' => '🎫 ' . $this->LANG['ticket_created'],
            'ticket_opened' => '🎫 ' . $this->LANG['ticket_opened'],
            'ticket_replied' => '🎫 ' . $this->LANG['ticket_replied'],
            'ticket_reply' => '🎫 ' . $this->LANG['ticket_reply'],
            
            // SERVIÇOS
            'service_activated' => '✅ ' . $this->LANG['service_activated'],
            'service_suspended' => '⏸️ ' . $this->LANG['service_suspended'],
            'service_unsuspended' => '▶️ ' . $this->LANG['service_unsuspended'],
            'service_terminated' => '🚫 ' . $this->LANG['service_terminated'],
            'cancellation_request' => '📝 ' . $this->LANG['cancellation_request'],
            
            // CLIENTES
            'client_added' => '👤 ' . $this->LANG['client_added'],
            'client_edited' => '✏️ ' . $this->LANG['client_edited'],
            'password_changed' => '🔑 ' . $this->LANG['password_changed'],
            
            // COTAÇÕES
            'quote_created' => '💰 ' . $this->LANG['quote_created'],
            'quote_modified' => '📝 ' . $this->LANG['quote_modified'],
            'quote_accepted' => '✅ ' . $this->LANG['quote_accepted'],
            
            // EMAIL/SISTEMA
            'email_presend' => '📧 ' . $this->LANG['email_presend'],
            'email_replaced' => '📧 ' . $this->LANG['email_replaced'],
            'whatsapp_validation' => '✅ ' . $this->LANG['whatsapp_validation'],
            
            // SISTEMA/DEBUG
            'test_message' => '📋 ' . zapcel_trans('test_message_event'),
            'custom_message_manual' => '📋 ' . zapcel_trans('custom_message_log'),
            'system_error' => '⚠️ ' . zapcel_trans('system_error'),
            'system_cleanup' => '🧹 ' . zapcel_trans('system_cleanup_log'),
            'debug_aftermodulesuspend' => '⏸️ ' . zapcel_trans('debug_service_suspended'),
            'debug_aftermoduleunsuspend' => '▶️ ' . zapcel_trans('debug_service_unsuspended'),
            'debug_aftermoduleterminate' => '🚫 ' . zapcel_trans('debug_service_terminated'),
            'debug_invoicecreated' => '📋 ' . zapcel_trans('debug_invoice_created'),
            'debug_ticketopened' => '🎫 ' . zapcel_trans('debug_ticket_opened'),
            'debug_default_variables' => '🔧 ' . zapcel_trans('debug_default_variables'),
            'debug_validationtest' => '🧪 ' . zapcel_trans('debug_validation_test'),
            
            // SERVIÇOS POR TIPO
            'service_activated_hosting' => '🌐 ' . zapcel_trans('hosting'),
            'service_activated_other' => '🌐 ' . zapcel_trans('other_services'),
            'service_activated_reseller' => '📋 ' . zapcel_trans('reseller'),
            'service_activated_vps' => '☁️ ' . zapcel_trans('dedicated_vps_server'),
            
            // Campanhas
            'campaign_start' => '▶️ ' . zapcel_trans('campaign_start_log'),
            'campaign_end' => '✅ ' . zapcel_trans('campaign_end_log'),
        ];

        return $names[$eventType] ?? ($this->LANG[$eventType] ?? $eventType);
    }

    /**
     * Obtém nome amigável para tipo de log
     */
    private function getLogTypeLabel($type)
    {
        $map = [
            'message_sent' => $this->LANG['message_sent'],
            'message_failed' => $this->LANG['message_failed'],
            'validation_sent' => $this->LANG['validation_sent'],
            'validation_success' => $this->LANG['validation_success'],
            'gateway_sync' => $this->LANG['gateway_sync'],
            'system_error' => $this->LANG['system_error']
        ];

        if (isset($map[$type])) {
            return $map[$type];
        }

        // genérico para qualquer gateway_* (sem nome do gateway)
        if (strpos($type, 'gateway_') === 0) {
            return $this->LANG['gateway_event'];
        }

        return $type; // fallback
    }

    // ===================== MÉTODOS AJAX (IMPLEMENTADOS) =====================

    private function ajaxDeactivateTemplate() {
        try {
            $id = (int)($_POST['template_id'] ?? 0);
            if (!$id) { return json_encode(['success' => false, 'error' => 'ID inválido']); }

            $tpl = Capsule::table('mod_zapcel_templates')->where('id', $id)->first();
            if (!$tpl) { return json_encode(['success' => false, 'error' => 'Template não encontrado']); }

            // APENAS DESATIVA (sempre coloca como 0)
            Capsule::table('mod_zapcel_templates')->where('id', $id)->update(['active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
            return json_encode(['success' => true, 'active' => false]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxActivateTemplate() {
        try {
            $id = (int)($_POST['template_id'] ?? 0);
            if (!$id) { return json_encode(['success' => false, 'error' => 'ID inválido']); }

            $tpl = Capsule::table('mod_zapcel_templates')->where('id', $id)->first();
            if (!$tpl) { return json_encode(['success' => false, 'error' => 'Template não encontrado']); }

            // APENAS ATIVA (sempre coloca como 1)
            Capsule::table('mod_zapcel_templates')->where('id', $id)->update(['active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            return json_encode(['success' => true, 'active' => true]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxDeleteTemplate() {
        try {
            $id = (int)($_POST['template_id'] ?? 0);
            if (!$id) { return json_encode(['success' => false, 'error' => $this->LANG['invalid_id']]); }
            
            Capsule::table('mod_zapcel_templates')->where('id', $id)->delete();
            return json_encode(['success' => true, 'message' => $this->LANG['template_deleted']]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxCreateTemplate() {
        try {
            $name = trim($_POST['name'] ?? '');
            $trigger = trim($_POST['trigger_event'] ?? '');
            $content = trim($_POST['template'] ?? '');
            $active = (int)($_POST['active'] ?? 0);
            if ($name === '' || $trigger === '' || $content === '') {
                return json_encode(['success' => false, 'error' => $this->LANG['fill_name_event_content']]);
            }

            $templateId = Capsule::table('mod_zapcel_templates')->insertGetId([
                'name' => $name,
                'trigger_event' => $trigger,
                'template' => $content,
                'active' => $active,
                'usage_count' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            return json_encode(['success' => true, 'message' => $this->LANG['template_created'], 'template_id' => $templateId]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxResendValidation() {
        try {
            $clientId = (int)($_POST['client_id'] ?? 0);
            if (!$clientId) { return json_encode(['success' => false, 'error' => $this->LANG['invalid_client']]); }
            $settings = $this->getSettings();
            $nv = new \WHMCS\Module\Addon\Zapcel\Api\NumberValidator($settings);
            $r = $nv->resendCode($clientId);
            return json_encode($r);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxGetValidationDetails() {
        try {
            $clientId = (int)($_POST['client_id'] ?? 0);
            if (!$clientId) { return json_encode(['success' => false, 'error' => $this->LANG['invalid_client']]); }
            $v = Capsule::table('mod_zapcel_validation')->where('client_id', $clientId)->first();
            if (!$v) { return json_encode(['success' => false, 'error' => $this->LANG['validation_not_found']]); }

            $statusBadge = '';
            switch ($v->status) {
                case 'validated':
                    $statusBadge = '<span class="badge badge-success">' . $this->LANG['validated'] . '</span>';
                    break;
                case 'pending':
                    $statusBadge = '<span class="badge badge-warning">' . $this->LANG['pending'] . '</span>';
                    break;
                case 'blocked':
                    $statusBadge = '<span class="badge badge-danger">' . $this->LANG['blocked'] . '</span>';
                    break;
                case 'expired':
                    $statusBadge = '<span class="badge badge-secondary">' . $this->LANG['expired'] . '</span>';
                    break;
            }

            $html = '
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <td><strong>' . $this->LANG['status'] . '</strong></td>
                        <td>' . $statusBadge . '</td>
                    </tr>
                    <tr>
                        <td><strong>' . $this->LANG['phone'] . '</strong></td>
                        <td>' . htmlspecialchars($v->phone_number) . '</td>
                    </tr>
                    <tr>
                        <td><strong>' . $this->LANG['attempts'] . '</strong></td>
                        <td>' . (int)$v->attempts. '</td>
                    </tr>
                    <tr>
                        <td><strong>' . $this->LANG['updated_at'] . '</strong></td>
                        <td>' .  date('d/m/Y H:i', strtotime($v->updated_at)) . '</td>
                    </tr>
                </tbody>
            </table>';

            return json_encode(['success' => true, 'html' => $html]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxResetValidation() {
        try {
            $clientId = (int)($_POST['client_id'] ?? 0);
            if (!$clientId) { return json_encode(['success' => false, 'error' => $this->LANG['invalid_client']]); }
            $settings = $this->getSettings();
            $nv = new \WHMCS\Module\Addon\Zapcel\Api\NumberValidator($settings);
            $r = $nv->resetValidation($clientId);
            return json_encode($r);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxSendPendingValidations() {
        try {
            $settings = $this->getSettings();
            $nv = new \WHMCS\Module\Addon\Zapcel\Api\NumberValidator($settings);
            $r = $nv->sendPendingValidations(50);
            if (!empty($r['success'])) {
                return json_encode(['success' => true, 'message' => $this->LANG['processed'] . ': '.$r['results']['total'].' ' . $this->LANG['pending']]);
            }
            return json_encode(['success' => false, 'error' => $r['error'] ?? $this->LANG['failure']]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxInstallGateway() {
        try {
            $gatewayId = trim($_POST['gateway_id'] ?? '');
            if ($gatewayId === '') { return json_encode(['success' => false, 'error' => $this->LANG['invalid_gateway']]); }

            $exists = Capsule::table('mod_zapcel_gateways')->where('gateway_id', $gatewayId)->first();
            if ($exists) {
                return json_encode(['success' => true, 'message' => $this->LANG['already_installed']]);
            }

            Capsule::table('mod_zapcel_gateways')->insert([
                'gateway_id' => $gatewayId,
                'name' => ucfirst(str_replace('_',' ',$gatewayId)),
                'config' => json_encode([]),
                'active' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return json_encode(['success' => true, 'message' => $this->LANG['gateway_installed']]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxToggleGateway() {
        try {
            $id = (int)($_POST['gateway_id'] ?? 0);
            $action = $_POST['action'] ?? '';
            if (!$id || !in_array($action, ['enable','disable'], true)) {
                return json_encode(['success' => false, 'error' => $this->LANG['invalid_parameters']]);
            }
            $active = $action === 'enable' ? 1 : 0;
            Capsule::table('mod_zapcel_gateways')->where('id', $id)->update(['active' => $active, 'updated_at' => date('Y-m-d H:i:s')]);
            return json_encode(['success' => true, 'active' => (bool)$active]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxRemoveGateway() {
        try {
            $id = (int)($_POST['gateway_id'] ?? 0);
            if (!$id) { return json_encode(['success' => false, 'error' => $this->LANG['invalid_id']]); }
            Capsule::table('mod_zapcel_gateways')->where('id', $id)->delete();
            return json_encode(['success' => true, 'message' => $this->LANG['gateway_removed']]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxGetLogDetails() {
    try {
        $id = (int)($_POST['log_id'] ?? 0);
        if (!$id) { 
            return json_encode(['success' => false, 'error' => $this->LANG['invalid_id']]); 
        }
        
        $log = Capsule::table('mod_zapcel_logs as l')
            ->leftJoin('tblclients as c', 'l.client_id', '=', 'c.id')
            ->select('l.*', 'c.firstname', 'c.lastname', 'c.email', 'c.phonenumber')
            ->where('l.id', $id)
            ->first();
            
        if (!$log) { 
            return json_encode(['success' => false, 'error' => $this->LANG['log_not_found']]); 
        }

        $html = '<div class="row">';
        
        // Informações básicas
        $html .= '<div class="col-md-6">';
        $html .= '<h6>' . $this->LANG['basic_information'] . '</h6>';
        $html .= '<table class="table table-sm table-borderless">';
        $html .= '<tr><td width="40%"><strong>' . $this->LANG['date_time'] . ':</strong></td><td>' . date('d/m/Y H:i:s', strtotime($log->created_at)) . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->LANG['event_type'] . ':</strong></td><td><span class="badge bg-' . $this->getLogTypeBadge($log->event_type) . '">' . $this->getEventTypeDisplayName($log->event_type) . '</span></td></tr>';
        $html .= '<tr><td><strong>' . $this->LANG['status'] . ':</strong></td><td>' . ($log->success ? '<span class="badge bg-success">' . $this->LANG['success'] . '</span>' : '<span class="badge bg-danger">' . $this->LANG['error'] . '</span>') . '</td></tr>';
        
        if ($log->client_id) {
            $html .= '<tr><td><strong>' . $this->LANG['client'] . ':</strong></td><td>';
            $html .= '<a href="clientssummary.php?userid=' . $log->client_id . '" target="_blank">';
            $html .= '#' . $log->client_id . ' - ' . htmlspecialchars($log->firstname . ' ' . $log->lastname);
            $html .= '</a></td></tr>';
            $html .= '<tr><td><strong>' . $this->LANG['phone'] . ':</strong></td><td>' . htmlspecialchars($log->phone_number) . '</td></tr>';
        }
        
        $html .= '</table>';
        $html .= '</div>';
        
        // Mensagem
        $html .= '<div class="col-md-12 mt-3">';
        $html .= '<h6>' . $this->LANG['message'] . '</h6>';
        $html .= '<div class="border rounded p-3 bg-light">';
        $html .= '<pre style="white-space: pre-wrap; font-family: inherit; margin: 0;">' . htmlspecialchars($log->message) . '</pre>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Resposta da API
        if ($log->response) {
            $html .= '<div class="col-md-12 mt-3">';
            $html .= '<h6>' . $this->LANG['api_response'] . '</h6>';
            $html .= '<div class="border rounded p-3">';
            
            $responseData = json_decode($log->response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($responseData)) {
                $html .= '<pre style="white-space: pre-wrap; font-size: 12px; margin: 0;">' . htmlspecialchars(json_encode($responseData, JSON_PRETTY_PRINT)) . '</pre>';
            } else {
                $html .= '<pre style="white-space: pre-wrap; margin: 0;">' . htmlspecialchars($log->response) . '</pre>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';

        return json_encode(['success' => true, 'html' => $html]);
    } catch (\Throwable $e) {
        return json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

    private function ajaxDeleteLog() {
        try {
            $id = (int)($_POST['log_id'] ?? 0);
            if (!$id) { return json_encode(['success' => false, 'error' => $this->LANG['invalid_id']]); }
            Capsule::table('mod_zapcel_logs')->where('id', $id)->delete();
            return json_encode(['success' => true, 'message' => $this->LANG['log_deleted']]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxClearLogs() {
        try {
            Capsule::table('mod_zapcel_logs')->truncate();
            return json_encode(['success' => true, 'message' => $this->LANG['logs_cleared']]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxSaveSettings() {
        try {
            // Remove subaction do POST
            $data = $_POST;
            unset($data['subaction']);

            // === CORREÇÃO URGENTE: REMOVE CAMPOS AUTOMÁTICOS DO WHMCS ===
            foreach ($data as $key => $value) {
                if (strpos($key, 'country-calling-code-') === 0) {
                    unset($data[$key]);
                }
            }
            // === FIM DA CORREÇÃO ===
            
            if (!$data) { 
                return json_encode(['success' => false, 'error' => $this->LANG['invalid_data']]); 
            }
            
            // Lista de campos que são checkboxes (devem ser tratados explicitamente)
            $checkboxFields = [
                'require_validation',
                'enable_logging',
                'zapcel_floating_button',
                'zapcel_hide_mobile',
                'zapcel_validation'
            ];
            
            // Para cada campo checkbox, se não foi enviado, define como 0
            foreach ($checkboxFields as $checkboxField) {
                if (!isset($data[$checkboxField])) {
                    $data[$checkboxField] = '0';
                }
            }
            
            // Validações e padrões para campos específicos
            $validations = [
                'status' => ['default' => 'active', 'options' => ['active', 'inactive']],
                'message_delay' => ['default' => '2', 'min' => 1, 'max' => 10],
                'max_attempts' => ['default' => '3', 'min' => 1, 'max' => 5],
                'language' => ['default' => 'portuguese', 'options' => ['portuguese', 'english']],
                'log_retention_days' => ['default' => '30', 'min' => 1, 'max' => 365],
                'validation_template' => ['default' => '']
            ];
            
            // Aplica validações e padrões
            foreach ($validations as $field => $rules) {
                if (isset($data[$field])) {
                    // Para campos com opções fixas
                    if (isset($rules['options']) && !in_array($data[$field], $rules['options'])) {
                        $data[$field] = $rules['default'];
                    }
                    
                    // Para campos numéricos
                    if (isset($rules['min']) && isset($rules['max'])) {
                        $value = intval($data[$field]);
                        if ($value < $rules['min'] || $value > $rules['max']) {
                            $data[$field] = $rules['default'];
                        } else {
                            $data[$field] = (string)$value;
                        }
                    }
                } else {
                    // Se campo não foi enviado, usa valor padrão
                    $data[$field] = $rules['default'];
                }
            }

            // Para o campo zapcel_company_phone_full, mantenha o valor exato que o usuário digitou
            if (isset($data['zapcel_company_phone_full'])) {
                // Remove TODOS os caracteres não numéricos para salvar apenas números
                $data['zapcel_company_phone_full'] = preg_replace('/[^\d]/', '', trim($data['zapcel_company_phone_full']));
            } else {
                $data['zapcel_company_phone_full'] = '';
            }
            
            // Salva todos os campos no banco de dados
            $savedFields = [];
            foreach ($data as $key => $value) {
                // Remove espaços em branco do início e fim
                $value = trim($value);
                
                Capsule::table('tbladdonmodules')->updateOrInsert(
                    ['module' => 'zapcel', 'setting' => $key],
                    ['value' => $value]
                );
                
                $savedFields[] = $key;
            }
            
            // Log para debug (opcional)
            /*Capsule::table('mod_zapcel_logs')->insert([
                'event_type' => 'settings_saved',
                'message' => 'Configurações salvas: ' . implode(', ', $savedFields),
                'success' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);*/
            
            return json_encode([
                'success' => true, 
                'message' => $this->LANG['settings_saved'],
                'saved_fields' => $savedFields // Para debug
            ]);
            
        } catch (\Throwable $e) {
            // Log do erro
            Capsule::table('mod_zapcel_logs')->insert([
                'event_type' => 'settings_error',
                'message' => 'Erro ao salvar configurações: ' . $e->getMessage(),
                'success' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxTestConnection()
    {
        try {

            // Se o objeto não foi carregado (é null), carregamos manualmente:
            if ($this->whatsappAPI === null) {
                $this->whatsappAPI = new WhatsAppAPI($this->getSettings());
            }

            // Agora SIM o objeto existe e podemos chamar:
            $result = $this->whatsappAPI->checkInstanceStatus();

            return json_encode($result);

        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ]);
        }
    }

    private function ajaxClearCache() {
        try {
            $this->statsManager->clearCache();
            return json_encode(['success' => true, 'message' => $this->LANG['cache_cleared']]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxSyncTemplates() {
        try {
            // stub: apenas informa sucesso
            return json_encode(['success' => true, 'message' => $this->LANG['templates_synced']]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ajaxSendTestMessage() {
        try {
            $clientId = (int)($_POST['client_id'] ?? 0);
            $simulate = !empty($_POST['simulate_only']);
            $message = trim($_POST['custom_message'] ?? '');

            if (!$clientId) { return json_encode(['success' => false, 'error' => $this->LANG['invalid_client']]); }
            if ($message === '') { return json_encode(['success' => false, 'error' => $this->LANG['empty_message']]); }

            // Carrega cliente
            $client = Capsule::table('tblclients')->where('id', $clientId)->first();
            if (!$client) { return json_encode(['success' => false, 'error' => $this->LANG['client_not_found']]); }

            // Substituição apenas de variáveis básicas
            $clientName = trim($client->firstname . ' ' . $client->lastname);
            $companyName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value') ?: $this->LANG['our_provider'];
            
            $msg = str_replace(['{cliente}', '{provedor}'], [$clientName, $companyName], $message);

            // Número de destino
            $phoneNumber = preg_replace('/\D+/', '', (string)$client->phonenumber);
            
            // Se o número tem 10 ou 11 dígitos (DDD + telefone), adiciona +55
            // Se tem 12 ou 13 dígitos, já tem o 55, só adiciona o +
            if (strlen($phoneNumber) == 10 || strlen($phoneNumber) == 11) {
                $to = '+55' . $phoneNumber;
            } else {
                $to = '+' . $phoneNumber;
            }

            // Monta HTML de resposta
            $html  = '<div class="alert alert-info">';
            $html .= '<h5><i class="fas fa-eye"></i> ' . $this->LANG['message_preview_title'] . '</h5>';
            $html .= '<div style="background: white; padding: 15px; border-radius: 5px; margin-top: 10px;">';
            $html .= nl2br(htmlspecialchars($msg));
            $html .= '</div></div>';
            
            $html .= '<div class="alert alert-secondary">';
            $html .= '<strong>' . $this->LANG['recipient'] . ':</strong> ' . htmlspecialchars($clientName) . ' (' . htmlspecialchars($to) . ')';
            $html .= '</div>';
            
            if (!$simulate) {
                // Inicializa WhatsAppAPI se necessário
                if (!$this->whatsappAPI) {
                    $this->whatsappAPI = new WhatsAppAPI($this->getSettings());
                }
                $send = $this->whatsappAPI->sendMessage($to, $msg, ['type' => 'test', 'client_id' => $clientId]);
                
                // Registra log do envio
                Capsule::table('mod_zapcel_logs')->insert([
                    'client_id' => $clientId,
                    'event_type' => 'test_message',
                    'phone_number' => $to,
                    'message' => $msg,
                    'success' => !empty($send['success']) ? 1 : 0,
                    'response' => json_encode($send),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                
                if (empty($send['success'])) {
                    return json_encode(['success' => false, 'error' => $send['error'] ?? $this->LANG['send_failure']]);
                }

                $adminName = $_SESSION['adminid'] ? Capsule::table('tbladmins')->where('id', $_SESSION['adminid'])->value('username') : 'Unknown';
                logActivity("Zapcel: " . $this->LANG['test_message_sent_by_admin'] . " ({$adminName}) para {$clientName}");

                $html .= '<div class="alert alert-success"><i class="fas fa-check-circle"></i> <strong>' . $this->LANG['message_sent_successfully'] . '</strong></div>';
            } else {
                // Registra log da simulação
                Capsule::table('mod_zapcel_logs')->insert([
                    'client_id' => $clientId,
                    'event_type' => 'test_message_simulation',
                    'phone_number' => $to,
                    'message' => $msg,
                    'success' => 1,
                    'response' => json_encode(['simulated' => true]),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $html .= '<div class="alert alert-warning"><i class="fas fa-info-circle"></i> <strong>' . $this->LANG['simulation_mode'] . '</strong></div>';
            }

            return json_encode(['success' => true, 'html' => $html]);
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Envia lembrete de fatura via WhatsApp
     */
    private function ajaxSendInvoiceReminder()
    {
        try {
            $invoiceId = (int)($_POST['invoice_id'] ?? 0);
            
            if (!$invoiceId) {
                return json_encode(['success' => false, 'error' => $this->LANG['invoice_id_not_provided']]);
            }
            
            // Busca dados da fatura
            $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
            if (!$invoice) {
                return json_encode(['success' => false, 'error' => $this->LANG['invoice_not_found']]);
            }
            
            $clientId = $invoice->userid;
            
            // Busca cliente
            $client = Capsule::table('tblclients')->where('id', $clientId)->first();
            if (!$client) {
                return json_encode(['success' => false, 'error' => $this->LANG['client_not_found']]);
            }
            
            // Busca template de lembrete de fatura
            $template = Capsule::table('mod_zapcel_templates')
                ->where('trigger_event', 'invoice_reminder')
                ->where('active', true)
                ->first();
                
            if (!$template) {
                return json_encode(['success' => false, 'error' => $this->LANG['reminder_template_not_found']]);
            }
            
            // Obtém configurações
            $settings = $this->getSettings();
            
            // Obtém número do cliente
            $phoneNumber = $client->phonenumber;
            if (!$phoneNumber) {
                return json_encode(['success' => false, 'error' => $this->LANG['client_no_phone']]);
            }
            
            // Formata número
            $phoneNumber = preg_replace('/\D+/', '', $phoneNumber);
            $len = strlen($phoneNumber);
            if ($len == 10 || $len == 11) {
                $phoneNumber = '+55' . $phoneNumber;
            } elseif ($len == 12 || $len == 13) {
                $phoneNumber = '+' . $phoneNumber;
            }
            
            // Busca dados de pagamento (PIX/Boleto)
            $invoiceData = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
            $codigoPix = '';
            $linhaDigitavel = '';
            $qrcodeUrl = '';
            
            if ($invoiceData['result'] == 'success') {
                // Tenta buscar PIX
                $pixData = Capsule::table('tblaccounts')
                    ->where('invoiceid', $invoiceId)
                    ->where('gateway', 'LIKE', '%pix%')
                    ->first();
                    
                if ($pixData && !empty($pixData->description)) {
                    // Extrai código PIX da descrição
                    if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[A-Z0-9]{32,})/i', $pixData->description, $matches)) {
                        $codigoPix = $matches[1];
                    }
                }
                
                // Tenta buscar boleto
                $boletoData = Capsule::table('mod_boleto')
                    ->where('invoice_id', $invoiceId)
                    ->first();
                    
                if ($boletoData && !empty($boletoData->linha_digitavel)) {
                    $linhaDigitavel = $boletoData->linha_digitavel;
                }
            }
            
            // Monta variáveis
            $variables = [
                'cliente' => trim($client->firstname . ' ' . $client->lastname),
                'numero_fatura' => $invoice->invoicenum,
                'valor' => 'R$ ' . number_format($invoice->total, 2, ',', '.'),
                'vencimento' => date('d/m/Y', strtotime($invoice->duedate)),
                'descricao' => $invoiceData['items'][0]['description'] ?? 'Fatura',
                'codigopix' => $codigoPix,
                'linhadigitavel' => $linhaDigitavel,
                'link_fatura' => $settings['zapcel_client_area_url'] . '/viewinvoice.php?id=' . $invoiceId,
                'assinatura' => $settings['zapcel_signature'],
                'provedor' => $settings['zapcel_company_name'],
                'quebrar_mensagem' => "\n\n" // Quebra de mensagem
            ];
            
            // Processa template
            require_once __DIR__ . '/../api/MessageProcessor.php';
            $processor = new MessageProcessor($settings);
            $message = $processor->processTemplate($template->template, $variables);
            
            // Envia mensagem
            require_once __DIR__ . '/../api/WhatsAppAPI.php';
            $api = new WhatsAppAPI($settings);
            $result = $api->sendMessage($phoneNumber, $message);
            
            // Registra log
            Capsule::table('mod_zapcel_logs')->insert([
                'client_id' => $clientId,
                'event_type' => 'invoice_reminder_manual',
                'phone_number' => $phoneNumber,
                'message' => $message,
                'success' => $result['success'] ? 1 : 0,
                'response' => json_encode($result),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            
            if ($result['success']) {
                return json_encode([
                    'success' => true,
                    'message' => $this->LANG['reminder_sent_success'] . ' ' . $client->firstname . ' (' . $phoneNumber . ')'
                ]);
            } else {
                return json_encode([
                    'success' => false,
                    'error' => $result['error'] ?? $this->LANG['send_failure']
                ]);
            }
            
        } catch (\Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

}

// Fim da classe AdminDispatcher
// O dispatcher é instanciado e executado pela função zapcel_output() no arquivo zapcel.php 4719