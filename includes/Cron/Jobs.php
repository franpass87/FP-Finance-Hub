<?php
/**
 * Cron Jobs
 * 
 * Sincronizzazioni automatiche
 */

namespace FP\FinanceHub\Cron;

use FP\FinanceHub\Integration\Aruba\ArubaSync;
use FP\FinanceHub\Integration\OpenBanking\YapilySyncService;
use FP\FinanceHub\Services\ReconciliationService;
use FP\FinanceHub\Services\AlertService;
use FP\FinanceHub\Services\ProjectionService;
use FP\FinanceHub\Services\Intelligence\IntelligenceAnalysisService;

if (!defined('ABSPATH')) {
    exit;
}

class Jobs {
    
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
     * Constructor
     */
    private function __construct() {
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
    }
    
    /**
     * Schedula tutti i cron job
     */
    public static function schedule() {
        // Sync Aruba giornaliero
        if (!wp_next_scheduled('fp_finance_hub_sync_aruba_daily')) {
            wp_schedule_event(time(), 'daily', 'fp_finance_hub_sync_aruba_daily');
        }
        
        // Sync Yapily ogni 6 ore
        if (!wp_next_scheduled('fp_finance_hub_sync_yapily_accounts')) {
            wp_schedule_event(time(), 'fp_finance_hub_6hours', 'fp_finance_hub_sync_yapily_accounts');
        }
        
        // Riconciliazione ogni 6 ore
        if (!wp_next_scheduled('fp_finance_hub_reconcile_transactions')) {
            wp_schedule_event(time(), 'fp_finance_hub_6hours', 'fp_finance_hub_reconcile_transactions');
        }
        
        // Verifica alert ogni ora
        if (!wp_next_scheduled('fp_finance_hub_check_alerts')) {
            wp_schedule_event(time(), 'hourly', 'fp_finance_hub_check_alerts');
        }
        
        // Calcolo proiezioni giornaliero
        if (!wp_next_scheduled('fp_finance_hub_calculate_projections')) {
            wp_schedule_event(time(), 'daily', 'fp_finance_hub_calculate_projections');
        }
        
        // Verifica alert Intelligence ogni 6 ore
        if (!wp_next_scheduled('fp_finance_hub_check_intelligence_alerts')) {
            wp_schedule_event(time(), 'fp_finance_hub_6hours', 'fp_finance_hub_check_intelligence_alerts');
        }
        
        // Refresh Intelligence giornaliero (precomputa report)
        if (!wp_next_scheduled('fp_finance_hub_refresh_intelligence')) {
            wp_schedule_event(time(), 'daily', 'fp_finance_hub_refresh_intelligence');
        }
        
        // Auto import CSV/OFX ogni ora
        if (!wp_next_scheduled('fp_finance_hub_auto_import_csv')) {
            wp_schedule_event(time(), 'hourly', 'fp_finance_hub_auto_import_csv');
        }
        
        // Pulizia file vecchi settimanale
        if (!wp_next_scheduled('fp_finance_hub_cleanup_import_files')) {
            wp_schedule_event(time(), 'weekly', 'fp_finance_hub_cleanup_import_files');
        }
    }
    
    /**
     * Rimuovi tutti i cron job
     */
    public static function unschedule() {
        $hooks = [
            'fp_finance_hub_sync_aruba_daily',
            'fp_finance_hub_sync_yapily_accounts',
            'fp_finance_hub_reconcile_transactions',
            'fp_finance_hub_check_alerts',
            'fp_finance_hub_calculate_projections',
            'fp_finance_hub_check_intelligence_alerts',
            'fp_finance_hub_refresh_intelligence',
        ];
        
        foreach ($hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }
    
    /**
     * Aggiungi schedulazioni personalizzate
     */
    public function add_cron_schedules($schedules) {
        $schedules['fp_finance_hub_6hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => 'Ogni 6 ore',
        ];
        
        return $schedules;
    }
    
    /**
     * Sync Aruba giornaliero
     */
    public static function sync_aruba_daily() {
        $aruba_sync = new ArubaSync();
        $aruba_sync->sync_invoices(
            date('Y-m-d', strtotime('-30 days')),
            date('Y-m-d')
        );
    }
    
    /**
     * Sync Yapily conti
     */
    public static function sync_yapily_accounts() {
        $yapily_sync = new YapilySyncService();
        $yapily_sync->sync_all_accounts();
    }
    
    /**
     * Riconciliazione automatica
     */
    public static function reconcile_transactions() {
        $reconciliation_service = ReconciliationService::get_instance();
        $reconciliation_service->reconcile_all();
    }
    
    /**
     * Verifica soglie e genera alert
     */
    public static function check_alerts() {
        $alert_service = AlertService::get_instance();
        $alert_service->check_thresholds();
    }
    
    /**
     * Calcola proiezioni
     */
    public static function calculate_projections() {
        $projection_service = ProjectionService::get_instance();
        
        // Calcola proiezioni per i prossimi 3 mesi
        for ($i = 0; $i < 3; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} months"));
            $month = date('n', strtotime($date));
            $year = date('Y', strtotime($date));
            
            $projection_service->calculate_income_projections($month, $year);
        }
    }
    
    /**
     * Verifica alert Intelligence
     */
    public static function check_intelligence_alerts() {
        $alert_service = AlertService::get_instance();
        $alert_service->check_intelligence_alerts();
    }
    
    /**
     * Refresh Intelligence (precomputa report per performance)
     */
    public static function refresh_intelligence() {
        $intelligence_service = IntelligenceAnalysisService::get_instance();
        $intelligence_service->force_refresh(30);
    }
}

// Registra hook cron
add_action('fp_finance_hub_sync_aruba_daily', [Jobs::class, 'sync_aruba_daily']);
add_action('fp_finance_hub_sync_yapily_accounts', [Jobs::class, 'sync_yapily_accounts']);
add_action('fp_finance_hub_reconcile_transactions', [Jobs::class, 'reconcile_transactions']);
add_action('fp_finance_hub_check_alerts', [Jobs::class, 'check_alerts']);
add_action('fp_finance_hub_calculate_projections', [Jobs::class, 'calculate_projections']);
add_action('fp_finance_hub_check_intelligence_alerts', [Jobs::class, 'check_intelligence_alerts']);
add_action('fp_finance_hub_refresh_intelligence', [Jobs::class, 'refresh_intelligence']);
