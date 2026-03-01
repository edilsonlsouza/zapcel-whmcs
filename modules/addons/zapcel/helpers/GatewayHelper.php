<?php
/**
 * CRIAR EM: /modules/addons/zapcel/helpers/GatewayHelper.php
 */

namespace WHMCS\Module\Addon\Zapcel\Helpers;

use WHMCS\Database\Capsule;

/**
 * Helper para gerenciar gateways de pagamento
 * 
 * Responsável por:
 * - Buscar gateway ativo configurado
 * - Instanciar gateway selecionado
 * - Extrair dados de PIX/Boleto
 */
class GatewayHelper
{
    /**
     * Retorna o nome do gateway ativo configurado
     * 
     * @return string|null Nome do gateway ativo (ex: 'iugu') ou null se nenhum
     */
    public static function getActiveGateway()
    {
        try {
            $gateway = Capsule::table('tbladdonmodules')
                ->where('module', 'zapcel')
                ->where('setting', 'zapcel_active_gateway')
                ->value('value');
            
            // Se for 'none' ou vazio, retorna null
            if (empty($gateway) || $gateway === 'none') {
                return null;
            }
            
            return $gateway;
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Retorna a instância do gateway ativo
     * 
     * @return object|null Instância do gateway ou null
     */
    public static function getActiveGatewayInstance()
    {
        $gatewayName = self::getActiveGateway();
        
        if (!$gatewayName) {
            return null;
        }
        
        // Monta o nome da classe (ex: 'iugu' -> 'IuguGateway')
        $className = ucfirst($gatewayName) . 'Gateway';
        $fullClassName = "\\WHMCS\\Module\\Addon\\Zapcel\\Gateways\\{$className}";
        
        // Verifica se a classe existe
        if (!class_exists($fullClassName)) {
            return null;
        }
        
        try {
            return new $fullClassName();
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Extrai dados de PIX da fatura usando o gateway ativo
     * 
     * @param int $invoiceId ID da fatura
     * @return array|null Array com dados do PIX ou null
     * 
     * Retorno esperado:
     * [
     *     'qrcode' => 'URL ou base64 da imagem QR Code',
     *     'copiaecola' => 'Código PIX Copia e Cola',
     *     'expiration' => 'Data de expiração (opcional)'
     * ]
     */
    public static function extractPixData($invoiceId)
    {
        $gateway = self::getActiveGatewayInstance();
        
        if (!$gateway) {
            return null;
        }
        
        try {
            return $gateway->extractPixData($invoiceId);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Extrai dados de Boleto da fatura usando o gateway ativo
     * 
     * @param int $invoiceId ID da fatura
     * @return array|null Array com dados do boleto ou null
     * 
     * Retorno esperado:
     * [
     *     'linha_digitavel' => 'Linha digitável do boleto',
     *     'pdf_url' => 'URL do PDF do boleto',
     *     'barcode' => 'Código de barras (opcional)',
     *     'expiration' => 'Data de vencimento (opcional)'
     * ]
     */
    public static function extractBoletoData($invoiceId)
    {
        $gateway = self::getActiveGatewayInstance();
        
        if (!$gateway) {
            return null;
        }
        
        try {
            return $gateway->extractBoletoData($invoiceId);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Verifica se há um gateway ativo configurado
     * 
     * @return bool True se há gateway ativo, false caso contrário
     */
    public static function hasActiveGateway()
    {
        return self::getActiveGateway() !== null;
    }
    
    /**
     * Mapeia dados do gateway para variáveis de template
     * 
     * Converte os dados retornados pelo gateway para as variáveis
     * usadas nos templates do Zapcel
     * 
     * @param int $invoiceId ID da fatura
     * @return array Array com variáveis mapeadas
     */
    public static function getTemplateVariables($invoiceId)
    {
        $variables = [
            'codigopix' => '',
            'qr_code_url' => '',
            'linhadigitavel' => '',
            'link_fatura' => '',
        ];
        
        // Extrai dados do PIX
        $pixData = self::extractPixData($invoiceId);
        if ($pixData) {
            $variables['codigopix'] = $pixData['copiaecola'] ?? '';
            $variables['qr_code_url'] = $pixData['qrcode'] ?? '';
        }
        
        // Extrai dados do Boleto
        $boletoData = self::extractBoletoData($invoiceId);
        if ($boletoData) {
            $variables['linhadigitavel'] = $boletoData['linha_digitavel'] ?? '';
            $variables['link_fatura'] = $boletoData['pdf_url'] ?? '';
        }
        
        return $variables;
    }
    
    /**
     * Substitui variáveis de gateway no template
     * 
     * @param string $template Template com variáveis
     * @param int $invoiceId ID da fatura
     * @return string Template com variáveis substituídas
     */
    public static function replaceTemplateVariables($template, $invoiceId)
    {
        $variables = self::getTemplateVariables($invoiceId);
        
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
}

