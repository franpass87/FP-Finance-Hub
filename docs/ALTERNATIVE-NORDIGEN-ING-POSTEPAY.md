# 🔄 Alternative Open Banking per ING e PostePay Evolution

**Data:** Gennaio 2026  
**Obiettivo:** Trovare soluzioni Open Banking per sincronizzare automaticamente movimenti e saldi di ING Direct Italia e PostePay Evolution

---

## 📋 **SITUAZIONE ATTUALE**

### ❌ **Servizi Non Più Disponibili**

- ❌ **Nordigen/GoCardless Bank Account Data:** Non fornisce più servizio
- ❌ **Yapily:** Non fornisce più servizio

**Nota:** Questi servizi potrebbero non essere più disponibili per uso personale o per il mercato italiano.

---

## 🎯 **ALTERNATIVE DISPONIBILI**

### 1. **TrueLayer** 💰 **A PAGAMENTO** ⭐ **CONSIGLIATA**

**Status:** ✅ **Attivo e supportato in Italia**

**Vantaggi:**
- ✅ **Supporto confermato PostePay**
- ✅ **Oltre 2,000 banche** supportate
- ✅ Sync illimitate/giorno
- ✅ Connettività permanente (no refresh 90 giorni)
- ✅ Documentazione eccellente
- ✅ Free tier per sviluppo/test
- ✅ API semplice e ben documentata
- ✅ OAuth 2.0 integrato

**Supporto Banche:**
- ✅ **PostePay:** Supportato
- ⚠️ **ING Italia:** Da verificare (non confermato esplicitamente)

**Costi (2026):**
- ⚠️ **Develop Plan (GRATIS - SOLO SANDBOX):**
  - $0/month per sempre
  - **⚠️ SOLO SANDBOX** (mock banks, NON conti reali)
  - Solo per test e sviluppo
  - **NON può essere usato per conti bancari reali**
  - Client IDs sandbox prefissati con "sandbox-"

- 💰 **Scale Plan (PRODUZIONE - CONTI REALI):**
  - **$326/month** (circa €300/mese)
  - **✅ Conti bancari REALI**
  - Pricing basato su utilizzo
  - Nessun costo setup
  - Supporto enterprise

**⚠️ IMPORTANTE:** Il piano gratuito è **SOLO per test** con banche mock. Per conti reali serve il piano a pagamento ($326/mese).

**Quando Scegliere:**
- ⚠️ **SOLO se hai budget** per piano produzione ($326/mese)
- ✅ Se serve solo PostePay (e hai budget)
- ✅ Se serve sync frequenti
- ❌ **NON adatto per uso personale gratuito** (solo sandbox nel free tier)

**Implementazione:**
- ✅ Documentazione completa presente (`IMPLEMENTAZIONE-TRUELAYER.md`)
- ⚠️ Non ancora implementato nel codice
- ⚠️ Richiede sviluppo completo

**Setup:**
1. Registrati su https://truelayer.com
2. Crea nuova Application
3. Ottieni `CLIENT_ID` e `CLIENT_SECRET`
4. Configura Redirect URI
5. Implementa OAuth flow

---

### 2. **Salt Edge** 💰 **A PAGAMENTO**

**Status:** ✅ **Supporta ING e PostePay confermato**

**Vantaggi:**
- ✅ **Supporto confermato ING Italia**
- ✅ **Supporto confermato PostePay**
- ✅ **1,586 banche in 71 paesi**
- ✅ **46 paesi supportati** (forte presenza in Europa)
- ✅ API ben documentata
- ✅ Account Information Services (AIS)
- ✅ Payment Initiation Services (PIS)
- ✅ Data Enrichment Platform (categorizzazione transazioni)
- ✅ PSD2-compliant

**Supporto Banche:**
- ✅ **ING Italia:** Supportato
- ✅ **PostePay:** Supportato

**Costi (2026):**
- ⚠️ **Pricing NON pubblicamente disponibile**
- ❌ **Nessuna versione gratuita o trial**
- 💰 **Richiede contatto diretto** per preventivo
- 📧 Contattare: https://www.saltedge.com

