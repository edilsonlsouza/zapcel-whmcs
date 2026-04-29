<?php
namespace WHMCS\Module\Addon\Zapcel\Gateways;

use WHMCS\Database\Capsule;

class GatewayManager
{
    private $gateways = [];
    
    public function __construct()
    {
        $this->loadGateways();
    }
    
    private function loadGateways()
    {
        $gatewaysPath = __DIR__;
        $files = glob($gatewaysPath . '/*Gateway.php');
        
        // DEBUG: Log dos arquivos encontrados
        $this->logDebug('loadGateways', 'Arquivos encontrados', ['files' => $files]);
        
        foreach ($files as $file) {
            $gatewayName = basename($file, 'Gateway.php');
            
            // Ignora arquivos base
            if (in_array($gatewayName, ['Abstract', 'GatewayInterface', 'GatewayManager'])) {
                continue;
            }
            
            $className = "WHMCS\\Module\\Addon\\Zapcel\\Gateways\\{$gatewayName}Gateway";
            
            $this->logDebug('loadGateways', 'Tentando carregar gateway', [
                'file' => $file,
                'gatewayName' => $gatewayName,
                'className' => $className
            ]);
            
            // VERIFICA SE CLASSE JÁ EXISTE ANTES DE INCLUIR
            if (!class_exists($className)) {
                if (file_exists($file)) {
                    require_once $file;
                    $this->logDebug('loadGateways', 'Arquivo incluído manualmente', ['file' => $file]);
                }
            }
            
            if (class_exists($className)) {
                $gateway = new $className();
                if ($gateway instanceof GatewayInterface) {
                    $this->gateways[$gateway->getGatewayId()] = $gateway;
                    $this->logDebug('loadGateways', 'Gateway carregado com sucesso', [
                        'gatewayId' => $gateway->getGatewayId(),
                        'gatewayName' => $gateway->getGatewayName()
                    ]);
                }
            } else {
                $this->logDebug('loadGateways', 'Classe não existe', ['className' => $className]);
            }
        }
        
        $this->logDebug('loadGateways', 'Gateways carregados', [
            'total' => count($this->gateways),
            'gateways' => array_keys($this->gateways)
        ]);
    }
    
    public function getAvailableGateways(): array
    {
        return $this->gateways;
    }
    
    public function getGateway($gatewayId): ?GatewayInterface
    {
        $gateway = $this->gateways[$gatewayId] ?? null;
        $this->logDebug('getGateway', 'Buscando gateway', [
            'gatewayId' => $gatewayId,
            'found' => !is_null($gateway),
            'available' => array_keys($this->gateways)
        ]);
        return $gateway;
    }
    
    /**
     * Detecta automaticamente o gateway usado na fatura
     */
    public function getActiveGatewayForInvoice($invoiceId): ?GatewayInterface
    {
        $this->logDebug('getActiveGatewayForInvoice', 'Iniciando detecção', ['invoice_id' => $invoiceId]);
        
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) {
            $this->logDebug('getActiveGatewayForInvoice', 'Fatura não encontrada', ['invoice_id' => $invoiceId]);
            return null;
        }
        
        $paymentMethod = $invoice->paymentmethod;
        $this->logDebug('getActiveGatewayForInvoice', 'Payment method da fatura', [
            'invoice_id' => $invoiceId,
            'payment_method' => $paymentMethod
        ]);
        
        $gateway = $this->getGateway($paymentMethod);
        
        // Se não encontrou pelo ID exato, tenta mapeamento
        if (!$gateway) {
            $this->logDebug('getActiveGatewayForInvoice', 'Gateway não encontrado pelo ID exato, tentando mapeamento');
            $gateway = $this->findGatewayByAlias($paymentMethod);
        }
        
        $result = $gateway && $gateway->isConfigured() ? $gateway : null;
        
        $this->logDebug('getActiveGatewayForInvoice', 'Resultado final', [
            'invoice_id' => $invoiceId,
            'payment_method' => $paymentMethod,
            'gateway_found' => !is_null($result),
            'gateway' => $result ? $result->getGatewayId() : 'none',
            'gateway_configured' => $result ? $result->isConfigured() : false
        ]);
        
