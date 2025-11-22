<?php
namespace WHMCS\Module\Addon\Zapcel\Gateways;

// Este modulo é baseado no IUGU Pix do Edvan

if (!class_exists('WHMCS\Module\Addon\Zapcel\Gateways\GatewayInterface')) {
    require_once __DIR__ . '/GatewayInterface.php';
}
if (!class_exists('WHMCS\Module\Addon\Zapcel\Gateways\AbstractGateway')) {
    require_once __DIR__ . '/AbstractGateway.php';
}


use WHMCS\Database\Capsule;

/**
 * Zapcel WHMCS - Gateway Iugu para iugupix
 */
class IugupixGateway extends AbstractGateway
{
    public function getGatewayId(): string
    {
        return 'iugupix'; // AGORA CORRETO - igual ao paymentmethod
    }

    public function getGatewayName(): string
    {
        return 'Iugu PIX';
    }

    protected function getRequiredConfigFields(): array
    {
        return []; // Não precisa de configuração para modo local
    }

    /**
     * Extrai dados do PIX de uma fatura (APENAS TABELA LOCAL)
     */
    public function extractPixData($invoiceId): array
    {
        try {
            // Busca transação Iugu da fatura na tabela iugupix
            $transaction = $this->findIuguTransactionFromIugupix($invoiceId);
            
            if (!$transaction) {
                throw new \Exception('Nenhuma transação PIX Iugu encontrada para esta fatura');
            }

            // Obtém dados do PIX diretamente da tabela iugupix
            $pixData = $this->getPixDataFromLocal($transaction->fatura);
            
            if (!$pixData['success']) {
                throw new \Exception($pixData['error']);
            }

            $this->logSuccess('Dados PIX extraídos com sucesso', [
                'invoice_id' => $invoiceId,
                'transaction_id' => $transaction->fatura
            ]);

            return [
                'success' => true,
                'pix' => [
                    'codigo_pix' => $pixData['qrcode_text'] ?? '',
                    'qr_code' => $pixData['qrcode'] ?? '',
                    'valor' => $transaction->total ?? 0,
                    'vencimento' => $transaction->due_date ?? '',
                    'identificador' => $transaction->secure_id ?? ''
                ]
            ];

        } catch (\Exception $e) {
            $this->logError('Erro ao extrair dados PIX: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extrai dados do boleto de uma fatura (APENAS TABELA LOCAL)
     */
    public function extractBoletoData($invoiceId): array
    {
        try {
            // Busca transação Iugu da fatura na tabela iuguboleto
            $transaction = $this->findIuguTransactionFromIuguboleto($invoiceId);
            
            if (!$transaction) {
                throw new \Exception('Nenhuma transação Boleto Iugu encontrada para esta fatura');
            }

            // Obtém dados do boleto diretamente da tabela iuguboleto
            $boletoData = $this->getBoletoDataFromLocal($transaction->fatura);
            
            if (!$boletoData['success']) {
                throw new \Exception($boletoData['error']);
            }

            $this->logSuccess('Dados boleto extraídos com sucesso', [
                'invoice_id' => $invoiceId,
                'transaction_id' => $transaction->fatura
            ]);

            return [
                'success' => true,
                'boleto' => [
                    'linha_digitavel' => $boletoData['digitable_line'] ?? '',
                    'codigo_barras' => $boletoData['barcode'] ?? '',
                    'url_pdf' => $boletoData['secure_url'] ?? '',
                    'valor' => $transaction->total ?? 0,
                    'vencimento' => $transaction->due_date ?? '',
                    'nosso_numero' => $transaction->external_reference ?? ''
                ]
            ];

        } catch (\Exception $e) {
            $this->logError('Erro ao extrair dados boleto: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica status de uma fatura (APENAS TABELA LOCAL)
     */
    public function checkInvoiceStatus($invoiceId): array
    {
        try {
            // Tenta primeiro encontrar na tabela PIX, se não encontrar, busca no boleto
            $transaction = $this->findIuguTransactionFromIugupix($invoiceId);
            if (!$transaction) {
                $transaction = $this->findIuguTransactionFromIuguboleto($invoiceId);
            }

            if (!$transaction) {
                return [
                    'success' => true,
                    'status' => 'pending',
                    'message' => 'Nenhuma transação Iugu encontrada'
                ];
            }

            // Obtém status diretamente da tabela
            $status = $this->getLocalStatus($transaction);

            $this->logAction('status_check', 'Status verificado', [
                'invoice_id' => $invoiceId,
                'transaction_id' => $transaction->fatura,
                'status' => $status
            ]);

            return [
                'success' => true,
                'status' => $status,
                'transaction_id' => $transaction->fatura,
                'last_update' => $transaction->updated_at ?? null
            ];

        } catch (\Exception $e) {
            $this->logError('Erro ao verificar status: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Testa conexão (SEM API - SEMPRE TRUE)
     */
    public function testConnection(): array
    {
        // Como só usa tabelas locais, sempre retorna sucesso
        $this->logSuccess('Conexão testada com sucesso - Modo Local');

        return [
            'success' => true,
            'message' => 'Gateway Iugu configurado para uso local (tabelas)',
            'local_mode' => true
        ];
    }

    /**
     * Sincroniza faturas pendentes (APENAS TABELAS LOCAIS)
     */
    public function syncPendingInvoices($limit = 50): array
    {
        try {
            // Busca faturas pendentes tanto na tabela PIX quanto na tabela boleto
            $pendingPix = Capsule::table('tblinvoices as i')
                ->join('iugupix as ip', 'i.id', '=', 'ip.invoice')
                ->where('i.status', 'Unpaid')
                ->select('i.id', 'i.total', 'i.duedate', 'ip.fatura as transid')
                ->get();

            $pendingBoletos = Capsule::table('tblinvoices as i')
                ->join('iuguboleto as ib', 'i.id', '=', 'ib.invoice')
                ->where('i.status', 'Unpaid')
                ->select('i.id', 'i.total', 'i.duedate', 'ib.fatura as transid')
                ->get();

            // Combina os resultados e remove duplicatas
            $pendingInvoices = $pendingPix->merge($pendingBoletos)->unique('id');

            $results = [
                'total' => count($pendingInvoices),
                'updated' => 0,
                'failed' => 0,
                'details' => []
            ];

            foreach ($pendingInvoices as $invoice) {
                $statusResult = $this->checkInvoiceStatus($invoice->id);
                
                if ($statusResult['success'] && $statusResult['status'] == 'paid') {
                    // Atualiza fatura como paga
                    $updateResult = $this->updateInvoiceStatus($invoice->id, 'paid', $invoice->transid);
                    
                    if ($updateResult) {
                        $results['updated']++;
                        $results['details'][] = [
                            'invoice_id' => $invoice->id,
                            'status' => 'updated',
                            'message' => 'Fatura atualizada como paga'
                        ];
                    } else {
                        $results['failed']++;
                        $results['details'][] = [
                            'invoice_id' => $invoice->id,
                            'status' => 'failed',
                            'message' => 'Falha ao atualizar fatura'
                        ];
                    }
                } else {
                    $results['details'][] = [
                        'invoice_id' => $invoice->id,
                        'status' => 'unchanged',
                        'message' => 'Status mantido: ' . ($statusResult['status'] ?? 'unknown')
                    ];
                }
            }

            $this->logAction('sync_completed', 'Sincronização concluída', $results);

            return [
                'success' => true,
                'results' => $results
            ];

        } catch (\Exception $e) {
            $this->logError('Erro na sincronização: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém estatísticas do gateway (APENAS TABELAS LOCAIS)
     */
    public function getStatistics(): array
    {
        try {
            // Conta faturas tanto da tabela PIX quanto da tabela boleto
            $totalPix = Capsule::table('iugupix')->count();
            $totalBoletos = Capsule::table('iuguboleto')->count();
            $totalInvoices = $totalPix + $totalBoletos;

            $pendingInvoices = Capsule::table('tblinvoices as i')
                ->join('iugupix as ip', 'i.id', '=', 'ip.invoice')
                ->where('i.status', 'Unpaid')
                ->count() + 
                Capsule::table('tblinvoices as i')
                ->join('iuguboleto as ib', 'i.id', '=', 'ib.invoice')
                ->where('i.status', 'Unpaid')
                ->count();

            $paidInvoices = Capsule::table('tblinvoices as i')
                ->join('iugupix as ip', 'i.id', '=', 'ip.invoice')
                ->where('i.status', 'Paid')
                ->count() + 
                Capsule::table('tblinvoices as i')
                ->join('iuguboleto as ib', 'i.id', '=', 'ib.invoice')
                ->where('i.status', 'Paid')
                ->count();

            // Última sincronização
            $lastSync = Capsule::table('mod_zapcel_logs')
                ->where('type', 'like', 'gateway_iugu_sync%')
                ->orderBy('created_at', 'desc')
                ->value('created_at');

            return [
                'success' => true,
                'statistics' => [
                    'total_invoices' => $totalInvoices,
                    'pending_invoices' => $pendingInvoices,
                    'paid_invoices' => $paidInvoices,
                    'success_rate' => $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 2) : 0,
                    'last_sync' => $lastSync,
                    'configured' => $this->isConfigured(),
                    'local_mode' => true
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ========================================================================
     * MÉTODOS PRIVADOS - APENAS TABELAS LOCAIS
     * ========================================================================
     */

    private function findIuguTransactionFromIugupix($invoiceId)
    {
        return Capsule::table('iugupix')
            ->where('invoice', $invoiceId)
            ->orderBy('id', 'desc')
            ->first();
    }

    private function findIuguTransactionFromIuguboleto($invoiceId)
    {
        return Capsule::table('iuguboleto')
            ->where('invoice', $invoiceId)
            ->orderBy('id', 'desc')
            ->first();
    }

    private function getPixDataFromLocal($transactionId)
    {
        // Busca dados diretamente da tabela iugupix
        $pixData = Capsule::table('iugupix')
            ->where('fatura', $transactionId)
            ->first();

        if (!$pixData) {
            return [
                'success' => false,
                'error' => 'PIX não encontrado na base local'
            ];
        }

        return [
            'success' => true,
            'qrcode' => $pixData->qrcode ?? '',
            'qrcode_text' => $pixData->qrcode_text ?? '',
            'payment_id' => $pixData->secure_id ?? ''
        ];
    }

    private function getBoletoDataFromLocal($transactionId)
    {
        // Busca dados diretamente da tabela iuguboleto
        $boletoData = Capsule::table('iuguboleto')
            ->where('fatura', $transactionId)
            ->first();

        if (!$boletoData) {
            return [
                'success' => false,
                'error' => 'Boleto não encontrado na base local'
            ];
        }

        return [
            'success' => true,
            'digitable_line' => $boletoData->digitable_line ?? '',
            'barcode' => $boletoData->barcode ?? '',
            'secure_url' => $boletoData->secure_url ?? ''
        ];
    }

    private function getLocalStatus($transaction)
    {
        // Mapeia status baseado nos campos da tabela
        if (isset($transaction->bank_slip_status)) {
            return $this->mapIuguStatus($transaction->bank_slip_status);
        }
        
        if (isset($transaction->qrcode_status)) {
            return $this->mapIuguStatus($transaction->qrcode_status);
        }

        return 'pending';
    }

    private function mapIuguStatus($iuguStatus)
    {
        $statusMap = [
            'pending' => 'pending',
            'paid' => 'paid',
            'canceled' => 'cancelled',
            'refunded' => 'refunded',
            'expired' => 'expired',
            'in_analysis' => 'analysis'
        ];

        return $statusMap[$iuguStatus] ?? 'unknown';
    }

    private function updateInvoiceStatus($invoiceId, $status, $transactionId)
    {
        try {
            if ($status == 'paid') {
                // Usa a API do WHMCS para marcar como paga
                localAPI('UpdateInvoice', [
                    'invoiceid' => $invoiceId,
                    'status' => 'Paid',
                    'paymentmethod' => 'iugupix'
                ]);

                // Registra o pagamento
                $result = localAPI('AddInvoicePayment', [
                    'invoiceid' => $invoiceId,
                    'transid' => $transactionId,
                    'gateway' => 'iugupix',
                    'amount' => Capsule::table('tblinvoices')->where('id', $invoiceId)->value('total')
                ]);

                return $result['result'] == 'success';
            }

            return false;

        } catch (\Exception $e) {
            $this->logError('Erro ao atualizar fatura: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId,
                'status' => $status
            ]);
            return false;
        }
    }
}