**Quando Scegliere:**
- ✅ Se serve supporto garantito per **entrambe** le banche (ING + PostePay)
- ✅ Se TrueLayer non supporta ING
- ✅ Budget disponibile (prezzo da negoziare)
- ✅ Per uso enterprise/produzione

**Implementazione:**
- ❌ Non ancora implementato
- ⚠️ Richiede sviluppo completo
- 📚 Documentazione: https://docs.saltedge.com

---

### 3. **enable:Banking** 💰 **A PAGAMENTO**

**Status:** ✅ **Supporta ING Italia confermato**

**Vantaggi:**
- ✅ **Supporto confermato ING Italia**
- ✅ 108 banche in 39 paesi
- ✅ Documentazione specifica per Italia
- ✅ API semplice

**Costi:**
- ⚠️ Pricing a pagamento (verificare su enablebanking.com)
- Free tier per test (probabilmente solo sandbox)

**Supporto Banche:**
- ✅ **ING Italia:** Supportato
- ⚠️ **PostePay:** Da verificare

**Quando Scegliere:**
- ✅ Se serve solo ING
- ✅ Se TrueLayer non supporta ING
- ✅ Budget disponibile

**Implementazione:**
- ❌ Non ancora implementato
- ⚠️ Richiede sviluppo completo

---

### 4. **Import Manuale CSV/OFX** 🆓 **GRATUITO** ⭐ **ALTERNATIVA GRATUITA**

**Status:** ✅ **Già implementato nel plugin**

**Vantaggi:**
- ✅ **Completamente GRATUITO**
- ✅ **Funziona con TUTTE le banche** (ING, PostePay, qualsiasi banca)
- ✅ **Conti REALI** (non sandbox)
- ✅ **Già implementato** nel plugin
- ✅ Nessun limite sync
- ✅ Nessun costo mensile
- ✅ Dati sempre aggiornati (quando importi)

**Come Funziona:**
1. Scarica CSV/OFX dall'area riservata della banca (ING, PostePay)
2. Vai su **FP Finance Hub → Import**
3. Carica il file CSV/OFX
4. Il plugin importa automaticamente:
   - Movimenti
   - Saldi
   - Categorizzazione automatica
   - Riconciliazione fatture

**Limitazioni:**
- ⚠️ **Manuale** (devi scaricare e caricare periodicamente)
- ⚠️ Non automatico (non sincronizza da solo)
- ⚠️ Richiede accesso all'area riservata banca

**Quando Scegliere:**
- ✅ **PRIMA SCELTA** se vuoi soluzione gratuita
- ✅ Se non serve sincronizzazione automatica in tempo reale
- ✅ Se puoi scaricare CSV/OFX periodicamente (es. settimanale/mensile)
- ✅ Per uso personale/familiare

**Implementazione:**
- ✅ **Già implementato!**
- ✅ Parser CSV/OFX già presenti
- ✅ Import automatico già funzionante

---

## 📊 **CONFRONTO SOLUZIONI**

| Soluzione | Costo | ING Italia | PostePay | Sync | Conti Reali | Status |
|-----------|-------|------------|----------|------|-------------|--------|
| **Import CSV/OFX** | 🆓 **GRATIS** | ✅ Sì | ✅ Sì | ⚠️ Manuale | ✅ Sì | ✅ **Già implementato** |
| **TrueLayer** | 💰 **$326/mese** | ⚠️ Da verificare | ✅ Sì | ✅ Automatico | ✅ Sì (solo a pagamento) | ❌ Non implementato |
| **Salt Edge** | 💰 **Da contattare** | ✅ Sì | ✅ Sì | ✅ Automatico | ✅ Sì | ❌ Non implementato |
| **enable:Banking** | 💰 Da verificare | ✅ Sì | ⚠️ Da verificare | ✅ Automatico | ✅ Sì | ❌ Non implementato |

**Nota:** TrueLayer free tier è SOLO sandbox (mock banks), non conti reali. Per conti reali serve piano a pagamento.

---

## 🎯 **RACCOMANDAZIONE FINALE**

### **Opzione 1: Import CSV/OFX (CONSIGLIATA PER USO GRATUITO)** ⭐🆓

