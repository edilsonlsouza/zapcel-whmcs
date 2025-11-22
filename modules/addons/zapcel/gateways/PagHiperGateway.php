<?php

namespace WHMCS\Module\Addon\Zapcel\Gateways;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Gateway: PagHiper
 * 
 * Extrai dados de PIX e Boleto do gateway PagHiper
 * Suporta tanto boleto bancário quanto PIX
 * 
 * @package    Zapcel
 * @author     Hostcel
 * @version    1.0.0
 * @link       https://www.paghiper.com/
 */
class PagHiperGateway extends AbstractGateway
{
    /**
     * Nome do gateway (deve ser EXATAMENTE o nome do módulo no WHMCS)
     * @var string
     */
    protected $gatewayName = 'paghiper';

    /**
     * Extrai dados do PIX da fatura
     * 
     * Campos disponíveis na tabela mod_paghiper:
     * - emv: Código PIX Copia e Cola
     * - qrcode_base64: QR Code em base64
     * - qrcode_image_url: URL da imagem do QR Code
     * - pix_url: URL do PIX
     * - bacen_url: URL do Bacen
     * 
     * @param int $invoiceId ID da fatura
     * @return array|null Array com dados do PIX ou null se não houver
     */
    public function extractPixData($invoiceId)
    {
        try {
            // Busca dados do PIX no banco
            $pixData = Capsule::table('mod_paghiper')
                ->where('order_id', $invoiceId)
                ->where('transaction_type', 'pix')
                ->orderBy('id', 'desc')
                ->first();

            // Se não houver dados, retorna null
            if (!$pixData) {
                return null;
            }

            // Monta array de retorno
            $result = [
                'copiaecola' => $pixData->emv ?? null,
            ];

            // Prioriza qrcode_image_url, se não tiver usa base64
            if (!empty($pixData->qrcode_image_url)) {
                $result['qrcode'] = $pixData->qrcode_image_url;
            } elseif (!empty($pixData->qrcode_base64)) {
                $result['qrcode'] = $pixData->qrcode_base64;
            }

            // Adiciona URLs extras se disponíveis
            if (!empty($pixData->pix_url)) {
                $result['pix_url'] = $pixData->pix_url;
            }

            if (!empty($pixData->bacen_url)) {
                $result['bacen_url'] = $pixData->bacen_url;
            }

            // Só retorna se tiver pelo menos o código copia e cola
            if (empty($result['copiaecola'])) {
                return null;
            }

            return $result;
            
        } catch (\Exception $e) {
            // Em caso de erro, retorna null
            return null;
        }
    }

    /**
     * Extrai dados do Boleto da fatura
     * 
     * Campos disponíveis na tabela mod_paghiper:
     * - digitable_line: Linha digitável do boleto
     * - bar_code_number_to_image: Código de barras
     * - url_slip: URL do boleto (HTML)
     * - url_slip_pdf: URL do PDF do boleto
     * - due_date: Data de vencimento
     * - slip_value: Valor do boleto
     * 
     * @param int $invoiceId ID da fatura
     * @return array|null Array com dados do Boleto ou null se não houver
     */
    public function extractBoletoData($invoiceId)
    {
        try {
            // Busca dados do Boleto no banco
            $boletoData = Capsule::table('mod_paghiper')
                ->where('order_id', $invoiceId)
                ->where('transaction_type', 'billet')
                ->orderBy('id', 'desc')
                ->first();

            // Se não houver dados, retorna null
            if (!$boletoData) {
                return null;
            }

            // Monta array de retorno
            $result = [
                'linha_digitavel' => $boletoData->digitable_line ?? null,
            ];

            // Prioriza PDF, se não tiver usa HTML
            if (!empty($boletoData->url_slip_pdf)) {
                $result['pdf_url'] = $boletoData->url_slip_pdf;
            } elseif (!empty($boletoData->url_slip)) {
                $result['pdf_url'] = $boletoData->url_slip;
            }

            // Adiciona código de barras se disponível
            if (!empty($boletoData->bar_code_number_to_image)) {
                $result['barcode'] = $boletoData->bar_code_number_to_image;
            }

            // Adiciona data de vencimento se disponível
            if (!empty($boletoData->due_date)) {
                $result['expiration'] = $boletoData->due_date;
            }

            // Adiciona valor do boleto se disponível
            if (!empty($boletoData->slip_value)) {
                $result['value'] = $boletoData->slip_value;
            }

            // Só retorna se tiver pelo menos a linha digitável
            if (empty($result['linha_digitavel'])) {
                return null;
            }

            return $result;
            
        } catch (\Exception $e) {
            // Em caso de erro, retorna null
            return null;
        }
    }
}

