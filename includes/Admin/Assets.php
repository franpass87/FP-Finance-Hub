<?php
/**
 * Admin Assets
 * 
 * Registrazione CSS/JS admin
 */

namespace FP\FinanceHub\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Assets {
    
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
     * Constructor
     */
    private function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Enqueue CSS/JS admin
     */
    public function enqueue_assets($hook) {
        // Solo nelle pagine del plugin
        if (strpos($hook, 'fp-finance-hub') === false) {
            return;
        }
        
        $version = FP_FINANCE_HUB_VERSION;
        
        // CSS Admin
        wp_enqueue_style(
            'fp-finance-hub-admin',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/css/admin.css',
            [],
            $version
        );
        
        // JavaScript Admin
        wp_enqueue_script(
            'fp-finance-hub-admin',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/admin.js',
            ['jquery'],
            $version,
            true
        );
        
        // Localize admin script
        wp_localize_script('fp-finance-hub-admin', 'fpFinanceHub', [
            'apiUrl' => rest_url('fp-finance-hub/v1/'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
        
        // Component JavaScript (caricati sempre per tutte le pagine)
        wp_enqueue_script(
            'fp-finance-hub-modal',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/components/modal.js',
            ['jquery', 'fp-finance-hub-admin'],
            $version,
            true
        );
        
        wp_enqueue_script(
            'fp-finance-hub-tabs',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/components/tabs.js',
            ['jquery', 'fp-finance-hub-admin'],
            $version,
            true
        );
        
        wp_enqueue_script(
            'fp-finance-hub-form-validation',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/components/form-validation.js',
            ['jquery', 'fp-finance-hub-admin'],
            $version,
            true
        );
        
        // Wizard Component (per setup guide)
        wp_enqueue_script(
            'fp-finance-hub-wizard',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/components/wizard.js',
            ['jquery', 'fp-finance-hub-admin'],
            $version,
            true
        );
        
        // Help Modal Component
        wp_enqueue_script(
            'fp-finance-hub-help-modal',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/components/help-modal.js',
            ['jquery', 'fp-finance-hub-admin'],
            $version,
            true
        );
        
        // Lazy Loading Component
        wp_enqueue_script(
            'fp-finance-hub-lazy-loading',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/components/lazy-loading.js',
            ['jquery', 'fp-finance-hub-admin'],
            $version,
            true
        );
        
        // Touch Gestures Component
        wp_enqueue_script(
            'fp-finance-hub-touch-gestures',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/components/touch-gestures.js',
            ['jquery', 'fp-finance-hub-admin'],
            $version,
            true
        );
        
        // Toast Component
        wp_enqueue_script(
            'fp-finance-hub-toast',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/components/toast.js',
            ['jquery', 'fp-finance-hub-admin'],
            $version,
            true
        );
        
        // Loading Component
        wp_enqueue_script(
            'fp-finance-hub-loading',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/components/loading.js',
            ['jquery', 'fp-finance-hub-admin'],
            $version,
            true
        );
        
        // CSS Components
        wp_enqueue_style(
            'fp-finance-hub-help',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/css/components/help.css',
            ['fp-finance-hub-admin'],
            $version
        );
        
        wp_enqueue_style(
            'fp-finance-hub-wizard',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/css/components/wizard.css',
            ['fp-finance-hub-admin'],
            $version
        );
        
        wp_enqueue_style(
            'fp-finance-hub-toast',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/css/components/toast.css',
            ['fp-finance-hub-admin'],
            $version
        );
        
        wp_enqueue_style(
            'fp-finance-hub-loading',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/css/components/loading.css',
            ['fp-finance-hub-admin'],
            $version
        );
        
        // Mobile Responsive CSS
        wp_enqueue_style(
            'fp-finance-hub-mobile',
            FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/css/mobile/responsive.css',
            ['fp-finance-hub-admin'],
            $version
        );
        
        // Dashboard JavaScript (solo per dashboard)
        if ($hook === 'toplevel_page_fp-finance-hub') {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                [],
                '4.4.0',
                true
            );
            
            wp_enqueue_script(
                'fp-finance-hub-dashboard',
                FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/dashboard.js',
                ['jquery', 'chart-js'],
                $version,
                true
            );
            
            // Localize script
            wp_localize_script('fp-finance-hub-dashboard', 'fpFinanceHub', [
                'apiUrl' => rest_url('fp-finance-hub/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
        
        // Setup Guide JavaScript (solo per pagina setup guide)
        if ($hook === 'finance-hub_page_fp-finance-hub-setup-guide') {
            wp_enqueue_script(
                'fp-finance-hub-setup-guide',
                FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/setup-guide.js',
                ['jquery', 'fp-finance-hub-wizard', 'fp-finance-hub-admin'],
                $version,
                true
            );
            
            // Localize script
            wp_localize_script('fp-finance-hub-setup-guide', 'fpFinanceHub', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
        
        // Analytics JavaScript (solo per pagina analytics)
        if ($hook === 'finance-hub_page_fp-finance-hub-analytics') {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                [],
                '4.4.0',
                true
            );
            
            wp_enqueue_script(
                'fp-finance-hub-analytics',
                FP_FINANCE_HUB_PLUGIN_URL . 'assets/admin/js/analytics.js',
                ['jquery', 'chart-js', 'fp-finance-hub-admin'],
                $version,
                true
            );
            
            // Localize script
            wp_localize_script('fp-finance-hub-analytics', 'fpFinanceHub', [
                'apiUrl' => rest_url('fp-finance-hub/v1/'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }
}
