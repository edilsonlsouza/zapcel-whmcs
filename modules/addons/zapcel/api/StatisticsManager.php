<?php
namespace WHMCS\Module\Addon\Zapcel\Api;

/**
 * Zapcel WHMCS - Gerenciador de Estatísticas e Relatórios
 * Sistema especializado em métricas e analytics
 * 
 * @package    Zapcel
 * @author     Hostcel
 * @version    2.1.1
 */

// Bloqueia acesso direto
if (!defined('WHMCS')) {
    die('Acesso não autorizado.');
}

use WHMCS\Database\Capsule;

/**
 * Classe especializada em estatísticas e relatórios
 */
class StatisticsManager
{
    /**
     * @var array Cache de estatísticas
     */
    private $cache = [];
    
    /**
     * @var int Tempo de vida do cache em segundos
     */
    private $cacheTtl = 300; // 5 minutos

    /**
     * @var NumberValidator Instância do validador (para evitar duplicação)
     */
    private $numberValidator;

    public function __construct()
    {
        // Instancia o NumberValidator para usar seus métodos específicos
        $this->numberValidator = new NumberValidator([]);
    }

    /**
     * Obtém estatísticas gerais do módulo (SEM DUPLICAÇÃO)
     */
    public function getGeneralStatistics(): array
    {
        $cacheKey = 'general_stats';
        if ($this->isCacheValid($cacheKey)) {
            return $this->cache[$cacheKey];
        }

        try {
            // Usa métodos específicos de validação do NumberValidator
            $validationStats = $this->numberValidator->getValidationStatistics();
            
            $stats = [
                'total_messages' => $this->getTotalMessagesCount(),
                'successful_messages' => $this->getSuccessfulMessagesCount(),
                'failed_messages' => $this->getFailedMessagesCount(),
                'success_rate' => $this->getSuccessRate(),
                'total_templates' => $this->getTemplatesCount(),
                'active_templates' => $this->getActiveTemplatesCount(),
                'today_messages' => $this->getTodayMessagesCount(),
                'month_messages' => $this->getMonthMessagesCount(),
                'most_used_template' => $this->getMostUsedTemplate(),
                'most_active_client' => $this->getMostActiveClient(),
                // Dados de validação vindos do NumberValidator
                'validated_clients' => $validationStats['statistics']['validated'] ?? 0,
                'validation_rate' => $validationStats['statistics']['validation_rate'] ?? 0,
            ];

            $this->cache[$cacheKey] = $stats;
            $this->updateCacheTimestamp($cacheKey);

            return $stats;

        } catch (\Exception $e) {
            error_log("Zapcel Statistics Error: " . $e->getMessage());
            return $this->getFallbackStatistics();
        }
    }

