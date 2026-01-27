# 🚀 Implementazione TrueLayer - FP Finance Hub

## 📋 **OVERVIEW**

Guida pratica per implementare il collegamento automatico dei conti bancari tramite **TrueLayer Open Banking API**.

---

## 🎯 **SETUP INIZIALE**

### **Step 1: Account TrueLayer**

1. Registrati su https://truelayer.com
2. Crea nuova **Application**
3. Ottieni:
   - `CLIENT_ID`
   - `CLIENT_SECRET`
4. Configura **Redirect URI**:
   ```
   https://tuosito.com/wp-admin/admin.php?page=fp-finance-hub-bank-connections&truelayer_callback=1
   ```

---

## 🗄️ **DATABASE SCHEMA**

```sql
-- Tabella connessioni bancarie
CREATE TABLE wp_fp_finance_hub_bank_connections (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT(20) NOT NULL,
  provider VARCHAR(50) DEFAULT 'truelayer',
  connection_id VARCHAR(255) NOT NULL,
  account_id VARCHAR(255) NOT NULL,
  bank_name VARCHAR(255),
  account_type VARCHAR(50), -- 'current_account', 'savings_account', 'credit_card'
  account_name VARCHAR(255),
  iban VARCHAR(34),
  currency VARCHAR(3) DEFAULT 'EUR',
  access_token TEXT, -- Criptato AES-256
  refresh_token TEXT, -- Criptato AES-256
  token_expires_at DATETIME,
  last_sync_at DATETIME,
  next_sync_at DATETIME,
  sync_frequency INT DEFAULT 6, -- ore
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

```php
namespace FP\FinanceHub\Services\Security;

class EncryptionService {
    
    private static $key;
    
    /**
     * Ottieni chiave criptazione
     */
    private static function get_key() {
        if (!self::$key) {
            // Usa opzione WordPress o genera se non esiste
            self::$key = get_option('fp_finance_hub_encryption_key');
            if (!self::$key) {
                self::$key = wp_generate_password(32, false);
                update_option('fp_finance_hub_encryption_key', self::$key);
            }
        }
        return self::$key;
    }
    
    /**
     * Cripta token
     */
    public static function encrypt($plaintext) {
        $key = self::get_key();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decripta token
     */
    public static function decrypt($ciphertext) {
        $key = self::get_key();
        $data = base64_decode($ciphertext);
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
```

---

## 🔌 **SERVIZIO TRUELAYER**

```php
namespace FP\FinanceHub\Services\OpenBanking;

class TrueLayerService {
    
    private $client_id;
    private $client_secret;
    private $base_url = 'https://api.truelayer.com';
    
    public function __construct() {
        $this->client_id = get_option('fp_finance_hub_truelayer_client_id');
        $this->client_secret = get_option('fp_finance_hub_truelayer_client_secret');
    }
    
    /**
     * Genera URL OAuth per collegamento conto
     */
    public function get_oauth_url($redirect_uri, $state = null) {
        $state = $state ?: wp_create_nonce('truelayer_connect');
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'accounts transactions balance offline_access',
            'state' => $state,
            'providers' => 'poste,ing', // Filtra banche disponibili
        ];
        
        return $this->base_url . '/v3/auth?' . http_build_query($params);
    }
    
    /**
     * Scambia authorization code con access token
     */
    public function exchange_code_for_token($code, $redirect_uri) {
        $response = wp_remote_post($this->base_url . '/v3/auth/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            return [
                'access_token' => $body['access_token'],
                'refresh_token' => $body['refresh_token'] ?? null,
                'expires_in' => $body['expires_in'] ?? 3600,
            ];
        }
        
        return false;
    }
    
    /**
     * Refresh access token
     */
    public function refresh_token($refresh_token) {
        $response = wp_remote_post($this->base_url . '/v3/auth/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            return [
                'access_token' => $body['access_token'],
                'refresh_token' => $body['refresh_token'] ?? $refresh_token,
                'expires_in' => $body['expires_in'] ?? 3600,
            ];
        }
        
        return false;
    }
    
