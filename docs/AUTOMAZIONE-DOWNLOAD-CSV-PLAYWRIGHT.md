# 🤖 Automazione Download CSV con Playwright - FP Finance Hub

**Data:** Gennaio 2026  
**Obiettivo:** Automatizzare il download CSV da ING e PostePay usando Playwright, poi importare automaticamente nel plugin

---

## 🎯 **SOLUZIONE PROPOSTA**

### **Architettura:**

```
┌─────────────────────────────────────────────────────────┐
│ 1. Cron Job (WordPress)                                 │
│    Esegue ogni X ore/giorni                             │
└──────────────┬──────────────────────────────────────────┘
               ↓
┌─────────────────────────────────────────────────────────┐
│ 2. Playwright Script (Node.js)                          │
│    - Accede all'area riservata banca                    │
│    - Fa login con credenziali criptate                   │
│    - Scarica CSV                                         │
│    - Salva in directory temporanea                      │
└──────────────┬──────────────────────────────────────────┘
               ↓
┌─────────────────────────────────────────────────────────┐
│ 3. WordPress Import Service                             │
│    - Legge CSV dalla directory                          │
│    - Usa Importer esistente                             │
│    - Importa movimenti e saldi                          │
│    - Categorizzazione automatica                        │
└─────────────────────────────────────────────────────────┘
```

---

## 📋 **COMPONENTI DA CREARE**

### **1. Playwright Script (Node.js)**

**File:** `scripts/bank-csv-downloader.js`

**Funzionalità:**
- Login automatico ING/PostePay
- Download CSV
- Gestione errori e retry
- Logging

**Dipendenze:**
```json
{
  "dependencies": {
    "playwright": "^1.40.0",
    "dotenv": "^16.0.0"
  }
}
```

### **2. WordPress Service**

**File:** `includes/Services/AutoImportService.php` (già esiste, da estendere)

**Funzionalità:**
- Chiama script Playwright
- Gestisce credenziali criptate
- Importa CSV scaricato
- Notifica errori

### **3. Database Schema**

**Tabella:** `wp_fp_finance_hub_bank_auto_import`

```sql
CREATE TABLE wp_fp_finance_hub_bank_auto_import (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT(20) NOT NULL,
  bank_type VARCHAR(50) NOT NULL, -- 'ing', 'postepay'
  username_encrypted TEXT, -- Criptato AES-256
  password_encrypted TEXT, -- Criptato AES-256
  last_download_at DATETIME,
  last_success_at DATETIME,
  last_error TEXT,
  is_active BOOLEAN DEFAULT TRUE,
  frequency VARCHAR(20) DEFAULT 'daily', -- 'hourly', 'daily', 'weekly'
  next_run_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY account_id (account_id),
  KEY next_run_at (next_run_at)
);
```

### **4. Admin UI**

**File:** `includes/Admin/Pages/BankAccountsPage.php` (da estendere)

**Funzionalità:**
- Form per configurare credenziali
- Selezione frequenza download
- Test download manuale
- Log download

---

## 🚀 **IMPLEMENTAZIONE**

### **Step 1: Playwright Script**

**File:** `scripts/bank-csv-downloader.js`

