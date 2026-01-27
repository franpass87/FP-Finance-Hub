<?php
/**
 * Service Gestione Conti Bancari
 * 
 * Logica business per gestione conti, saldi, movimenti
 */

namespace FP\FinanceHub\Services;

use FP\FinanceHub\Database\Models\BankAccount as BankAccountModel;
use FP\FinanceHub\Database\Models\Transaction as TransactionModel;
use FP\FinanceHub\Services\CategorizationEngine;
use FP\FinanceHub\Services\CacheService;

if (!defined('ABSPATH')) {
    exit;
}

class BankService {
    
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
     * Crea nuovo conto bancario
     */
    public function create_account($data) {
        return BankAccountModel::create($data);
    }
    
    /**
     * Ottieni conto per ID
     */
    public function get_account($id) {
        return BankAccountModel::get($id);
    }
    
    /**
     * Ottieni tutti i conti attivi
     */
    public function get_active_accounts() {
        return BankAccountModel::get_active();
    }
    
    /**
     * Ottieni saldo totale di tutti i conti (con cache)
     */
    public function get_total_balance() {
        $cache_service = CacheService::get_instance();
        $cache_key = 'total_balance_all';
        
        // Cache per 5 minuti (saldo cambia frequentemente)
        return $cache_service->remember($cache_key, function() {
            return BankAccountModel::get_total_balance();
        }, 300);
    }
    
    /**
     * Aggiorna saldo conto (invalida cache)
     */
    public function update_account_balance($account_id, $balance, $balance_date = null) {
        $result = BankAccountModel::update_balance($account_id, $balance, $balance_date);
        
        // Invalida cache saldo totale
        $cache_service = CacheService::get_instance();
        $cache_service->delete('total_balance_all');
        
        return $result;
    }
    
    /**
     * Importa movimento bancario (invalida cache)
     */
    public function import_transaction($account_id, $transaction_data) {
        // Categorizza automaticamente
        $categorization = CategorizationEngine::get_instance()->categorize(
            (object) $transaction_data
        );
        
        // Aggiungi dati categorizzazione
        $transaction_data['category'] = $categorization['category'];
        $transaction_data['subcategory'] = $categorization['subcategory'];
        $transaction_data['transaction_type'] = $categorization['transaction_type'];
        $transaction_data['is_personal'] = $categorization['is_personal'];
        $transaction_data['is_business'] = $categorization['is_business'];
        $transaction_data['account_id'] = $account_id;
        
        $transaction_id = TransactionModel::create($transaction_data);
        
        // FASE 4: Trigger hook per apprendimento se categoria assegnata automaticamente
        if ($categorization['category'] && $transaction_id && !is_wp_error($transaction_id)) {
            $transaction = TransactionModel::get($transaction_id);
            if ($transaction) {
                // Usa category_id se disponibile, altrimenti cerca per nome categoria
                $category_id = $transaction->category_id ?? null;
                if ($category_id || $categorization['category']) {
                    do_action('fp_finance_hub_transaction_categorized', $transaction_id, $category_id, $transaction);
                }
            }
        }
        
        // Invalida cache saldo e statistiche
        $cache_service = CacheService::get_instance();
        $cache_service->delete('total_balance_all');
        $cache_service->invalidate_pattern('stats_period_*');
        $cache_service->invalidate_pattern('trend_12m_*');
        
        return $transaction_id;
    }
    
    /**
     * Ottieni movimenti per conto
     */
    public function get_transactions($account_id, $args = []) {
        return TransactionModel::get_by_account($account_id, $args);
    }
    
    /**
     * Calcola totale entrate/uscite per periodo
     */
    public function calculate_totals($account_id, $start_date, $end_date, $type = null) {
        return TransactionModel::calculate_totals($account_id, $start_date, $end_date, $type);
    }
    
    /**
     * Ottieni ultimo saldo importato per conto
     */
    public function get_last_balance($account_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT balance FROM {$table} 
            WHERE account_id = %d 
            ORDER BY transaction_date DESC, id DESC 
            LIMIT 1",
            $account_id
        ));
    }
    
    /**
     * Ottieni movimenti recenti
     */
    public function get_recent_transactions($account_id = null, $limit = 20) {
        global $wpdb;
        
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        $where = [];
        $values = [];
        
        if ($account_id) {
            $where[] = "account_id = %d";
            $values[] = $account_id;
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM {$transactions_table} {$where_clause} ORDER BY transaction_date DESC, id DESC LIMIT %d";
        
        if (!empty($values)) {
            $values[] = $limit;
            $sql = $wpdb->prepare($sql, $values);
        } else {
            $sql = $wpdb->prepare($sql, $limit);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Calcola cash flow netto per periodo
     */
    public function calculate_cashflow($start_date, $end_date, $account_id = null) {
        $totals = $this->calculate_totals($account_id, $start_date, $end_date);
        
        return [
            'income' => floatval($totals->total_income ?: 0),
            'expenses' => floatval($totals->total_expenses ?: 0),
            'net' => floatval($totals->total_income ?: 0) - floatval($totals->total_expenses ?: 0),
        ];
    }
}
