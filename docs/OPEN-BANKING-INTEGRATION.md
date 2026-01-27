# 🔌 Integrazione Open Banking - FP Finance Hub

## 📋 **SITUAZIONE ATTUALE API BANCARIE**

### PostePay Evolution

**Status:** ❌ Nessuna API pubblica diretta per sviluppatori privati

**Dettagli:**
- Le API pagoPA sono disponibili solo per PSP (Payment Service Providers) e Enti Creditori
- Non ci sono endpoint pubblici per sincronizzare movimenti di conti personali
- Supporta **Open Banking/PSD2** tramite TPP (Third Party Providers) autorizzati

**Disponibile:**
- ✅ Export CSV/OFX manuale dall'area riservata
- ✅ Accesso tramite aggregatori Open Banking autorizzati

---

### ING Direct Italia

**Status:** ❌ Nessuna API pubblica diretta per sviluppatori privati

**Dettagli:**
- ING non mette a disposizione API pubbliche per singoli sviluppatori
- Opera all'interno del framework **Open Banking/PSD2** regolamentato UE
- Accesso programmatico possibile solo tramite TPP autorizzati (AISP)

**Disponibile:**
- ✅ Export CSV manuale dall'area riservata
- ✅ Accesso tramite aggregatori Open Banking autorizzati
- ✅ App ING per consultazione saldi/movimenti

---

## ✅ **OPEN BANKING INTEGRATION - SOLUZIONI AUTOMATICHE**

### 🎯 **OPZIONI DISPONIBILI PER COLLEGAMENTO AUTOMATICO**

#### **1. Yapily** ⭐ **GRATUITO PER SVILUPPATORI** 🆓

**Status:** ✅ **Account gratuito per sviluppatori disponibile**

**Vantaggi:**
- ✅ **Account gratuito** per sviluppatori
- ✅ **Sandbox gratuito** (Modelo Sandbox) per test
- ✅ **Senza limiti** su numero di conti
- ✅ Supporto banche italiane (Unicredit, Intesa San Paolo, UBI Banca, BancoPosta, Banco BPM, BNL, ecc.)
- ✅ API PSD2 compliant
- ✅ Documentazione completa
- ✅ Nessun limite di sync/giorno

**Limitazioni:**
- ⚠️ Pricing per produzione da verificare (contattare Yapily)
- ⚠️ ING Italia supporto da verificare

**Costi:**
- ✅ **GRATIS** per account sviluppatori
- ✅ **GRATIS** per sandbox e test
- ⚠️ Pricing produzione da verificare

**Quando Scegliere:**
- ✅ **PRIMA SCELTA** se vuoi soluzione Open Banking moderna
- ✅ Account gratuito per sviluppatori
- ✅ Supporto banche italiane
- ✅ Nessun limite sync

**Setup:**
1. Account gratuito su https://console.yapily.com
2. Crea nuova Application
3. Ottieni `applicationUuid` (Application ID) e `secret` (Application Secret)
4. Implementa OAuth flow con consent-based authorization
5. **GRATIS per sviluppatori**

---

#### **2. TrueLayer** ⭐ **A PAGAMENTO** 💰

**Status:** ✅ **Attivo e supportato in Italia**

**Status:** ✅ **Attivo e supportato in Italia**

**Banche Supportate:**
- ✅ **Poste Italiane** (BancoPosta, PostePay)
- ✅ UniCredit, Intesa Sanpaolo, Banco BPM
- ✅ Banca Carige, ICCREA Banca, UBI Banca
- ⚠️ **ING Direct** (verificare copertura specifica)

**Vantaggi:**
- ✅ API semplice e ben documentata
- ✅ OAuth 2.0 integrato
- ✅ Supporto italiano completo
- ✅ Free tier disponibile per sviluppo
- ✅ SDK PHP disponibile
- ✅ Dashboard developer friendly

**Costi:**
- Free tier per test (fino a ~1000 chiamate/mese)
- Pricing pay-as-you-go per produzione

