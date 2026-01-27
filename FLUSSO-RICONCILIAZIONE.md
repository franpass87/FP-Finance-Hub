# 🔄 Flusso Riconciliazione Completo

**Come il plugin determina se una fattura è incassata o non incassata**

---

## 📋 **OVERVIEW**

Il plugin combina dati da **Aruba** (fatture emesse) e **Conti Bancari** (movimenti) per determinare automaticamente lo stato delle fatture.

---

## 🔄 **FLUSSO COMPLETO**

```
┌─────────────────────┐
│   ARUBA (Fonte)     │
│  Fatture Emesse     │
└──────────┬──────────┘
           │
           │ 1. Import Fatture
           ↓
┌─────────────────────┐
│   WORDPRESS DB      │
│  Fatture + Clienti  │
└──────────┬──────────┘
           │
           │ 2. Calcolo Potenziale
           ↓
┌─────────────────────┐
│  POTENZIALE ENTRATE │
│  (Fatture non pagate)│
└──────────┬──────────┘
           │
           │ 3. Import Movimenti
           ↓
┌─────────────────────┐
│   CONTI BANCARI     │
│  PostePay + ING     │
└──────────┬──────────┘
           │
           │ 4. Riconciliazione
           ↓
┌─────────────────────┐
│  STATO FINALE       │
│  Incassata /        │
│  Non Incassata      │
└─────────────────────┘
```

---

## 📥 **STEP 1: Import da Aruba**

### Cosa Importa

1. **Fatture Emesse**:
   - Numero fattura
   - Data emissione
   - Importo totale (da XML)
   - Cliente (da XML)
   - Stato SDI (Inviata, Accettata, Consegnata, etc.)
   - ID SDI

2. **Clienti** (estratte dalle fatture):
   - Nome/Ragione sociale
   - Partita IVA
   - Codice Fiscale
   - Paese

### Codice Esempio

```php
// Sincronizza fatture da Aruba
$aruba_invoices = $aruba_api->getInvoices([
  'startDate' => '2025-01-01',
  'endDate' => '2025-01-31'
]);

foreach ($aruba_invoices as $aruba_invoice) {
  // Estrai dati da XML
  $invoice_data = parseInvoiceXML($aruba_invoice->file);
  
  // Crea/aggiorna fattura in WordPress
  $invoice_id = createOrUpdateInvoice([
    'invoice_number' => $invoice_data['number'],
    'issue_date' => $invoice_data['date'],
    'total_amount' => $invoice_data['total'],
    'aruba_id' => $aruba_invoice->id,
    'aruba_sdi_id' => $aruba_invoice->idSdi,
    'aruba_status' => $aruba_invoice->invoices[0]->status,
    'status' => 'pending' // Default: non pagata
  ]);
  
  // Estrai e crea/aggiorna cliente
  $client_id = createOrUpdateClient([
    'name' => $aruba_invoice->receiver->description,
    'piva' => $aruba_invoice->receiver->vatCode,
    'cf' => $aruba_invoice->receiver->fiscalCode
  ]);
}
```

---

## 💰 **STEP 2: Calcolo Potenziale Entrate**

### Logica

```php
// Potenziale = Somma fatture non pagate
$potential_income = sum(
  SELECT total_amount 
  FROM wp_fp_client_manager_invoices
  WHERE status != 'paid'
    AND aruba_status IN ('Inviata', 'Accettata', 'Consegnata')
);
```

### Dashboard

```
┌─────────────────────────────────────┐
│ 💰 Potenziale Entrate               │
├─────────────────────────────────────┤
│                                     │
│ Fatture Non Pagate: 15              │
│ Importo Totale: € 25,450.00         │
│                                     │
│ Breakdown per Stato SDI:            │
│ • Inviata: € 5,200.00 (3 fatture)   │
│ • Accettata: € 12,300.00 (7 fatture)│
│ • Consegnata: € 7,950.00 (5 fatture)│
└─────────────────────────────────────┘
```

---

## 🏦 **STEP 3: Import Movimenti Bancari**

### Da PostePay/ING

```php
// Import CSV movimenti
$transactions = $csv_parser->parse($csv_file);

foreach ($transactions as $transaction) {
  createBankTransaction([
    'account_id' => $account_id,
    'transaction_date' => $transaction['date'],
    'amount' => $transaction['amount'],
    'description' => $transaction['description'],
    'balance' => $transaction['balance']
  ]);
}
```

---

## 🔗 **STEP 4: Riconciliazione Automatica**

### Algoritmo Matching

