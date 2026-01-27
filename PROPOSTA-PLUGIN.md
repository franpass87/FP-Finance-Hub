# 📊 FP Finance Monitor - Proposta Plugin

**CRM + Dashboard Finanziario Completo (Aziendale + Familiare) per Francesco Passeri**

**Plugin WordPress per gestione clienti, monitoraggio, proiezioni e analisi finanziarie (business + famiglia)**

---

## 🎯 **OBIETTIVO**

Creare un plugin **CRM + Dashboard Finanziario Completo (Aziendale + Familiare)** che:
- ✅ **Gestisce anagrafiche clienti** (CRM personale)
- ✅ **Importa clienti da Aruba** (dalle fatture emesse)
- ✅ **Sincronizza clienti** verso FP Publisher, FP Task Agenda, Digital Marketing Suite
- ✅ **Monitora economia aziendale** (entrate/uscite business)
- ✅ **Monitora economia familiare** (spese personali/famiglia)
- ✅ **Separa automaticamente** movimenti aziendali vs familiari
- ✅ **Calcola proiezioni** di entrate/uscite (aziendali e familiari)
- ✅ **Stima** entrate e uscite previste (business + famiglia)
- ✅ **Monitora** saldi conti bancari e flussi di cassa (totali e separati)
- ✅ **Allerta** quando si superano soglie di sicurezza
- ✅ **Genera statistiche** e report analitici (dashboard, grafici, trend)

---

## 📋 **FUNZIONALITÀ PRINCIPALI**

### 0. **CRM Clienti** ⭐ **CORE - NUOVO**
- **Anagrafica Clienti Completa**:
  - Dati anagrafici (nome, ragione sociale, partita IVA, codice fiscale)
  - Contatti (email, telefono, cellulare, sito web)
  - Indirizzo completo (via, città, CAP, provincia, nazione)
  - Note e documenti
  - Tags e categorie clienti
  - Metadati personalizzati
- **Import Clienti da Aruba**:
  - Estrazione automatica clienti dalle fatture Aruba
  - Matching intelligente (evita duplicati per P.IVA)
  - Aggiornamento automatico dati esistenti
  - Sincronizzazione periodica
- **Hub CRM Centrale**:
  - **FP Client Manager** = **Source of Truth** per anagrafiche
  - Lista completa clienti con ricerca avanzata
  - Filtri per categoria, tag, fonte (Aruba, manuale, etc.)
  - Vista dettaglio cliente con storico fatture e interazioni
- **Sincronizzazione verso Altri Plugin**:
  - **FP Publisher**: Export clienti come "Remote Sites"
  - **FP Task Agenda**: Export clienti per assegnazione task
  - **Digital Marketing Suite**: Export clienti per campagne marketing
  - Sincronizzazione bidirezionale (opzionale) o unidirezionale (FP Client Manager → altri)
  - Mapping campi personalizzabile per ogni plugin
  - Log sincronizzazioni e stati

### 1. **Dashboard Finanziario** ⭐ **CORE**
- **Overview At-a-Glance**:
  - Saldo totale conti bancari (somma PostePay + ING)
  - Entrate mese corrente (effettive vs proiettate) - Totali, Aziendali, Familiari
  - Uscite mese corrente - Totali, Aziendali, Familiari
  - Flusso di cassa netto mensile - Totali, Aziendali, Familiari
  - Potenziale entrate (fatture emesse non ancora incassate)
  - Trend 12 mesi (grafico entrate/uscite totali)
  - **Widget Economia Familiare**:
    - Spese famiglia mese corrente
    - Budget familiare vs spese effettive
    - Top 5 categorie spesa famiglia
- **Widget Informativi**:
  - Soglia sicurezza: Alert se saldo < soglia minima
  - Fatture scadute non pagate
  - Prossime scadenze fiscali (IVA, tasse)
  - Confronto mese corrente vs mese precedente

### 2. **Proiezioni e Stime** ⭐ **CORE**
- **Proiezioni Entrate**:
  - **Automatiche**: Basate su fatture Aruba non pagate + entrate ricorrenti
  - **Scenari Multipli**:
    - Ottimistico (100% fatture incassate)
    - Realistico (80% fatture incassate)
    - Pessimistico (60% fatture incassate)
  - Proiezione mensile/trimestrale/annuale
  - Grafici confronto scenari