**Implementazione:**
```php
// Flusso semplificato TrueLayer
1. Utente clicca "Collega Conto Bancario"
2. Redirect a TrueLayer Connect (OAuth)
3. Utente seleziona banca (PostePay/ING)
4. Autenticazione banca (SCA)
5. TrueLayer restituisce access_token
6. Plugin salva token (criptato)
7. Cron job sincronizza ogni 6 ore
```

---

#### **2. Yapily**

**Status:** ✅ **Disponibile in Italia**

**Vantaggi:**
- ✅ Unified API per 2000+ istituti europei
- ✅ Copertura ampia
- ✅ Yapily Connect (senza necessità licenza TPP)

**Limitazioni:**
- ⚠️ Meno specifico per Italia rispetto a TrueLayer
- ⚠️ Supporto italiano meno esteso

---

#### **3. Plaid (Europa/Italia)**

**Status:** ✅ **Disponibile in Italia**

**Vantaggi:**
- ✅ Plaid Link (UI pre-fatta per connessioni)
- ✅ Standardizzazione dati eccellente
- ✅ API mature e stabili

**Limitazioni:**
- ⚠️ Focus principalmente su mercato anglo-americano
- ⚠️ Copertura banche italiane in crescita

---

#### **4. CBI Globe**

**Status:** ⚠️ **Più complesso**

**Requisiti:**
- Registrazione come TPP presso Bank of Italy
- Certificati eIDAS
- Compliance PSD2 completa
- Setup infrastruttura avanzato

**Quando Usare:**
- Se hai già registrazione TPP
- Per integrazioni enterprise
- Se servono banche non coperte da TrueLayer/Yapily

---

### 🚀 **IMPLEMENTAZIONE RACCOMANDATA**

#### **Opzione 1: Nordigen** 🆓 **GRATIS** (CONSIGLIATA per budget zero)

**Perché Nordigen:**
1. ✅ **100% GRATUITO** per sempre (AIS)
2. ✅ **Supporto Italia** (PostePay incluso)
3. ✅ **Setup rapido** (account gratuito in minuti)
4. ✅ **API semplice** e ben documentata
5. ✅ **4 sync/giorno** incluse (sufficienti per uso personale)

**Quando Scegliere Nordigen:**
- ✅ Budget zero
- ✅ 4 aggiornamenti/giorno sufficienti
- ✅ Uso personale/familiare
- ✅ Vuoi soluzione completamente gratuita

**Documentazione:** Vedi `IMPLEMENTAZIONE-NORDIGEN-GRATUITO.md`

---

#### **Opzione 2: TrueLayer** 💰 **A PAGAMENTO**

**Perché TrueLayer:**
1. ✅ **Supporto Italia nativo** (PostePay incluso)
2. ✅ **Sync illimitate** al giorno
3. ✅ **Connettività permanente** (no refresh 90 giorni)
4. ✅ **Documentazione eccellente** per PHP
5. ✅ **OAuth gestito** dalla loro piattaforma
6. ✅ **Free tier** per test/sviluppo

**Quando Scegliere TrueLayer:**
- ✅ Budget disponibile (~€10-15/mese)
- ✅ Serve sync più frequenti
- ✅ Connettività permanente preferibile

#### **Flusso Sincronizzazione Automatica:**
```
┌─────────────────────────────────────────┐
│ 1. Utente → "Collega Conto PostePay"   │
└──────────────┬──────────────────────────┘
               ↓
┌─────────────────────────────────────────┐
│ 2. Redirect → TrueLayer Connect (OAuth) │
│    - Selezione banca (PostePay/ING)     │
│    - Login banca (SCA richiesto)        │
└──────────────┬──────────────────────────┘
               ↓
┌─────────────────────────────────────────┐
│ 3. Callback → Plugin riceve:            │
│    - access_token (criptato, salvo DB)  │
│    - account_id                         │
│    - connection_id                      │
└──────────────┬──────────────────────────┘
               ↓
┌─────────────────────────────────────────┐
│ 4. Sincronizzazione Immediata           │
│    - Fetch saldi                        │
│    - Fetch movimenti ultimi 90 giorni   │
│    - Salva nel database                 │
└──────────────┬──────────────────────────┘
               ↓
┌─────────────────────────────────────────┐
│ 5. Cron Job Automatico (ogni 6 ore)     │
│    - Fetch nuovi movimenti              │
│    - Aggiorna saldi                     │
│    - Categorizzazione automatica        │
│    - Riconciliazione fatture            │
└─────────────────────────────────────────┘
```

