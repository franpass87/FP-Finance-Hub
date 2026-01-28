<?php
/**
 * Database Schema
 * 
 * Gestisce la creazione e gestione delle tabelle del database
 */

namespace FP\FinanceHub\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Schema {
    
    private static $instance = null;
    
    const DB_VERSION = '1.0.0';
    const DB_VERSION_OPTION = 'fp_finance_hub_db_version';
    
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
     * Verifica se schema deve essere aggiornato
     */
    public function check_schema_version() {
        $current_version = get_option(self::DB_VERSION_OPTION, '0.0.0');
        return version_compare($current_version, self::DB_VERSION, '<');
    }
    
    /**
     * Crea tutte le tabelle del database
     */
    public static function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $schema = self::get_instance();
        $schema->create_clients_table();
        $schema->create_invoices_table();
        $schema->create_recurring_income_table();
        $schema->create_projections_table();
        $schema->create_taxes_table();
        $schema->create_bank_accounts_table();
        $schema->create_bank_transactions_table();
        $schema->create_expense_categories_table();
        $schema->create_family_budget_table();
        $schema->create_thresholds_table();
        $schema->create_alerts_table();
        $schema->create_recurring_expenses_table();
        $schema->create_categorization_rules_table();
        $schema->create_categorization_learning_table();
        $schema->create_aruba_logs_table();
        
        // Migrazioni per colonne mancanti
        $schema->migrate_bank_accounts_add_bank_name();
        