**Perché:**
1. ✅ **Completamente GRATUITO**
2. ✅ **Già implementato** nel plugin
3. ✅ **Funziona con TUTTE le banche** (ING, PostePay, qualsiasi)
4. ✅ **Conti REALI** (non sandbox)
5. ✅ Nessun costo mensile
6. ✅ Categorizzazione automatica già presente
7. ✅ Riconciliazione fatture già presente

**Limitazioni:**
- ⚠️ **Manuale** (devi scaricare CSV/OFX periodicamente)
- ⚠️ Non sincronizza automaticamente

**Quando Usare:**
- ✅ **PRIMA SCELTA** se vuoi soluzione gratuita
- ✅ Se puoi scaricare CSV/OFX settimanalmente/mensilmente
- ✅ Per uso personale/familiare
- ✅ Se non serve sincronizzazione in tempo reale

**Come Usare:**
1. Vai su **FP Finance Hub → Import**
2. Scarica CSV/OFX dall'area riservata ING/PostePay
3. Carica il file
4. Il plugin importa automaticamente tutto

---

### **Opzione 2: TrueLayer (SE SERVE AUTOMATICO)** 💰

**Perché:**
1. ✅ **Sincronizzazione automatica** (ogni 6 ore)
2. ✅ **Supporto PostePay confermato**
3. ✅ **Nessun limite sync/giorno**
4. ✅ Documentazione eccellente
5. ⚠️ ING da verificare

**Costi:**
- **⚠️ IMPORTANTE:** Free tier è **SOLO sandbox** (mock banks)
- **Per conti reali:** 💰 **$326/mese** (circa €300/mese)

**Quando Usare:**
- ✅ Se serve sincronizzazione automatica in tempo reale
- ✅ Se hai budget per $326/mese
- ✅ Se non vuoi import manuale

**Prossimi Passi:**
1. Contattare TrueLayer per preventivo
2. Verificare supporto ING nella lista banche
3. Se accettabile → Implementare TrueLayerService
4. Testare con PostePay
5. Se ING supportato → Usa TrueLayer per entrambe

---

### **Opzione 3: Salt Edge (SE SERVE AUTOMATICO E BUDGET LIMITATO)**

**Perché:**
1. ✅ **Supporto garantito ING e PostePay**
2. ✅ API ben documentata
3. ⚠️ Richiede sviluppo completo
4. ⚠️ **Costi da negoziare** (potrebbero essere più bassi di TrueLayer)

**Prossimi Passi:**
1. Contattare Salt Edge per preventivo: https://www.saltedge.com
2. Verificare costi per uso personale (potrebbero avere pricing più basso di TrueLayer)
3. Se accettabile → Implementare Salt Edge
4. Testare con entrambe le banche

---

## 💡 **STRATEGIA RACCOMANDATA**

### **Per Uso Personale/Familiare (GRATUITO):**

**Usa Import CSV/OFX** (già implementato):
- ✅ Gratuito
- ✅ Funziona con tutte le banche
- ✅ Conti reali
- ⚠️ Manuale (scarica CSV/OFX settimanalmente/mensilmente)

**Frequenza consigliata:**
- Settimanale: Scarica CSV ogni settimana
- Mensile: Scarica CSV ogni mese
- Il plugin categorizza e riconcilia automaticamente

### **Per Uso Professionale (AUTOMATICO):**

**Se hai budget ($300-500/mese):**
1. Contatta **Salt Edge** per preventivo (potrebbe essere più economico)
2. Se troppo costoso → Contatta **TrueLayer** ($326/mese)
3. Scegli quello più conveniente

**Se budget limitato:**
- Usa **Import CSV/OFX** (gratuito)
- Importa più frequentemente (es. ogni 2-3 giorni)

---

## 🚀 **PIANO DI AZIONE IMMEDIATO**

### **Step 1: Verifica TrueLayer (1 giorno)**

1. Registrati su https://truelayer.com (piano gratuito)
2. Crea nuova Application
3. Verifica banche disponibili per Italia:
   - Cerca "ING" o "ING Direct"
   - Cerca "PostePay" o "Poste Italiane"
4. Se entrambe supportate → **Procedi con TrueLayer**
5. Se solo PostePay → **Valuta Salt Edge per ING**

### **Step 2: Implementa TrueLayer (3-5 giorni)**