    /**
     * Ottieni lista conti collegati
     */
    public function get_accounts($access_token) {
        $response = wp_remote_get($this->base_url . '/v3/data/accounts', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return $body['results'] ?? [];
    }
    
    /**
     * Ottieni saldo conto
     */
    public function get_balance($access_token, $account_id) {
        $response = wp_remote_get(
            $this->base_url . '/v3/data/accounts/' . $account_id . '/balance',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                ],
            ]
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return $body['results'][0] ?? null;
    }
    
    /**
     * Ottieni movimenti conto
     */
    public function get_transactions($access_token, $account_id, $from_date = null, $to_date = null) {
        $params = [];
        if ($from_date) {
            $params['from'] = date('Y-m-d', strtotime($from_date));
        }
        if ($to_date) {
            $params['to'] = date('Y-m-d', strtotime($to_date));
        }
        
        $url = $this->base_url . '/v3/data/accounts/' . $account_id . '/transactions';
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return $body['results'] ?? [];
    }
}
```

---

## 🔄 **SERVIZIO SINCRONIZZAZIONE**

```php
namespace FP\FinanceHub\Services\OpenBanking;

use FP\FinanceHub\Services\Security\EncryptionService;

class SyncService {
    
    private $truelayer;
    private $categorization_engine;
    
    public function __construct() {
        $this->truelayer = new TrueLayerService();
        // $this->categorization_engine = new \FP\FinanceHub\Services\CategorizationEngine();
    }
    
    /**
     * Sincronizza tutti i conti attivi
     */
    public function sync_all_accounts() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_connections';
        
        // Trova conti da sincronizzare (next_sync_at <= now)
        $accounts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE is_active = 1
            AND sync_enabled = 1
            AND next_sync_at <= NOW()",
            []
        ));
        
        foreach ($accounts as $account) {
            $this->sync_account($account);
        }
    }
    
    /**
     * Sincronizza singolo conto
     */
    public function sync_account($account) {
        global $wpdb;
        
        // Decripta access token
        $access_token = EncryptionService::decrypt($account->access_token);
        
        // Verifica scadenza token
        if (strtotime($account->token_expires_at) <= time()) {
            // Refresh token
            $refresh_token = EncryptionService::decrypt($account->refresh_token);
            $new_tokens = $this->truelayer->refresh_token($refresh_token);
            
            if (!$new_tokens) {
                error_log("[FP Finance Hub] Errore refresh token per account {$account->id}");
                return false;
            }
            
            // Salva nuovi token
            $this->update_tokens($account->id, $new_tokens);
            $access_token = $new_tokens['access_token'];
        }
        
        // 1. Ottieni saldo
        $balance = $this->truelayer->get_balance($access_token, $account->account_id);
        if ($balance) {
            $this->update_account_balance($account->account_id, $balance);
        }
        
        // 2. Ottieni movimenti (ultimi 90 giorni o dall'ultima sync)
        $from_date = $account->last_sync_at 
            ? date('Y-m-d', strtotime($account->last_sync_at . ' -1 day'))
            : date('Y-m-d', strtotime('-90 days'));
        
        $transactions = $this->truelayer->get_transactions(
            $access_token,
            $account->account_id,
            $from_date
        );
        
        if ($transactions) {
            $this->import_transactions($account, $transactions);
        }
        
        // Aggiorna timestamp sync
        $next_sync = date('Y-m-d H:i:s', strtotime("+{$account->sync_frequency} hours"));
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
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        foreach ($transactions as $tx) {
            // Verifica se già esistente
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table}
                WHERE account_id = %d
                AND external_transaction_id = %s",
                $account->id,
                $tx['transaction_id']
            ));
            
            if ($existing) {
                continue; // Già importato
            }
            
            // Categorizza automaticamente
            // $category = $this->categorization_engine->categorize($tx);
            
            // Inserisci movimento
            $wpdb->insert($table, [
                'account_id' => $account->id,
                'external_transaction_id' => $tx['transaction_id'],
                'transaction_date' => $tx['timestamp'],
                'description' => $tx['description'],
                'amount' => $tx['amount'],
                'currency' => $tx['currency'] ?? 'EUR',
                'transaction_type' => $tx['amount'] >= 0 ? 'credit' : 'debit',
                // 'category_id' => $category['category_id'] ?? null,
                // 'subcategory' => $category['subcategory'] ?? null,
                // 'is_business' => $category['is_business'] ?? false,
                'created_at' => current_time('mysql'),
            ]);
        }
    }
    
    /**
     * Aggiorna saldo conto
     */
    private function update_account_balance($account_id, $balance_data) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'fp_finance_hub_bank_accounts',
            [
                'current_balance' => $balance_data['current'],
                'available_balance' => $balance_data['available'] ?? $balance_data['current'],
                'last_balance_date' => current_time('mysql'),
            ],
            ['id' => $account_id]
        );
    }
    
    /**
     * Aggiorna token criptati
     */
    private function update_tokens($account_id, $tokens) {
        global $wpdb;
        
        $expires_at = date('Y-m-d H:i:s', time() + ($tokens['expires_in'] ?? 3600));
        
        $wpdb->update(
            $wpdb->prefix . 'fp_finance_hub_bank_connections',
            [
                'access_token' => EncryptionService::encrypt($tokens['access_token']),
                'refresh_token' => EncryptionService::encrypt($tokens['refresh_token']),
                'token_expires_at' => $expires_at,
            ],
            ['id' => $account_id]
        );
    }
}
```

---

## ⏰ **CRON JOB**

```php
namespace FP\FinanceHub\Cron;

