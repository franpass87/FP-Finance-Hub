<?php
/**
 * Logger
 * 
 * Sistema logging operazioni
 */

namespace FP\FinanceHub\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    
    /**
     * Log operazione
     */
    public static function log($operation, $message, $context = []) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_aruba_logs';
        
        $wpdb->insert($table, [
            'operation' => $operation,
            'status' => isset($context['error']) ? 'error' : 'success',
            'message' => $message,
            'aruba_response' => !empty($context) ? json_encode($context) : null,
            'created_at' => current_time('mysql'),
        ], [
            '%s', '%s', '%s', '%s', '%s',
        ]);
        
        // Log anche in error_log se WP_DEBUG attivo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[FP Finance Hub] %s: %s %s',
                $operation,
                $message,
                !empty($context) ? json_encode($context) : ''
            ));
        }
    }
}