    /**
     * Obtém estatísticas diárias detalhadas
     */
    public function getDailyStatistics(int $days = 30): array
    {
        $cacheKey = "daily_stats_{$days}";
        if ($this->isCacheValid($cacheKey)) {
            return $this->cache[$cacheKey];
        }

        try {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            
            $dailyStats = Capsule::table('mod_zapcel_logs')
                ->selectRaw('
                    DATE(created_at) as date,
                    COUNT(*) as total_messages,
                    SUM(status = "success") as successful_messages,
                    SUM(status = "error") as failed_messages
                ')
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get()
                ->toArray();

            // Calcula taxa de sucesso
            foreach ($dailyStats as &$stat) {
                $total = $stat->total_messages;
                $stat->success_rate = $total > 0 ? round(($stat->successful_messages / $total) * 100, 2) : 0;
            }

            $filledStats = $this->fillMissingDays($dailyStats, $days);
            
            $this->cache[$cacheKey] = $filledStats;
            $this->updateCacheTimestamp($cacheKey);

            return $filledStats;

        } catch (\Exception $e) {
            error_log("Zapcel Daily Stats Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém estatísticas por tipo de evento
     */
    public function getEventTypeStatistics(int $days = 30): array
    {
        $cacheKey = "event_stats_{$days}";
        if ($this->isCacheValid($cacheKey)) {
            return $this->cache[$cacheKey];
        }

        try {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            
            $eventStats = Capsule::table('mod_zapcel_logs')
                ->selectRaw('
                    type as event_type,
                    COUNT(*) as total_messages,
                    SUM(status = "success") as successful_messages,
                    SUM(status = "error") as failed_messages
                ')
                ->where('created_at', '>=', $startDate)
                ->groupBy('type')
                ->orderBy('total_messages', 'desc')
                ->get()
                ->toArray();

            // Calcula taxa de sucesso
            foreach ($eventStats as &$stat) {
                $total = $stat->total_messages;
                $stat->success_rate = $total > 0 ? round(($stat->successful_messages / $total) * 100, 2) : 0;
            }

            $this->cache[$cacheKey] = $eventStats;
            $this->updateCacheTimestamp($cacheKey);

            return $eventStats;

        } catch (\Exception $e) {
            error_log("Zapcel Event Stats Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém estatísticas de performance horária
     */
    public function getHourlyStatistics(int $days = 7): array
    {
        $cacheKey = "hourly_stats_{$days}";
        if ($this->isCacheValid($cacheKey)) {
            return $this->cache[$cacheKey];
        }

        try {
            $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $hourlyStats = Capsule::table('mod_zapcel_logs')
                ->selectRaw('
                    HOUR(created_at) as hour,
                    COUNT(*) as total_messages,
                    SUM(status = "success") as successful_messages
                ')
                ->where('created_at', '>=', $startDate)
                ->groupBy('hour')
                ->orderBy('hour', 'asc')
                ->get()
                ->toArray();

            // Calcula taxa de sucesso e preenche horas
            foreach ($hourlyStats as &$stat) {
                $stat->success_rate = $stat->total_messages > 0 ? 
                    round(($stat->successful_messages / $stat->total_messages) * 100, 2) : 0;
            }

            $filledStats = $this->fillMissingHours($hourlyStats);
            
            $this->cache[$cacheKey] = $filledStats;
            $this->updateCacheTimestamp($cacheKey);

            return $filledStats;

        } catch (\Exception $e) {
            error_log("Zapcel Hourly Stats Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém alertas e problemas detectados
     */
    public function getSystemAlerts(): array
    {
        $alerts = [];

        try {
            // Verifica taxa de sucesso recente
            $recentSuccessRate = $this->getRecentSuccessRate(24);
            
            if ($recentSuccessRate < 80) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Taxa de Sucesso Baixa',
                    'message' => "A taxa de sucesso está em {$recentSuccessRate}%. Verifique a conexão com a API.",
                    'icon' => 'exclamation-triangle',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }

            // Verifica falhas consecutivas
            $consecutiveFailures = $this->getConsecutiveFailures();
            if ($consecutiveFailures >= 5) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => 'Falhas Consecutivas',
                    'message' => "{$consecutiveFailures} falhas consecutivas detectadas.",
                    'icon' => 'times-circle',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }

            // Verifica templates inativos
            $inactiveTemplates = $this->getInactiveTemplatesCount();
            if ($inactiveTemplates > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => 'Templates Inativos',
                    'message' => "{$inactiveTemplates} template(s) inativo(s).",
                    'icon' => 'info-circle',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }

        } catch (\Exception $e) {
            error_log("Zapcel Alerts Error: " . $e->getMessage());
        }

        return $alerts;
    }

    /**
     * Obtém relatório de clientes mais ativos
     */
    public function getTopClients(int $limit = 10): array
    {
        $cacheKey = "top_clients_{$limit}";
        if ($this->isCacheValid($cacheKey)) {
            return $this->cache[$cacheKey];
        }

        try {
            $topClients = Capsule::table('mod_zapcel_logs as log')
                ->selectRaw('
                    log.client_id,
                    c.firstname,
                    c.lastname,
                    c.companyname,
                    COUNT(*) as total_messages,
                    SUM(log.status = "success") as successful_messages
                ')
                ->leftJoin('tblclients as c', 'log.client_id', '=', 'c.id')
                ->whereNotNull('log.client_id')
                ->groupBy('log.client_id', 'c.firstname', 'c.lastname', 'c.companyname')
                ->orderBy('total_messages', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();

            // Calcula taxa de sucesso
            foreach ($topClients as &$client) {
                $client->success_rate = $client->total_messages > 0 ? 
                    round(($client->successful_messages / $client->total_messages) * 100, 2) : 0;
            }

            $this->cache[$cacheKey] = $topClients;
            $this->updateCacheTimestamp($cacheKey);

            return $topClients;

        } catch (\Exception $e) {
            error_log("Zapcel Top Clients Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém relatório de templates mais utilizados
     */
    public function getTopTemplates(int $limit = 10): array
    {
        $cacheKey = "top_templates_{$limit}";
        if ($this->isCacheValid($cacheKey)) {
            return $this->cache[$cacheKey];
        }

        try {
            $topTemplates = Capsule::table('mod_zapcel_templates as t')
                ->selectRaw('
                    t.id,
                    t.name,
                    t.trigger_event,
                    t.usage_count,
                    t.active,
                    COUNT(log.id) as actual_usage
                ')
                ->leftJoin('mod_zapcel_logs as log', 't.trigger_event', '=', 'log.type')
                ->groupBy('t.id', 't.name', 't.trigger_event', 't.usage_count', 't.active')
                ->orderBy('t.usage_count', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();

            $this->cache[$cacheKey] = $topTemplates;
            $this->updateCacheTimestamp($cacheKey);

            return $topTemplates;

        } catch (\Exception $e) {
            error_log("Zapcel Top Templates Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém métricas de performance da API
     */
    public function getPerformanceMetrics(): array
    {
        $cacheKey = 'performance_metrics';
        if ($this->isCacheValid($cacheKey)) {
            return $this->cache[$cacheKey];
        }

        try {
            // Métricas básicas - foco em dados disponíveis
            $totalMessages = $this->getTotalMessagesCount();
            $successfulMessages = $this->getSuccessfulMessagesCount();
            
            $metrics = [
                'avg_response_time' => 0, // Não temos este dado ainda
                'max_response_time' => 0,
                'min_response_time' => 0,
                'total_requests' => $totalMessages,
                'success_rate' => $totalMessages > 0 ? round(($successfulMessages / $totalMessages) * 100, 2) : 0
            ];

            $this->cache[$cacheKey] = $metrics;
            $this->updateCacheTimestamp($cacheKey);

            return $metrics;

        } catch (\Exception $e) {
            error_log("Zapcel Performance Metrics Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gera relatório completo em formato para exportação
     */
    public function generateFullReport(string $period = 'month'): array
    {
        $startDate = $this->getPeriodStartDate($period);
        
        try {
            $report = [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => date('Y-m-d H:i:s'),
                'general_stats' => $this->getGeneralStatistics(),
                'daily_stats' => $this->getDailyStatistics($this->getDaysForPeriod($period)),
                'event_stats' => $this->getEventTypeStatistics($this->getDaysForPeriod($period)),
                'top_clients' => $this->getTopClients(15),
                'top_templates' => $this->getTopTemplates(15),
                'performance_metrics' => $this->getPerformanceMetrics(),
                'system_alerts' => $this->getSystemAlerts(),
                'generated_at' => date('Y-m-d H:i:s')
            ];

            return $report;

        } catch (\Exception $e) {
            error_log("Zapcel Full Report Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Limpa cache de estatísticas
     */
    public function clearCache(): bool
    {
        $this->cache = [];
        return true;
    }

    // ========== MÉTODOS PRIVADOS ESPECÍFICOS DE ESTATÍSTICAS ==========

    private function getTotalMessagesCount(): int
    {
        // CORREÇÃO v2.0.1: Filtra logs de debug/sistema
        return Capsule::table('mod_zapcel_logs')
            ->where(function($query) {
                $query->where('event_type', 'NOT LIKE', 'debug_%')
                      ->where('event_type', '!=', 'gateway_manager_debug')
                      ->where('event_type', '!=', 'system_log');
            })
            ->whereIn('status', ['success', 'error'])
            ->count();
    }

    private function getSuccessfulMessagesCount(): int
    {
        // CORREÇÃO v2.0.1: Filtra logs de debug/sistema
        return Capsule::table('mod_zapcel_logs')
            ->where('status', 'success')
            ->where(function($query) {
                $query->where('event_type', 'NOT LIKE', 'debug_%')
                      ->where('event_type', '!=', 'gateway_manager_debug')
                      ->where('event_type', '!=', 'system_log');
            })
            ->count();
    }

    private function getFailedMessagesCount(): int
    {
        // CORREÇÃO v2.0.1: Filtra logs de debug/sistema
        return Capsule::table('mod_zapcel_logs')
            ->where('status', 'error')
            ->where(function($query) {
                $query->where('event_type', 'NOT LIKE', 'debug_%')
                      ->where('event_type', '!=', 'gateway_manager_debug')
                      ->where('event_type', '!=', 'system_log');
            })
            ->count();
    }

    private function getSuccessRate(): float
    {
        $total = $this->getTotalMessagesCount();
        $successful = $this->getSuccessfulMessagesCount();
        
        return $total > 0 ? round(($successful / $total) * 100, 2) : 0;
    }

    private function getTemplatesCount(): int
    {
        return Capsule::table('mod_zapcel_templates')->count();
    }

    private function getActiveTemplatesCount(): int
    {
        return Capsule::table('mod_zapcel_templates')->where('active', true)->count();
    }

    private function getInactiveTemplatesCount(): int
    {
        return Capsule::table('mod_zapcel_templates')->where('active', false)->count();
    }

    private function getTodayMessagesCount(): int
    {
        // CORREÇÃO v2.0.1: Filtra logs de debug/sistema
        return Capsule::table('mod_zapcel_logs')
            ->whereDate('created_at', date('Y-m-d'))
            ->where(function($query) {
                $query->where('event_type', 'NOT LIKE', 'debug_%')
                      ->where('event_type', '!=', 'gateway_manager_debug')
                      ->where('event_type', '!=', 'system_log');
            })
            ->whereIn('status', ['success', 'error'])
            ->count();
    }

    private function getMonthMessagesCount(): int
    {
        // CORREÇÃO v2.0.1: Filtra logs de debug/sistema
        return Capsule::table('mod_zapcel_logs')
            ->whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->where(function($query) {
                $query->where('event_type', 'NOT LIKE', 'debug_%')
                      ->where('event_type', '!=', 'gateway_manager_debug')
                      ->where('event_type', '!=', 'system_log');
            })
            ->whereIn('status', ['success', 'error'])
            ->count();
    }

    private function getMostUsedTemplate(): array
    {
        $template = Capsule::table('mod_zapcel_templates')
            ->orderBy('usage_count', 'desc')
            ->first();

        return $template ? (array)$template : ['name' => 'Nenhum', 'usage_count' => 0];
    }

    private function getMostActiveClient(): array
    {
        $client = Capsule::table('mod_zapcel_logs')
            ->selectRaw('client_id, COUNT(*) as message_count')
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->orderBy('message_count', 'desc')
            ->first();

        if ($client) {
            $clientInfo = Capsule::table('tblclients')
                ->where('id', $client->client_id)
                ->first();
                
            return [
                'client_id' => $client->client_id,
                'name' => $clientInfo ? trim($clientInfo->firstname . ' ' . $clientInfo->lastname) : 'Cliente #' . $client->client_id,
                'message_count' => $client->message_count
            ];
        }

        return ['name' => 'Nenhum', 'message_count' => 0];
    }

    private function getRecentSuccessRate(int $hours = 24): float
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $stats = Capsule::table('mod_zapcel_logs')
            ->selectRaw('COUNT(*) as total, SUM(status = "success") as successful')
            ->where('created_at', '>=', $startDate)
            ->first();

        if ($stats && $stats->total > 0) {
            return round(($stats->successful / $stats->total) * 100, 2);
        }

        return 100;
    }

    private function getConsecutiveFailures(): int
    {
        $failures = Capsule::table('mod_zapcel_logs')
            ->where('status', 'error')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $consecutive = 0;
        foreach ($failures as $failure) {
            if ($failure->status === 'error') {
                $consecutive++;
            } else {
                break;
            }
        }

        return $consecutive;
    }

    // ========== MÉTODOS PRIVADOS AUXILIARES ==========

    private function isCacheValid(string $key): bool
    {
        if (!isset($this->cache[$key . '_timestamp'])) {
            return false;
        }

        $timestamp = $this->cache[$key . '_timestamp'];
        return (time() - $timestamp) < $this->cacheTtl;
    }

    private function updateCacheTimestamp(string $key): void
    {
        $this->cache[$key . '_timestamp'] = time();
    }

    private function fillMissingDays(array $stats, int $days): array
    {
        $filledStats = [];
        $endDate = date('Y-m-d');
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $found = false;
            
            foreach ($stats as $stat) {
                if ($stat->date === $date) {
                    $filledStats[] = $stat;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $filledStats[] = (object)[
                    'date' => $date,
                    'total_messages' => 0,
                    'successful_messages' => 0,
                    'failed_messages' => 0,
                    'success_rate' => 0
                ];
            }
        }
        
        return $filledStats;
    }

    private function fillMissingHours(array $stats): array
    {
        $filledStats = [];
        
        for ($hour = 0; $hour < 24; $hour++) {
            $found = false;
            
            foreach ($stats as $stat) {
                if ($stat->hour == $hour) {
                    $filledStats[] = $stat;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $filledStats[] = (object)[
                    'hour' => $hour,
                    'total_messages' => 0,
                    'successful_messages' => 0,
                    'success_rate' => 0
                ];
            }
        }
        
        return $filledStats;
    }

    private function getPeriodStartDate(string $period): string
    {
        switch ($period) {
            case 'day':
                return date('Y-m-d 00:00:00');
            case 'week':
                return date('Y-m-d 00:00:00', strtotime('-1 week'));
            case 'month':
                return date('Y-m-d 00:00:00', strtotime('-1 month'));
            case 'year':
                return date('Y-m-d 00:00:00', strtotime('-1 year'));
            default:
                return date('Y-m-d 00:00:00', strtotime('-1 month'));
        }
    }

    private function getDaysForPeriod(string $period): int
    {
        switch ($period) {
            case 'day': return 1;
            case 'week': return 7;
            case 'month': return 30;
            case 'year': return 365;
            default: return 30;
        }
    }

    private function getFallbackStatistics(): array
    {
        return [
            'total_messages' => 0,
            'successful_messages' => 0,
            'failed_messages' => 0,
            'success_rate' => 0,
            'total_templates' => 0,
            'active_templates' => 0,
            'validated_clients' => 0,
            'validation_rate' => 0,
            'today_messages' => 0,
            'month_messages' => 0,
            'most_used_template' => ['name' => 'N/A', 'usage_count' => 0],
            'most_active_client' => ['name' => 'N/A', 'message_count' => 0],
        ];
    }
}