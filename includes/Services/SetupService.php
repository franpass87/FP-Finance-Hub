<?php
/**
 * Setup Service
 * 
 * Rilevamento e tracking configurazione iniziale plugin
 */

namespace FP\FinanceHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

class SetupService {
    
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
     * Verifica se setup è completato
     */
    public function is_setup_complete() {
        $aruba_configured = $this->is_aruba_configured();
        $has_bank_data = $this->has_bank_data();
        $has_aruba_sync = $this->has_aruba_sync();
        
        return $aruba_configured && ($has_bank_data || $has_aruba_sync);
    }
    
    /**
     * Verifica se Aruba è configurato
     */
    public function is_aruba_configured() {
        $username = get_option('fp_finance_hub_aruba_username', '');
        $password = get_option('fp_finance_hub_aruba_password', '');
        
        return !empty($username) && !empty($password);
    }
    
    /**
     * Verifica se ci sono conti bancari o transazioni (da import CSV)
     */
    public function has_bank_data() {
        global $wpdb;
        $accounts_table = $wpdb->prefix . 'fp_finance_hub_bank_accounts';
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        $accounts_count = $wpdb->get_var("SELECT COUNT(*) FROM {$accounts_table}");
        $transactions_count = $wpdb->get_var("SELECT COUNT(*) FROM {$transactions_table}");
        
        return intval($accounts_count) > 0 || intval($transactions_count) > 0;
    }
    
    /**
     * Verifica se ci sono fatture sincronizzate da Aruba
     */
    public function has_aruba_sync() {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_finance_hub_invoices';
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE aruba_id IS NOT NULL AND aruba_id != ''"
        );
        
        return intval($count) > 0;
    }
    
    /**
     * Ottieni progresso setup
     */
    public function get_setup_progress() {
        $steps = [
            'bank_data' => [
                'name' => 'Conti Bancari / Import',
                'completed' => $this->has_bank_data(),
                'url' => admin_url('admin.php?page=fp-finance-hub-bank-accounts'),
            ],
            'aruba_configured' => [
                'name' => 'Configurazione Aruba',
                'completed' => $this->is_aruba_configured(),
                'url' => admin_url('admin.php?page=fp-finance-hub-settings'),
            ],
            'aruba_synced' => [
                'name' => 'Sincronizza Aruba',
                'completed' => $this->has_aruba_sync(),
                'url' => admin_url('admin.php?page=fp-finance-hub-import&tab=aruba'),
            ],
        ];
        
        $completed = array_filter($steps, function($step) {
            return $step['completed'];
        });
        
        $progress = [
            'steps' => $steps,
            'completed_count' => count($completed),
            'total_count' => count($steps),
            'percentage' => (count($completed) / count($steps)) * 100,
            'is_complete' => $this->is_setup_complete(),
        ];
        
        return $progress;
    }
    
    /**
     * Ottieni step successivo da completare
     */
    public function get_next_step() {
        $progress = $this->get_setup_progress();
        
        foreach ($progress['steps'] as $key => $step) {
            if (!$step['completed']) {
                return [
                    'key' => $key,
                    'step' => $step,
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Salva progresso step
     */
    public function mark_step_completed($step_key) {
        $user_id = get_current_user_id();
        $completed_steps = get_user_meta($user_id, 'fp_finance_hub_setup_steps', true);
        
        if (!is_array($completed_steps)) {
            $completed_steps = [];
        }
        
        if (!in_array($step_key, $completed_steps)) {
            $completed_steps[] = $step_key;
            update_user_meta($user_id, 'fp_finance_hub_setup_steps', $completed_steps);
        }
    }
}
