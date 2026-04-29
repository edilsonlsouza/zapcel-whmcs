<?php
namespace WHMCS\Module\Addon\Zapcel\Gateways;

// SE A INTERFACE NÃO EXISTIR, CARREGA
if (!interface_exists('WHMCS\Module\Addon\Zapcel\Gateways\GatewayInterface')) {
    require_once __DIR__ . '/GatewayInterface.php';
}

use WHMCS\Database\Capsule;

abstract class AbstractGateway implements GatewayInterface
{
    protected $config;

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $config = Capsule::table('mod_zapcel_gateways')
            ->where('gateway_name', $this->getGatewayId())
            ->first();

        if ($config) {
            $this->config = json_decode($config->config, true) ?? [];
        } else {
            $this->config = [];
        }
    }

    public function saveConfig(array $config): bool
    {
        try {
            $existing = Capsule::table('mod_zapcel_gateways')
                ->where('gateway_name', $this->getGatewayId())
                ->first();

            $data = [
                'gateway' => $this->getGatewayId(),
                'name' => $this->getGatewayName(),
                'config' => json_encode($config),
                'active' => $config['active'] ?? false,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($existing) {
                Capsule::table('mod_zapcel_gateways')
                    ->where('gateway_name', $this->getGatewayId())
                    ->update($data);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                Capsule::table('mod_zapcel_gateways')
                    ->insert($data);
            }

            $this->config = $config;
            return true;

        } catch (\Exception $e) {
            $this->logError('Erro ao salvar configurações: ' . $e->getMessage());
            return false;
        }
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isConfigured(): bool
    {
        $requiredFields = $this->getRequiredConfigFields();
        
        foreach ($requiredFields as $field) {
            if (empty($this->config[$field])) {
                return false;
            }
        }

        return !empty($this->config['active']);
    }

    abstract protected function getRequiredConfigFields(): array;

    // Métodos de log (já existentes no seu código)
    protected function logAction(string $action, string $message, array $details = [], string $status = 'info'): void
    {
        try {
            Capsule::table('mod_zapcel_logs')->insert([
                'type' => 'gateway_' . $this->getGatewayId() . '_' . $action,
                'message' => $message,
                'details' => json_encode(array_merge($details, ['gateway' => $this->getGatewayId()])),
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Falha silenciosa
        }
    }

    protected function logError(string $message, array $details = []): void
    {
        $this->logAction('error', $message, $details, 'error');
    }

    protected function logSuccess(string $message, array $details = []): void
    {
        $this->logAction('success', $message, $details, 'success');
    }
}