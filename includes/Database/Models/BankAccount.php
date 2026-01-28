<?php
/**
 * Model Conto Bancario
 * 
 * Gestisce CRUD conti bancari nel database
 */

namespace FP\FinanceHub\Database\Models;

if (!defined('ABSPATH')) {
    exit;
}

class BankAccount {
    
    /**
     * Ottieni tabella conti bancari
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_finance_hub_bank_accounts';
    }
    
    /**
     * Crea nuovo conto bancario
     */
    public static function create($data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'type' => 'current',
            'currency' => 'EUR',
            'current_balance' => 0.00,
            'starting_balance' => 0.00,
            'is_active' => true,
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
     * Ottieni conto per ID
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
     * Ottieni tutti i conti attivi
     */
    public static function get_active() {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY name ASC"
        );
    }
    
    /**
     * Calcola saldo totale di tutti i conti
     */
    public static function get_total_balance() {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $result = $wpdb->get_var(
            "SELECT SUM(current_balance) FROM {$table} WHERE is_active = 1"
        );
        
        return floatval($result ?: 0);
    }
    
    /**
     * Aggiorna saldo conto
     */
    public static function update_balance($id, $balance, $balance_date = null) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $data = [
            'current_balance' => floatval($balance),
            'updated_at' => current_time('mysql'),
        ];
        
        if ($balance_date) {
            $data['last_balance_date'] = $balance_date;
        } else {
            $data['last_balance_date'] = current_time('Y-m-d');
        }
        
        return $wpdb->update(
            $table,
            $data,
            ['id' => $id],
            ['%f', '%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Aggiorna conto
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
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
        
        return true;
    }
    
    /**
     * Elimina conto
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
     * Sanitizza dati conto
     */
    private static function sanitize($data) {
        $sanitized = [];
        
        $allowed = [
            'name', 'type', 'account_number', 'iban', 'currency', 'bank_name',
            'current_balance', 'last_balance_date', 'starting_balance',
            'is_active', 'notes',
        ];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['current_balance', 'starting_balance'])) {
                    $sanitized[$field] = floatval($data[$field]);
                } elseif ($field === 'is_active') {
                    $sanitized[$field] = (bool) $data[$field];
                } elseif ($field === 'notes') {
                    $sanitized[$field] = sanitize_textarea_field($data[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                }
            }
        }
        
        return $sanitized;
    }
}
