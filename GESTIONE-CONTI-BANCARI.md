# 🏦 Gestione Conti Bancari - FP Client Manager

**Documentazione tecnica gestione conti PostePay Evolution e ING Direct**

---

## 📋 **OVERVIEW**

Il plugin gestisce i conti bancari per:
- ✅ Import movimenti da PostePay Evolution e ING Direct
- ✅ Riconciliazione automatica con fatture pagate
- ✅ Visualizzazione saldi e flussi di cassa
- ✅ Report finanziari completi

### 🔌 **API BANCARIE - SITUAZIONE ATTUALE**

**PostePay Evolution:**
- ❌ **Nessuna API pubblica diretta** per sviluppatori privati
- ✅ Supporta **Open Banking/PSD2** tramite TPP autorizzati
- ✅ Export **CSV/OFX** disponibile dall'area riservata

**ING Direct Italia:**
- ❌ **Nessuna API pubblica diretta** per sviluppatori privati
- ✅ Supporta **Open Banking/PSD2** tramite TPP autorizzati (AISP)
- ✅ Export **CSV** disponibile dall'area riservata

**Soluzione Implementata:**
- ✅ **Import CSV/OFX manuale** (soluzione attuale, funzionante)
- 🔮 **Futuro:** Integrazione con aggregatori Open Banking (CBI Globe, Yapily, TrueLayer) per sincronizzazione automatica

---

## 🏦 **CONTI SUPPORTATI**

### 1. **PostePay Evolution**
- **Export Disponibile**: CSV (per account business/e-commerce)
- **Formato CSV**: Standard PostePay
- **Campi**: Data, Descrizione, Importo, Saldo
- **Note**: Se disponibile solo PDF, parsing automatico (opzionale)

### 2. **ING Direct Italia**
- **Export Disponibile**: CSV diretto dall'area riservata
- **Formato CSV**: Standard ING
- **Campi**: Data, Valuta, Descrizione, Addebito/Accredito, Saldo
- **Procedura**: Area Riservata → Conto Corrente → Scarica movimenti → CSV

### 3. **Altri Conti** (Estendibile)
- Supporto formato OFX universale
- Import CSV generico con mapping personalizzabile

---

## 📥 **IMPORT SALDI E MOVIMENTI**

### Flusso Import CSV

```
1. Scarica CSV da PostePay/ING
   ↓
2. Upload file in WordPress Admin
   ↓
3. Selezione conto bancario
   ↓
4. Parsing e validazione dati
   ↓
5. Import movimenti nel database
   ↓
6. 🧠 Categorizzazione Intelligente Automatica
   - Analisi descrizione/causale
   - Rilevamento Business vs Personal
   - Assegnazione categoria (Alimentari, Utenze, etc.)
   - Calcolo confidenza
   ↓
7. Import/aggiornamento saldo conto
   ↓
8. Riconciliazione automatica con fatture
   ↓
9. Aggiornamento dashboard saldi
```

### 🧠 **Categorizzazione Intelligente**

Il plugin analizza automaticamente la **descrizione/causale** di ogni movimento per:

- ✅ **Determinare tipo**: Business o Personal/Familiare
- ✅ **Assegnare categoria**: Alimentari, Utenze, Trasporti, Salute, Shopping, etc.
- ✅ **Calcolare confidenza**: Livello di certezza categorizzazione (0-100%)

**Tecniche utilizzate:**
- Pattern matching con keyword (dizionario predefinito)
- Analisi del testo (NLP base)
- Regole configurabili utente
- Apprendimento automatico (migliora con categorizzazioni manuali)

**Esempio:**
```
Movimento: "PAGAMENTO BONIFICO ENEL ENERGIA S.P.A. BOLETTA N.123"
→ Categorizzato: Utenze (Luce), Personal, Confidenza: 95%
```

Vedi [Categorizzazione Intelligente](docs/CATEGORIZZAZIONE-INTELLIGENTE.md) per dettagli tecnici.

### Formati Supportati

#### CSV PostePay Evolution
```csv
Data,Descrizione,Importo,Saldo
2025-01-15,Bonifico ricevuto,1000.00,5000.00
2025-01-16,Prelievo ATM,-200.00,4800.00
```
**Saldo**: L'ultimo saldo nel CSV viene salvato come saldo attuale del conto.

#### CSV ING Direct
```csv
Data,Valuta,Descrizione,Addebito,Accredito,Saldo
15/01/2025,EUR,Bonifico ricevuto,,1000.00,5000.00
16/01/2025,EUR,Prelievo ATM,200.00,,4800.00
```
**Saldo**: L'ultimo saldo nel CSV viene salvato come saldo attuale del conto.