Se TrueLayer supporta entrambe o almeno PostePay:
1. Ottieni CLIENT_ID e CLIENT_SECRET
2. Implementa `TrueLayerService.php` (vedi `IMPLEMENTAZIONE-TRUELAYER.md`)
3. Implementa `TrueLayerSyncService.php`
4. Crea UI collegamento conti (`BankConnectionsPage.php` o nuova pagina)
5. Testa OAuth flow con PostePay
6. Se ING supportato, testa anche con ING
7. Verifica sincronizzazione automatica
8. Testa categorizzazione automatica

**Vantaggio:** Piano gratuito per sempre = puoi testare senza costi!

### **Step 3: Implementa Salt Edge (se necessario) (3-5 giorni)**

Se TrueLayer non supporta ING:
1. Contatta Salt Edge per preventivo: https://www.saltedge.com
2. Verifica costi per uso personale
3. Se accettabile → Crea account Salt Edge
4. Implementa `SaltEdgeService.php`
5. Implementa `SaltEdgeSyncService.php`
6. Aggiungi supporto multipli provider nel plugin
7. Testa con ING

---

## 📝 **NOTE IMPORTANTI**

### **Verifica Supporto Banche**

Prima di implementare, **verifica sempre** il supporto specifico:
- **TrueLayer:** Dashboard TrueLayer → Institutions → Filtra per Italia
- **Salt Edge:** https://www.saltedge.com/products/account_information
- **enable:Banking:** https://enablebanking.com/docs/markets/it

### **Costi Produzione (2026)**

- **TrueLayer:**
  - **Sviluppo/Test:** 🆓 **GRATIS** per sempre (100 conti)
  - **Produzione:** 💰 **$326/mese** (circa €300/mese)
  
- **Salt Edge:**
  - **Sviluppo/Test:** ❌ Nessun free tier
  - **Produzione:** 💰 **Da contattare** (prezzo personalizzato)
  
- **enable:Banking:**
  - **Sviluppo/Test:** 🆓 Free tier disponibile
  - **Produzione:** 💰 Da verificare

### **Sicurezza**

Tutte le soluzioni usano:
- ✅ OAuth 2.0 standard PSD2
- ✅ SCA (Strong Customer Authentication)
- ✅ Token criptati nel database
- ✅ HTTPS obbligatorio

---

## ✅ **CONCLUSIONE**

### **Per Uso Personale/Familiare (GRATUITO):**

**Raccomandazione:** Usa **Import CSV/OFX** (già implementato, completamente gratuito).

**Vantaggi:**
- ✅ Gratuito per sempre
- ✅ Funziona con tutte le banche (ING, PostePay, qualsiasi)
- ✅ Conti reali (non sandbox)
- ✅ Già implementato nel plugin
- ✅ Categorizzazione automatica
- ✅ Riconciliazione fatture

**Come fare:**
1. Vai su **FP Finance Hub → Import**
2. Scarica CSV/OFX dall'area riservata banca (ING/PostePay)
3. Carica il file
4. Il plugin importa tutto automaticamente

**Frequenza:** Importa CSV/OFX settimanalmente o mensilmente

---

### **Per Uso Professionale (AUTOMATICO):**

**Raccomandazione:** Contatta **Salt Edge** e **TrueLayer** per preventivi, scegli quello più conveniente.

**Costi Stimati:**
- **Salt Edge:** 💰 Da contattare (prezzo personalizzato)
- **TrueLayer:** 💰 **$326/mese** (circa €300/mese)

**⚠️ IMPORTANTE:** Nessuna soluzione Open Banking è gratuita per conti reali. Tutte le soluzioni automatiche richiedono pagamento mensile.

**Tempo stimato implementazione:** 3-5 giorni (solo se scegli soluzione a pagamento)

---

## ⚠️ **NOTE IMPORTANTI**

### **Servizi Non Più Disponibili**
- ❌ **Nordigen/GoCardless Bank Account Data:** Non fornisce più servizio
- ❌ **Yapily:** Non fornisce più servizio

Questi servizi potrebbero non essere più disponibili per uso personale o per il mercato italiano. Il codice esistente per Yapily nel plugin può essere rimosso o mantenuto per riferimento futuro.

---

**Ultimo aggiornamento:** Gennaio 2026
