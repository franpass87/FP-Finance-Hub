<?php
/**
 * Model Movimento Bancario
 * 
 * Gestisce CRUD movimenti bancari nel database
 */

namespace FP\FinanceHub\Database\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Transaction {
    
    /**
     * Ottieni tabella movimenti
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_finance_hub_bank_transactions';
    }
    
    /**
     * Crea nuovo movimento
     */
    public static function create($data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'reconciled' => false,
            'is_personal' => false,
            'is_business' => false,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitizza dati
        $data = self::sanitize($data);
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            return new \WP_Error('db_insert_error', $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Ottieni movimento per ID
     */
    public static function get($id) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Ottieni movimenti per conto
     */
    public static function get_by_account($account_id, $args = []) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'start_date' => null,
            'end_date' => null,
            'type' => null, // 'business' o 'personal'
            'reconciled' => null,
            'limit' => null,
            'orderby' => 'transaction_date',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ["account_id = %d"];
        $values = [$account_id];
        
        if (!empty($args['start_date'])) {
            $where[] = "transaction_date >= %s";
            $values[] = $args['start_date'];
        }
        
        if (!empty($args['end_date'])) {
            $where[] = "transaction_date <= %s";
            $values[] = $args['end_date'];
        }
        
        if ($args['type'] === 'business') {
            $where[] = "is_business = 1";
        } elseif ($args['type'] === 'personal') {
            $where[] = "is_personal = 1";
        }
        
        if ($args['reconciled'] !== null) {
            $where[] = "reconciled = %d";
            $values[] = (int) $args['reconciled'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM {$table} WHERE {$where_clause}";
        $sql .= " ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        if (!empty($args['limit'])) {
            $sql .= " LIMIT " . intval($args['limit']);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Cerca movimenti per riconciliazione (matching fattura)
     */
    public static function find_matching($invoice_amount, $due_date, $tolerance = 0.01, $days_range = 7) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $start_date = date('Y-m-d', strtotime($due_date . " -{$days_range} days"));
        $end_date = date('Y-m-d', strtotime($due_date . " +{$days_range} days"));
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE reconciled = 0
            AND ABS(amount - %f) <= %f
            AND transaction_date BETWEEN %s AND %s
            AND amount > 0
            ORDER BY ABS(amount - %f) ASC
            LIMIT 10",
            $invoice_amount,
            $tolerance,
            $start_date,
            $end_date,
            $invoice_amount
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Aggiorna movimento
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        // Ottieni dati esistenti per confronto categoria
        $existing = self::get($id);
        $old_category_id = $existing->category_id ?? null;
        
        // Sanitizza dati
        $data = self::sanitize($data);
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table,
            $data,
            ['id' => $id],
            null,
            ['%d']
        );
        
        if ($result === false) {
            return new \WP_Error('db_update_error', $wpdb->last_error);
        }
        
        // FASE 4: Trigger hook per apprendimento se categoria è cambiata
        $new_category_id = $data['category_id'] ?? null;
        if ($old_category_id != $new_category_id && $new_category_id) {
            $transaction = self::get($id);
            do_action('fp_finance_hub_transaction_category_updated', $id, $old_category_id, $new_category_id, $transaction);
            
            // Invalida cache statistiche
            $cache_service = \FP\FinanceHub\Services\CacheService::get_instance();
            $cache_service->invalidate_pattern('stats_period_*');
            $cache_service->invalidate_pattern('trend_12m_*');
        }
        
        return true;
    }
    
    /**
     * Riconcilia movimento con fattura
     */
    public static function reconcile($transaction_id, $invoice_id) {
        return self::update($transaction_id, [
            'invoice_id' => $invoice_id,
            'reconciled' => true,
            'reconciled_at' => current_time('mysql'),
        ]);
    }
    
    /**
     * Elimina movimento
     */
    public static function delete($id) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->delete(
            $table,
            ['id' => $id],
            ['%d']
        );
    }
    
    /**
     * Calcola totale entrate/uscite per periodo
     */
    public static function calculate_totals($account_id, $start_date, $end_date, $type = null) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $where = [];
        $values = [];
        
        // Se account_id è null, calcola per tutti i conti
        if ($account_id !== null) {
            $where[] = "account_id = %d";
            $values[] = $account_id;
        }
        
        // Filtro data
        if ($start_date && $end_date) {
            $where[] = "transaction_date BETWEEN %s AND %s";
            $values[] = $start_date;
            $values[] = $end_date;
        } elseif ($start_date) {
            $where[] = "transaction_date >= %s";
            $values[] = $start_date;
        } elseif ($end_date) {
            $where[] = "transaction_date <= %s";
            $values[] = $end_date;
        }
        
        // Filtro tipo (business/personal)
        if ($type === 'business') {
            $where[] = "is_business = 1";
        } elseif ($type === 'personal') {
            $where[] = "is_personal = 1";
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Prepara query
        if (!empty($values)) {
            $sql = $wpdb->prepare(
                "SELECT 
                    COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total_income,
                    COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as total_expenses,
                    COUNT(*) as transaction_count
                FROM {$table}
                {$where_clause}",
                $values
            );
        } else {
            $sql = "SELECT 
                COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as total_expenses,
                COUNT(*) as transaction_count
            FROM {$table}
            {$where_clause}";
        }
        
        $result = $wpdb->get_row($sql);
        
        // Assicura che i valori siano sempre numerici (non null)
        if (!$result) {
            $result = (object) [
                'total_income' => 0,
                'total_expenses' => 0,
                'transaction_count' => 0,
            ];
        } else {
            $result->total_income = floatval($result->total_income ?? 0);
            $result->total_expenses = floatval($result->total_expenses ?? 0);
            $result->transaction_count = intval($result->transaction_count ?? 0);
        }
        
        return $result;
    }
    
    /**
     * Sanitizza dati movimento
     */
    private static function sanitize($data) {
        $sanitized = [];
        
        $allowed = [
            'account_id', 'transaction_date', 'value_date',
            'amount', 'balance', 'description', 'reference',
            'category', 'subcategory', 'transaction_type',
            'is_personal', 'is_business', 'invoice_id',
            'reconciled', 'reconciled_at', 'import_source', 'raw_data',
        ];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['amount', 'balance'])) {
                    $sanitized[$field] = floatval($data[$field]);
                } elseif (in_array($field, ['account_id', 'invoice_id'])) {
                    $sanitized[$field] = absint($data[$field]);
                } elseif (in_array($field, ['is_personal', 'is_business', 'reconciled'])) {
                    $sanitized[$field] = (bool) $data[$field];
                } elseif ($field === 'description') {
                    $sanitized[$field] = sanitize_textarea_field($data[$field]);
                } elseif ($field === 'raw_data' && is_array($data[$field])) {
                    $sanitized[$field] = json_encode($data[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                }
            }
        }
        
        return $sanitized;
    }
}