#### OFX (Formato Universale)
```xml
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <DTPOSTED>20250115</DTPOSTED>
            <TRNAMT>1000.00</TRNAMT>
            <MEMO>Bonifico ricevuto</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
```

---

## 🔄 **RICONCILIAZIONE AUTOMATICA**

### Algoritmo Matching

Il plugin riconcilia automaticamente i movimenti bancari con le fatture usando:

1. **Matching per Importo**:
   - Confronta importo movimento con totale fattura
   - Tolleranza: ±0.01€ (arrotondamenti)

2. **Matching per Data**:
   - Cerca movimenti entro ±7 giorni dalla data scadenza fattura
   - Configurabile (default 7 giorni)

3. **Matching per Descrizione**:
   - Cerca riferimenti nella descrizione movimento
   - Pattern: numero fattura, nome cliente, P.IVA

4. **Matching Manuale**:
   - Suggerimenti per movimenti non riconciliati
   - Interfaccia drag & drop per matching manuale

### Stati Riconciliazione

- ✅ **Riconciliato**: Movimento collegato a fattura
- ⏳ **In Attesa**: Movimento non ancora riconciliato
- ⚠️ **Discrepanza**: Importo o data non corrispondono
- ❌ **Non Riconciliabile**: Nessuna fattura corrispondente

---

## 💰 **GESTIONE SALDI**

### Import Saldi

Il saldo viene importato direttamente dal CSV:

```php
// Durante l'import CSV
foreach ($transactions as $transaction) {
  // Salva movimento
  createBankTransaction([
    'account_id' => $account_id,
    'amount' => $transaction['amount'],
    'balance' => $transaction['balance'], // Saldo dopo movimento
    ...
  ]);
  
  // Ultimo movimento = saldo attuale conto
  if ($is_last_transaction) {
    updateAccountBalance($account_id, $transaction['balance']);
  }
}
```

### Calcolo Saldi

```php
// Saldo attuale = ultimo movimento importato (o saldo manuale)
$current_balance = get_account_current_balance($account_id);

// Saldo storico = saldo a una data specifica
$historical_balance = get_balance_at_date($account_id, $date);

// Saldo iniziale = saldo prima del primo movimento importato
$starting_balance = get_account_starting_balance($account_id);
```

### Aggiornamento Saldo

```php
// Dopo ogni import CSV
$last_transaction = get_latest_transaction($account_id);
if ($last_transaction) {
  update_account_balance($account_id, $last_transaction->balance);
  update_account_last_balance_date($account_id, $last_transaction->transaction_date);
}
```

### Visualizzazione

- **Dashboard Conti**: Panoramica tutti i conti con saldi attuali
- **Grafico Saldi**: Trend saldo nel tempo (Chart.js) - mostra evoluzione
- **Saldo Storico**: Visualizza saldo a qualsiasi data
- **Movimenti Recenti**: Ultimi 10 movimenti per conto con saldo dopo movimento
- **Flussi di Cassa**: Entrate vs Uscite mensili con saldo finale mese

---

## 🗄️ **DATABASE**

### Tabella Conti Bancari

```sql
CREATE TABLE wp_fp_client_manager_bank_accounts (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL, -- "PostePay Evolution", "Conto ING"
  type VARCHAR(50) NOT NULL, -- postepay, ing, other
  account_number VARCHAR(100) NULL,
  iban VARCHAR(34) NULL,
  currency VARCHAR(3) DEFAULT 'EUR',
  current_balance DECIMAL(10,2) DEFAULT 0.00, -- Saldo attuale
  last_balance_date DATE NULL, -- Data ultimo saldo importato
  starting_balance DECIMAL(10,2) DEFAULT 0.00, -- Saldo iniziale (prima import)
  is_active BOOLEAN DEFAULT TRUE,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY type (type),
  KEY is_active (is_active)
);
```

### Tabella Movimenti Bancari

```sql
CREATE TABLE wp_fp_client_manager_bank_transactions (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT(20) NOT NULL,
  transaction_date DATE NOT NULL,
  value_date DATE NULL,
  amount DECIMAL(10,2) NOT NULL, -- positivo=entrata, negativo=uscita
  balance DECIMAL(10,2) NULL, -- saldo dopo movimento
  description TEXT,
  reference VARCHAR(255) NULL, -- riferimento bonifico, causale
  category VARCHAR(50) NULL, -- entrata, uscita, bonifico, prelievo, etc.
  invoice_id BIGINT(20) NULL, -- riconciliazione con fattura
  reconciled BOOLEAN DEFAULT FALSE,
  reconciled_at DATETIME NULL,
  import_source VARCHAR(50) NULL, -- csv, ofx, manual
  raw_data LONGTEXT NULL, -- JSON dati originali import
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY account_id (account_id),
  KEY transaction_date (transaction_date),
  KEY invoice_id (invoice_id),
  KEY reconciled (reconciled),
  KEY category (category)
);
```