```javascript
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

/**
 * Scarica CSV da banca usando Playwright
 * 
 * @param {string} bankType - 'ing' o 'postepay'
 * @param {string} username - Username banca
 * @param {string} password - Password banca
 * @param {string} outputDir - Directory dove salvare CSV
 * @returns {Promise<string>} Path del file CSV scaricato
 */
async function downloadBankCSV(bankType, username, password, outputDir) {
    const browser = await chromium.launch({
        headless: true, // Esegui in background
    });
    
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    });
    
    const page = await context.newPage();
    
    try {
        if (bankType === 'ing') {
            return await downloadINGCSV(page, username, password, outputDir);
        } else if (bankType === 'postepay') {
            return await downloadPostePayCSV(page, username, password, outputDir);
        } else {
            throw new Error(`Tipo banca non supportato: ${bankType}`);
        }
    } finally {
        await browser.close();
    }
}

/**
 * Download CSV da ING Direct
 */
async function downloadINGCSV(page, username, password, outputDir) {
    console.log('[ING] Navigazione a login...');
    await page.goto('https://www.ingdirect.it/');
    
    // Clicca su "Accedi"
    await page.click('text=Accedi');
    await page.waitForTimeout(2000);
    
    // Compila form login
    console.log('[ING] Compilazione form login...');
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    
    // Attendi login (verifica presenza elementi dashboard)
    await page.waitForSelector('.dashboard', { timeout: 10000 });
    console.log('[ING] Login completato');
    
    // Naviga a export movimenti
    console.log('[ING] Navigazione a export movimenti...');
    await page.goto('https://www.ingdirect.it/area-riservata/movimenti/export');
    
    // Seleziona periodo (ultimi 90 giorni)
    await page.selectOption('select[name="period"]', '90');
    
    // Clicca "Esporta CSV"
    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.click('button:has-text("Esporta CSV")'),
    ]);
    
    // Salva file
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const filename = `ing-movimenti-${timestamp}.csv`;
    const filepath = path.join(outputDir, filename);
    
    await download.saveAs(filepath);
    console.log(`[ING] CSV salvato: ${filepath}`);
    
    return filepath;
}

/**
 * Download CSV da PostePay Evolution
 */
async function downloadPostePayCSV(page, username, password, outputDir) {
    console.log('[PostePay] Navigazione a login...');
    await page.goto('https://www.postepay.it/');
    
    // Clicca su "Accedi"
    await page.click('text=Accedi');
    await page.waitForTimeout(2000);
    
    // Compila form login
    console.log('[PostePay] Compilazione form login...');
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    
    // Attendi login
    await page.waitForSelector('.dashboard', { timeout: 10000 });
    console.log('[PostePay] Login completato');
    
    // Naviga a export movimenti
    console.log('[PostePay] Navigazione a export movimenti...');
    await page.goto('https://www.postepay.it/area-riservata/movimenti/export');
    
    // Seleziona periodo
    await page.selectOption('select[name="period"]', '90');
    
    // Clicca "Esporta CSV"
    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.click('button:has-text("Esporta CSV")'),
    ]);
    
    // Salva file
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const filename = `postepay-movimenti-${timestamp}.csv`;
    const filepath = path.join(outputDir, filename);
    
    await download.saveAs(filepath);
    console.log(`[PostePay] CSV salvato: ${filepath}`);
    
    return filepath;
}

// CLI entry point
if (require.main === module) {
    const args = process.argv.slice(2);
    const bankType = args[0]; // 'ing' o 'postepay'
    const username = args[1];
    const password = args[2];
    const outputDir = args[3] || process.cwd();
    
    downloadBankCSV(bankType, username, password, outputDir)
        .then(filepath => {
            console.log(`✅ Download completato: ${filepath}`);
            process.exit(0);
        })
        .catch(error => {
            console.error(`❌ Errore: ${error.message}`);
            process.exit(1);
        });
}

module.exports = { downloadBankCSV };
```

---

### **Step 2: WordPress Service**

**File:** `includes/Services/AutoImportService.php` (estendere esistente)

```php
<?php
namespace FP\FinanceHub\Services;

use FP\FinanceHub\Integration\OpenBanking\EncryptionService;
use FP\FinanceHub\Import\Importer;

class AutoImportService {
    
    /**
     * Esegue download automatico CSV e importa
     */
    public function run_auto_import($auto_import_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_auto_import';
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND is_active = 1",
            $auto_import_id
        ));
        
        if (!$config) {
            return new \WP_Error('not_found', 'Configurazione auto-import non trovata');
        }
        
        // Decripta credenziali
        $username = EncryptionService::decrypt($config->username_encrypted);
        $password = EncryptionService::decrypt($config->password_encrypted);
        
        if (is_wp_error($username) || is_wp_error($password)) {
            return new \WP_Error('decrypt_error', 'Errore decriptazione credenziali');
        }
        
        // Esegui Playwright script
        $script_path = plugin_dir_path(__FILE__) . '../../scripts/bank-csv-downloader.js';
        $output_dir = wp_upload_dir()['basedir'] . '/fp-finance-hub-temp';
        
        // Crea directory se non esiste
        if (!file_exists($output_dir)) {
            wp_mkdir_p($output_dir);
        }
        
        // Esegui script Node.js
        $command = sprintf(
            'node %s %s %s %s %s 2>&1',
            escapeshellarg($script_path),
            escapeshellarg($config->bank_type),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($output_dir)
        );
        
        exec($command, $output, $return_code);
        
        if ($return_code !== 0) {
            $error = implode("\n", $output);
            $this->log_error($auto_import_id, $error);
            return new \WP_Error('download_failed', 'Errore download CSV: ' . $error);
        }
        
        // Trova file CSV scaricato (ultimo file nella directory)
        $files = glob($output_dir . '/' . $config->bank_type . '-movimenti-*.csv');
        if (empty($files)) {
            return new \WP_Error('no_file', 'Nessun file CSV trovato');
        }
        
        $csv_file = end($files); // Ultimo file
        
        // Importa CSV
        $importer = new Importer();
        $result = $importer->import_file($config->account_id, $csv_file, $config->bank_type);
        
        if (is_wp_error($result)) {
            $this->log_error($auto_import_id, $result->get_error_message());
            return $result;
        }
        
        // Aggiorna timestamp successo
        $wpdb->update(
            $table,
            [
                'last_download_at' => current_time('mysql'),
                'last_success_at' => current_time('mysql'),
                'last_error' => null,
                'next_run_at' => $this->calculate_next_run($config->frequency),
            ],
            ['id' => $auto_import_id]
        );
        
        // Rimuovi file CSV dopo import
        @unlink($csv_file);
        
        return $result;
    }
    
    /**
     * Calcola prossima esecuzione
     */
    private function calculate_next_run($frequency) {
        switch ($frequency) {
            case 'hourly':
                return date('Y-m-d H:i:s', strtotime('+1 hour'));
            case 'daily':
                return date('Y-m-d H:i:s', strtotime('+1 day'));
            case 'weekly':
                return date('Y-m-d H:i:s', strtotime('+1 week'));
            default:
                return date('Y-m-d H:i:s', strtotime('+1 day'));
        }
    }
    
    /**
     * Log errore
     */
    private function log_error($auto_import_id, $error) {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_finance_hub_bank_auto_import';
        
        $wpdb->update(
            $table,
            [
                'last_download_at' => current_time('mysql'),
                'last_error' => $error,
                'next_run_at' => date('Y-m-d H:i:s', strtotime('+1 hour')), // Retry tra 1 ora
            ],
            ['id' => $auto_import_id]
        );
    }
}
```

