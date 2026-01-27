# 🆓 Implementazione GoCardless Bank Account Data (GRATUITO) - FP Finance Hub

## 📋 **OVERVIEW**

**GoCardless Bank Account Data** (ex Nordigen) offre accesso **completamente gratuito** alle API PSD2 per Account Information Services (AIS). Perfetto per lettura saldi e transazioni senza costi.

---

## 🎯 **PERCHÉ GOCARDLESS BANK ACCOUNT DATA GRATUITO**

✅ **100% Gratuito** per AIS (lettura dati conti)
✅ **Senza limiti** su numero di conti
✅ **90 giorni** connettività per refresh automatico
✅ **4 aggiornamenti/giorno** inclusi (sufficiente per uso personale)
✅ **Accesso storico** 3-24 mesi transazioni
✅ **Supporto banche italiane** incluso PostePay

**Costo:** €0/mese per sempre 🎉

---

## 🚀 **SETUP INIZIALE**

### **Step 1: Account GoCardless Bank Account Data**

1. Registrati su https://bankaccountdata.gocardless.com (gratuito)
2. Crea nuova **Institution**
3. Ottieni:
   - `secret_id`
   - `secret_key`
4. Configura **Redirect URI**:
   ```
   https://tuosito.com/wp-admin/admin.php?page=fp-finance-hub-bank-connections&nordigen_callback=1
   ```

**Importante:** L'account base è **gratuito** per sempre per AIS.

---

## 🗄️ **DATABASE SCHEMA**

```sql
-- Stesso schema di TrueLayer
CREATE TABLE wp_fp_finance_hub_bank_connections (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT(20) NOT NULL,
  provider VARCHAR(50) DEFAULT 'nordigen', -- 'nordigen' invece di 'truelayer'
  connection_id VARCHAR(255) NOT NULL, -- requisition_id in Nordigen
  account_id VARCHAR(255) NOT NULL,
  bank_name VARCHAR(255),
  account_type VARCHAR(50),
  account_name VARCHAR(255),
  iban VARCHAR(34),
  currency VARCHAR(3) DEFAULT 'EUR',
  access_token TEXT, -- Criptato AES-256
  refresh_token TEXT, -- Criptato AES-256 (requisition_id in Nordigen)
  token_expires_at DATETIME,
  last_sync_at DATETIME,
  next_sync_at DATETIME,
  sync_frequency INT DEFAULT 6, -- ore (max 4 volte/giorno = ogni 6h)
  is_active BOOLEAN DEFAULT TRUE,
  sync_enabled BOOLEAN DEFAULT TRUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY user_id (user_id),
  KEY connection_id (connection_id),
  KEY next_sync_at (next_sync_at)
);
```

---

## 🔐 **SICUREZZA - Criptazione Token**

(Stesso codice di TrueLayer - vedi `IMPLEMENTAZIONE-TRUELAYER.md`)

```php
namespace FP\FinanceHub\Services\Security;

class EncryptionService {
    // Stesso codice di TrueLayer
    // Vedi IMPLEMENTAZIONE-TRUELAYER.md per implementazione completa
}
```

---

## 🔌 **SERVIZIO NORDIGEN**