```php
// Per ogni fattura non pagata
$unpaid_invoices = getUnpaidInvoices();

foreach ($unpaid_invoices as $invoice) {
  // Cerca movimento bancario corrispondente
  $matching_transaction = findMatchingTransaction([
    'amount' => $invoice->total_amount,
    'date_range' => [
      'start' => $invoice->due_date - 7 days,
      'end' => $invoice->due_date + 7 days
    ],
    'description_pattern' => [
      $invoice->invoice_number,
      $invoice->client->name,
      $invoice->client->piva
    ]
  ]);
  
  if ($matching_transaction) {
    // Fattura INCASSATA
    updateInvoiceStatus($invoice->id, 'paid');
    linkTransactionToInvoice($matching_transaction->id, $invoice->id);
    updateInvoicePaidDate($invoice->id, $matching_transaction->transaction_date);
  } else {
    // Fattura NON INCASSATA (ancora in attesa)
    // Stato rimane "pending" o "in_attesa"
  }
}
```

### Criteri Matching

1. **Importo**: ±0.01€ di tolleranza
2. **Data**: ±7 giorni dalla scadenza fattura
3. **Descrizione**: Cerca numero fattura, nome cliente, P.IVA

---

## 📊 **STATI FINALI FATTURE**

### Stati Possibili

| Stato WordPress | Significato | Origine |
|-----------------|-------------|---------|
| `pending` | Non incassata | Nessun movimento corrispondente |
| `paid` | Incassata | Movimento bancario trovato |
| `overdue` | Scaduta | Scadenza passata, non pagata |
| `cancelled` | Annullata | Fattura annullata in Aruba |

### Aggiornamento Automatico

```php
// Cron job giornaliero
add_action('fp_cm_daily_reconciliation', function() {
  // 1. Sincronizza fatture da Aruba
  syncInvoicesFromAruba();
  
  // 2. Import movimenti bancari (se nuovo CSV)
  // (Manuale o automatico se disponibile)
  
  // 3. Riconciliazione automatica
  reconcileInvoicesWithTransactions();
  
  // 4. Aggiorna potenziale entrate
  updatePotentialIncome();
});
```

---

## 💡 **ESEMPIO PRATICO**

### Scenario

1. **Aruba**: Fattura #123 emessa il 10/01/2025, importo €1,000.00, cliente "ABC SRL"
2. **Movimento Bancario**: 15/01/2025, +€1,000.00, descrizione "Bonifico ABC SRL"

### Riconciliazione

```
Fattura #123:
  - Importo: €1,000.00
  - Scadenza: 10/01/2025
  - Cliente: ABC SRL

Movimento 15/01:
  - Importo: +€1,000.00
  - Data: 15/01/2025 (entro ±7 giorni da 10/01)
  - Descrizione: "ABC SRL" (match!)

→ RISULTATO: Fattura marcata come "Pagata" (incassata)
```

---

## 📈 **DASHBOARD FINALE**

```
┌─────────────────────────────────────────┐
│ 📊 Dashboard Finanziario               │
├─────────────────────────────────────────┤
│                                         │
│ 💰 Entrate Mese Corrente                │
│    € 15,230.00 (fatture incassate)     │
│                                         │
│ 📋 Potenziale Entrate                    │
│    € 25,450.00 (fatture non pagate)    │
│                                         │
│ ✅ Fatture Incassate: 12                │
│ ⏳ Fatture in Attesa: 15                │
│                                         │
│ 🏦 Saldo Conti                          │
│    PostePay: € 5,234.56                 │
│    ING: € 12,890.12                     │
│    Totale: € 18,124.68                  │
└─────────────────────────────────────────┘
```

---

## 🔄 **SINCRONIZZAZIONE PERIODICA**

### Cron Job

```php
// Sincronizzazione giornaliera Aruba
wp_schedule_event(time(), 'daily', 'fp_cm_sync_aruba_daily');

// Riconciliazione ogni 6 ore
wp_schedule_event(time(), 'fp_cm_reconcile_hourly', 'fp_cm_reconcile_transactions');
```

### Flusso Automatico

```
Ogni giorno alle 2:00 AM:
  1. Sincronizza fatture da Aruba (ultime 30 giorni)
  2. Estrae nuovi clienti
  3. Aggiorna stati SDI
  4. Riconcilia con movimenti bancari
  5. Aggiorna potenziale entrate
  6. Invia notifica email (se configurata)
```

---

## ✅ **VANTAGGI**

1. **Automatizzazione**: Nessun inserimento manuale
2. **Accuratezza**: Matching intelligente riduce errori
3. **Tempo Reale**: Stato sempre aggiornato
4. **Trasparenza**: Vedi esattamente cosa è incassato e cosa no
5. **Proiezioni**: Calcolo automatico potenziale entrate

---

**Versione**: 1.0  
**Data**: Gennaio 2025