- **Stime Uscite**:
  - **Uscite Aziendali**:
    - Uscite ricorrenti business (canoni, abbonamenti, spese fisse)
    - Stima tasse da versare (IVA, IRPEF, INPS)
    - Uscite previste per categoria business
  - **Uscite Familiari**:
    - Uscite ricorrenti famiglia (mutuo/affitto, utenze, abbonamenti)
    - Spese previste familiari (alimentari, salute, scuola, svago)
    - Budget mensile familiare
  - Proiezione uscite mensili totali (aziendali + familiari)
- **Cash Flow Previsto**:
  - Entrate previste - Uscite previste = Cash Flow Netto
  - Proiezione saldo futuro (es. "a fine mese: €X")
  - Alert se proiezione < soglia sicurezza

### 3. **Monitoraggio Finanziario** ⭐ **CORE**
- **Monitoraggio Saldi**:
  - Saldo attuale per conto (PostePay, ING)
  - Saldo totale combinato
  - Trend saldo ultimi 12 mesi (grafico)
  - Variazione saldo mese corrente vs precedente
- **Monitoraggio Entrate**:
  - Entrate effettive per mese/trimestre/anno
  - Confronto con proiezioni (scostamenti)
  - Top 5 clienti per fatturato
  - Trend entrate (crescita/decrescita %)
- **Monitoraggio Uscite**:
  - Uscite per categoria (fisse, variabili, tasse)
  - Uscite mensili/trimestrali/annuali
  - Confronto uscite previste vs effettive
  - Trend uscite
  - **Separazione Aziendale/Familiare**:
    - Uscite aziendali vs familiari
    - Categorie spese familiari (alimentari, utenze, salute, svago, etc.)
    - Analisi spese familiari per categoria
    - Confronto spese aziendali vs familiari
- **Monitoraggio Fatture**:
  - Fatture emesse (da Aruba)
  - Fatture pagate vs non pagate
  - Ritardo pagamenti medio (giorni)
  - Valore fatture scadute non pagate

### 4. **Soglie di Sicurezza e Alert** ⭐ **CORE**
- **Soglie Configurabili**:
  - Saldo minimo conto corrente (soglia sicurezza)
  - Saldo minimo totale combinato
  - Soglia cash flow negativo (alert se < X per N mesi)
  - Soglia fatture scadute (alert se > X€)
- **Sistema di Alert**:
  - **Alert Rosso**: Saldo < soglia sicurezza
  - **Alert Giallo**: Saldo < soglia warning (1.5x soglia sicurezza)
  - **Alert Arancione**: Cash flow negativo previsto
  - **Alert Blu**: Fatture scadute > soglia
  - Notifiche email/WordPress admin (opzionale)
- **Dashboard Alert**:
  - Widget con tutti gli alert attivi
  - Filtri per tipo alert
  - Storico alert

### 5. **Statistiche e Analisi** ⭐ **CORE**
- **Statistiche Entrate**:
  - Media entrate mensili (ultimi 12 mesi)
  - Entrate min/max mensili
  - Crescita % annua
  - Stagionalità (quali mesi sono migliori/peggiori)
- **Statistiche Uscite**:
  - Uscite medie mensili (totali, aziendali, familiari)
  - Categorie spesa principali (%) - aziendali e familiari separate
  - Trend spese per categoria (business vs famiglia)
  - **Statistiche Familiari**:
    - Spese familiari per categoria (alimentari, utenze, trasporti, svago, etc.)
    - Confronto mensile spese famiglia
    - Media spese familiari mensili
    - Top categorie spesa famiglia
- **Analisi Trend**:
  - Grafico entrate/uscite 12 mesi (doppio asse)
  - Grafico saldo nel tempo
  - Grafico cash flow mensile (positivo/negativo)
  - Indicatori: crescita, stabilità, declino
- **Confronti Temporali**:
  - Mese corrente vs mese precedente
  - Trimestre corrente vs trimestre precedente
  - Anno corrente vs anno precedente
  - Media mobile 3/6/12 mesi