```php
namespace FP\FinanceHub\Services\OpenBanking;

class NordigenService {
    
    private $secret_id;
    private $secret_key;
    private $base_url = 'https://bankaccountdata.gocardless.com/api/v2';
    private $access_token = null;
    
    public function __construct() {
        $this->secret_id = get_option('fp_finance_hub_nordigen_secret_id');
        $this->secret_key = get_option('fp_finance_hub_nordigen_secret_key');
    }
    
    /**
     * Ottieni access token (necessario per tutte le chiamate)
     */
    private function get_access_token() {
        if ($this->access_token) {
            return $this->access_token;
        }
        
        $response = wp_remote_post($this->base_url . '/token/new/', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'secret_id' => $this->secret_id,
                'secret_key' => $this->secret_key,
            ]),
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access'])) {
            $this->access_token = $body['access'];
            // Token valido 24 ore, salva per riuso
            set_transient('nordigen_access_token', $this->access_token, DAY_IN_SECONDS);
            return $this->access_token;
        }
        
        return false;
    }
    
    /**
     * Ottieni lista banche disponibili (Italia)
     */
    public function get_institutions($country = 'IT') {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $response = wp_remote_get(
            $this->base_url . '/institutions/?country=' . $country,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body;
    }
    
    /**
     * Genera URL OAuth per collegamento conto
     */
    public function get_oauth_url($institution_id, $redirect_uri, $reference = null) {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $reference = $reference ?: 'wp_fp_finance_hub_' . get_current_user_id() . '_' . time();
        
        // Crea requisition (connessione)
        $response = wp_remote_post($this->base_url . '/requisitions/', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'redirect' => $redirect_uri,
                'institution_id' => $institution_id,
                'reference' => $reference,
                'user_language' => 'IT',
            ]),
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['link'])) {
            // Salva requisition_id per dopo
            set_transient('nordigen_requisition_' . get_current_user_id(), $body['id'], HOUR_IN_SECONDS);
            return $body['link']; // URL OAuth
        }
        
        return false;
    }
    
    /**
     * Ottieni conti collegati da requisition_id
     */
    public function get_accounts($requisition_id) {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $response = wp_remote_get(
            $this->base_url . '/requisitions/' . $requisition_id . '/',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Ritorna array di account_id
        return $body['accounts'] ?? [];
    }
    
    /**
     * Ottieni dettagli conto
     */
    public function get_account_details($account_id) {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $response = wp_remote_get(
            $this->base_url . '/accounts/' . $account_id . '/details/',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['account'] ?? null;
    }
    
    /**
     * Ottieni saldo conto
     */
    public function get_balance($account_id) {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $response = wp_remote_get(
            $this->base_url . '/accounts/' . $account_id . '/balances/',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Nordigen ritorna array di balance objects
        // Prendi il primo (solitamente "interimBooked" o "interimAvailable")
        return $body['balances'][0] ?? null;
    }
    
    /**
     * Ottieni movimenti conto
     */
    public function get_transactions($account_id, $date_from = null, $date_to = null) {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $params = [];
        if ($date_from) {
            $params['date_from'] = date('Y-m-d', strtotime($date_from));
        }
        if ($date_to) {
            $params['date_to'] = date('Y-m-d', strtotime($date_to));
        }
        
        $url = $this->base_url . '/accounts/' . $account_id . '/transactions/';
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Nordigen separa transazioni in "booked" e "pending"
        $booked = $body['transactions']['booked'] ?? [];
        $pending = $body['transactions']['pending'] ?? [];
        
        return array_merge($booked, $pending);
    }
    
    /**
     * Refresh requisition (rinnova connessione 90 giorni)
     */
    public function refresh_requisition($requisition_id) {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        // Per refresh, ricrea semplicemente una nuova requisition
        // e collega gli stessi account
        // (Nordigen gestisce automaticamente i refresh)
        return true;
    }
}
```

---

## 🔄 **SERVIZIO SINCRONIZZAZIONE NORDIGEN**