---

## 🎨 **INTERFACCIA ADMIN**

### Pagina Conti Bancari

```
┌─────────────────────────────────────────┐
│ 🏦 Conti Bancari                        │
├─────────────────────────────────────────┤
│                                         │
│ ┌──────────────┐  ┌──────────────┐    │
│ │ PostePay     │  │ ING Direct   │    │
│ │ Evolution    │  │              │    │
│ │              │  │              │    │
│ │ Saldo:       │  │ Saldo:       │    │
│ │ € 5,234.56   │  │ € 12,890.12  │    │
│ │              │  │              │    │
│ │ Aggiornato:  │  │ Aggiornato:  │    │
│ │ 15/01/2025   │  │ 20/01/2025   │    │
│ │              │  │              │    │
│ │ [Dettagli]   │  │ [Dettagli]   │    │
│ └──────────────┘  └──────────────┘    │
│                                         │
│ [+ Aggiungi Conto] [Import Saldi/Movimenti]│
└─────────────────────────────────────────┘
```

### Pagina Import Saldi e Movimenti

```
┌─────────────────────────────────────────┐
│ 📥 Import Saldi e Movimenti            │
├─────────────────────────────────────────┤
│                                         │
│ Conto: [PostePay Evolution ▼]          │
│                                         │
│ File CSV/OFX: [Sfoglia...]             │
│                                         │
│ Formato: [Auto-rileva ▼]                │
│                                         │
│ Opzioni Import:                          │
│ ☑ Importa movimenti                     │
│ ☑ Aggiorna saldo conto                  │
│ ☐ Riconciliazione automatica            │
│                                         │
│ [Importa]                               │
│                                         │
│ ⚠️ Formati supportati:                  │
│ • CSV PostePay Evolution                │
│ • CSV ING Direct                        │
│ • OFX (formato universale)              │
│                                         │
│ ℹ️ Il saldo verrà aggiornato con        │
│    l'ultimo saldo presente nel file.    │
└─────────────────────────────────────────┘
```

### Pagina Riconciliazione

```
┌─────────────────────────────────────────┐
│ 🔄 Riconciliazione Movimenti           │
├─────────────────────────────────────────┤
│                                         │
│ Movimenti Non Riconciliati: 12          │
│                                         │
│ ┌─────────────────────────────────────┐│
│ │ 15/01/2025  +€1,000.00              ││
│ │ Bonifico ricevuto                    ││
│ │                                      ││
│ │ [Suggerimenti]                       ││
│ │ • Fattura #123 - Cliente ABC         ││
│ │   (€1,000.00, scadenza 10/01)       ││
│ │                                      ││
│ │ [Collega] [Ignora]                   ││
│ └─────────────────────────────────────┘│
└─────────────────────────────────────────┘
```

---

## 🔧 **PARSER CSV**

### Parser PostePay Evolution

```php
namespace FP\ClientManager\Import;

class PostePayParser {
  public function parse($csv_content) {
    // Parsing CSV PostePay
    // Formato: Data,Descrizione,Importo,Saldo
    $lines = str_getcsv($csv_content, "\n");
    $transactions = [];
    $last_balance = null;
    
    foreach ($lines as $line) {
      $data = str_getcsv($line);
      if (count($data) >= 4) {
        $balance = floatval($data[3]);
        $last_balance = $balance; // Salva ultimo saldo
        
        $transactions[] = [
          'date' => $this->parseDate($data[0]),
          'description' => $data[1],
          'amount' => floatval($data[2]),
          'balance' => $balance // Saldo dopo movimento
        ];
      }
    }
    
    return [
      'transactions' => $transactions,
      'final_balance' => $last_balance // Saldo finale da aggiornare
    ];
  }
}
```

### Parser ING Direct

```php
class INGParser {
  public function parse($csv_content) {
    // Parsing CSV ING
    // Formato: Data,Valuta,Descrizione,Addebito,Accredito,Saldo
    $lines = str_getcsv($csv_content, "\n");
    $transactions = [];
    $last_balance = null;
    
    foreach ($lines as $line) {
      $data = str_getcsv($line);
      if (count($data) >= 6) {
        $addebito = floatval($data[3] ?: 0);
        $accredito = floatval($data[4] ?: 0);
        $amount = $accredito > 0 ? $accredito : -$addebito;
        $balance = floatval($data[5]);
        $last_balance = $balance; // Salva ultimo saldo
        
        $transactions[] = [
          'date' => $this->parseDate($data[0]),
          'description' => $data[2],
          'amount' => $amount,
          'balance' => $balance // Saldo dopo movimento
        ];
      }
    }
    
    return [
      'transactions' => $transactions,
      'final_balance' => $last_balance // Saldo finale da aggiornare
    ];
  }
}
```

