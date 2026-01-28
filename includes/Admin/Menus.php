<?php
/**
 * Admin Menus
 * 
 * Registrazione menu WordPress
 */

namespace FP\FinanceHub\Admin;

use FP\FinanceHub\Admin\Pages\DashboardPage;
use FP\FinanceHub\Admin\Pages\ClientsPage;
use FP\FinanceHub\Admin\Pages\InvoicesPage;
use FP\FinanceHub\Admin\Pages\BankAccountsPage;
use FP\FinanceHub\Admin\Pages\AnalyticsPage;
use FP\FinanceHub\Admin\Pages\AlertsPage;
use FP\FinanceHub\Admin\Pages\ImportPage;
use FP\FinanceHub\Admin\Pages\ReportsPage;
use FP\FinanceHub\Admin\Pages\SettingsPage;
use FP\FinanceHub\Admin\Pages\SetupGuidePage;

if (!defined('ABSPATH')) {
    exit;
}

class Menus {
    
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
        // Registra menu immediatamente (viene chiamato durante admin_menu)
        $this->register_menus();
    }
    
    /**
     * Registra menu e sottomenu
     */
    public function register_menus() {
        $capability = 'manage_options';
        
        // Menu principale
        add_menu_page(
            __('FP Finance Hub', 'fp-finance-hub'),
            __('FP Finance Hub', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub',
            [DashboardPage::class, 'render'],
            'dashicons-chart-line',
            30
        );
        
        // Dashboard (pagina principale)
        add_submenu_page(
            'fp-finance-hub',
            __('Dashboard', 'fp-finance-hub'),
            __('📊 Dashboard', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub',
            [DashboardPage::class, 'render']
        );
        
        // Guida Setup (solo se setup incompleto o sempre visibile)
        add_submenu_page(
            'fp-finance-hub',
            __('Guida Setup', 'fp-finance-hub'),
            __('🎯 Guida Setup', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub-setup-guide',
            [SetupGuidePage::class, 'render']
        );
        
        // Clienti (CRM)
        add_submenu_page(
            'fp-finance-hub',
            __('Clienti', 'fp-finance-hub'),
            __('👥 Clienti', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub-clients',
            [ClientsPage::class, 'render']
        );
        
        // Fatture
        add_submenu_page(
            'fp-finance-hub',
            __('Fatture', 'fp-finance-hub'),
            __('📄 Fatture', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub-invoices',
            [InvoicesPage::class, 'render']
        );
        
        // Conti Bancari
        add_submenu_page(
            'fp-finance-hub',
            __('Conti Bancari', 'fp-finance-hub'),
            __('🏦 Conti Bancari', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub-bank-accounts',
            [BankAccountsPage::class, 'render']
        );
        
        // Analisi Finanziarie (Proiezioni, Monitoraggio, Statistiche unificati)
        add_submenu_page(
            'fp-finance-hub',
            __('Analisi Finanziarie', 'fp-finance-hub'),
            __('📊 Analisi Finanziarie', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub-analytics',
            [AnalyticsPage::class, 'render']
        );
        
        // Alert
        add_submenu_page(
            'fp-finance-hub',
            __('Alert', 'fp-finance-hub'),
            __('⚠️ Alert', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub-alerts',
            [AlertsPage::class, 'render']
        );
        
        // Import
        add_submenu_page(
            'fp-finance-hub',
            __('Import Dati', 'fp-finance-hub'),
            __('🔗 Import Dati', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub-import',
            [ImportPage::class, 'render']
        );
        
        // Report
        add_submenu_page(
            'fp-finance-hub',
            __('Report', 'fp-finance-hub'),
            __('📄 Report', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub-reports',
            [ReportsPage::class, 'render']
        );
        
        // Impostazioni
        add_submenu_page(
            'fp-finance-hub',
            __('Impostazioni', 'fp-finance-hub'),
            __('⚙️ Impostazioni', 'fp-finance-hub'),
            $capability,
            'fp-finance-hub-settings',
            [SettingsPage::class, 'render']
        );
    }
}