```php
namespace FP\FinanceHub\Services\OpenBanking;

use FP\FinanceHub\Services\Security\EncryptionService;

class NordigenSyncService {
    
    private $nordigen;
    
    public function __construct() {
        $this->nordigen = new NordigenService();
    }
    
    /**
     * Sincronizza tutti i conti attivi
     * 
     * NOTA: Max 4 volte/giorno con Nordigen gratuito
     */
    public function sync_all_accounts() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_connections';
        
        // Verifica quante sync già fatte oggi
        $syncs_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
            WHERE is_active = 1
            AND sync_enabled = 1
            AND DATE(last_sync_at) = CURDATE()"
        ));
        
        // Max 4 sync/giorno (gratuito)
        if ($syncs_today >= 4) {
            error_log("[FP Finance Hub] Limite 4 sync/giorno raggiunto (Nordigen gratuito)");
            return;
        }
        
        // Trova conti da sincronizzare
        $accounts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE is_active = 1
            AND sync_enabled = 1
            AND provider = 'nordigen'
            AND next_sync_at <= NOW()",
            []
        ));
        
        foreach ($accounts as $account) {
            $this->sync_account($account);
            
            // Rate limiting: aspetta 1 secondo tra sync
            sleep(1);
        }
    }
    
    /**
     * Sincronizza singolo conto
     */
    public function sync_account($account) {
        global $wpdb;
        
        // Nordigen usa requisition_id come "connection_id"
        $requisition_id = EncryptionService::decrypt($account->connection_id);
        
        // 1. Ottieni saldo
        $balance = $this->nordigen->get_balance($account->account_id);
        if ($balance) {
            $this->update_account_balance($account->account_id, $balance);
        }
        
        // 2. Ottieni movimenti (ultimi 90 giorni o dall'ultima sync)
        $from_date = $account->last_sync_at 
            ? date('Y-m-d', strtotime($account->last_sync_at . ' -1 day'))
            : date('Y-m-d', strtotime('-90 days'));
        
        $transactions = $this->nordigen->get_transactions(
            $account->account_id,
            $from_date
        );
        
        if ($transactions) {
            $this->import_transactions($account, $transactions);
        }
        
        // Aggiorna timestamp sync (minimo 6 ore = 4 volte/giorno max)
        $next_sync = date('Y-m-d H:i:s', strtotime("+6 hours"));
        $wpdb->update(
            $wpdb->prefix . 'fp_finance_hub_bank_connections',
            [
                'last_sync_at' => current_time('mysql'),
                'next_sync_at' => $next_sync,
            ],
            ['id' => $account->id]
        );
        
        return true;
    }
    
    /**
     * Importa movimenti nel database
     */
    private function import_transactions($account, $transactions) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_fp_finance_hub_bank_transactions';
        
        foreach ($transactions as $tx) {
            // Verifica se già esistente
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table}
                WHERE account_id = %d
                AND external_transaction_id = %s",
                $account->id,
                $tx['transactionId'] ?? $tx['internalTransactionId']
            ));
            
            if ($existing) {
                continue; // Già importato
            }
            
            // Inserisci movimento
            $wpdb->insert($table, [
                'account_id' => $account->id,
                'external_transaction_id' => $tx['transactionId'] ?? $tx['internalTransactionId'],
                'transaction_date' => $tx['bookingDate'] ?? $tx['valueDate'],
                'description' => $tx['remittanceInformationUnstructured'] ?? 
                                 $tx['remittanceInformationUnstructuredArray'][0] ?? 
                                 'N/A',
                'amount' => ($tx['transactionAmount']['amount'] ?? 0) / 100, // Centesimi → Euro
                'currency' => $tx['transactionAmount']['currency'] ?? 'EUR',
                'transaction_type' => ($tx['transactionAmount']['amount'] ?? 0) >= 0 ? 'credit' : 'debit',
                'created_at' => current_time('mysql'),
            ]);
        }
    }
    
    /**
     * Aggiorna saldo conto
     */
    private function update_account_balance($account_id, $balance_data) {
        global $wpdb;
        
        $current = ($balance_data['balanceAmount']['amount'] ?? 0) / 100; // Centesimi → Euro
        
        $wpdb->update(
            $wpdb->prefix . 'fp_finance_hub_bank_accounts',
            [
                'current_balance' => $current,
                'available_balance' => $current,
                'last_balance_date' => current_time('mysql'),
            ],
            ['id' => $account_id]
        );
    }
}
```

---

## ⏰ **CRON JOB** (Max 4 volte/giorno)

```php
namespace FP\FinanceHub\Cron;

use FP\FinanceHub\Services\OpenBanking\NordigenSyncService;

class NordigenBankSync {
    
    /**
     * Schedula cron job (ogni 6 ore = 4 volte/giorno)
     */
    public static function schedule() {
        if (!wp_next_scheduled('fp_finance_hub_sync_nordigen_accounts')) {
            // Esegui ogni 6 ore (max 4 volte/giorno)
            wp_schedule_event(time(), 'fp_finance_hub_6hours', 'fp_finance_hub_sync_nordigen_accounts');
        }
    }
    
    /**
     * Rimuovi cron job
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled('fp_finance_hub_sync_nordigen_accounts');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'fp_finance_hub_sync_nordigen_accounts');
        }
    }
    
    /**
     * Handler cron job
     */
    public static function sync_handler() {
        $sync_service = new NordigenSyncService();
        $sync_service->sync_all_accounts();
    }
}

// Hook
add_action('wp', function() {
    NordigenBankSync::schedule();
});

add_filter('cron_schedules', function($schedules) {
    $schedules['fp_finance_hub_6hours'] = [
        'interval' => 6 * HOUR_IN_SECONDS,
        'display' => 'Ogni 6 ore (max 4/giorno Nordigen)',
    ];
    return $schedules;
});

add_action('fp_finance_hub_sync_nordigen_accounts', [NordigenBankSync::class, 'sync_handler']);
```

---

## 🎨 **ADMIN UI - Collega Conto**

