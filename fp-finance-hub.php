<?php
/**
 * Plugin Name: FP Finance Hub
 * Plugin URI: https://francescopasseri.com
 * Description: CRM + Dashboard Finanziario Completo (Aziendale + Familiare)
 * Version: 1.0.0
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fp-finance-hub
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definisci costanti del plugin
define('FP_FINANCE_HUB_VERSION', '1.0.0');
define('FP_FINANCE_HUB_PLUGIN_DIR', dirname(__FILE__) . '/');
define('FP_FINANCE_HUB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FP_FINANCE_HUB_PLUGIN_FILE', __FILE__);
define('FP_FINANCE_HUB_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Carica Composer autoload (PSR-4)
$autoload_file = FP_FINANCE_HUB_PLUGIN_DIR . 'vendor/autoload.php';

if (file_exists($autoload_file)) {
    require_once $autoload_file;
} else {
    add_action('admin_notices', function() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>';
        echo '<strong>FP Finance Hub:</strong> ';
        echo 'Esegui <code>composer install</code> nella cartella del plugin.';
        echo '</p></div>';
    });
    return;
}

// Usa namespace
use FP\FinanceHub\Plugin;

/**
 * Inizializza il plugin
 */
function fp_finance_hub_init() {
    if (!defined('ABSPATH')) {
        return false;
    }
    
    try {
        // Carica traduzioni
        load_plugin_textdomain(
            'fp-finance-hub', 
            false, 
            dirname(FP_FINANCE_HUB_PLUGIN_BASENAME) . '/languages'
        );
        
        // Inizializza il plugin principale
        return Plugin::get_instance();
    } catch (Exception $e) {
        error_log('[FP-FINANCE-HUB] Errore fatale: ' . $e->getMessage());
        return false;
    }
}

// Hook di attivazione
register_activation_hook(__FILE__, function() {
    if (class_exists('\FP\FinanceHub\Activation')) {
        \FP\FinanceHub\Activation::activate();
    }
});

// Hook di disattivazione
register_deactivation_hook(__FILE__, function() {
    if (class_exists('\FP\FinanceHub\Deactivation')) {
        \FP\FinanceHub\Deactivation::deactivate();
    }
});

// Avvia il plugin
add_action('plugins_loaded', 'fp_finance_hub_init', 10);
