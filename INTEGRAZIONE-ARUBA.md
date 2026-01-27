# 🔗 Integrazione Aruba Fatturazione Elettronica

**Documentazione tecnica integrazione FP Client Manager ↔ Aruba**

---

## 📋 **OVERVIEW**

Il plugin si integra con le **API REST di Aruba Fatturazione Elettronica** (v1.21.0) per:
- ✅ **Importare fatture emesse** da Aruba (solo lettura)
- ✅ **Importare clienti** dalle fatture Aruba
- ✅ **Calcolare potenziale entrate** basato su fatture non pagate
- ✅ **Aggiornare stato fatture** (incassata/non incassata) tramite riconciliazione con movimenti bancari

---

## 🔐 **AUTENTICAZIONE**

### Metodi Supportati

#### 1. **API Key** (Consigliato)
```php
// Configurazione in WordPress Admin
API Key: [inserisci chiave da pannello Aruba]
Username: [username Aruba]
```

#### 2. **OAuth 2** (Per utenze Premium)
```php
// Flusso OAuth completo
Client ID: [da pannello Aruba]
Client Secret: [da pannello Aruba]
Redirect URI: [callback WordPress]
```

### Gestione Token

- **Token Refresh Automatico**: Il plugin gestisce automaticamente il refresh dei token
- **Cache Token**: Token salvati in transients WordPress (scadenza 1 ora)
- **Retry Logic**: Retry automatico in caso di token scaduto

---

## 📥 **IMPORT FATTURE DA ARUBA** ⭐ **SOLO LETTURA**

### Flusso Completo

```
1. Connessione ad Aruba (autenticazione)
   ↓
2. Ricerca fatture emesse (findByUsername)
   ↓
3. Download dettagli fatture (getByInvoiceId)
   ↓
4. Estrazione dati cliente da fattura
   ↓
5. Import fatture in WordPress
   ↓
6. Import/aggiornamento clienti
   ↓
7. Calcolo potenziale entrate (fatture non pagate)
   ↓
8. Riconciliazione con movimenti bancari
   ↓
9. Aggiornamento stato (incassata/non incassata)
```

### Endpoint Utilizzati (Solo Lettura)

```php
// Ricerca fatture inviate
GET /services/invoice/out/findByUsername?username=...

// Dettaglio fattura singola
GET /services/invoice/out/{invoiceId}

// Ricerca per ID SDI
GET /services/invoice/out/getByIdSdi?idSdi=...
```

**NOTA**: Il plugin NON invia fatture ad Aruba, solo le legge.

---

## 📥 **IMPORT FATTURE E CLIENTI DA ARUBA**

### Sincronizzazione Automatica

```php
// Sincronizza tutte le fatture emesse
GET /wp-json/fp-client-manager/v1/aruba/sync-invoices

// Sincronizza fatture di un periodo specifico
GET /wp-json/fp-client-manager/v1/aruba/sync-invoices?startDate=2025-01-01&endDate=2025-01-31
```

### Mappatura Dati Fatture

| Aruba | WordPress | Note |
|-------|-----------|------|
| `invoices[].number` | `invoice_number` | Numero fattura |
| `invoices[].invoiceDate` | `issue_date` | Data emissione |
| `receiver.vatCode` | Cliente P.IVA | Usato per matching cliente |
| `receiver.description` | Cliente nome | Crea/aggiorna cliente |
| `idSdi` | `aruba_sdi_id` | ID Sistema Interscambio |
| `invoices[].status` | `aruba_status` | Stato SDI (Inviata, Accettata, etc.) |
| `file` (XML) | Parse → `total_amount` | Estrae importo totale da XML |

### Estrazione Clienti da Fatture

```php
// Dalla fattura Aruba estrae:
$client_data = [
  'name' => $aruba_invoice->receiver->description,
  'piva' => $aruba_invoice->receiver->vatCode,
  'cf' => $aruba_invoice->receiver->fiscalCode,
  'country' => $aruba_invoice->receiver->countryCode
];

// Crea o aggiorna cliente in WordPress
```

### Calcolo Potenziale Entrate

