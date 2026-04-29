<?php
/**
 * Zapcel WHMCS - Auto Login API
 * Com método getStatistics()
 * 
 * @package    Zapcel
 * @version    4.1.0
 */

namespace WHMCS\Module\Addon\Zapcel\Api;

use function \zapcel_trans;

// Bloqueia acesso direto
if (!defined('WHMCS')) {
    die(zapcel_trans('access_denied'));
}

use WHMCS\Database\Capsule;

class AutoLogin
{
    private $settings;

    public function __construct($settings = [])
    {
        $this->settings = $settings;
    }

    /**
     * Gera token para fatura
     */
    public function generateInvoiceToken($clientId, $invoiceId, $expiresInHours = 72)
    {
        if (empty($clientId) || !is_numeric($clientId)) {
            return ['success' => false, 'error' => zapcel_trans('invalid_client')];
        }

        $expirationTime = $expiresInHours * 3600;
        
        // Verificar se já existe token ativo
        $tokenData = Capsule::table('mod_zapcel_autologin')
            ->where('client_id', $clientId)
            ->where('target_type', 'invoice')
            ->where('target_id', $invoiceId)
            ->where('status', 'active')
            ->first();
        
        if ($tokenData && strtotime($tokenData->expires_at) > time()) {
            $token = $tokenData->token;
            $expiresAt = $tokenData->expires_at;
        } else {
            if ($tokenData) {
                Capsule::table('mod_zapcel_autologin')->where('id', $tokenData->id)->delete();
            }
            
            $token = md5(uniqid(rand(), true));
            $expiresAt = date('Y-m-d H:i:s', time() + $expirationTime);
            
            Capsule::table('mod_zapcel_autologin')->insert([
                'client_id' => $clientId,
                'token' => $token,
                'target_type' => 'invoice',
                'target_id' => $invoiceId,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'access_count' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // DEBUG - REMOVER DEPOIS
        error_log('AutoLogin Settings: ' . print_r($this->settings, true));
        error_log('AutoLogin Domain: ' . ($this->settings['autologin_domain'] ?? 'VAZIO'));

        /*$whmcsUrl = Capsule::table('tblconfiguration')
            ->where('setting', 'SystemURL')
            ->value('value');
        
        //$whmcsUrl = str_replace('/central', '', $whmcsUrl);
        $url = rtrim($whmcsUrl, '/') . "/autologin.php?token=$token";*/

        $whmcsUrl = !empty($this->settings['autologin_domain']) 
            ? $this->settings['autologin_domain'] 
            : Capsule::table('tblconfiguration')
                ->where('setting', 'SystemURL')
                ->value('value');

        $url = rtrim($whmcsUrl, '/') . "/autologin.php?token=$token";
        
        return [
            'success' => true,
            'token' => $token,
            'url' => $url,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Gera token para ticket
     */
    public function generateTicketToken($clientId, $ticketId, $expiresInHours = 72)
    {
        if (empty($clientId) || !is_numeric($clientId)) {
            return ['success' => false, 'error' => zapcel_trans('invalid_client')];
        }

        $ticket = Capsule::table('tbltickets')->where('id', $ticketId)->first();
        
        if (!$ticket) {
            return ['success' => false, 'error' => zapcel_trans('ticket_not_found')];
        }

        $expirationTime = $expiresInHours * 3600;
        
        $tokenData = Capsule::table('mod_zapcel_autologin')
            ->where('client_id', $clientId)
            ->where('target_type', 'ticket')
            ->where('target_id', $ticketId)
            ->where('status', 'active')
            ->first();
        
        if ($tokenData && strtotime($tokenData->expires_at) > time()) {
            $token = $tokenData->token;
            $expiresAt = $tokenData->expires_at;
        } else {
            if ($tokenData) {
                Capsule::table('mod_zapcel_autologin')->where('id', $tokenData->id)->delete();
            }
            
            $token = md5(uniqid(rand(), true));
            $expiresAt = date('Y-m-d H:i:s', time() + $expirationTime);
            
            Capsule::table('mod_zapcel_autologin')->insert([
                'client_id' => $clientId,
                'token' => $token,
                'target_type' => 'ticket',
                'target_id' => $ticketId,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'access_count' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        /*$whmcsUrl = Capsule::table('tblconfiguration')
            ->where('setting', 'SystemURL')
            ->value('value');
        
        //$whmcsUrl = str_replace('/central', '', $whmcsUrl);
        $url = rtrim($whmcsUrl, '/') . "/autologin.php?token=$token";*/

        $whmcsUrl = !empty($this->settings['autologin_domain']) 
            ? $this->settings['autologin_domain'] 
            : Capsule::table('tblconfiguration')
                ->where('setting', 'SystemURL')
                ->value('value');

        $url = rtrim($whmcsUrl, '/') . "/autologin.php?token=$token";
        
        return [
            'success' => true,
            'token' => $token,
            'url' => $url,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Retorna estatísticas do auto login
     */
    public function getStatistics()
    {
        // Total de tokens
        $total = Capsule::table('mod_zapcel_autologin')->count();
        
        // Tokens ativos (não expirados)
        $active = Capsule::table('mod_zapcel_autologin')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->count();
        
        // Tokens expirados
        $expired = Capsule::table('mod_zapcel_autologin')
            ->where('expires_at', '<=', date('Y-m-d H:i:s'))
            ->count();
        
        // Total de acessos
        $totalAccesses = Capsule::table('mod_zapcel_autologin')
            ->sum('access_count');
        
        // Tokens mais acessados (top 5)
        $mostAccessed = Capsule::table('mod_zapcel_autologin as a')
            ->join('tblclients as c', 'a.client_id', '=', 'c.id')
            ->select('a.*', 'c.firstname', 'c.lastname', 'c.email')
            ->orderBy('a.access_count', 'desc')
            ->limit(5)
            ->get();
        
        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'total_accesses' => $totalAccesses ?? 0,
            'most_accessed' => $mostAccessed
        ];
    }
}

