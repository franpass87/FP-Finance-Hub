<?php
/**
 * Model Fattura
 * 
 * Gestisce CRUD fatture nel database
 */

namespace FP\FinanceHub\Database\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Invoice {
    
    /**
     * Ottieni tabella fatture
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_finance_hub_invoices';
    }
    
    /**
     * Crea nuova fattura
     */
    public static function create($data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'user_id' => get_current_user_id(),
            'status' => 'pending',
            'tax_rate' => 0.00,
            'tax_amount' => 0.00,
            'aruba_sync_status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Calcola total_amount se non fornito
        if (!isset($data['total_amount'])) {
            $data['total_amount'] = $data['amount'] + $data['tax_amount'];
        }
        
        // Sanitizza dati
        $data = self::sanitize($data);
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            return new \WP_Error('db_insert_error', $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Ottieni fattura per ID
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
     * Trova fattura per numero
     */
    public static function find_by_number($invoice_number) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE invoice_number = %s LIMIT 1",
            $invoice_number
        ));
    }
    
    /**
     * Trova fattura per Aruba ID
     */
    public static function find_by_aruba_id($aruba_id) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE aruba_id = %s LIMIT 1",
            $aruba_id
        ));
    }
    
    /**
     * Ottieni fatture non pagate
     */
    public static function get_unpaid($args = []) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'client_id' => null,
            'aruba_status' => [],
            'limit' => null,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ["status != 'paid'"];
        $values = [];
        
        if (!empty($args['client_id'])) {
            $where[] = "client_id = %d";
            $values[] = $args['client_id'];
        }
        
        if (!empty($args['aruba_status'])) {
            $placeholders = implode(',', array_fill(0, count($args['aruba_status']), '%s'));
            $where[] = "aruba_status IN ($placeholders)";
            $values = array_merge($values, $args['aruba_status']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY issue_date DESC";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        if (!empty($args['limit'])) {
            $sql .= " LIMIT " . intval($args['limit']);
        }
        
        $results = $wpdb->get_results($sql);
        return is_array($results) ? $results : [];
    }
    
    /**
     * Calcola potenziale entrate (fatture non pagate)
     */
    public static function calculate_potential_income($aruba_statuses = ['Inviata', 'Accettata', 'Consegnata']) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $placeholders = implode(',', array_fill(0, count($aruba_statuses), '%s'));
        
        $sql = $wpdb->prepare(
            "SELECT SUM(total_amount) as total
            FROM {$table}
            WHERE status != 'paid'
            AND aruba_status IN ($placeholders)",
            $aruba_statuses
        );
        
        $result = $wpdb->get_var($sql);
        
        return floatval($result ?: 0);
    }
    
    /**
     * Aggiorna fattura
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
     * Elimina fattura
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
     * Crea o aggiorna fattura (upsert per Aruba ID)
     */
    public static function create_or_update($data) {
        // Se ha aruba_id, cerca esistente
        if (!empty($data['aruba_id'])) {
            $existing = self::find_by_aruba_id($data['aruba_id']);
            
            if ($existing) {
                // Aggiorna esistente
                self::update($existing->id, $data);
                return $existing->id;
            }
        }
        
        // Crea nuova
        return self::create($data);
    }
    
    /**
     * Sanitizza dati fattura
     */
    private static function sanitize($data) {
        $sanitized = [];
        
        $allowed = [
            'client_id', 'invoice_number', 'issue_date', 'due_date', 'paid_date',
            'amount', 'tax_rate', 'tax_amount', 'total_amount',
            'status', 'payment_method', 'notes',
            'aruba_id', 'aruba_sdi_id', 'aruba_status', 'aruba_sent_at',
            'aruba_xml_path', 'aruba_sync_status', 'aruba_last_sync',
            'user_id',
        ];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['amount', 'tax_rate', 'tax_amount', 'total_amount'])) {
                    $sanitized[$field] = floatval($data[$field]);
                } elseif (in_array($field, ['client_id', 'user_id'])) {
                    $sanitized[$field] = absint($data[$field]);
                } elseif (in_array($field, ['issue_date', 'due_date', 'paid_date', 'aruba_sent_at', 'aruba_last_sync'])) {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
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