        // Aggiorna versione database
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }
    
    /**
     * Migrazione: aggiunge colonna bank_name a bank_accounts se non esiste
     */
    public function migrate_bank_accounts_add_bank_name() {
        global $wpdb;
        
        $table_name = $this->get_table_name('bank_accounts');
        
        // Verifica se la colonna esiste già
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table_name} LIKE %s",
                'bank_name'
            )
        );
        
        // Se la colonna non esiste, aggiungila
        if (empty($column_exists)) {
            $wpdb->query(
                "ALTER TABLE {$table_name} ADD COLUMN bank_name VARCHAR(255) NULL AFTER iban"
            );
        }
    }
    
    /**
     * Ottieni nome tabella con prefisso
     */
    public function get_table_name($table_name) {
        global $wpdb;
        return $wpdb->prefix . 'fp_finance_hub_' . $table_name;
    }
    
    /**
     * Crea tabella clienti (CRM)
     */
    private function create_clients_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('clients');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            business_name VARCHAR(255) NULL,
            vat_number VARCHAR(50) NULL,
            fiscal_code VARCHAR(50) NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            mobile VARCHAR(50) NULL,
            website VARCHAR(255) NULL,
            address TEXT NULL,
            city VARCHAR(100) NULL,
            postcode VARCHAR(20) NULL,
            province VARCHAR(10) NULL,
            country VARCHAR(2) DEFAULT 'IT',
            source VARCHAR(50) DEFAULT 'manual',
            source_id VARCHAR(255) NULL,
            tags TEXT NULL,
            category VARCHAR(100) NULL,
            notes TEXT NULL,
            metadata TEXT NULL,
            synced_to_publisher BOOLEAN DEFAULT FALSE,
            synced_to_task_agenda BOOLEAN DEFAULT FALSE,
            synced_to_dms BOOLEAN DEFAULT FALSE,
            last_sync_publisher DATETIME NULL,
            last_sync_task_agenda DATETIME NULL,
            last_sync_dms DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name),
            KEY vat_number (vat_number),
            KEY source (source),
            KEY category (category),
            KEY name_vat (name, vat_number),
            KEY source_category (source, category)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella fatture
     */
    private function create_invoices_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('invoices');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT(20) UNSIGNED NULL,
            invoice_number VARCHAR(100) NOT NULL,
            issue_date DATE NOT NULL,
            due_date DATE NULL,
            paid_date DATE NULL,
            amount DECIMAL(10,2) NOT NULL,
            tax_rate DECIMAL(5,2) DEFAULT 0.00,
            tax_amount DECIMAL(10,2) DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            payment_method VARCHAR(50) NULL,
            notes TEXT NULL,
            aruba_id VARCHAR(255) NULL,
            aruba_sdi_id VARCHAR(255) NULL,
            aruba_status VARCHAR(50) NULL,
            aruba_sent_at DATETIME NULL,
            aruba_xml_path VARCHAR(500) NULL,
            aruba_sync_status VARCHAR(50) DEFAULT 'pending',
            aruba_last_sync DATETIME NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY invoice_number (invoice_number),
            KEY issue_date (issue_date),
            KEY status (status),
            KEY aruba_id (aruba_id),
            KEY aruba_sdi_id (aruba_sdi_id),
            KEY client_status (client_id, status),
            KEY issue_date_status (issue_date, status),
            KEY due_date_status (due_date, status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella entrate ricorrenti
     */
    private function create_recurring_income_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('recurring_income');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT(20) UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            frequency VARCHAR(50) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            next_due_date DATE NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY frequency (frequency),
            KEY next_due_date (next_due_date),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella proiezioni
     */
    private function create_projections_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('projections');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            month INT(2) NOT NULL,
            year INT(4) NOT NULL,
            projected_income DECIMAL(10,2) DEFAULT 0.00,
            actual_income DECIMAL(10,2) DEFAULT 0.00,
            scenario VARCHAR(50) DEFAULT 'realistic',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY month_year_scenario (month, year, scenario)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella tasse
     */
    private function create_taxes_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('taxes');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tax_type VARCHAR(50) NOT NULL,
            period VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            due_date DATE NULL,
            paid_date DATE NULL,
            status VARCHAR(50) DEFAULT 'pending',
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tax_type (tax_type),
            KEY period (period),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella conti bancari
     */
    private function create_bank_accounts_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('bank_accounts');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            account_number VARCHAR(100) NULL,
            iban VARCHAR(34) NULL,
            bank_name VARCHAR(255) NULL,
            currency VARCHAR(3) DEFAULT 'EUR',
            current_balance DECIMAL(10,2) DEFAULT 0.00,
            last_balance_date DATE NULL,
            starting_balance DECIMAL(10,2) DEFAULT 0.00,
            is_active BOOLEAN DEFAULT TRUE,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella movimenti bancari
     */
    private function create_bank_transactions_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('bank_transactions');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id BIGINT(20) UNSIGNED NOT NULL,
            transaction_date DATE NOT NULL,
            value_date DATE NULL,
            amount DECIMAL(10,2) NOT NULL,
            balance DECIMAL(10,2) NULL,
            description TEXT NULL,
            reference VARCHAR(255) NULL,
            category VARCHAR(50) NULL,
            subcategory VARCHAR(100) NULL,
            transaction_type VARCHAR(50) NULL,
            is_personal BOOLEAN DEFAULT FALSE,
            is_business BOOLEAN DEFAULT FALSE,
            invoice_id BIGINT(20) UNSIGNED NULL,
            reconciled BOOLEAN DEFAULT FALSE,
            reconciled_at DATETIME NULL,
            import_source VARCHAR(50) NULL,
            raw_data LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY account_id (account_id),
            KEY transaction_date (transaction_date),
            KEY invoice_id (invoice_id),
            KEY reconciled (reconciled),
            KEY category (category),
            KEY transaction_type (transaction_type),
            KEY is_personal (is_personal),
            KEY is_business (is_business),
            KEY account_date (account_id, transaction_date),
            KEY date_category (transaction_date, category),
            KEY account_reconciled (account_id, reconciled),
            KEY date_type (transaction_date, transaction_type)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella categorie spese
     */
    private function create_expense_categories_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('expense_categories');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL,
            parent_id BIGINT(20) UNSIGNED NULL,
            icon VARCHAR(50) NULL,
            color VARCHAR(7) NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY parent_id (parent_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella budget familiare
     */
    private function create_family_budget_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('family_budget');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            month VARCHAR(7) NOT NULL,
            category_id BIGINT(20) UNSIGNED NOT NULL,
            budgeted_amount DECIMAL(10,2) NOT NULL,
            actual_amount DECIMAL(10,2) DEFAULT 0.00,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY month_category (month, category_id),
            KEY category_id (category_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella soglie sicurezza
     */
    private function create_thresholds_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('thresholds');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            threshold_type VARCHAR(50) NOT NULL,
            threshold_value DECIMAL(10,2) NOT NULL,
            threshold_period VARCHAR(50) NULL,
            is_active BOOLEAN DEFAULT TRUE,
            alert_level VARCHAR(50) DEFAULT 'warning',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY threshold_type (threshold_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella alert
     */
    private function create_alerts_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('alerts');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            alert_type VARCHAR(50) NOT NULL,
            severity VARCHAR(50) DEFAULT 'warning',
            message TEXT NOT NULL,
            threshold_id BIGINT(20) UNSIGNED NULL,
            current_value DECIMAL(10,2) NULL,
            threshold_value DECIMAL(10,2) NULL,
            is_active BOOLEAN DEFAULT TRUE,
            acknowledged BOOLEAN DEFAULT FALSE,
            acknowledged_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY alert_type (alert_type),
            KEY severity (severity),
            KEY is_active (is_active),
            KEY acknowledged (acknowledged)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella uscite ricorrenti
     */
    private function create_recurring_expenses_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('recurring_expenses');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            frequency VARCHAR(50) NOT NULL,
            category VARCHAR(100) NULL,
            expense_type VARCHAR(50) DEFAULT 'business',
            start_date DATE NOT NULL,
            end_date DATE NULL,
            is_active BOOLEAN DEFAULT TRUE,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY frequency (frequency),
            KEY expense_type (expense_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella regole categorizzazione
     */
    private function create_categorization_rules_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('categorization_rules');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_type VARCHAR(50) NOT NULL,
            pattern TEXT NOT NULL,
            category_id BIGINT(20) UNSIGNED NOT NULL,
            subcategory_id BIGINT(20) UNSIGNED NULL,
            transaction_type VARCHAR(50) DEFAULT 'personal',
            priority INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            match_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category_id (category_id),
            KEY priority (priority)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella apprendimento categorizzazione
     */
    private function create_categorization_learning_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('categorization_learning');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            transaction_id BIGINT(20) UNSIGNED NOT NULL,
            original_description TEXT NOT NULL,
            normalized_description TEXT NULL,
            assigned_category_id BIGINT(20) UNSIGNED NOT NULL,
            assigned_by VARCHAR(50) DEFAULT 'manual',
            confidence DECIMAL(3,2) DEFAULT 1.00,
            keywords_extracted TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY transaction_id (transaction_id),
            KEY category_id (assigned_category_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crea tabella log Aruba
     */
    private function create_aruba_logs_table() {
        global $wpdb;
        
        $table_name = $this->get_table_name('aruba_logs');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT(20) UNSIGNED NULL,
            operation VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            message TEXT NULL,
            aruba_response LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY operation (operation),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
}
