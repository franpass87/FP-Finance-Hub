<?php
/**
 * Model Categorization Learning
 * 
 * Gestisce CRUD apprendimento categorizzazione nel database
 */

namespace FP\FinanceHub\Database\Models;

if (!defined('ABSPATH')) {
    exit;
}

class CategorizationLearning {
    
    /**
     * Ottieni tabella learning
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_finance_hub_categorization_learning';
    }
    
    /**
     * Crea nuovo record learning
     */
    public static function create($data) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $defaults = [
            'assigned_by' => 'manual',
            'confidence' => 1.00,
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
     * Ottieni learning per categoria
     */
    public static function get_by_category($category_id, $limit = 100) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE assigned_category_id = %d 
            ORDER BY created_at DESC 
            LIMIT %d",
            $category_id,
            $limit
        ));
        
        return is_array($results) ? $results : [];
    }
    
    /**
     * Ottieni learning per pattern simile
     */
    public static function find_similar_patterns($normalized_description, $limit = 10) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        // Cerca pattern simili usando LIKE (fuzzy matching più avanzato nel service)
        $keywords = explode(' ', $normalized_description);
        $keywords = array_filter($keywords, function($kw) {
            return strlen($kw) > 3; // Solo keyword significative
        });
        
        if (empty($keywords)) {
            return [];
        }
        
        $like_clauses = [];
        foreach ($keywords as $keyword) {
            $like_clauses[] = $wpdb->prepare("normalized_description LIKE %s", '%' . $wpdb->esc_like($keyword) . '%');
        }
        
        $where_clause = implode(' OR ', $like_clauses);
        
        $sql = "SELECT 
                    *,
                    assigned_category_id as category_id,
                    COUNT(*) as match_count
                FROM {$table}
                WHERE {$where_clause}
                GROUP BY assigned_category_id, normalized_description
                ORDER BY match_count DESC, confidence DESC
                LIMIT " . intval($limit);
        
        $results = $wpdb->get_results($sql);
        
        return is_array($results) ? $results : [];
    }
    
    /**
     * Aggiorna confidence per pattern
     */
    public static function update_confidence($pattern, $category_id, $new_confidence) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $wpdb->update(
            $table,
            ['confidence' => $new_confidence],
            [
                'normalized_description' => $pattern,
                'assigned_category_id' => $category_id,
            ],
            ['%f'],
            ['%s', '%d']
        );
    }
    
    /**
     * Ottieni statistiche pattern per categoria
     */
    public static function get_pattern_stats($category_id) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_patterns,
                AVG(confidence) as avg_confidence,
                COUNT(DISTINCT normalized_description) as unique_patterns
            FROM {$table}
            WHERE assigned_category_id = %d",
            $category_id
        ));
        
        return $result ? [
            'total_patterns' => intval($result->total_patterns ?? 0),
            'avg_confidence' => floatval($result->avg_confidence ?? 0),
            'unique_patterns' => intval($result->unique_patterns ?? 0),
        ] : [
            'total_patterns' => 0,
            'avg_confidence' => 0,
            'unique_patterns' => 0,
        ];
    }
    
    /**
     * Ottieni keywords estratte per categoria
     */
    public static function get_keywords_for_category($category_id, $limit = 50) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT keywords_extracted 
            FROM {$table}
            WHERE assigned_category_id = %d 
            AND keywords_extracted IS NOT NULL 
            AND keywords_extracted != ''
            ORDER BY created_at DESC 
            LIMIT %d",
            $category_id,
            $limit
        ));
        
        $all_keywords = [];
        foreach ($results as $row) {
            $keywords = json_decode($row->keywords_extracted, true);
            if (is_array($keywords)) {
                $all_keywords = array_merge($all_keywords, $keywords);
            }
        }
        
        // Conta frequenza keywords
        $keyword_counts = array_count_values($all_keywords);
        arsort($keyword_counts);
        
        return array_slice($keyword_counts, 0, $limit, true);
    }
    
    /**
     * Sanitizza dati learning
     */
    private static function sanitize($data) {
        $sanitized = [];
        
        $allowed = [
            'transaction_id', 'original_description', 'normalized_description',
            'assigned_category_id', 'assigned_by', 'confidence', 'keywords_extracted',
        ];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if ($field === 'keywords_extracted') {
                    $sanitized[$field] = is_array($data[$field]) 
                        ? json_encode($data[$field]) 
                        : sanitize_text_field($data[$field]);
                } elseif (in_array($field, ['transaction_id', 'assigned_category_id'])) {
                    $sanitized[$field] = absint($data[$field]);
                } elseif (in_array($field, ['confidence'])) {
                    $sanitized[$field] = floatval($data[$field]);
                } elseif (in_array($field, ['original_description', 'normalized_description'])) {
                    $sanitized[$field] = sanitize_textarea_field($data[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                }
            }
        }
        
        return $sanitized;
    }
}
