<?php
/**
 * Model Alert
 * 
 * Gestisce CRUD alert nel database
 */

namespace FP\FinanceHub\Database\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Alert {
    
    /**
     * Ottieni tabella alert
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_finance_hub_alerts';
    }
    
    /**
     * Crea nuovo alert
     */
    public static function create($data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'severity' => 'warning',
            'is_active' => true,
            'acknowledged' => false,
            'created_at' => current_time('mysql'),
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
     * Ottieni alert per ID
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
     * Ottieni alert attivi
     */
    public static function get_active($args = []) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'severity' => null,
            'alert_type' => null,
            'threshold_id' => null,
            'acknowledged' => false,
            'limit' => null,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ["is_active = 1"];
        $values = [];
        
        if ($args['acknowledged'] !== null) {
            $where[] = "acknowledged = %d";
            $values[] = (int) $args['acknowledged'];
        }
        
        if (!empty($args['severity'])) {
            $where[] = "severity = %s";
            $values[] = $args['severity'];
        }
        
        if (!empty($args['alert_type'])) {
            $where[] = "alert_type = %s";
            $values[] = $args['alert_type'];
        }
        
        if (!empty($args['threshold_id'])) {
            $where[] = "threshold_id = %d";
            $values[] = absint($args['threshold_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        if (!empty($args['limit'])) {
            $sql .= " LIMIT " . intval($args['limit']);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Conta alert attivi non riconosciuti
     */
    public static function count_active_unacknowledged() {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND acknowledged = 0"
        );
    }
    
    /**
     * Aggiorna alert
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        // Sanitizza dati
        $data = self::sanitize($data);
        
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
     * Riconosci alert
     */
    public static function acknowledge($id) {
        return self::update($id, [
            'acknowledged' => true,
            'acknowledged_at' => current_time('mysql'),
        ]);
    }
    
    /**
     * Disattiva alert
     */
    public static function deactivate($id) {
        return self::update($id, [
            'is_active' => false,
        ]);
    }
    
    /**
     * Elimina alert
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
     * Elimina alert vecchi (oltre N giorni)
     */
    public static function cleanup_old($days = 30) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s AND acknowledged = 1",
            $date
        ));
    }
    
    /**
     * Sanitizza dati alert
     */
    private static function sanitize($data) {
        $sanitized = [];
        
        $allowed = [
            'alert_type', 'severity', 'message', 'threshold_id',
            'current_value', 'threshold_value',
            'is_active', 'acknowledged', 'acknowledged_at',
        ];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['current_value', 'threshold_value'])) {
                    $sanitized[$field] = floatval($data[$field]);
                } elseif (in_array($field, ['threshold_id'])) {
                    $sanitized[$field] = absint($data[$field]);
                } elseif (in_array($field, ['is_active', 'acknowledged'])) {
                    $sanitized[$field] = (bool) $data[$field];
                } elseif ($field === 'message') {
                    $sanitized[$field] = sanitize_textarea_field($data[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                }
            }
        }
        
        return $sanitized;
    }
}