#### **Sicurezza:**
- ✅ **Token criptati** nel database
- ✅ **OAuth 2.0** standard PSD2
- ✅ **SCA (Strong Customer Authentication)** gestito da banca
- ✅ **Refresh token** per rinnovo automatico
- ✅ **Consenso utente** esplicito e tracciabile

#### **Costi Stimati:**
- **Sviluppo/Test**: Gratuito (free tier)
- **Produzione**: ~€0.01-0.05 per chiamata API
- **Stima mensile** (2 conti, sync ogni 6h): ~€5-15/mese

#### **Tempo Implementazione:**
- **Setup account TrueLayer**: 1 giorno
- **Sviluppo OAuth flow**: 2-3 giorni
- **API integration**: 3-5 giorni
- **Testing e sicurezza**: 2-3 giorni
- **TOTALE**: ~2 settimane

---

### 📋 **REQUISITI IMPLEMENTAZIONE**

**Tecnici:**
- ✅ Account TrueLayer Developer (gratuito)
- ✅ SSL/HTTPS sul sito (obbligatorio OAuth)
- ✅ PHP 7.4+ con curl/openssl
- ✅ Database per salvare token (criptati)

**Legal/Compliance:**
- ✅ Privacy Policy aggiornata
- ✅ Consenso esplicito utente
- ✅ GDPR compliance (dati bancari sensibili)
- ✅ Trasparenza su dati accessibili

**Non Richiesto:**
- ❌ Registrazione TPP propria
- ❌ Certificati eIDAS
- ❌ Compliance PSD2 diretta (gestita da TrueLayer)

---

## 📊 **CONFRONTO SOLUZIONI**

| Caratteristica | CSV/OFX Manuale | TrueLayer Auto |
|----------------|-----------------|----------------|
| **Setup** | ✅ Immediato | ⚠️ ~2 settimane |
| **Costo** | ✅ Gratuito | ⚠️ ~€5-15/mese |
| **Frequenza** | ❌ Manuale | ✅ Automatica (ogni 6h) |
| **Affidabilità** | ✅ Sempre disponibile | ✅ Alta (API gestita) |
| **Sicurezza** | ✅ Locale | ✅ OAuth + Criptazione |
| **PostePay** | ✅ Sì | ✅ Sì |
| **ING Direct** | ✅ Sì | ⚠️ Da verificare |
| **Real-time** | ❌ No | ✅ Quasi (6h max) |

---

## 🎯 **RACCOMANDAZIONE FINALE**

### **Opzione 1: Nordigen (GRATUITA)** 🆓 **CONSIGLIATA SE BUDGET ZERO**

**Quando Scegliere:**
- ✅ Vuoi sincronizzazione automatica
- ✅ Budget disponibile (~€10-15/mese)
- ✅ Vuoi aggiornamenti frequenti (6h)
- ✅ Priorità: Convenienza vs costo

**Implementazione:**
1. Setup account TrueLayer Developer
2. Integrazione OAuth flow
3. Cron job sincronizzazione automatica
4. Dashboard collegamento conti

**Tempo:** ~2 settimane sviluppo

---

### **Opzione 2: TrueLayer (A Pagamento)** 💰

**Quando Scegliere:**
- ✅ Vuoi sync illimitate/giorno
- ✅ Budget disponibile (~€10-15/mese)
- ✅ Connettività permanente preferibile
- ✅ Vuoi supporto premium

**Implementazione:**
- Vedi `IMPLEMENTAZIONE-TRUELAYER.md`

**Tempo:** ~2 settimane sviluppo

---

### **Opzione 3: CSV/OFX Import (Manuale)**

**Quando Scegliere:**
- ✅ Vuoi soluzione gratuita
- ✅ Non serve real-time
- ✅ Puoi scaricare CSV periodicamente
- ✅ Priorità: Costo zero vs automatismo

