<?php
/**
 * Intelligence Analysis Service
 * 
 * Servizio principale per analisi intelligente dei dati finanziari
 * Orchestra tutti i sottoservizi AI (AnomalyDetector, PatternAnalyzer, etc.)
 */

namespace FP\FinanceHub\Services\Intelligence;

use FP\FinanceHub\Services\StatsService;
use FP\FinanceHub\Services\BankService;
use FP\FinanceHub\Services\InvoiceService;
use FP\FinanceHub\Services\ProjectionService;

if (!defined('ABSPATH')) {
    exit;
}

class IntelligenceAnalysisService {
    
    private static $instance = null;
    
    private $anomaly_detector;
    private $pattern_analyzer;
    private $insights_generator;
    private $recommendations_engine;
    private $predictive_analyzer;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->anomaly_detector = new AnomalyDetector();
        $this->pattern_analyzer = new PatternAnalyzer();
        $this->insights_generator = new InsightsGenerator();
        $this->recommendations_engine = new RecommendationsEngine();
        $this->predictive_analyzer = new PredictiveAnalyzer($this->pattern_analyzer);
    }
    
    /**
     * Genera report completo di intelligenza finanziaria
     */
    public function generate_intelligence_report($period_days = 30) {
        $start_time = microtime(true);
        $cache_key = 'fp_finance_hub_intelligence_report_' . $period_days;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $report = [
            'period_days' => $period_days,
            'generated_at' => time(),
            'summary' => [],
            'anomalies' => [],
            'patterns' => [],
            'insights' => [],
            'recommendations' => [],
            'predictions' => [],
        ];
        
        try {
            // Calcola periodo analisi
            $end_date = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime("-{$period_days} days"));
            
            // 1. Analisi anomalie
            $report['anomalies'] = $this->anomaly_detector->detect_all($start_date, $end_date);
            
            // Log anomalie critical
            $critical_count = count(array_filter($report['anomalies'], function ($a) {
                return ($a['severity'] ?? 'low') === 'critical';
            }));
            if ($critical_count > 0) {
                error_log(sprintf(
                    '[FP Finance Hub Intelligence] Rilevate %d anomalie critical per periodo %d giorni',
                    $critical_count,
                    $period_days
                ));
            }
            
            // 2. Analisi pattern
            $report['patterns'] = $this->pattern_analyzer->analyze_patterns($start_date, $end_date);
            
            // 3. Generazione insights
            $report['insights'] = $this->insights_generator->generate_all($start_date, $end_date);
            
            // 4. Raccomandazioni
            $report['recommendations'] = $this->recommendations_engine->generate_recommendations($start_date, $end_date);
            
            // 5. Predizioni
            $report['predictions'] = $this->predictive_analyzer->generate_predictions($period_days);
            
            // 6. Calcola summary
            $report['summary'] = $this->calculate_summary($report);
            
            // Cache TTL configurabile (default 2 ore)
            $cache_ttl = absint(get_option('fp_finance_hub_intelligence_cache_ttl', 2 * HOUR_IN_SECONDS));
            set_transient($cache_key, $report, $cache_ttl);
            
            // Log performance se lenta
            $duration = microtime(true) - $start_time;
            if ($duration > 5.0) {
                error_log(sprintf(
                    '[FP Finance Hub Intelligence] Report generato in %.2fs (periodo: %d giorni)',
                    $duration,
                    $period_days
                ));
            }
            
        } catch (\Exception $e) {
            $report['error'] = $e->getMessage();
            error_log(sprintf(
                '[FP Finance Hub Intelligence] Errore generazione report: %s | Stack: %s',
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }
        
        return $report;
    }
    
    /**
     * Ottieni insights completi
     */
    public function get_insights($start_date = null, $end_date = null) {
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        return [
            'anomalies' => $this->anomaly_detector->detect_all($start_date, $end_date),
            'patterns' => $this->pattern_analyzer->analyze_patterns($start_date, $end_date),
            'insights' => $this->insights_generator->generate_all($start_date, $end_date),
            'recommendations' => $this->recommendations_engine->generate_recommendations($start_date, $end_date),
        ];
    }
    
    /**
     * Ottieni anomalie
     */
    public function get_anomalies($start_date = null, $end_date = null) {
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        return $this->anomaly_detector->detect_all($start_date, $end_date);
    }
    
    /**
     * Ottieni pattern
     */
    public function get_patterns($start_date = null, $end_date = null) {
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-90 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        return $this->pattern_analyzer->analyze_patterns($start_date, $end_date);
    }
    
    /**
     * Ottieni raccomandazioni
     */
    public function get_recommendations($start_date = null, $end_date = null) {
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        return $this->recommendations_engine->generate_recommendations($start_date, $end_date);
    }
    
    /**
     * Ottieni predizioni
     */
    public function get_predictions($days_ahead = 30) {
        return $this->predictive_analyzer->generate_predictions($days_ahead);
    }
    
    /**
     * Calcola summary del report (include intelligence_score 0–100)
     */
    private function calculate_summary($report) {
        $critical = count(array_filter($report['anomalies'], function ($a) {
            return ($a['severity'] ?? 'low') === 'critical';
        }));
        $summary = [
            'total_anomalies' => count($report['anomalies']),
            'critical_anomalies' => $critical,
            'total_patterns' => count($report['patterns']),
            'total_insights' => count($report['insights']),
            'total_recommendations' => count($report['recommendations']),
            'high_priority_recommendations' => count(array_filter($report['recommendations'], function ($r) {
                return ($r['priority'] ?? 'low') === 'high' || ($r['priority'] ?? 'low') === 'critical';
            })),
        ];

        $avg_insight = empty($report['insights'])
            ? 0.5
            : array_sum(array_map(function ($i) { return (float) ($i['confidence'] ?? 0.5); }, $report['insights'])) / count($report['insights']);
        $anomaly_penalty = min(1.0, (count($report['anomalies']) * 0.5 + $critical * 0.5) / 5);
        $summary['intelligence_score'] = (int) round(100 * ($avg_insight * 0.6 + (1 - $anomaly_penalty) * 0.4));
        $summary['intelligence_score'] = max(0, min(100, $summary['intelligence_score']));

        return $summary;
    }
    
    /**
     * Force refresh analisi (clear cache)
     */
    public function force_refresh($period_days = 30) {
        $cache_key = 'fp_finance_hub_intelligence_report_' . $period_days;
        delete_transient($cache_key);
        return $this->generate_intelligence_report($period_days);
    }

    /**
     * Invalida cache Intelligence (chiamare dopo import/sync dati)
     *
     * @param int|null $period_days Se null, invalida tutti i periodi comuni
     */
    public static function invalidate_cache($period_days = null) {
        if ($period_days !== null) {
            $cache_key = 'fp_finance_hub_intelligence_report_' . $period_days;
            delete_transient($cache_key);
        } else {
            // Invalida periodi comuni
            $common_periods = [7, 30, 90, 180];
            foreach ($common_periods as $period) {
                $cache_key = 'fp_finance_hub_intelligence_report_' . $period;
                delete_transient($cache_key);
            }
        }
    }
}