### 6. **Report e Export**
- **Report Automatici**:
  - Report mensile finanziario (entrate/uscite/saldo)
  - Report trimestrale
  - Report annuale
- **Export Dati**:
  - Export CSV (per analisi Excel)
  - Export PDF report (stampabile)
  - Dati grezzi per analisi esterne

### 7. **Import Dati** (per alimentare statistiche)
- **Da Aruba Fatturazione Elettronica**:
  - Import fatture emesse (solo lettura)
  - Estrazione clienti da fatture
  - Calcolo potenziale entrate (fatture non pagate)
  - Riconciliazione con movimenti bancari (per determinare se pagate)
- **Da Conti Bancari**:
  - Import movimenti CSV/OFX (PostePay, ING)
  - Import saldi conti
  - Categorizzazione automatica movimenti
  - Riconciliazione con fatture Aruba

---

## 🗄️ **STRUTTURA DATABASE**

### Tabelle Principali

```sql
-- Clienti (CRM - Source of Truth)
wp_fp_client_manager_clients (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL, -- Ragione sociale
  business_name VARCHAR(255), -- Nome commerciale
  vat_number VARCHAR(50), -- Partita IVA
  fiscal_code VARCHAR(50), -- Codice Fiscale
  email VARCHAR(255),
  phone VARCHAR(50),
  mobile VARCHAR(50),
  website VARCHAR(255),
  address TEXT, -- Indirizzo completo
  city VARCHAR(100),
  postcode VARCHAR(20),
  province VARCHAR(10),
  country VARCHAR(2) DEFAULT 'IT',
  -- Sincronizzazione
  source VARCHAR(50) DEFAULT 'manual', -- aruba, manual, fp_publisher, task_agenda
  source_id VARCHAR(255), -- ID nel sistema sorgente (es. aruba_id)
  -- Metadati
  tags TEXT, -- JSON array di tag
  category VARCHAR(100), -- Categoria cliente
  notes TEXT, -- Note libere
  metadata TEXT, -- JSON metadati personalizzati
  -- Sincronizzazione plugin FP
  synced_to_publisher BOOLEAN DEFAULT FALSE,
  synced_to_task_agenda BOOLEAN DEFAULT FALSE,
  synced_to_dms BOOLEAN DEFAULT FALSE,
  last_sync_publisher DATETIME NULL,
  last_sync_task_agenda DATETIME NULL,
  last_sync_dms DATETIME NULL,
  -- Timestamps
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY name (name),
  KEY vat_number (vat_number),
  KEY source (source),
  KEY category (category)
)

-- Fatture/Entrate
wp_fp_client_manager_invoices (
  id, client_id, invoice_number,
  issue_date, due_date, paid_date,
  amount, tax_rate, tax_amount, total_amount,
  status, payment_method, notes,
  -- Integrazione Aruba
  aruba_id, aruba_sdi_id, aruba_status, -- stato SDI
  aruba_sent_at, aruba_xml_path, -- percorso XML locale
  aruba_sync_status, aruba_last_sync, -- sync status
  user_id, created_at, updated_at
)

-- Entrate Ricorrenti (per proiezioni)
wp_fp_client_manager_recurring_income (
  id, client_id, name, amount,
  frequency, -- monthly, quarterly, yearly
  start_date, end_date,
  next_due_date, is_active,
  created_at, updated_at
)

-- Proiezioni
wp_fp_client_manager_projections (
  id, month, year,
  projected_income, actual_income,
  scenario, -- optimistic, realistic, pessimistic
  created_at, updated_at
)

-- Tasse
wp_fp_client_manager_taxes (
  id, tax_type, -- IVA, IRPEF, INPS
  period, -- 2024-01, 2024-Q1, 2024
  amount, due_date, paid_date,
  status, notes,
  created_at, updated_at
)

-- Conti Bancari
wp_fp_client_manager_bank_accounts (
  id, name, type, -- postepay, ing, other
  account_number, iban, currency,
  current_balance, -- Saldo attuale
  last_balance_date, -- Data ultimo saldo importato
  starting_balance, -- Saldo iniziale
  is_active, notes,
  created_at, updated_at
)

-- Movimenti Bancari
wp_fp_client_manager_bank_transactions (
  id, account_id,
  transaction_date, value_date,
  amount, balance, -- saldo dopo movimento
  description, reference, -- riferimento bonifico
  category, -- entrata, uscita, bonifico, etc.
  subcategory, -- categoria dettagliata (es. "alimentari", "utenze", "trasporti")
  transaction_type VARCHAR(50), -- 'business' o 'personal' o 'mixed'
  is_personal BOOLEAN DEFAULT FALSE, -- spesa familiare/personale
  is_business BOOLEAN DEFAULT FALSE, -- spesa aziendale
  invoice_id, -- riconciliazione con fattura (solo business)
  reconciled, reconciled_at,
  import_source, -- csv, ofx, manual
  raw_data, -- JSON dati originali
  created_at, updated_at
)

-- Categorie Spese Familiari
wp_fp_client_manager_expense_categories (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL, -- "Alimentari", "Utenze", "Trasporti"
  type VARCHAR(50) NOT NULL, -- 'business' o 'personal'
  parent_id BIGINT(20) NULL, -- categoria padre (es. "Utenze" -> "Luce", "Gas")
  icon VARCHAR(50), -- icona categoria
  color VARCHAR(7), -- colore categoria
  is_active BOOLEAN DEFAULT TRUE,
  created_at, updated_at,
  KEY type (type),
  KEY parent_id (parent_id)
)

-- Soglie di Sicurezza e Configurazione
wp_fp_client_manager_thresholds (
  id, name, -- "Saldo Minimo", "Cash Flow Warning"
  threshold_type, -- balance_min, cashflow_negative, invoices_overdue
  threshold_value DECIMAL(10,2), -- valore soglia
  threshold_period VARCHAR(50), -- monthly, quarterly, none
  is_active BOOLEAN DEFAULT TRUE,
  alert_level, -- warning, critical
  created_at, updated_at
)

-- Alert e Notifiche
wp_fp_client_manager_alerts (
  id, alert_type, -- balance_low, cashflow_negative, invoices_overdue
  severity, -- warning, critical
  message TEXT,
  threshold_id BIGINT(20),
  current_value DECIMAL(10,2),
  threshold_value DECIMAL(10,2),
  is_active BOOLEAN DEFAULT TRUE,
  acknowledged BOOLEAN DEFAULT FALSE,
  acknowledged_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)

-- Uscite Ricorrenti (per stime)
wp_fp_client_manager_recurring_expenses (
  id, name, -- "Canone hosting", "Abbonamento software"
  amount DECIMAL(10,2),
  frequency, -- monthly, quarterly, yearly
  category, -- fisse, variabili, tasse
  expense_type VARCHAR(50) DEFAULT 'business', -- 'business' o 'personal'
  start_date DATE,
  end_date DATE NULL,
  is_active BOOLEAN DEFAULT TRUE,
  notes TEXT,
  created_at, updated_at
)

-- Budget Familiare Mensile
wp_fp_client_manager_family_budget (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  month YEAR(4), -- Anno-Mese (YYYY-MM)
  category_id BIGINT(20), -- Riferimento a expense_categories
  budgeted_amount DECIMAL(10,2), -- Budget previsto
  actual_amount DECIMAL(10,2) DEFAULT 0.00, -- Spesa effettiva (calcolata)
  notes TEXT,
  created_at, updated_at,
  UNIQUE KEY month_category (month, category_id),
  KEY category_id (category_id)
)
```