**Implementazione:**
- ✅ Già prevista nella roadmap
- Upload CSV/OFX manuale
- Import immediato con categorizzazione

**Tempo:** Già pianificato

---

### **Opzione 3: Ibrida** 🎯 **IDEALE**

**Approccio:**
- ✅ **Default:** TrueLayer automatico (consigliato)
- ✅ **Fallback:** CSV/OFX manuale sempre disponibile
- ✅ Utente può scegliere modalità preferita

**Vantaggi:**
- Flessibilità massima
- Utente sceglie in base a necessità
- Backup se TrueLayer ha problemi

---

## 🚀 **PROSSIMI PASSI IMPLEMENTAZIONE**

### **Step 1: Setup TrueLayer Account**
1. Registrazione su https://truelayer.com
2. Creazione app per WordPress plugin
3. Ottieni Client ID e Secret
4. Configura callback URL OAuth

### **Step 2: Database Schema**
```sql
-- Tabella connessioni bancarie Open Banking
CREATE TABLE wp_fp_finance_hub_bank_connections (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT(20) NOT NULL,
  provider VARCHAR(50) DEFAULT 'truelayer',
  connection_id VARCHAR(255) NOT NULL,
  account_id VARCHAR(255) NOT NULL,
  bank_name VARCHAR(255),
  account_type VARCHAR(50),
  account_name VARCHAR(255),
  iban VARCHAR(34),
  access_token TEXT, -- Criptato
  refresh_token TEXT, -- Criptato
  token_expires_at DATETIME,
  last_sync_at DATETIME,
  is_active BOOLEAN DEFAULT TRUE,
  sync_frequency INT DEFAULT 6, -- ore
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY user_id (user_id),
  KEY connection_id (connection_id)
);
```

### **Step 3: Classi PHP**
- `Services\OpenBanking\TrueLayerService` - Gestione API TrueLayer
- `Services\OpenBanking\OAuthHandler` - Flusso OAuth
- `Services\OpenBanking\SyncService` - Sincronizzazione automatica
- `Admin\BankConnections` - UI collegamento conti

### **Step 4: Cron Jobs**
- `cron_sync_bank_accounts()` - Eseguito ogni 6 ore
- Sincronizza saldi e movimenti automaticamente

---

## 📝 **NOTE IMPORTANTI**

### **Sicurezza:**
- ⚠️ **Token criptati** sempre nel database (AES-256)
- ⚠️ **HTTPS obbligatorio** per OAuth callback
- ⚠️ **Refresh token** gestiti automaticamente
- ⚠️ **Log accessi** per audit

### **Privacy:**
- ✅ Utente deve **consentire esplicitamente** connessione
- ✅ Privacy Policy deve includere dati bancari
- ✅ Possibilità di **revocare** connessione in qualsiasi momento
- ✅ Dati cancellati se utente disconnette

### **Limitazioni:**
- ⚠️ **Rate limiting** TrueLayer (gestito automaticamente)
- ⚠️ **Token expiry** (refresh automatico)
- ⚠️ **Banche non coperte** → Fallback CSV/OFX

---

## 📝 **NOTE TECNICHE**

### Alternative Attuali

1. **Web Scraping** ❌
   - Non consigliato (viola ToS)
   - Fragile (cambia con aggiornamenti sito)
   - Problematico legalmente

2. **App Automation** ❌
   - Complesso da mantenere
   - Fragile
   - Non scalabile

3. **CSV/OFX Import** ✅
   - **RACCOMANDATO**: Soluzione attuale
   - Legale, affidabile, funzionante

---

## 🔗 **RISORSE**

- [CBI Globe - Gateway Open Banking Italia](https://www.cbi-org.eu/)
- [PSD2 Directive - EU Regulation](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:32015L2366)
- [Bank of Italy - TPP Registration](https://www.bancaditalia.it/)
- [Berlin Group - Open Banking Standards](https://www.berlin-group.org/)

---

**Conclusione:** Per ora, **CSV/OFX import manuale è la soluzione migliore**. Open Banking può essere valutato in futuro se necessario.
