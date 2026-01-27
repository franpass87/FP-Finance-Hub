<?php
/**
 * Model Categorization Rule
 * 
 * Gestisce CRUD regole categorizzazione nel database
 */

namespace FP\FinanceHub\Database\Models;

if (!defined('ABSPATH')) {
    exit;
}

class CategorizationRule {
    
    /**
     * Ottieni tabella regole
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_finance_hub_categorization_rules';
    }
    
    /**
     * Crea nuova regola
     */
    public static function create($data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'rule_type' => 'pattern',
            'transaction_type' => 'personal',
            'priority' => 0,
            'is_active' => true,
            'match_count' => 0,
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
     * Ottieni regola per ID
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
     * Trova regole che matchano pattern
     */
    public static function find_matching_rules($pattern, $transaction_type = null) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $where = [
            "is_active = 1",
            "pattern LIKE %s",
        ];
        
        $values = ['%' . $wpdb->esc_like($pattern) . '%'];
        
        if ($transaction_type) {
            $where[] = "transaction_type = %s";
            $values[] = $transaction_type;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE {$where_clause}
            ORDER BY priority DESC, match_count DESC",
            $values
        ));
        
        return is_array($results) ? $results : [];
    }
    
    /**
     * Crea o aggiorna regola (upsert per pattern)
     */
    public static function create_or_update($data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        // Cerca esistente per pattern e categoria
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE pattern = %s 
            AND category_id = %d 
            LIMIT 1",
            $data['pattern'],
            $data['category_id']
        ));
        
        if ($existing) {
            // Aggiorna esistente
            $data = self::sanitize($data);
            $data['updated_at'] = current_time('mysql');
            $data['match_count'] = intval($existing->match_count) + 1;
            
            $wpdb->update(
                $table,
                $data,
                ['id' => $existing->id],
                null,
                ['%d']
            );
            
            return $existing->id;
        }
        
        // Crea nuova
        return self::create($data);
    }
    
    /**
     * Incrementa match count per regola
     */
    public static function increment_match_count($id) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} 
            SET match_count = match_count + 1,
                updated_at = %s
            WHERE id = %d",
            current_time('mysql'),
            $id
        ));
    }
    
    /**
     * Ottieni regole attive per categoria
     */
    public static function get_active_rules_for_category($category_id) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE category_id = %d 
            AND is_active = 1 
            ORDER BY priority DESC, match_count DESC",
            $category_id
        ));
        
        return is_array($results) ? $results : [];
    }
    
    /**
     * Promuovi pattern da learning a regola permanente
     */
    public static function promote_from_learning($pattern, $category_id, $subcategory_id = null, $transaction_type = 'personal', $priority = 50) {
        return self::create_or_update([
            'rule_type' => 'learned',
            'pattern' => $pattern,
            'category_id' => $category_id,
            'subcategory_id' => $subcategory_id,
            'transaction_type' => $transaction_type,
            'priority' => $priority,
            'is_active' => true,
        ]);
    }
    
    /**
     * Aggiorna regola
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
     * Elimina regola
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
     * Sanitizza dati regola
     */
    private static function sanitize($data) {
        $sanitized = [];
        
        $allowed = [
            'rule_type', 'pattern', 'category_id', 'subcategory_id',
            'transaction_type', 'priority', 'is_active', 'match_count',
        ];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['category_id', 'subcategory_id', 'priority', 'match_count'])) {
                    $sanitized[$field] = absint($data[$field]);
                } elseif ($field === 'is_active') {
                    $sanitized[$field] = (bool)$data[$field];
                } elseif (in_array($field, ['rule_type', 'transaction_type'])) {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                } elseif ($field === 'pattern') {
                    $sanitized[$field] = sanitize_textarea_field($data[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                }
            }
        }
        
        return $sanitized;
    }
}