use FP\FinanceHub\Services\OpenBanking\SyncService;

class BankSync {
    
    /**
     * Schedula cron job
     */
    public static function schedule() {
        if (!wp_next_scheduled('fp_finance_hub_sync_bank_accounts')) {
            // Esegui ogni 6 ore
            wp_schedule_event(time(), 'fp_finance_hub_6hours', 'fp_finance_hub_sync_bank_accounts');
        }
    }
    
    /**
     * Rimuovi cron job
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled('fp_finance_hub_sync_bank_accounts');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'fp_finance_hub_sync_bank_accounts');
        }
    }
    
    /**
     * Handler cron job
     */
    public static function sync_handler() {
        $sync_service = new SyncService();
        $sync_service->sync_all_accounts();
    }
    
    /**
     * Registra intervallo personalizzato (6 ore)
     */
    public static function add_cron_intervals($schedules) {
        $schedules['fp_finance_hub_6hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => 'Ogni 6 ore',
        ];
        return $schedules;
    }
}

// Hook
add_action('wp', function() {
    BankSync::schedule();
});

add_filter('cron_schedules', [BankSync::class, 'add_cron_intervals']);
add_action('fp_finance_hub_sync_bank_accounts', [BankSync::class, 'sync_handler']);
```

---

## 🎨 **ADMIN UI - Collega Conto**

```php
namespace FP\FinanceHub\Admin;

use FP\FinanceHub\Services\OpenBanking\TrueLayerService;

class BankConnectionsPage {
    