---

## 🎨 **INTERFACCIA ADMIN**

### Menu WordPress
```
FP Client Manager (CRM + Finance)
├── 👥 Clienti ⭐ **CRM**
│   ├── Tutti i Clienti
│   ├── Aggiungi Cliente
│   ├── Import da Aruba
│   ├── Sincronizza con Altri Plugin
│   │   ├── FP Publisher
│   │   ├── FP Task Agenda
│   │   └── Digital Marketing Suite
│   ├── Categorie e Tag
│   └── Report Clienti
├── 📊 Dashboard (overview finanziario completo)
│   ├── Saldi Conti
│   ├── Entrate/Uscite Mese
│   ├── Cash Flow
│   ├── Alert Attivi
│   └── Trend 12 Mesi
├── 📈 Proiezioni e Stime
│   ├── Proiezioni Entrate (scenari)
│   ├── Stime Uscite
│   │   ├── Uscite Aziendali
│   │   ├── Uscite Familiari
│   │   └── Budget Familiare Mensile
│   ├── Cash Flow Previsto
│   │   ├── Totale (Aziendale + Familiare)
│   │   ├── Solo Aziendale
│   │   └── Solo Familiare
│   └── Configura Scenari
├── 📉 Monitoraggio
│   ├── Monitoraggio Saldi
│   ├── Monitoraggio Entrate
│   │   ├── Entrate Aziendali
│   │   └── Entrate Familiari
│   ├── Monitoraggio Uscite
│   │   ├── Uscite Aziendali
│   │   ├── Uscite Familiari
│   │   └── Categorie Spese Familiari
│   ├── Monitoraggio Fatture
│   └── Storico Movimenti
│       ├── Tutti i Movimenti
│       ├── Solo Aziendali
│       └── Solo Familiari
├── ⚠️ Soglie e Alert
│   ├── Configura Soglie
│   ├── Alert Attivi
│   ├── Storico Alert
│   └── Notifiche
├── 📊 Statistiche e Analisi
│   ├── Statistiche Entrate
│   │   ├── Totale
│   │   ├── Aziendali
│   │   └── Familiari
│   ├── Statistiche Uscite
│   │   ├── Totale
│   │   ├── Aziendali (per categoria)
│   │   ├── Familiari (per categoria)
│   │   └── Confronto Aziendale vs Familiare
│   ├── Analisi Trend
│   │   ├── Trend Totali
│   │   ├── Trend Aziendali
│   │   └── Trend Familiari
│   ├── Confronti Temporali
│   └── Grafici Interattivi
│       ├── Grafico Entrate/Uscite Totali
│       ├── Grafico Solo Aziendali
│       └── Grafico Solo Familiari
├── 🔗 Import Dati
│   ├── Integrazione Aruba ⭐
│   │   ├── Impostazioni API
│   │   ├── Sincronizza Fatture
│   │   ├── Potenziale Entrate
│   │   └── Log Operazioni
│   ├── Conti Bancari ⭐
│   │   ├── Panoramica Conti
│   │   ├── Import Saldi/Movimenti
│   │   ├── Categorizzazione Movimenti
│   │   │   ├── Classifica Aziendale/Familiare
│   │   │   ├── Auto-categorizzazione
│   │   │   └── Regole Categorizzazione
│   │   ├── Riconciliazione
│   │   └── Saldi Storici
│   └── Dati Manuali
│       ├── Entrate Manuali
│       ├── Uscite Aziendali Manuali
│       └── Uscite Familiari Manuali
├── 📄 Report
│   ├── Report Mensile
│   ├── Report Trimestrale
│   ├── Report Annuale
│   └── Export Dati (CSV/PDF)
└── ⚙️ Impostazioni
    ├── Configurazione Generale
    ├── Categorie Spese
    │   ├── Categorie Aziendali
    │   ├── Categorie Familiari
    │   └── Regole Auto-Categorizzazione
    ├── Soglie di Sicurezza
    ├── Impostazioni Fiscali
    └── Integrazioni
```

