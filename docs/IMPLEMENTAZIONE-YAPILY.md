# 🔗 Implementazione Yapily (Open Banking) - FP Finance Hub

## 📋 **OVERVIEW**

**Yapily** offre accesso alle API PSD2 per Account Information Services (AIS) tramite Open Banking. Account gratuito per sviluppatori disponibile.

---

## 🎯 **PERCHÉ YAPILY**

✅ **Account gratuito** per sviluppatori
✅ **Sandbox gratuito** per test (Modelo Sandbox)
✅ **Senza limiti** su numero di conti
✅ **Supporto banche italiane** (Unicredit, Intesa San Paolo, UBI Banca, BancoPosta, Banco BPM, BNL, ecc.)
✅ **Accesso storico** transazioni
✅ **API PSD2 compliant**

**Costo:** Account gratuito per sviluppatori (verificare pricing per produzione)

---

## 🚀 **SETUP INIZIALE**

### **Step 1: Account Yapily Console**

1. Registrati su https://console.yapily.com (gratuito)
2. Crea una nuova **Application**
3. Ottieni:
   - `applicationUuid` (Application ID)
   - `secret` (Application Secret)
4. Configura **Redirect URI**:
   ```
   https://tuosito.com/wp-admin/admin.php?page=fp-finance-hub-bank-connections&yapily_callback=1
   ```

**Importante:** L'account base è **gratuito** per sviluppatori.

---

## 🗄️ **DATABASE SCHEMA**

```sql
CREATE TABLE wp_fp_finance_hub_bank_connections (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT(20) NOT NULL,
  provider VARCHAR(50) DEFAULT 'yapily',
  connection_id VARCHAR(255) NOT NULL, -- consent_id in Yapily (criptato)
  account_id VARCHAR(255) NOT NULL,
  bank_name VARCHAR(255),
  account_type VARCHAR(50),
  account_name VARCHAR(255),
  iban VARCHAR(34),
  currency VARCHAR(3) DEFAULT 'EUR',
  access_token TEXT, -- Non usato (Yapily usa consent_id)
  refresh_token TEXT, -- Criptato AES-256 (consent_id in Yapily)
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

Usa `EncryptionService` per criptare il `consent_id` (AES-256).

---

## 🔌 **SERVIZIO YAPILY**

```php
namespace FP\FinanceHub\Integration\OpenBanking;

class YapilyService {
    
    private $app_id;
    private $app_secret;
    private $base_url = 'https://api.yapily.com';
    
    public function __construct() {
        $this->app_id = get_option('fp_finance_hub_yapily_app_id', '');
        $this->app_secret = get_option('fp_finance_hub_yapily_app_secret', '');
    }
    
    /**
     * Autenticazione HTTP Basic Auth
     */
    private function get_auth_headers() {
        $credentials = base64_encode($this->app_id . ':' . $this->app_secret);
        return [
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/json',
        ];
    }
    
    /**
     * Ottieni lista banche disponibili (Italia)
     */
    public function get_institutions($country = 'IT') {
        // GET /institutions?country=IT
        // Filtra solo banche con features ACCOUNTS o ACCOUNT_TRANSACTIONS
    }
    
    /**
     * Crea consent request per collegamento conto
     */
    public function create_consent($institution_id, $redirect_uri, $callback_uri = null) {
        // POST /account-auth-requests
        // Ritorna authorizationUrl e consent_id
    }
    
    /**
     * Ottieni stato consent
     */
    public function get_consent($consent_id) {
        // GET /consents/{consentId}
    }
    
    /**
     * Ottieni conti collegati
     */
    public function get_accounts($consent_id) {
        // GET /accounts?consent={consentId}
    }
    
    /**
     * Ottieni dettagli conto
     */
    public function get_account_details($account_id, $consent_id) {
        // GET /accounts/{accountId}?consent={consentId}
    }
    
    /**
     * Ottieni saldo conto
     */
    public function get_balance($account_id, $consent_id) {
        // GET /accounts/{accountId}/balances?consent={consentId}
    }
    
    /**
     * Ottieni movimenti conto
     */
    public function get_transactions($account_id, $consent_id, $date_from = null, $date_to = null) {
        // GET /accounts/{accountId}/transactions?consent={consentId}&from={date}&to={date}
    }
}
```

---

## 🔄 **SERVIZIO SINCRONIZZAZIONE YAPILY**

```php
namespace FP\FinanceHub\Integration\OpenBanking;

class YapilySyncService {
    
    private $yapily;
    
    public function __construct() {
        $this->yapily = new YapilyService();
    }
    
    /**
     * Sincronizza tutti i conti attivi
     * 
     * NOTA: Yapily non ha limite di 4 sync/giorno come GoCardless
     */
    public function sync_all_accounts() {
        // Trova conti con provider = 'yapily' e next_sync_at <= NOW()
        // Per ogni conto: sync_account()
    }
    
    /**
     * Sincronizza singolo conto
     */
    public function sync_account($account) {
        // 1. Decripta consent_id
        // 2. Verifica che consent sia ancora AUTHORIZED
        // 3. Ottieni saldo
        // 4. Ottieni transazioni (ultimi 90 giorni o dall'ultima sync)
        // 5. Importa nel database
        // 6. Aggiorna timestamp sync
    }
}
```

---

## ⏰ **CRON JOB**

```php
namespace FP\FinanceHub\Cron;

use FP\FinanceHub\Integration\OpenBanking\YapilySyncService;

class Jobs {
    
    /**
     * Schedula cron job (ogni 6 ore)
     */
    public static function schedule() {
        if (!wp_next_scheduled('fp_finance_hub_sync_yapily_accounts')) {
            wp_schedule_event(time(), 'fp_finance_hub_6hours', 'fp_finance_hub_sync_yapily_accounts');
        }
    }
    
    /**
     * Handler cron job
     */
    public static function sync_yapily_accounts() {
        $sync_service = new YapilySyncService();
        $sync_service->sync_all_accounts();
    }
}

// Hook
add_action('fp_finance_hub_sync_yapily_accounts', [Jobs::class, 'sync_yapily_accounts']);
```

---

## 🎨 **ADMIN UI - Collega Conto**

Il flusso OAuth Yapily:

1. Utente seleziona banca
2. Crea `account-auth-request` → ottiene `authorizationUrl`
3. Redirect utente a `authorizationUrl`
4. Callback con `consentToken` nel parametro
5. Usa `consentToken` per ottenere accounts
6. Salva ogni account nel database con `consent_id` criptato

---

## ✅ **DIFFERENZE DA GOCARDLESS/NORDIGEN**

| Caratteristica | Yapily | GoCardless/Nordigen |
|----------------|--------|---------------------|
| **Autenticazione** | HTTP Basic Auth | Bearer Token |
| **OAuth Flow** | Consent-based | Requisition-based |
| **Limite sync** | Nessun limite | Max 4/giorno |
| **Sandbox** | Modelo Sandbox | Proprio sandbox |
| **Pricing** | Gratuito per dev | Era gratuito |

---

**Yapily è la soluzione Open Banking per FP Finance Hub!** 🎉