```php
// Fatture non pagate = potenziale entrata
$potential_income = sum(
  fatture con status != 'paid' 
  AND aruba_status IN ('Inviata', 'Accettata', 'Consegnata')
);
```

---

## 🔔 **MONITORAGGIO STATI SDI** (Solo Lettura)

### Stati Fatture Aruba

- `Inviata` - Fattura inviata a SDI
- `Accettata` - Fattura accettata da SDI
- `Consegnata` - Fattura consegnata al destinatario
- `Rifiutata` - Fattura rifiutata
- `Scartata` - Fattura scartata da SDI
- `Errore Elaborazione` - Errore tecnico

### Aggiornamento Stati

Il plugin sincronizza periodicamente gli stati da Aruba:

```php
// Cron job giornaliero (o manuale)
wp_schedule_event(time(), 'daily', 'fp_cm_sync_aruba_invoices');

// Aggiorna stati fatture esistenti
foreach ($aruba_invoices as $invoice) {
  update_invoice_status($invoice->id, $invoice->status);
}
```

### Riconciliazione con Movimenti Bancari

```
Fattura Aruba (status: "Consegnata")
  ↓
Cerca movimento bancario corrispondente
  ↓
Se trovato → Stato WordPress: "Pagata" (incassata)
Se non trovato → Stato WordPress: "In Attesa" (non incassata)
```

---

## 🗄️ **DATABASE INTEGRAZIONE**

### Campi Aggiunti a `wp_fp_client_manager_invoices`

```sql
ALTER TABLE wp_fp_client_manager_invoices ADD COLUMN aruba_id VARCHAR(255) NULL;
ALTER TABLE wp_fp_client_manager_invoices ADD COLUMN aruba_sdi_id VARCHAR(255) NULL;
ALTER TABLE wp_fp_client_manager_invoices ADD COLUMN aruba_status VARCHAR(50) NULL;
ALTER TABLE wp_fp_client_manager_invoices ADD COLUMN aruba_sent_at DATETIME NULL;
ALTER TABLE wp_fp_client_manager_invoices ADD COLUMN aruba_xml_path VARCHAR(500) NULL;
ALTER TABLE wp_fp_client_manager_invoices ADD COLUMN aruba_sync_status VARCHAR(50) DEFAULT 'pending';
ALTER TABLE wp_fp_client_manager_invoices ADD COLUMN aruba_last_sync DATETIME NULL;
```

### Tabella Log Operazioni

```sql
CREATE TABLE wp_fp_client_manager_aruba_logs (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT(20) NULL,
  operation VARCHAR(50) NOT NULL, -- send, import, status_check
  status VARCHAR(50) NOT NULL, -- success, error
  message TEXT,
  aruba_response LONGTEXT, -- JSON response da Aruba
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY invoice_id (invoice_id),
  KEY operation (operation),
  KEY created_at (created_at)
);
```

---

## ⚙️ **CONFIGURAZIONE**

### Impostazioni Plugin

```php
// Admin → FP Client Manager → Integrazione Aruba
Settings:
  - API Key: [input text]
  - Username: [input text]
  - Ambiente: [Test / Produzione]
  - Auto-sync: [checkbox] Sincronizza automaticamente
  - Callback URL: [readonly] URL per webhook Aruba
  - Test Connessione: [button]
```

### Test Connessione

```php
// Verifica credenziali
GET /api/userInfo

// Risposta
{
  "username": "tuousername",
  "email": "email@example.com",
  "status": "active"
}
```

---

## 🔄 **SINCRONIZZAZIONE** (Solo Import da Aruba)

### Scenari

#### Scenario 1: Import Fatture da Aruba
```
Aruba → API findByUsername → Lista fatture
  ↓
Per ogni fattura: Download dettagli (getByInvoiceId)
  ↓
Parse XML fattura → Estrai dati
  ↓
Crea/aggiorna fattura in WordPress
  ↓
Estrai dati cliente → Crea/aggiorna cliente
```

#### Scenario 2: Calcolo Potenziale Entrate
```
Fatture Aruba (status: "Inviata", "Accettata", "Consegnata")
  ↓
Filtra fatture non pagate in WordPress
  ↓
Somma importi → Potenziale entrata
```