---

## 🔗 **INTEGRAZIONI** (Solo per import dati → analisi)

### Con Aruba Fatturazione Elettronica ⭐ **PRIORITARIO** (Solo Lettura)
**Scopo**: 
- **Import Clienti** (dalle fatture emesse) → CRM centrale
- Import fatture emesse per calcolare:
  - Potenziale entrate (fatture non pagate)
  - Proiezioni entrate future
  - Statistiche fatturato per cliente/periodo
- **Autenticazione**:
  - Username + Password o API Key
  - Gestione token automatica
  - Refresh token automatico
- **Import da Aruba** (Solo Lettura):
  - **Import Fatture**: Scarica fatture emesse da Aruba
  - **Import Clienti**: Estrae clienti dalle fatture Aruba
  - **Stati SDI**: Legge stati fatture (Inviata, Accettata, Consegnata, etc.)
  - **Sincronizzazione Periodica**: Aggiorna fatture e stati automaticamente
- **Operazioni Disponibili**:
  - Ricerca fatture inviate (findByUsername)
  - Download dettagli fattura (getByInvoiceId)
  - Parsing XML fatture (estrazione importo, cliente, dati)
  - Estrazione clienti da fatture
- **Calcolo Potenziale Entrate**:
  - Somma fatture non pagate da Aruba
  - Filtra per stato SDI (Inviata, Accettata, Consegnata)
  - Calcola totale potenziale incasso