        return $result;
    }
    
    /**
     * Mapeia gateways do WHMCS para seus equivalentes
     */
    private function findGatewayByAlias($whmcsGateway): ?GatewayInterface
    {
        $aliasMap = [
            'iugupix' => 'iugupix',
            'iuguboleto' => 'iugupix',
            'iugu' => 'iugupix',
            'paghiperpix' => 'paghiper',
            'paghiperboleto' => 'paghiper',
            'mercadopago' => 'mercadopago',
        ];
        
        $gatewayId = $aliasMap[$whmcsGateway] ?? $whmcsGateway;
        
        $this->logDebug('findGatewayByAlias', 'Mapeamento', [
            'whmcs_gateway' => $whmcsGateway,
            'mapped_to' => $gatewayId
        ]);
        
        return $this->getGateway($gatewayId);
    }
    
    /**
     * Extrai dados de pagamento de forma dinâmica para qualquer gateway
    */
    public function extractPaymentData($invoiceId): array
    {
        $this->logDebug('extractPaymentData', 'Iniciando extração', ['invoice_id' => $invoiceId]);
        
        $data = [
            'pix_code' => '',
            'pix_qrcode' => '',
            'barcode' => '',
            'pdf_url' => '',
            'gateway' => 'unknown',
            'success' => false
        ];
        
        $gateway = $this->getActiveGatewayForInvoice($invoiceId);
        
        if (!$gateway) {
            $this->logDebug('extractPaymentData', 'Nenhum gateway ativo encontrado', ['invoice_id' => $invoiceId]);
            return $data;
        }
        
        $data['gateway'] = $gateway->getGatewayId();
        $data['gateway_name'] = $gateway->getGatewayName();
        
        $this->logDebug('extractPaymentData', 'Gateway selecionado', [
            'gateway' => $data['gateway'],
            'gateway_name' => $data['gateway_name']
        ]);
        
        // Tenta extrair dados PIX
        try {
            $this->logDebug('extractPaymentData', 'Chamando extractPixData', ['invoice_id' => $invoiceId]);
            $pixData = $gateway->extractPixData($invoiceId);
            
            $this->logDebug('extractPaymentData', 'Resposta extractPixData', [
                'success' => $pixData['success'] ?? false,
                'pix_data_structure' => $pixData ? array_keys($pixData) : []
            ]);
            
            if ($pixData && ($pixData['success'] ?? false)) {
                if (isset($pixData['pix']['codigo_pix'])) {
                    $data['pix_code'] = $pixData['pix']['codigo_pix'];
                    $data['pix_qrcode'] = $pixData['pix']['qr_code'] ?? '';
                    $data['success'] = true;
                    
                    $this->logDebug('extractPaymentData', 'Dados PIX extraídos', [
                        'pix_code_length' => strlen($data['pix_code']),
                        'pix_qrcode_exists' => !empty($data['pix_qrcode'])
                    ]);
                } 
                // Fallback para estrutura alternativa
                elseif (isset($pixData['codigo_pix'])) {
                    $data['pix_code'] = $pixData['codigo_pix'];
                    $data['pix_qrcode'] = $pixData['qrcode'] ?? '';
                    $data['success'] = true;
                }
            }
        } catch (\Exception $e) {
            $this->logDebug('extractPaymentData', 'ERRO ao extrair PIX', [
                'error' => $e->getMessage()
            ]);
        }
        
        // Tenta extrair dados Boleto
        try {
            $this->logDebug('extractPaymentData', 'Chamando extractBoletoData', ['invoice_id' => $invoiceId]);
            $boletoData = $gateway->extractBoletoData($invoiceId);
            
            if ($boletoData && ($boletoData['success'] ?? false)) {
                if (isset($boletoData['boleto']['codigo_barras'])) {
                    $data['barcode'] = $boletoData['boleto']['codigo_barras'];
                    $data['pdf_url'] = $boletoData['boleto']['url_pdf'] ?? '';
                    $data['success'] = true;
                }
                // Fallback para estrutura alternativa
                elseif (isset($boletoData['barcode'])) {
                    $data['barcode'] = $boletoData['barcode'];
                    $data['pdf_url'] = $boletoData['pdf_url'] ?? '';
                    $data['success'] = true;
                }
            }
        } catch (\Exception $e) {
            $this->logDebug('extractPaymentData', 'ERRO ao extrair Boleto', [
                'error' => $e->getMessage()
            ]);
        }
        
        $this->logDebug('extractPaymentData', 'Resultado final', [
            'invoice_id' => $invoiceId,
            'pix_code' => !empty($data['pix_code']) ? 'EXISTE' : 'VAZIO',
            'pix_qrcode' => !empty($data['pix_qrcode']) ? 'EXISTE' : 'VAZIO',
            'barcode' => !empty($data['barcode']) ? 'EXISTE' : 'VAZIO',
            'success' => $data['success']
        ]);
        
        return $data;
    }
    
    /**
     * Lista gateways configurados e ativos
     */
    public function getConfiguredGateways(): array
    {
        $configured = [];
        
        foreach ($this->gateways as $gatewayId => $gateway) {
            if ($gateway->isConfigured()) {
                $configured[$gatewayId] = $gateway->getGatewayName();
            }
        }
        
        return $configured;
    }
    
    /**
     * Log de debug
     */
    private function logDebug($method, $message, $data = [])
    {
        try {
            Capsule::table('mod_zapcel_logs')->insert([
                'event_type' => 'gateway_manager_debug',
                'message' => "[GatewayManager] {$method}: {$message}",
                'details' => json_encode($data),
                'success' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Silencia erros de log
        }
    }
}