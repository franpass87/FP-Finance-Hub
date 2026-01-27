<?php
/**
 * Model Cliente
 * 
 * Gestisce CRUD clienti nel database
 */

namespace FP\FinanceHub\Database\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Client {
    
    /**
     * Ottieni tabella clienti
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_finance_hub_clients';
    }
    
    /**
     * Crea nuovo cliente
     */
    public static function create($data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'name' => '',
            'source' => 'manual',
            'country' => 'IT',
            'synced_to_publisher' => false,
            'synced_to_task_agenda' => false,
            'synced_to_dms' => false,
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
     * Ottieni cliente per ID
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
     * Trova cliente per P.IVA
     */
    public static function find_by_vat($vat_number) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE vat_number = %s LIMIT 1",
            $vat_number
        ));
    }
    
    /**
     * Ottieni tutti i clienti
     */
    public static function get_all($args = []) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'search' => '',
            'source' => '',
            'category' => '',
            'orderby' => 'name',
            'order' => 'ASC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['1=1'];
        $values = [];
        
        if (!empty($args['search'])) {
            $where[] = "(name LIKE %s OR email LIKE %s OR vat_number LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }
        
        if (!empty($args['source'])) {
            $where[] = "source = %s";
            $values[] = $args['source'];
        }
        
        if (!empty($args['category'])) {
            $where[] = "category = %s";
            $values[] = $args['category'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Prepara tutti i valori insieme
        $all_values = $values;
        $all_values[] = $args['per_page'];
        $all_values[] = $offset;
        
        $sql = "SELECT * FROM {$table} WHERE {$where_clause}";
        $sql .= " ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        $sql .= " LIMIT %d OFFSET %d";
        
        $sql = $wpdb->prepare($sql, $all_values);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Conta clienti totali
     */
    public static function count($args = []) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $where = ['1=1'];
        $values = [];
        
        if (!empty($args['search'])) {
            $where[] = "(name LIKE %s OR email LIKE %s OR vat_number LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }
        
        if (!empty($args['source'])) {
            $where[] = "source = %s";
            $values[] = $args['source'];
        }
        
        if (!empty($args['category'])) {
            $where[] = "category = %s";
            $values[] = $args['category'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Aggiorna cliente
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
     * Elimina cliente
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
     * Crea o aggiorna cliente (upsert per P.IVA)
     */
    public static function create_or_update($data) {
        // Se ha P.IVA, cerca esistente
        if (!empty($data['vat_number'])) {
            $existing = self::find_by_vat($data['vat_number']);
            
            if ($existing) {
                // Aggiorna esistente
                self::update($existing->id, $data);
                return $existing->id;
            }
        }
        
        // Crea nuovo
        return self::create($data);
    }
    
    /**
     * Sanitizza dati cliente
     */
    private static function sanitize($data) {
        $sanitized = [];
        
        $allowed = [
            'name', 'business_name', 'vat_number', 'fiscal_code',
            'email', 'phone', 'mobile', 'website',
            'address', 'city', 'postcode', 'province', 'country',
            'source', 'source_id', 'tags', 'category', 'notes', 'metadata',
            'synced_to_publisher', 'synced_to_task_agenda', 'synced_to_dms',
            'last_sync_publisher', 'last_sync_task_agenda', 'last_sync_dms',
        ];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
                
                // Campi TEXT speciali
                if (in_array($field, ['address', 'notes'])) {
                    $sanitized[$field] = sanitize_textarea_field($data[$field]);
                }
                
                // Campi JSON
                if (in_array($field, ['tags', 'metadata'])) {
                    if (is_array($data[$field])) {
                        $sanitized[$field] = json_encode($data[$field]);
                    } else {
                        $sanitized[$field] = $data[$field];
                    }
                }
            }
        }
        
        return $sanitized;
    }
}
