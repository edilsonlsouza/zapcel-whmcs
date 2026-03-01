<?php
namespace WHMCS\Module\Addon\Zapcel\Gateways;

/**
 * Zapcel WHMCS - Interface para Gateways de Pagamento
 * 
 * @package    Zapcel
 * @author     Hostcel
 * @version    2.0.0
 */


/**
 * Interface comum para gateways de pagamento
 */
interface GatewayInterface
{
    /**
     * Retorna identificador único do gateway
     */
    public function getGatewayId(): string;

    /**
     * Retorna nome amigável do gateway
     */
    public function getGatewayName(): string;

    /**
     * Verifica se o gateway está configurado e ativo
     */
    public function isConfigured(): bool;

    /**
     * Obtém configurações do gateway
     */
    public function getConfig(): array;

    /**
     * Salva configurações do gateway
     */
    public function saveConfig(array $config): bool;

    /**
     * Extrai dados do PIX de uma fatura
     */
    public function extractPixData($invoiceId): array;

    /**
     * Extrai dados do boleto de uma fatura
     */
    public function extractBoletoData($invoiceId): array;

    /**
     * Verifica status de uma fatura
     */
    public function checkInvoiceStatus($invoiceId): array;

    /**
     * Testa conexão com o gateway
     */
    public function testConnection(): array;

    /**
     * Sincroniza faturas pendentes
     */
    public function syncPendingInvoices($limit = 50): array;

    /**
     * Obtém estatísticas do gateway
     */
    public function getStatistics(): array;
}

/**
 * Classe base abstrata para gateways
 */
abstract class AbstractGateway implements GatewayInterface
{
    protected $config;
    protected $gatewayId;
    protected $gatewayName;

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Carrega configurações do banco de dados
     */
    protected function loadConfig(): void
    {
        $config = \WHMCS\Database\Capsule::table('mod_zapcel_gateways')
            ->where('gateway_name', $this->getGatewayId())
            ->first();

        if ($config) {
            $this->config = json_decode($config->config, true) ?? [];
        } else {
            $this->config = [];
        }
    }

    /**
     * Salva configurações no banco de dados
     */
    public function saveConfig(array $config): bool
    {
        try {
            $existing = \WHMCS\Database\Capsule::table('mod_zapcel_gateways')
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
                \WHMCS\Database\Capsule::table('mod_zapcel_gateways')
                    ->where('gateway_name', $this->getGatewayId())
                    ->update($data);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                \WHMCS\Database\Capsule::table('mod_zapcel_gateways')
                    ->insert($data);
            }

            $this->config = $config;
            return true;

        } catch (\Exception $e) {
            $this->logError('Erro ao salvar configurações: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém configurações do gateway
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Verifica se o gateway está configurado
     */
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

    /**
     * Retorna campos de configuração necessários
     */
    abstract protected function getRequiredConfigFields(): array;

    /**
     * Valida configurações do gateway
     */
    public function validateConfig(array $config): array
    {
        $errors = [];
        $requiredFields = $this->getRequiredConfigFields();

        foreach ($requiredFields as $field) {
            if (empty($config[$field])) {
                $errors[] = "O campo '{$field}' é obrigatório";
            }
        }

        return $errors;
    }

    /**
     * Processa template com variáveis
     */
    protected function processTemplate($template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    /**
     * Registra log do gateway
     */
    protected function logAction(string $action, string $message, array $details = [], string $status = 'info'): void
    {
        try {
            \WHMCS\Database\Capsule::table('mod_zapcel_logs')->insert([
                'type' => 'gateway_' . $this->getGatewayId() . '_' . $action,
                'message' => $message,
                'details' => json_encode(array_merge($details, ['gateway' => $this->getGatewayId()])),
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Falha silenciosa no log
        }
    }

    /**
     * Registra erro do gateway
     */
    protected function logError(string $message, array $details = []): void
    {
        $this->logAction('error', $message, $details, 'error');
    }

    /**
     * Registra sucesso do gateway
     */
    protected function logSuccess(string $message, array $details = []): void
    {
        $this->logAction('success', $message, $details, 'success');
    }

    /**
     * Faz requisição HTTP
     */
    protected function makeHttpRequest(string $url, array $options = []): array
    {
        $defaultOptions = [
            'timeout' => 30,
            'headers' => [],
            'verify_ssl' => true,
            'post_data' => null,          // opcional
            'custom_request' => null      // opcional: 'PUT', 'DELETE', etc.
        ];
        $options = array_merge($defaultOptions, $options);

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $options['timeout'],
                CURLOPT_SSL_VERIFYPEER => $options['verify_ssl'],
                CURLOPT_FOLLOWLOCATION => true
            ]);

            if (!empty($options['headers'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
            }

            if ($options['post_data'] !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $options['post_data']);
            }

            if (!empty($options['custom_request'])) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $options['custom_request']);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception('cURL Error: ' . $error);
            }

            return [
                'success' => $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'data' => $response,
                'error' => null
            ];
        } catch (\Exception $e) {
            $this->logError('Erro na requisição HTTP: ' . $e->getMessage(), [
                'url' => $url,
                'options' => ['has_post_data' => $options['post_data'] !== null, 'custom_request' => $options['custom_request']]
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Formata valor para padrão monetário
     */
    protected function formatCurrency($value): string
    {
        return number_format($value, 2, ',', '.');
    }

    /**
     * Formata data para padrão brasileiro
     */
    protected function formatDate($date): string
    {
        return date('d/m/Y', strtotime($date));
    }

    /**
     * Formata data e hora para padrão brasileiro
     */
    protected function formatDateTime($datetime): string
    {
        return date('d/m/Y H:i', strtotime($datetime));
    }

    /**
     * Obtém dados da fatura do WHMCS
     */
    protected function getInvoiceData($invoiceId): array
    {
        try {
            $invoice = \WHMCS\Database\Capsule::table('tblinvoices')
                ->where('id', $invoiceId)
                ->first();

            if (!$invoice) {
                throw new \Exception("Fatura #{$invoiceId} não encontrada");
            }

            $client = \WHMCS\Database\Capsule::table('tblclients')
                ->where('id', $invoice->userid)
                ->first();

            $items = \WHMCS\Database\Capsule::table('tblinvoiceitems')
                ->where('invoiceid', $invoiceId)
                ->get();

            return [
                'invoice' => $invoice,
                'client' => $client,
                'items' => $items,
                'success' => true
            ];

        } catch (\Exception $e) {
            $this->logError('Erro ao obter dados da fatura: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Valida resposta do gateway
     */
    protected function validateGatewayResponse(array $response): array
    {
        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Erro desconhecido no gateway'
            ];
        }

        return [
            'success' => true,
            'data' => $response['data'] ?? null
        ];
    }
}