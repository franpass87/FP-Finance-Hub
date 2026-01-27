# 📖 Proposta README.md Principale

Ecco come potrebbe essere strutturato il README.md principale (più snello e professionale):

---

```markdown
# FP Finance Hub

**CRM + Dashboard Finanziario Completo per Business e Famiglia**

[![WordPress](https://img.shields.io/badge/WordPress-6.0+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)

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
- ✅ Riconciliazione automatica fatture ↔ movimenti
- ✅ Categorizzazione automatica movimenti

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

1. Carica la cartella plugin in `/wp-content/plugins/`
2. Attiva il plugin dalla schermata "Plugin" di WordPress
3. Vai a **FP Finance Hub → Impostazioni** per configurare

---

## ⚙️ Configurazione Base

### 1. Integrazione Aruba
- Configura API Key e Username
- Testa connessione
- Sincronizza fatture e clienti

### 2. Conti Bancari
- Aggiungi conti (PostePay, ING)
- Importa movimenti CSV/OFX
- Configura categorie spese

### 3. Soglie e Alert
- Imposta soglia saldo minimo
- Configura alert cash flow
- Abilita notifiche

---

## 📚 Documentazione

### Guide Utente
- [Getting Started](docs/user-guide/getting-started.md)
- [Gestione Clienti](docs/user-guide/crm-clients.md)
- [Dashboard Finanziario](docs/user-guide/finance-dashboard.md)
- [Conti Bancari](docs/user-guide/bank-accounts.md)
- [Spese Familiari](docs/user-guide/family-expenses.md)

### Documentazione Tecnica
- [Architettura](docs/technical/architecture.md)
- [Schema Database](docs/technical/database-schema.md)
- [API Reference](docs/technical/api-reference.md)
- [Integrazione Aruba](docs/technical/integration-aruba.md)
- [Sincronizzazione Plugin](docs/technical/synchronization.md)

---

## 🎯 Uso Base

### Import Clienti da Aruba
```
1. Vai a FP Finance Hub → Integrazione Aruba
2. Configura credenziali API
3. Clicca "Sincronizza Fatture"
4. I clienti vengono importati automaticamente
```

### Import Movimenti Bancari
```
1. Vai a FP Finance Hub → Conti Bancari → Import
2. Seleziona conto (PostePay o ING)
3. Carica file CSV/OFX
4. I movimenti vengono categorizzati automaticamente
```

### Visualizza Dashboard
```
1. Vai a FP Finance Hub → Dashboard
2. Visualizza overview finanziario
3. Espandi widget per dettagli
```

---

## 🔧 Sviluppo

### Setup Ambiente Sviluppo
```bash
# Clone repository
git clone [repo-url]

# Installa dipendenze
composer install

# Configura WordPress locale
# Attiva plugin in ambiente sviluppo
```

Vedi [Development Guide](docs/development/setup.md) per dettagli.

---

## 📝 Roadmap

### Versione 1.0 (MVP)
- [x] CRM clienti base
- [x] Import clienti da Aruba
- [x] Dashboard finanziario
- [x] Import conti bancari
- [x] Categorizzazione spese

### Versione 1.1
- [ ] Sincronizzazione avanzata plugin FP
- [ ] Budget familiare avanzato
- [ ] Report personalizzabili

### Versione 1.2
- [ ] Export fatture PDF
- [ ] Multi-valuta
- [ ] App mobile (API REST)

---

## 🤝 Contribuire

[Info su come contribuire]

---

## 📄 Licenza

[GPL v2 o successiva](LICENSE)

---

## 👤 Autore

**Francesco Passeri**

---

## 🙏 Ringraziamenti

- Aruba per API Fatturazione Elettronica
- WordPress Community

---

**⭐ Se questo plugin ti è utile, considera di dargli una stella!**
```

---

## 📊 Confronto: Proposta vs Attuale

| Aspetto | Attuale (PROPOSTA-PLUGIN.md) | Proposta (README.md) |
|---------|------------------------------|----------------------|
| **Lunghezza** | ~700 righe | ~150 righe |
| **Focus** | Proposta dettagliata | Overview + Quick Start |
| **Target** | Pianificazione | Utente finale + Developer |
| **Organizzazione** | Tutto in un file | Modulare con link |
| **Leggibilità** | Densa | Scannabile |

---

## ✅ Raccomandazione

1. **README.md** → Overview snello (questa proposta)
2. **PROPOSTA-PLUGIN.md** → Mantieni come riferimento completo
3. **docs/** → Documentazione dettagliata organizzata

---

**Ti piace questa struttura?** 🤔
