# 🏗️ Struttura Plugin FP-Finance-Hub (Standard FP)

## 📋 Standard Plugin FP

Basato su analisi di:
- FP-Task-Agenda
- FP-Civic-Engagement  
- FP-Git-Updater
- FP-Performance

---

## 📁 **STRUTTURA COMPLETA**

```
FP-Finance-Hub/
├── fp-finance-hub.php                 ⭐ **FILE PRINCIPALE**
├── composer.json                      ⭐ **COMPOSER (PSR-4)**
├── README.md                          - Overview plugin
├── CHANGELOG.md                       - Storico versioni
│
├── includes/                          📂 **CODICE PRINCIPALE** (PSR-4)
│   ├── Plugin.php                     - Classe principale (Singleton)
│   ├── Activation.php                 - Hook attivazione
│   ├── Deactivation.php               - Hook disattivazione
│   │
│   ├── Admin/                         👤 **ADMIN**
│   │   ├── Pages/
│   │   │   ├── DashboardPage.php      - Dashboard finanziario
│   │   │   ├── ClientsPage.php        - Gestione clienti
│   │   │   ├── InvoicesPage.php       - Gestione fatture
│   │   │   ├── BankAccountsPage.php   - Conti bancari
│   │   │   ├── ProjectionsPage.php    - Proiezioni
│   │   │   ├── AlertsPage.php         - Soglie e alert
│   │   │   ├── ReportsPage.php        - Report
│   │   │   └── SettingsPage.php       - Impostazioni
│   │   ├── Menus.php                  - Registrazione menu
│   │   └── Assets.php                 - CSS/JS admin
│   │
│   ├── REST/                          🔌 **API REST**
│   │   ├── Controller.php             - Controller base
│   │   ├── ClientsController.php      - API clienti
│   │   ├── InvoicesController.php     - API fatture
│   │   ├── BankAccountsController.php - API conti bancari
│   │   ├── ProjectionsController.php  - API proiezioni
│   │   └── SyncController.php         - API sincronizzazione
│   │
│   ├── Database/                      🗄️ **DATABASE**
│   │   ├── Schema.php                 - Schema tabelle
│   │   ├── Migrations.php             - Migrazioni DB
│   │   └── Models/
│   │       ├── Client.php             - Model cliente
│   │       ├── Invoice.php            - Model fattura
│   │       ├── BankAccount.php        - Model conto bancario
│   │       ├── Transaction.php        - Model movimento
│   │       └── Alert.php              - Model alert
│   │
│   ├── Integration/                   🔗 **INTEGRAZIONI**
│   │   ├── Aruba/
│   │   │   ├── ArubaAPI.php           - Client API Aruba
│   │   │   ├── ArubaSync.php          - Sincronizzazione Aruba
│   │   │   ├── ArubaReconciliation.php - Riconciliazione
│   │   │   └── ArubaXMLParser.php     - Parser XML fatture
│   │   │
│   │   ├── Publisher/
│   │   │   └── PublisherSync.php      - Sync verso FP Publisher
│   │   │
│   │   ├── TaskAgenda/
│   │   │   └── TaskAgendaSync.php     - Sync verso Task Agenda
│   │   │
│   │   └── DMS/
│   │       └── DMSSync.php            - Sync verso DMS
│   │
│   ├── Import/                        📥 **IMPORT**
│   │   ├── Bank/
│   │   │   ├── PostePayParser.php     - Parser CSV PostePay
│   │   │   ├── INGParser.php          - Parser CSV ING
│   │   │   └── OFXParser.php          - Parser OFX generico
│   │   │
│   │   └── Importer.php               - Classe base import
│   │
│   ├── Services/                      ⚙️ **SERVIZI**
│   │   ├── ClientService.php          - Logica business clienti
│   │   ├── InvoiceService.php         - Logica business fatture
│   │   ├── BankService.php            - Logica business conti
│   │   ├── ReconciliationService.php  - Logica riconciliazione
│   │   ├── ProjectionService.php      - Calcolo proiezioni
│   │   ├── AlertService.php           - Gestione alert
│   │   └── StatsService.php           - Calcolo statistiche
│   │
│   ├── Cron/                          ⏰ **CRON JOBS**
│   │   └── Jobs.php                   - Job schedulati
│   │
│   └── Utils/                         🛠️ **UTILITY**
│       ├── Helpers.php                - Funzioni helper
│       ├── Logger.php                 - Sistema logging
│       └── Validator.php              - Validazione dati
│
├── assets/                            📦 **ASSETS**
│   ├── admin/
│   │   ├── css/
│   │   │   └── admin.css              - Stili admin
│   │   └── js/
│   │       ├── admin.js               - JavaScript admin
│   │       └── dashboard.js           - JavaScript dashboard
│   │
│   └── frontend/                      (se necessario)
│
├── templates/                         📄 **TEMPLATES**
│   └── admin/                         - Template admin pages
│
├── languages/                         🌐 **TRADUZIONI**
│   └── fp-finance-hub.pot             - File traduzione
│
├── vendor/                            📚 **COMPOSER** (generato)
│   └── autoload.php
│
└── docs/                              📖 **DOCUMENTAZIONE**
    ├── INTEGRAZIONE-ARUBA.md
    ├── GESTIONE-CONTI-BANCARI.md
    ├── FLUSSO-RICONCILIAZIONE.md
    └── SINCRONIZZAZIONE-PLUGIN.md
```

