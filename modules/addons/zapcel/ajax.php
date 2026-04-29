<?php
/**
 * Zapcel WHMCS - Handler AJAX Independente
 * Este arquivo processa todas as requisições AJAX do módulo
 * sem passar pelo sistema de dispatch do WHMCS
 */

// Limpa qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}

// Define header JSON
header('Content-Type: application/json');

// Carrega o init.php do WHMCS para ter acesso ao Capsule
require_once __DIR__ . '/../../init.php';

// Verifica se é admin
use WHMCS\Session;
if (!Session::get('adminid')) {
    echo json_encode(['success' => false, 'error' => 'Acesso não autorizado']);
    exit;
}

use WHMCS\Database\Capsule;

// Pega a subaction
$subaction = $_POST['subaction'] ?? '';

try {
    switch ($subaction) {
        case 'toggle_template':
            $id = (int)($_POST['template_id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                exit;
            }

            $tpl = Capsule::table('mod_zapcel_templates')->where('id', $id)->first();
            if (!$tpl) {
                echo json_encode(['success' => false, 'error' => 'Template não encontrado']);
                exit;
            }

            $new = $tpl->active ? 0 : 1;
            Capsule::table('mod_zapcel_templates')
                ->where('id', $id)
                ->update([
                    'active' => $new,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            
            echo json_encode(['success' => true, 'active' => (bool)$new]);
            break;

        case 'delete_template':
            $id = (int)($_POST['template_id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                exit;
            }
            
            Capsule::table('mod_zapcel_templates')->where('id', $id)->delete();
            echo json_encode(['success' => true, 'message' => 'Template removido']);
            break;

        case 'create_template':
            $name = trim($_POST['name'] ?? '');
            $trigger = trim($_POST['trigger_event'] ?? '');
            $content = trim($_POST['template'] ?? '');
            $active = (int)($_POST['active'] ?? 0);
            
            if ($name === '' || $trigger === '' || $content === '') {
                echo json_encode(['success' => false, 'error' => 'Preencha nome, evento e conteúdo']);
                exit;
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
            
            echo json_encode(['success' => true, 'message' => 'Template criado', 'template_id' => $templateId]);
            break;

        case 'update_template':
            $id = (int)($_POST['template_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $content = trim($_POST['template'] ?? '');
            $active = isset($_POST['active']) && $_POST['active'] == '1' ? 1 : 0;
            
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID do template não informado']);
                exit;
            }
            
            if ($name === '') {
                echo json_encode(['success' => false, 'error' => 'Nome do template é obrigatório']);
                exit;
            }
            
            if ($content === '') {
                echo json_encode(['success' => false, 'error' => 'Conteúdo do template é obrigatório']);
                exit;
            }
            
            Capsule::table('mod_zapcel_templates')
                ->where('id', $id)
                ->update([
                    'name' => $name,
                    'template' => $content,
                    'active' => $active,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            echo json_encode(['success' => true, 'message' => 'Template atualizado']);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Ação não reconhecida: ' . $subaction]);
            break;
    }
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Error $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit;