- **Riconciliazione Stato Incassata**:
  - Confronta fatture Aruba con movimenti bancari
  - Aggiorna stato: "Pagata" (incassata) o "In Attesa" (non incassata)
  - Matching automatico (importo, data, descrizione)
- **Dashboard Integrazione**:
  - Stato connessione Aruba
  - Ultime fatture sincronizzate
  - Potenziale entrate (fatture non pagate)
  - Fatture incassate vs non incassate
  - Log operazioni

### Con Conti Bancari ⭐ **PRIORITARIO**
**Scopo**: Importare movimenti e saldi per:
- Monitoraggio saldi real-time
- Calcolo entrate/uscite effettive
- Riconciliazione con fatture (determinare se pagate)
- Calcolo cash flow effettivo

**Operazioni**:
- Import movimenti CSV/OFX (PostePay, ING)
- Import saldi conti (aggiornamento automatico)
- **Categorizzazione Movimenti**:
  - Classificazione automatica aziendale/familiare
  - Auto-categorizzazione per descrizione (regole configurabili)
  - Categorie spese familiari predefinite (alimentari, utenze, trasporti, etc.)
  - Assegnazione manuale categoria
- Riconciliazione automatica fatture ↔ movimenti (solo business)
- Calcolo saldi storici per analisi trend

### Con FP Publisher ⭐ **PRIORITARIO**
**Scopo**: Esportare clienti come "Remote Sites"
- **Sincronizzazione**: FP Client Manager → FP Publisher
- Mapping: Cliente → Remote Site (tabella `wp_fp_pub_remote_sites`)
- Campi sincronizzati: nome, URL sito, note
- Opzioni: Sincronizzazione automatica o manuale

### Con FP Task Agenda ⭐ **PRIORITARIO**
**Scopo**: Esportare clienti per assegnazione task
- **Sincronizzazione**: FP Client Manager → FP Task Agenda
- Mapping: Cliente → Cliente Task Agenda (tabella `wp_fp_task_agenda_clients`)
- Campi sincronizzati: nome, source, source_id
- Compatibilità: Usa stesso formato di `Client::sync_from_publisher()`

### Con Digital Marketing Suite (FPDMS) ⭐ **PRIORITARIO**
**Scopo**: Esportare clienti per campagne marketing
- **Sincronizzazione**: FP Client Manager → Digital Marketing Suite
- Mapping: Cliente → Cliente DMS (struttura da definire)
- Campi sincronizzati: nome, email, contatti, tags
- Funzionalità: Preparazione clienti per campagne email/SMS

---

## 📊 **FEATURES AVANZATE (Future)**

- **Multi-valuta**: Supporto EUR, USD, etc.
- **Integrazione Contabilità**: Export per software contabilità (Comarch, etc.)
- **App Mobile**: API REST per app mobile
- **Machine Learning**: Predizioni entrate basate su pattern storici

---

## 🚀 **TECNOLOGIA**

- **Namespace**: `FP\ClientManager`
- **PSR-4 Autoload**: Via Composer
- **WordPress**: 6.0+
- **PHP**: 7.4+
- **Database**: MySQL 5.7+

---

## 📝 **PRIORITÀ IMPLEMENTAZIONE**

### Fase 1 (MVP - CRM + Core Statistico)
1. ✅ **CRM Clienti Base** ⭐
   - Tabella clienti completa
   - CRUD clienti (crea, leggi, modifica, elimina)
   - Interfaccia lista clienti
   - Vista dettaglio cliente
2. ✅ **Import Clienti da Aruba** ⭐
   - Estrazione clienti dalle fatture Aruba
   - Parsing XML fatture
   - Matching P.IVA (evita duplicati)
   - Aggiornamento dati esistenti