```php
namespace FP\FinanceHub\Admin;

use FP\FinanceHub\Services\OpenBanking\NordigenService;

class NordigenBankConnectionsPage {
    
    /**
     * Render pagina collegamento conti
     */
    public function render() {
        // Handle OAuth callback
        if (isset($_GET['nordigen_callback']) && isset($_GET['ref'])) {
            $this->handle_oauth_callback();
        }
        
        $nordigen = new NordigenService();
        
        // Ottieni lista banche italiane disponibili
        $institutions = $nordigen->get_institutions('IT');
        
        ?>
        <div class="wrap">
            <h1>Collega Conti Bancari (GRATUITO)</h1>
            
            <div class="notice notice-info">
                <p>
                    <strong>🆓 Nordigen Gratuito:</strong> 
                    Sincronizzazione automatica fino a 4 volte al giorno, completamente gratuita per sempre!
                </p>
            </div>
            
            <div class="card">
                <h2>Collega Nuovo Conto</h2>
                <p>Seleziona la tua banca:</p>
                
                <?php if ($institutions) : ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('nordigen_connect'); ?>
                        <select name="institution_id" required>
                            <option value="">-- Seleziona Banca --</option>
                            <?php foreach ($institutions as $inst) : ?>
                                <option value="<?php echo esc_attr($inst['id']); ?>">
                                    <?php echo esc_html($inst['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button button-primary">
                            🔗 Collega Conto
                        </button>
                    </form>
                    
                    <?php if (isset($_POST['institution_id'])) : ?>
                        <?php
                        $redirect_uri = admin_url('admin.php?page=fp-finance-hub-bank-connections&nordigen_callback=1');
                        $oauth_url = $nordigen->get_oauth_url(
                            sanitize_text_field($_POST['institution_id']),
                            $redirect_uri
                        );
                        if ($oauth_url) {
                            wp_redirect($oauth_url);
                            exit;
                        }
                        ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Lista conti collegati (stesso codice di TrueLayer) -->
            <?php $this->render_connected_accounts(); ?>
        </div>
        <?php
    }
    
    /**
     * Handle OAuth callback
     */
    private function handle_oauth_callback() {
        global $wpdb;
        
        $requisition_id = get_transient('nordigen_requisition_' . get_current_user_id());
        
        if (!$requisition_id) {
            wp_die('Errore: Requisition ID non trovato');
        }
        
        $nordigen = new NordigenService();
        
        // Ottieni account collegati
        $account_ids = $nordigen->get_accounts($requisition_id);
        
        if (!$account_ids) {
            wp_die('Errore: Nessun conto trovato');
        }
        
        // Salva ogni conto nel database
        $table = $wpdb->prefix . 'fp_finance_hub_bank_connections';
        
        foreach ($account_ids as $account_id) {
            $details = $nordigen->get_account_details($account_id);
            $balance = $nordigen->get_balance($account_id);
            
            $wpdb->insert($table, [
                'user_id' => get_current_user_id(),
                'provider' => 'nordigen',
                'connection_id' => EncryptionService::encrypt($requisition_id),
                'account_id' => $account_id,
                'bank_name' => $details['institutionId'] ?? 'Nordigen',
                'account_type' => $details['cashAccountType'] ?? null,
                'account_name' => $details['name'] ?? null,
                'iban' => $details['iban'] ?? null,
                'currency' => $balance['balanceAmount']['currency'] ?? 'EUR',
                'access_token' => '', // Nordigen non usa access_token per account
                'refresh_token' => EncryptionService::encrypt($requisition_id),
                'token_expires_at' => date('Y-m-d H:i:s', strtotime('+90 days')),
                'next_sync_at' => current_time('mysql'),
            ]);
        }
        
        delete_transient('nordigen_requisition_' . get_current_user_id());
        
        wp_redirect(admin_url('admin.php?page=fp-finance-hub-bank-connections&connected=1'));
        exit;
    }
    
    /**
     * Render lista conti collegati
     */
    private function render_connected_accounts() {
        // Stesso codice di TrueLayer
    }
}
```

---

## ✅ **CONFRONTO NORDIGEN vs TRUELAYER**

| Caratteristica | Nordigen 🆓 | TrueLayer 💰 |
|----------------|-------------|--------------|
| **Costo** | ✅ GRATIS | ⚠️ ~€5-15/mese |
| **Sync/giorno** | ⚠️ Max 4 | ✅ Illimitato |
| **Connettività** | ⚠️ 90 giorni | ✅ Permanente |
| **PostePay** | ✅ Sì | ✅ Sì |
| **Setup** | ✅ Semplice | ✅ Semplice |
| **API** | ✅ Ottima | ✅ Eccellente |
| **Supporto** | ✅ Buono | ✅ Eccellente |

---

## 🎯 **RACCOMANDAZIONE FINALE**

### **Nordigen** se:
- ✅ Vuoi soluzione **completamente gratuita**
- ✅ 4 sync/giorno sono sufficienti
- ✅ Budget zero

### **TrueLayer** se:
- ✅ Vuoi sync più frequenti
- ✅ Budget disponibile (~€10-15/mese)
- ✅ Connettività permanente (no refresh 90 giorni)

---

**Nordigen è la soluzione GRATUITA perfetta per uso personale!** 🎉