---

## 📄 **FILE PRINCIPALE** (`fp-finance-hub.php`)

```php
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
```

---

## 📦 **COMPOSER.JSON**

```json
{
    "name": "franpass87/fp-finance-hub",
    "description": "CRM + Dashboard Finanziario Completo (Aziendale + Familiare)",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "version": "1.0.0",
    "authors": [
        {
            "name": "Francesco Passeri",
            "email": "info@francescopasseri.com",
            "homepage": "https://www.francescopasseri.com"
        }
    ],
    "require": {
        "php": ">=7.4"
    },
    "autoload": {
        "psr-4": {
            "FP\\FinanceHub\\": "includes/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true
    }
}
```

---

## 🔧 **CLASSE PLUGIN PRINCIPALE** (`includes/Plugin.php`)

```php
<?php
/**
 * Classe principale del plugin
 * 
 * Gestisce l'inizializzazione e il coordinamento delle varie componenti
 */

namespace FP\FinanceHub;

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
        if (is_admin()) {
            add_action('admin_menu', [$this, 'init_admin']);
        }
        
        // REST API
        add_action('rest_api_init', [$this, 'init_rest_api']);
        
        // Cron jobs
        add_action('init', [$this, 'init_cron']);
    }
    
    /**
     * Inizializza componenti
     */
    private function init_components() {
        // Inizializza database
        Database\Schema::get_instance();
        
        // Inizializza servizi
        Services\ClientService::get_instance();
        Services\InvoiceService::get_instance();
        Services\BankService::get_instance();
    }
    
    /**
     * Inizializza database
     */
    public function init_database() {
        Database\Schema::get_instance();
    }
    
    /**
     * Inizializza admin
     */
    public function init_admin() {
        Admin\Menus::get_instance();
    }
    
    /**
     * Inizializza REST API
     */
    public function init_rest_api() {
        REST\ClientsController::get_instance();
        REST\InvoicesController::get_instance();
        // ... altri controller
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
}
```

---

## 🎯 **CARATTERISTICHE ARCHITETTURA**

### ✅ **Standard FP Rispettati**

1. **PSR-4 Autoload**
   - Namespace: `FP\FinanceHub\`
   - Mapping: `FP\FinanceHub\ → includes/`
   - Autoload via Composer

2. **Pattern Singleton**
   - Classe `Plugin` principale
   - Servizi principali come Singleton

3. **Struttura Modulare**
   - Cartelle per dominio (Admin, REST, Database, Integration)
   - Separazione concerns (Models, Services, Controllers)

4. **File Principale Semplice**
   - Solo costanti, autoload, hook inizializzazione
   - Logica nel namespace

5. **Naming Convention**
   - Classi: PascalCase (`ClientService.php`)
   - File: PascalCase matching classe
   - Namespace: PSR-4 standard

---

## 🔄 **FACILE DA MODIFICARE CON CURSOR AI**

### **Vantaggi Struttura Modulare:**

1. **Ricerca Facile**
   - `ClientService` → `includes/Services/ClientService.php`
   - `ArubaAPI` → `includes/Integration/Aruba/ArubaAPI.php`

2. **Modifiche Localizzate**
   - Modifica `BankService` → solo quel file
   - Aggiungi nuovo parser → nuova classe in `Import/Bank/`

3. **Dipendenze Chiare**
   - Namespace mostra dipendenze
   - Autoload gestisce inclusioni

4. **Testabile**
   - Servizi isolati
   - Dependency injection possibile

---

## 📝 **PRIMI FILE DA CREARE**

### Fase 1: Setup Base
1. ✅ `fp-finance-hub.php` (file principale)
2. ✅ `composer.json` (autoload PSR-4)
3. ✅ `includes/Plugin.php` (classe principale)
4. ✅ `includes/Activation.php`
5. ✅ `includes/Deactivation.php`
6. ✅ `includes/Database/Schema.php`

### Fase 2: Core Features
7. ✅ `includes/Database/Models/Client.php`
8. ✅ `includes/Services/ClientService.php`
9. ✅ `includes/Admin/Menus.php`
10. ✅ `includes/Admin/Pages/ClientsPage.php`

---

## ✅ **CHECKLIST IMPLEMENTAZIONE**

- [ ] Struttura cartelle creata
- [ ] File principale creato
- [ ] composer.json configurato
- [ ] Classe Plugin.php creata
- [ ] Activation/Deactivation hooks
- [ ] Database Schema
- [ ] Autoload funzionante
- [ ] Namespace corretto
- [ ] Test caricamento plugin

---

**Questa struttura rispetta lo standard FP ed è ottimizzata per Cursor AI!** 🚀