3. ✅ **Sincronizzazione Base**
   - Export verso FP Publisher
   - Export verso FP Task Agenda
   - Interfaccia sincronizzazione manuale
4. ✅ **Dashboard Finanziario Base**
   - Widget saldi conti
   - Widget entrate/uscite mese
   - Widget cash flow
5. ✅ **Import Conti Bancari** (PostePay + ING)
   - Import CSV movimenti
   - Import saldi
   - Calcolo entrate/uscite effettive
6. ✅ **Categorizzazione Movimenti**
   - Classificazione aziendale/familiare
   - Categorie spese familiari predefinite
   - Interfaccia categorizzazione manuale
7. ✅ **Monitoraggio Saldi**
   - Visualizzazione saldo attuale
   - Grafico trend saldo
8. ✅ **Dashboard Economia Familiare**
   - Widget spese famiglia
   - Statistiche spese per categoria
   - Confronto budget vs effettivo
9. ✅ **Soglie di Sicurezza Base**
   - Configurazione soglia minima
   - Alert se saldo < soglia

### Fase 2 (Proiezioni e Stime + Sincronizzazione Avanzata)
8. ✅ **Sincronizzazione Avanzata**
   - Export verso Digital Marketing Suite
   - Sincronizzazione automatica (cron job)
   - Mapping campi personalizzabile
   - Log sincronizzazioni
9. ✅ **Integrazione Aruba Avanzata**
   - Sincronizzazione periodica fatture
   - Aggiornamento stati fatture
   - Calcolo potenziale entrate
10. ✅ **Proiezioni Entrate**
    - Scenari (ottimistico/realistico/pessimistico)
    - Proiezione mensile/trimestrale
11. ✅ **Stime Uscite**
    - Uscite ricorrenti configurabili
    - Stime tasse (IVA, IRPEF)
12. ✅ **Cash Flow Previsto**
    - Calcolo entrate - uscite previste
    - Proiezione saldo futuro

### Fase 3 (Analisi Avanzate)
13. ✅ **Riconciliazione Automatica**
   - Matching fatture ↔ movimenti bancari
   - Aggiornamento stato pagamenti
14. ✅ **Statistiche e Trend**
    - Grafici entrate/uscite 12 mesi
    - Analisi crescita/declino
    - Confronti temporali
15. ✅ **Soglie e Alert Avanzati**
    - Multi-soglia (warning/critico)
    - Alert cash flow negativo
    - Notifiche email
16. ✅ **Report e Export**
    - Report mensile/trimestrale/annuale
    - Export CSV/PDF
    - Report clienti
17. ✅ **CRM Avanzato**
    - Tags e categorie clienti
    - Ricerca avanzata
    - Filtri multipli
    - Storico interazioni cliente

---

## 💡 **VANTAGGI**

1. **Dashboard Centralizzato**: Tutte le info finanziarie in un unico posto
2. **Proiezioni Automatiche**: Calcolo automatico basato su dati reali (fatture + conti)
3. **Monitoraggio Proattivo**: Alert automatici quando superi soglie
4. **Privacy**: Dati sul tuo server (niente cloud esterno)
5. **Costo Zero**: Nessun abbonamento mensile
6. **Personalizzabile**: Adattabile alle tue esigenze specifiche

---

## 🎯 **DESTINATARIO**

**Francesco Passeri** - **CRM + Dashboard Finanziario Completo (Aziendale + Familiare)** per:
- 👥 **Gestire anagrafiche clienti** (hub centrale)
- 🔗 **Sincronizzare clienti** verso Publisher, Task Agenda, Digital Marketing Suite
- 📊 **Monitorare economia aziendale** (entrate/uscite business, fatture, proiezioni)
- 🏠 **Monitorare economia familiare** (spese personali/famiglia, budget, categorie)
- 📈 **Proiettare** entrate/uscite future (business + famiglia)
- ⚠️ **Ricevere alert** automatici quando superi soglie
- 📉 **Monitorare** continuamente saldi e cash flow (totali e separati)
- 💰 **Gestire budget familiare** e confrontare con spese effettive

---

**Vuoi che inizi a svilupparlo?** 🚀