---

### **Step 3: Cron Job**

**File:** `includes/Cron/Jobs.php` (estendere esistente)

```php
/**
 * Cron job per auto-import CSV
 */
public static function auto_import_bank_csvs() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'fp_finance_hub_bank_auto_import';
    
    // Trova configurazioni da eseguire
    $configs = $wpdb->get_results(
        "SELECT * FROM {$table}
        WHERE is_active = 1
        AND next_run_at <= NOW()
        ORDER BY next_run_at ASC"
    );
    
    $auto_import_service = new AutoImportService();
    
    foreach ($configs as $config) {
        $auto_import_service->run_auto_import($config->id);
        
        // Rate limiting: aspetta 30 secondi tra download
        sleep(30);
    }
}

// Registra cron job
add_action('fp_finance_hub_auto_import_csvs', [Jobs::class, 'auto_import_bank_csvs']);

// Schedula ogni ora
if (!wp_next_scheduled('fp_finance_hub_auto_import_csvs')) {
    wp_schedule_event(time(), 'hourly', 'fp_finance_hub_auto_import_csvs');
}
```

---

## 🔐 **SICUREZZA**

### **Criptazione Credenziali**

Usa `EncryptionService` esistente per criptare username e password nel database.

### **Protezione Directory**

- Directory temporanea protetta con `.htaccess`
- File CSV rimossi dopo import
- Permessi directory ristretti

### **Logging**

- Log tutte le operazioni
- Non loggare password in chiaro
- Alert su errori ripetuti

---

## ⚙️ **CONFIGURAZIONE**

### **Requisiti Sistema**

1. **Node.js** installato (v18+)
2. **Playwright** installato:
   ```bash
   cd scripts/
   npm install playwright
   npx playwright install chromium
   ```
3. **PHP exec()** abilitato (per eseguire script Node.js)

### **Setup**

1. Crea directory `scripts/` nel plugin
2. Installa dipendenze Node.js
3. Crea tabella database
4. Configura credenziali in Admin UI
5. Schedula cron job

---

## ✅ **VANTAGGI**

- ✅ **Completamente automatico** (cron job)
- ✅ **Gratuito** (nessun costo mensile)
- ✅ **Conti reali** (non sandbox)
- ✅ **Flessibile** (frequenza configurabile)
- ✅ **Sicuro** (credenziali criptate)
- ✅ **Affidabile** (Playwright gestisce errori)

---

## ⚠️ **LIMITAZIONI**

- ⚠️ Richiede Node.js installato sul server
- ⚠️ Richiede PHP exec() abilitato
- ⚠️ Dipende dalla struttura HTML del sito banca (può cambiare)
- ⚠️ Richiede manutenzione se banca cambia interfaccia

---

## 🚀 **PROSSIMI PASSI**

1. ✅ Creare script Playwright
2. ✅ Estendere AutoImportService
3. ✅ Creare tabella database
4. ✅ Aggiungere UI configurazione
5. ✅ Testare con ING e PostePay
6. ✅ Documentare setup

---

**Ultimo aggiornamento:** Gennaio 2026
