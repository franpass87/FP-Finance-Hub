<?php
/**
 * Alert Service
 * 
 * Gestione soglie e alert automatici
 */

namespace FP\FinanceHub\Services;

use FP\FinanceHub\Database\Models\Alert as AlertModel;
use FP\FinanceHub\Database\Models\BankAccount as BankAccountModel;
use FP\FinanceHub\Services\Intelligence\IntelligenceAnalysisService;

if (!defined('ABSPATH')) {
    exit;
}

class AlertService {
    
    private static $instance = null;
    
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
     * Verifica tutte le soglie e genera alert
     */
    public function check_thresholds() {
        global $wpdb;
        
        $thresholds_table = $wpdb->prefix . 'fp_finance_hub_thresholds';
        
        $thresholds = $wpdb->get_results(
            "SELECT * FROM {$thresholds_table} WHERE is_active = 1"
        );
        
        foreach ($thresholds as $threshold) {
            $this->check_single_threshold($threshold);
        }
        
        // Verifica alert Intelligence
        $this->check_intelligence_alerts();
    }
    
    /**
     * Verifica singola soglia
     */
    private function check_single_threshold($threshold) {
        switch ($threshold->threshold_type) {
            case 'balance_min':
                $this->check_balance_threshold($threshold);
                break;
            case 'cashflow_negative':
                $this->check_cashflow_threshold($threshold);
                break;
            case 'invoices_overdue':
                $this->check_invoices_threshold($threshold);
                break;
        }
    }
    
    /**
     * Verifica soglia saldo minimo
     */
    private function check_balance_threshold($threshold) {
        $total_balance = BankAccountModel::get_total_balance();
        
        if ($total_balance < floatval($threshold->threshold_value)) {
            // Verifica se alert già esistente
            $existing = AlertModel::get_active([
                'alert_type' => 'balance_low',
                'threshold_id' => $threshold->id,
                'acknowledged' => false,
            ]);
            
            if (empty($existing)) {
                AlertModel::create([
                    'alert_type' => 'balance_low',
                    'severity' => $threshold->alert_level,
                    'message' => sprintf(
                        'Saldo totale (€%.2f) inferiore alla soglia minima (€%.2f)',
                        $total_balance,
                        floatval($threshold->threshold_value)
                    ),
                    'threshold_id' => $threshold->id,
                    'current_value' => $total_balance,
                    'threshold_value' => floatval($threshold->threshold_value),
                ]);
            }
        }
    }
    
    /**
     * Verifica soglia cash flow negativo
     */
    private function check_cashflow_threshold($threshold) {
        // Implementazione futura
    }
    
    /**
     * Verifica soglia fatture scadute
     */
    private function check_invoices_threshold($threshold) {
        // Implementazione futura
    }
    
    /**
     * Verifica alert da Intelligence (anomalie critical e intelligence_score basso)
     */
    public function check_intelligence_alerts() {
        $alert_critical_enabled = get_option('fp_finance_hub_intelligence_alert_critical', true);
        $score_threshold = absint(get_option('fp_finance_hub_intelligence_score_threshold', 40));
        
        try {
            $intelligence_service = IntelligenceAnalysisService::get_instance();
            
            // 1. Verifica anomalie critical
            if ($alert_critical_enabled) {
                $anomalies = $intelligence_service->get_anomalies();
                
                foreach ($anomalies as $anomaly) {
                    if (($anomaly['severity'] ?? 'low') === 'critical') {
                        // Verifica se alert già esistente per questa anomalia
                        $existing = AlertModel::get_active([
                            'alert_type' => 'intelligence_anomaly',
                            'acknowledged' => false,
                        ]);
                        
                        // Controlla se esiste già un alert con stesso messaggio (per evitare duplicati)
                        $message_exists = false;
                        foreach ($existing as $alert) {
                            if (strpos($alert->message, substr($anomaly['message'] ?? '', 0, 50)) !== false) {
                                $message_exists = true;
                                break;
                            }
                        }
                        
                        if (!$message_exists) {
                            AlertModel::create([
                                'alert_type' => 'intelligence_anomaly',
                                'severity' => 'critical',
                                'message' => sprintf(
                                    __('Anomalia critica rilevata: %s', 'fp-finance-hub'),
                                    $anomaly['message'] ?? __('Anomalia sconosciuta', 'fp-finance-hub')
                                ),
                                'current_value' => $anomaly['current_value'] ?? null,
                            ]);
                        }
                    }
                }
            }
            
            // 2. Verifica intelligence_score
            $report = $intelligence_service->generate_intelligence_report(30);
            $intelligence_score = absint($report['summary']['intelligence_score'] ?? 100);
            
            if ($intelligence_score < $score_threshold) {
                // Verifica se alert già esistente
                $existing = AlertModel::get_active([
                    'alert_type' => 'intelligence_score_low',
                    'acknowledged' => false,
                ]);
                
                if (empty($existing)) {
                    AlertModel::create([
                        'alert_type' => 'intelligence_score_low',
                        'severity' => $intelligence_score < 30 ? 'critical' : 'warning',
                        'message' => sprintf(
                            __('Intelligence Score basso: %d (soglia: %d). Verifica anomalie e insights.', 'fp-finance-hub'),
                            $intelligence_score,
                            $score_threshold
                        ),
                        'current_value' => $intelligence_score,
                        'threshold_value' => $score_threshold,
                    ]);
                }
            }
        } catch (\Exception $e) {
            error_log('[FP Finance Hub] Errore check_intelligence_alerts: ' . $e->getMessage());
        }
    }
}
