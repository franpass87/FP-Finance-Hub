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

/**
 * Esegue composer install se necessario
 * 
 * @param string $plugin_dir Directory del plugin
 * @return bool True se composer install è stato eseguito con successo
 */
if (!function_exists('fp_finance_hub_run_composer_install')) {
    function fp_finance_hub_run_composer_install($plugin_dir) {
    $composer_json = $plugin_dir . 'composer.json';
    if (!file_exists($composer_json)) {
        return false;
    }
    
    // Prova prima con composer globale
    $composer_path = '';
    $possible_paths = array(
        'composer', // PATH
        '/usr/local/bin/composer',
        '/usr/bin/composer',
        '/opt/composer/composer',
    );
    
    foreach ($possible_paths as $path) {
        $test_cmd = (strpos($path, '/') === 0 ? $path : 'which ' . $path) . ' 2>&1';
        $output = array();
        $return_var = 0;
        @exec($test_cmd, $output, $return_var);
        if ($return_var === 0) {
            $composer_path = $path;
            break;
        }
    }
    
    if (empty($composer_path)) {
        // Prova con composer.phar locale
        $composer_phar = $plugin_dir . 'composer.phar';
        if (file_exists($composer_phar)) {
            $composer_path = 'php ' . escapeshellarg($composer_phar);
        }
    }
    
    if (empty($composer_path)) {
        return false;
    }
    
    // Esegui composer install
    $full_cmd = $composer_path . ' install --no-dev --optimize-autoloader --working-dir=' . escapeshellarg($plugin_dir) . ' 2>&1';
    $output = array();
    $return_var = 0;
    
    @set_time_limit(300);
    @exec($full_cmd, $output, $return_var);
    
    return $return_var === 0;
    }
}

/**
 * Esegue composer install automaticamente dopo installazione/aggiornamento
 * Utile quando il plugin viene installato tramite Git Updater
 */
add_action('upgrader_process_complete', function($upgrader, $hook_extra) {
    // Verifica se è un'installazione/aggiornamento di plugin
    if (!isset($hook_extra['plugin']) && !isset($hook_extra['plugins'])) {
        return;
    }
    
    // Determina se questo plugin è stato installato/aggiornato
    $plugin_updated = false;
    if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === FP_FINANCE_HUB_PLUGIN_BASENAME) {
        $plugin_updated = true;
    } elseif (isset($hook_extra['plugins']) && is_array($hook_extra['plugins'])) {
        foreach ($hook_extra['plugins'] as $plugin) {
            if ($plugin === FP_FINANCE_HUB_PLUGIN_BASENAME) {
                $plugin_updated = true;
                break;
            }
        }
    }
    
    if (!$plugin_updated) {
        return;
    }
    
    // Verifica se vendor/autoload.php esiste già
    $autoload_file = FP_FINANCE_HUB_PLUGIN_DIR . 'vendor/autoload.php';
    if (file_exists($autoload_file)) {
        return; // Composer install già eseguito
    }
    
    // Esegui composer install usando la funzione helper
    $result = fp_finance_hub_run_composer_install(FP_FINANCE_HUB_PLUGIN_DIR);
    
    if ($result && file_exists($autoload_file)) {
        error_log('[FP-FINANCE-HUB] Composer install eseguito con successo dopo aggiornamento');
        
        // Pulisci cache WordPress
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    } else {
        error_log('[FP-FINANCE-HUB] Composer install fallito dopo aggiornamento. Esegui manualmente: composer install');
    }
}, 10, 2);

// Carica Composer autoload (PSR-4)
$autoload_file = FP_FINANCE_HUB_PLUGIN_DIR . 'vendor/autoload.php';

if (file_exists($autoload_file)) {
    require_once $autoload_file;
} else {
    // Tenta di eseguire composer install automaticamente
    $composer_installed = fp_finance_hub_run_composer_install(FP_FINANCE_HUB_PLUGIN_DIR);
    
    // Verifica di nuovo se autoload esiste dopo composer install
    if (file_exists($autoload_file)) {
        require_once $autoload_file;
    } else {
        // Se composer install non ha funzionato, mostra l'errore
        add_action('admin_notices', function() use ($composer_installed) {
            if (!current_user_can('activate_plugins')) {
                return;
            }
            echo '<div class="notice notice-error"><p>';
            echo '<strong>FP Finance Hub:</strong> ';
            if ($composer_installed) {
                echo 'Composer install eseguito ma autoload non trovato. Verifica i permessi della directory.';
            } else {
                echo 'Esegui <code>composer install</code> nella cartella del plugin. Composer potrebbe non essere disponibile sul server.';
            }
            echo '</p></div>';
        });
        return;
    }
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
