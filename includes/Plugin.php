<?php
/**
 * Classe principale del plugin
 * 
 * Gestisce l'inizializzazione e il coordinamento delle varie componenti
 */

namespace FP\FinanceHub;

use FP\FinanceHub\Admin\Pages\BankAccountsPage;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    
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
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Inizializza hook WordPress
     */
    private function init_hooks() {
        // Database
        add_action('plugins_loaded', [$this, 'init_database'], 5);
        
        // Admin
        add_action('admin_init', [$this, 'run_db_migrations'], 5);
        add_action('admin_init', [Admin\Pages\BankAccountsPage::class, 'init'], 10);
        add_action('admin_menu', [$this, 'init_admin'], 9);
        
        // REST API
        add_action('rest_api_init', [$this, 'init_rest_api']);
        
        // Cron jobs
        add_action('init', [$this, 'init_cron']);
        
        // AJAX handlers
        add_action('wp_ajax_fp_finance_hub_check_setup_progress', [$this, 'ajax_check_setup_progress']);
    }
    
    /**
     * Inizializza componenti
     */
    private function init_components() {
        // Database Schema (verifica versione)
        $schema = Database\Schema::get_instance();
        if ($schema->check_schema_version()) {
            Database\Schema::create_tables();
        } else {
            // Esegui migrazioni anche se versione DB è aggiornata (per colonne mancanti)
            $schema->migrate_bank_accounts_add_bank_name();
        }
        
        // Services base
        Services\CacheService::get_instance(); // Cache service per performance
        Services\ClientService::get_instance();
        Services\InvoiceService::get_instance();
        Services\BankService::get_instance();
        $categorization_engine = Services\CategorizationEngine::get_instance();
        Services\ReconciliationService::get_instance();
        Services\ProjectionService::get_instance();
        Services\AlertService::get_instance();
        Services\StatsService::get_instance();
        
        // FASE 4: Registra hook per apprendimento categorizzazione
        $this->init_categorization_learning_hooks($categorization_engine);
    }
    
    /**
     * FASE 4: Inizializza hook di apprendimento categorizzazione
     */
    private function init_categorization_learning_hooks($categorization_engine) {
        // Hook quando utente modifica categoria transazione
        add_action('fp_finance_hub_transaction_category_updated', function($transaction_id, $old_category_id, $new_category_id, $transaction) use ($categorization_engine) {
            if ($new_category_id && $transaction) {
                // Apprendi dalla correzione
                $categorization_engine->learn_from_correction($transaction_id, $old_category_id, $new_category_id);
            }
        }, 10, 4);
        
        // Hook quando transazione viene categorizzata automaticamente (per apprendimento futuro)
        add_action('fp_finance_hub_transaction_categorized', function($transaction_id, $category_id, $transaction) use ($categorization_engine) {
            if ($category_id && $transaction) {
                $is_business = !empty($transaction->is_business);
                $categorization_engine->learn_from_transaction($transaction, $category_id, $is_business);
            }
        }, 10, 3);
    }
    
    /**
     * Inizializza database
     */
    public function init_database() {
        Database\Schema::get_instance();
    }
    
    /**
     * Esegue migrazioni DB (colonne mancanti) su ogni caricamento admin
     */
    public function run_db_migrations() {
        $schema = Database\Schema::get_instance();
        $schema->migrate_bank_accounts_add_bank_name();
    }
    
    /**
     * Inizializza admin
     */
    public function init_admin() {
        Admin\Menus::get_instance();
        Admin\Assets::get_instance();
    }
    
    /**
     * Inizializza REST API
     */
    public function init_rest_api() {
        REST\ClientsController::get_instance();
        REST\InvoicesController::get_instance();
        REST\BankAccountsController::get_instance();
        REST\TransactionsController::get_instance();
        REST\ProjectionsController::get_instance();
        REST\SyncController::get_instance();
        REST\StatsController::get_instance();
        REST\IntelligenceController::get_instance();
    }
    
    /**
     * Inizializza cron jobs
     */
    public function init_cron() {
        Cron\Jobs::get_instance();
    }
    
    /**
     * Attivazione plugin
     */
    public static function activate() {
        // Crea tabelle database
        Database\Schema::create_tables();
        
        // Imposta opzioni default
        self::set_default_options();
        
        // Schedula cron jobs
        Cron\Jobs::schedule();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Disattivazione plugin
     */
    public static function deactivate() {
        // Rimuovi cron jobs
        Cron\Jobs::unschedule();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Imposta opzioni predefinite
     */
    private static function set_default_options() {
        $defaults = [
            'fp_finance_hub_version' => FP_FINANCE_HUB_VERSION,
            'fp_finance_hub_encryption_key' => wp_generate_password(32, false),
        ];
        
        foreach ($defaults as $key => $value) {
            if (false === get_option($key)) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * AJAX handler: Check setup progress
     */
    public function ajax_check_setup_progress() {
        // Verifica nonce (può essere wp_rest o wp_ajax)
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_rest')) {
            wp_send_json_error(['message' => 'Nonce non valido'], 403);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permessi insufficienti'], 403);
            return;
        }
        
        $setup_service = Services\SetupService::get_instance();
        $progress = $setup_service->get_setup_progress();
        
        // Converti steps per JavaScript
        $steps_data = [];
        foreach ($progress['steps'] as $key => $step) {
            $steps_data[$key] = [
                'name' => $step['name'],
                'completed' => $step['completed'],
                'url' => $step['url'],
            ];
        }
        
        wp_send_json_success([
            'steps' => $steps_data,
            'completed_count' => $progress['completed_count'],
            'total_count' => $progress['total_count'],
            'percentage' => $progress['percentage'],
            'is_complete' => $progress['is_complete'],
        ]);
    }
}