#### Scenario 3: Riconciliazione Stato Incassata
```
Fattura Aruba (importo: €1,000)
  ↓
Cerca movimento bancario corrispondente
  ↓
Se trovato (±7 giorni, ±0.01€) → Stato: "Pagata" (incassata)
Se non trovato → Stato: "In Attesa" (non incassata)
```

---

## 🛡️ **SICUREZZA**

### Best Practices

1. **API Key Criptata**: Salva API key criptata nel database
2. **HTTPS Obbligatorio**: Tutte le chiamate via HTTPS
3. **Validazione Input**: Sanitizza tutti i dati prima dell'invio
4. **Rate Limiting**: Limita chiamate API (max 100/ora)
5. **Log Operazioni**: Logga tutte le operazioni per audit

### Gestione Errori

```php
// Retry automatico per errori temporanei
- 429 Too Many Requests → Retry dopo 60 secondi
- 500 Server Error → Retry dopo 30 secondi
- 401 Unauthorized → Refresh token e retry
- 400 Bad Request → Log errore, non retry
```

---

## 📊 **DASHBOARD INTEGRAZIONE**

### Widget Dashboard

```
┌─────────────────────────────────────┐
│ 🔗 Integrazione Aruba              │
├─────────────────────────────────────┤
│ Stato: ✅ Connesso                  │
│ Ultima Sync: 2 minuti fa            │
│ Fatture in attesa: 3                │
│ Errori ultimi 7 giorni: 0           │
│                                     │
│ [Sincronizza Ora] [Log Operazioni]  │
└─────────────────────────────────────┘
```

### Pagina Monitoraggio

- Lista fatture sincronizzate
- Stati SDI in tempo reale
- Log operazioni (filtrabili)
- Statistiche sincronizzazione

---

## 🚀 **IMPLEMENTAZIONE**

### Classi PHP

```php
namespace FP\ClientManager\Integration;

class ArubaAPI {
  // Gestione chiamate API (solo lettura)
  public function authenticate();
  public function getInvoices($filters = []); // findByUsername
  public function getInvoice($aruba_id); // getByInvoiceId
  public function parseInvoiceXML($xml_base64); // Estrae dati da XML
}

class ArubaSync {
  // Sincronizzazione (solo import)
  public function syncInvoicesFromAruba($start_date = null, $end_date = null);
  public function syncClientsFromInvoices(); // Estrae clienti da fatture
  public function updateInvoiceStatuses(); // Aggiorna stati da Aruba
}

class ArubaReconciliation {
  // Riconciliazione con movimenti bancari
  public function reconcileInvoicesWithBankTransactions();
  public function markInvoiceAsPaid($invoice_id, $transaction_id);
  public function calculatePotentialIncome(); // Fatture non pagate
}
```

---

## 📝 **NOTE TECNICHE**

### Requisiti Aruba

- Account Aruba Fatturazione Elettronica attivo
- Modulo "API" abilitato (per utenze Premium)
- API Key generata dal pannello Aruba

### Limitazioni API

- **Rate Limit**: ~100 richieste/ora (dipende dal piano)
- **File Size**: Max 5MB per file XML
- **Timeout**: 30 secondi per chiamata

### Supporto

- **Documentazione Aruba**: https://fatturazioneelettronica.aruba.it/apidoc/docs.html
- **Ambiente Test**: Disponibile per testing
- **Supporto**: Via ticket Aruba per problemi API

---

## ✅ **CHECKLIST IMPLEMENTAZIONE**

- [ ] Configurazione API Key / Username+Password
- [ ] Test connessione (userInfo)
- [ ] Import fatture da Aruba (findByUsername)
- [ ] Parsing XML fatture (estrazione importo, cliente)
- [ ] Estrazione clienti da fatture
- [ ] Calcolo potenziale entrate (fatture non pagate)
- [ ] Riconciliazione con movimenti bancari
- [ ] Aggiornamento stato fatture (incassata/non incassata)
- [ ] Sincronizzazione periodica (cron job)
- [ ] Dashboard monitoraggio
- [ ] Gestione errori
- [ ] Log operazioni
- [ ] Documentazione utente

---

**Versione**: 1.0  
**Data**: Gennaio 2025  
**API Aruba**: v1.21.0
