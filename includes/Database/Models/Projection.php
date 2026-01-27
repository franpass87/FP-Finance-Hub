<?php
/**
 * Model Proiezione
 * 
 * Gestisce CRUD proiezioni nel database
 */

namespace FP\FinanceHub\Database\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Projection {
    
    /**
     * Ottieni tabella proiezioni
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_finance_hub_projections';
    }
    
    /**
     * Crea nuova proiezione
     */
    public static function create($data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'projected_income' => 0.00,
            'actual_income' => 0.00,
            'scenario' => 'realistic',
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
     * Ottieni proiezione per mese/anno/scenario
     */
    public static function get($month, $year, $scenario = 'realistic') {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE month = %d AND year = %d AND scenario = %s",
            $month,
            $year,
            $scenario
        ));
    }
    
    /**
     * Crea o aggiorna proiezione (upsert)
     */
    public static function create_or_update($data) {
        $existing = self::get($data['month'], $data['year'], $data['scenario'] ?? 'realistic');
        
        if ($existing) {
            return self::update($existing->id, $data);
        }
        
        return self::create($data);
    }
    
    /**
     * Ottieni proiezioni per periodo
     */
    public static function get_by_period($start_month, $start_year, $end_month, $end_year, $scenario = null) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $where = [
            "(year > %d OR (year = %d AND month >= %d))",
            "(year < %d OR (year = %d AND month <= %d))",
        ];
        
        $values = [
            $start_year, $start_year, $start_month,
            $end_year, $end_year, $end_month,
        ];
        
        if (!empty($scenario)) {
            $where[] = "scenario = %s";
            $values[] = $scenario;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY year ASC, month ASC",
            $values
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Aggiorna proiezione
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
     * Elimina proiezione
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
     * Sanitizza dati proiezione
     */
    private static function sanitize($data) {
        $sanitized = [];
        
        $allowed = [
            'month', 'year', 'projected_income', 'actual_income', 'scenario',
        ];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['projected_income', 'actual_income'])) {
                    $sanitized[$field] = floatval($data[$field]);
                } elseif (in_array($field, ['month', 'year'])) {
                    $sanitized[$field] = absint($data[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                }
            }
        }
        
        return $sanitized;
    }
}
