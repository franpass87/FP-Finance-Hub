# FP Finance Hub

**CRM + Dashboard Finanziario Completo (Aziendale + Familiare)**

---

## 🎯 Panoramica

**FP Finance Hub** è un plugin WordPress completo che unisce:
- 👥 **CRM Clienti** (anagrafiche, import da Aruba, sincronizzazione plugin FP)
- 💼 **Gestione Finanziaria Aziendale** (fatture, entrate, proiezioni, tasse)
- 🏠 **Gestione Economia Familiare** (spese, budget, categorie)
- 📊 **Dashboard Finanziario** (monitoraggio, statistiche, alert, report)

---

## ✨ Caratteristiche Principali

### 👥 CRM Clienti
- ✅ Anagrafica clienti completa (P.IVA, CF, contatti, indirizzo)
- ✅ Import automatico clienti da Aruba Fatturazione Elettronica
- ✅ Sincronizzazione verso FP Publisher, FP Task Agenda, Digital Marketing Suite
- ✅ Ricerca avanzata, filtri, tags e categorie

### 💰 Finanza Aziendale
- ✅ Import fatture da Aruba (solo lettura)
- ✅ Calcolo potenziale entrate (fatture non pagate)
- ✅ Proiezioni entrate (scenari ottimistico/realistico/pessimistico)
- ✅ Monitoraggio cash flow e saldi
- ✅ Stime tasse (IVA, IRPEF, INPS)

### 🏠 Economia Familiare
- ✅ Categorizzazione automatica spese (aziendali vs familiari)
- ✅ Budget mensile familiare per categoria
- ✅ Statistiche spese famiglia (alimentari, utenze, trasporti, etc.)
- ✅ Confronto budget vs spese effettive

### 📊 Dashboard e Analisi
- ✅ Dashboard finanziario completo (business + famiglia)
- ✅ Grafici trend 12 mesi
- ✅ Alert automatici (soglie sicurezza, cash flow, fatture scadute)
- ✅ Report mensili/trimestrali/annuali
- ✅ Export CSV/PDF

### 🏦 Conti Bancari
- ✅ Import movimenti CSV/OFX (PostePay, ING Direct)
- ✅ Import saldi conti
- ✅ **Categorizzazione intelligente automatica** (analisi causali)
- ✅ Riconciliazione automatica fatture ↔ movimenti

---

## 🔗 Integrazioni

| Integrazione | Tipo | Descrizione |
|--------------|------|-------------|
| **Aruba Fatturazione Elettronica** | Import | Fatture emesse, clienti, stati SDI |
| **FP Publisher** | Export | Clienti come Remote Sites |
| **FP Task Agenda** | Export | Clienti per assegnazione task |
| **Digital Marketing Suite** | Export | Clienti per campagne marketing |
| **Conti Bancari** | Import | PostePay, ING Direct (CSV/OFX) |

---

## 📋 Requisiti

- **WordPress**: 6.0 o superiore
- **PHP**: 7.4 o superiore
- **MySQL**: 5.7 o superiore
- **Plugin**: FP Publisher, FP Task Agenda (opzionali per sync)

---

## 🚀 Installazione

### Via Git Updater (Consigliato)

1. Installa [Git Updater](https://github.com/afragen/git-updater) sul tuo sito WordPress
2. Vai a **Settings → Git Updater → Install Plugin**
3. Inserisci: `franpass87/FP-Finance-Hub`
4. Clicca **Install Plugin**
5. Esegui `composer install` nella cartella del plugin
6. Attiva il plugin dalla schermata "Plugin" di WordPress
7. Vai a **FP Finance Hub → Impostazioni** per configurare

### Installazione Manuale

1. Scarica l'ultima release
2. Carica nella cartella `/wp-content/plugins/FP-Finance-Hub/`
3. Esegui `composer install` nella cartella del plugin
4. Attiva il plugin dalla schermata "Plugin" di WordPress
5. Vai a **FP Finance Hub → Impostazioni** per configurare

### Setup Sviluppo Locale (Junction)

1. Esegui `composer install` nella cartella LAB
2. Crea JUNCTION usando `RICREA-JUNCTION-FP-FINANCE-HUB.bat` (come amministratore)
3. Attiva il plugin dalla schermata "Plugin" di WordPress

---

## 📚 Documentazione

Vedi cartella `docs/` per documentazione dettagliata:
- [Proposta Plugin](PROPOSTA-PLUGIN.md)
- [Struttura Plugin Standard](STRUTTURA-PLUGIN-STANDARD.md)
- [Integrazione Aruba](docs/INTEGRAZIONE-ARUBA.md)
- [Gestione Conti Bancari](docs/GESTIONE-CONTI-BANCARI.md)
- [Categorizzazione Intelligente](docs/CATEGORIZZAZIONE-INTELLIGENTE.md)
- [Open Banking Integration](docs/OPEN-BANKING-INTEGRATION.md)

---

## 🔧 Setup Sviluppo

```bash
# Nella cartella LAB
composer install

# Il plugin verrà caricato tramite JUNCTION nel sito locale
```

---

## 📝 Roadmap

### Versione 1.0 (MVP)
- [x] Struttura plugin standard FP
- [ ] CRM clienti base
- [ ] Import clienti da Aruba
- [ ] Dashboard finanziario
- [ ] Import conti bancari (CSV/OFX)
- [ ] Categorizzazione intelligente

### Versione 1.1
- [ ] Sincronizzazione plugin FP
- [ ] Budget familiare avanzato
- [ ] Report personalizzabili

---

**⭐ Plugin in sviluppo per Francesco Passeri**