---

## 🤖 **RICONCILIAZIONE AUTOMATICA**

### Classe Riconciliazione

```php
namespace FP\ClientManager\Reconciliation;

class AutoReconciler {
  
  public function reconcileTransaction($transaction_id) {
    $transaction = $this->getTransaction($transaction_id);
    $invoices = $this->findMatchingInvoices($transaction);
    
    if (count($invoices) === 1) {
      // Match unico → riconcilia automatica
      $this->linkTransactionToInvoice($transaction_id, $invoices[0]->id);
      return ['status' => 'auto_reconciled', 'invoice_id' => $invoices[0]->id];
    } elseif (count($invoices) > 1) {
      // Match multipli → suggerimenti
      return ['status' => 'suggestions', 'invoices' => $invoices];
    } else {
      // Nessun match → manuale
      return ['status' => 'manual'];
    }
  }
  
  private function findMatchingInvoices($transaction) {
    global $wpdb;
    
    // Cerca fatture non pagate con importo simile
    $tolerance = 0.01;
    $date_range = 7; // giorni
    
    $query = $wpdb->prepare("
      SELECT * FROM {$wpdb->prefix}fp_client_manager_invoices
      WHERE status != 'paid'
        AND ABS(total_amount - %f) <= %f
        AND DATEDIFF(%s, due_date) BETWEEN -%d AND %d
      ORDER BY ABS(total_amount - %f) ASC
      LIMIT 5
    ", 
      abs($transaction->amount),
      $tolerance,
      $transaction->transaction_date,
      $date_range,
      $date_range,
      abs($transaction->amount)
    );
    
    return $wpdb->get_results($query);
  }
}
```

---

## 📊 **REPORT FINANZIARI**

### Report Flussi di Cassa

- **Entrate Mensili**: Somma movimenti positivi per mese
- **Uscite Mensili**: Somma movimenti negativi per mese
- **Saldo Finale**: Saldo conto a fine mese
- **Grafico Trend**: Visualizzazione entrate/uscite nel tempo

### Report Riconciliazione

- **Fatture Riconciliate**: % fatture con pagamento confermato
- **Movimenti Non Riconciliati**: Lista movimenti senza fattura
- **Discrepanze**: Differenze importo o data

---

## 🔒 **SICUREZZA**

### Best Practices

1. **Validazione File**: Verifica formato e struttura CSV/OFX
2. **Sanitizzazione**: Pulizia dati importati (XSS, SQL injection)
3. **Duplicati**: Controllo movimenti già importati
4. **Backup**: Backup automatico prima di import massivi
5. **Permessi**: Solo admin possono importare movimenti

---

## ✅ **CHECKLIST IMPLEMENTAZIONE**

- [ ] Creazione tabelle database (con campo saldo)
- [ ] Interfaccia gestione conti
- [ ] Parser CSV PostePay (estrazione saldo)
- [ ] Parser CSV ING (estrazione saldo)
- [ ] Parser OFX generico (estrazione saldo)
- [ ] Import movimenti
- [ ] Import/aggiornamento saldo conto
- [ ] Calcolo saldo storico (a data specifica)
- [ ] Algoritmo riconciliazione automatica
- [ ] Interfaccia riconciliazione manuale
- [ ] Dashboard conti con saldi
- [ ] Grafici saldi nel tempo
- [ ] Grafici flussi di cassa
- [ ] Report riconciliazione
- [ ] Export dati

---

## 📝 **NOTE TECNICHE**

### Formati CSV Supportati

**PostePay Evolution**:
- Separatore: virgola (`,`)
- Encoding: UTF-8
- Decimali: punto (`.`)
- Data: `YYYY-MM-DD` o `DD/MM/YYYY`

**ING Direct**:
- Separatore: virgola (`,`)
- Encoding: UTF-8
- Decimali: punto (`.`)
- Data: `DD/MM/YYYY`
- Campi: Data, Valuta, Descrizione, Addebito, Accredito, Saldo

### Limitazioni

- **Import Manuale**: Richiede download CSV da area riservata
- **Frequenza**: Nessun limite, ma consigliato import mensile
- **Dimensione File**: Max 10MB per file CSV
- **Duplicati**: Movimenti con stessa data/importo/descrizione vengono ignorati

---

**Versione**: 1.0  
**Data**: Gennaio 2025  
**Conti Supportati**: PostePay Evolution, ING Direct Italia