    /**
     * Render pagina collegamento conti
     */
    public function render() {
        // Handle OAuth callback
        if (isset($_GET['truelayer_callback']) && isset($_GET['code'])) {
            $this->handle_oauth_callback();
        }
        
        // Mostra lista conti collegati
        $connections = $this->get_user_connections();
        
        ?>
        <div class="wrap">
            <h1>Collega Conti Bancari</h1>
            
            <div class="card">
                <h2>Collega Nuovo Conto</h2>
                <p>Collega automaticamente i tuoi conti PostePay o ING Direct tramite Open Banking.</p>
                
                <a href="<?php echo esc_url($this->get_connect_url()); ?>" 
                   class="button button-primary button-large">
                    🔗 Collega Conto Bancario
                </a>
            </div>
            
            <?php if (!empty($connections)) : ?>
                <h2>Conti Collegati</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Banca</th>
                            <th>Conto</th>
                            <th>IBAN</th>
                            <th>Ultima Sync</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($connections as $conn) : ?>
                            <tr>
                                <td><?php echo esc_html($conn->bank_name); ?></td>
                                <td><?php echo esc_html($conn->account_name); ?></td>
                                <td><?php echo esc_html($conn->iban); ?></td>
                                <td><?php echo $conn->last_sync_at ? esc_html($conn->last_sync_at) : 'Mai'; ?></td>
                                <td>
                                    <button class="button" onclick="syncNow(<?php echo $conn->id; ?>)">
                                        🔄 Sincronizza Ora
                                    </button>
                                    <button class="button" onclick="disconnect(<?php echo $conn->id; ?>)">
                                        ❌ Disconnetti
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle OAuth callback
     */
    private function handle_oauth_callback() {
        $code = $_GET['code'] ?? null;
        $state = $_GET['state'] ?? null;
        
        if (!wp_verify_nonce($state, 'truelayer_connect')) {
            wp_die('Errore: Invalid state parameter');
        }
        
        $redirect_uri = admin_url('admin.php?page=fp-finance-hub-bank-connections&truelayer_callback=1');
        
        $truelayer = new TrueLayerService();
        $tokens = $truelayer->exchange_code_for_token($code, $redirect_uri);
        
        if (!$tokens) {
            wp_die('Errore: Impossibile ottenere token');
        }
        
        // Ottieni lista conti
        $accounts = $truelayer->get_accounts($tokens['access_token']);
        
        // Salva connessioni nel database
        $this->save_connections($accounts, $tokens);
        
        wp_redirect(admin_url('admin.php?page=fp-finance-hub-bank-connections&connected=1'));
        exit;
    }
    
    /**
     * Salva connessioni nel database
     */
    private function save_connections($accounts, $tokens) {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_finance_hub_bank_connections';
        
        foreach ($accounts as $account) {
            $expires_at = date('Y-m-d H:i:s', time() + $tokens['expires_in']);
            
            $wpdb->insert($table, [
                'user_id' => get_current_user_id(),
                'connection_id' => $account['connection_id'],
                'account_id' => $account['account_id'],
                'bank_name' => $account['provider']['display_name'],
                'account_type' => $account['account_type'],
                'account_name' => $account['display_name'],
                'iban' => $account['account_identifiers'][0]['iban'] ?? null,
                'currency' => $account['currency'],
                'access_token' => EncryptionService::encrypt($tokens['access_token']),
                'refresh_token' => EncryptionService::encrypt($tokens['refresh_token']),
                'token_expires_at' => $expires_at,
                'next_sync_at' => current_time('mysql'),
            ]);
        }
    }
    
    /**
     * Ottieni URL OAuth
     */
    private function get_connect_url() {
        $redirect_uri = admin_url('admin.php?page=fp-finance-hub-bank-connections&truelayer_callback=1');
        $truelayer = new TrueLayerService();
        return $truelayer->get_oauth_url($redirect_uri);
    }
    
    /**
     * Ottieni conti collegati utente
     */
    private function get_user_connections() {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_fp_finance_hub_bank_connections';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND is_active = 1",
            get_current_user_id()
        ));
    }
}
```

---

## ✅ **CHECKLIST IMPLEMENTAZIONE**

- [ ] Account TrueLayer creato
- [ ] Client ID e Secret configurati
- [ ] Database schema creato
- [ ] Servizio Encryption implementato
- [ ] TrueLayerService implementato
- [ ] SyncService implementato
- [ ] Cron job schedulato
- [ ] Admin UI creata
- [ ] OAuth flow testato
- [ ] Sincronizzazione testata
- [ ] Error handling implementato
- [ ] Logging implementato

---

**Implementazione completa!** 🚀
