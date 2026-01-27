# 📦 Istruzioni Installazione FP Finance Hub

## 🎯 Setup LAB + JUNCTION

### 1. **Cartella LAB Creata** ✅
- **Percorso**: `C:\Users\franc\OneDrive\Desktop\FP-Finance-Hub-1`
- ✅ File principali creati
- ✅ Composer.json configurato

### 2. **Genera Autoload Composer**
```powershell
cd "C:\Users\franc\OneDrive\Desktop\FP-Finance-Hub-1"
composer dump-autoload --optimize
```

### 3. **Crea JUNCTION** (Come Amministratore)

**Opzione A: Script BAT (Consigliato)**
1. Click destro su `RICREA-JUNCTION-FP-FINANCE-HUB.bat`
2. **Esegui come amministratore**
3. Segui le istruzioni

**Opzione B: Manuale**
```powershell
# PowerShell come amministratore
$PLUGINS = "C:\Users\franc\Local Sites\fp-development\app\public\wp-content\plugins"
$PLUGIN_NAME = "FP-Finance-Hub"
$LAB = "C:\Users\franc\OneDrive\Desktop\FP-Finance-Hub-1"

# Elimina se esiste già
if (Test-Path "$PLUGINS\$PLUGIN_NAME") {
    Remove-Item "$PLUGINS\$PLUGIN_NAME" -Recurse -Force
}

# Crea junction
cmd /c mklink /J "$PLUGINS\$PLUGIN_NAME" "$LAB"
```

### 4. **Verifica**
1. Vai in WordPress Admin → Plugin
2. Verifica che appaia **FP Finance Hub**
3. Attiva il plugin
4. Controlla che non ci siano errori fatal

---

## ✅ Checklist Setup

- [ ] Cartella LAB creata su OneDrive Desktop
- [ ] Composer autoload generato (`vendor/autoload.php`)
- [ ] JUNCTION creata nel sito locale
- [ ] Plugin visibile in WordPress → Plugin
- [ ] Plugin attivato senza errori
- [ ] Struttura `includes/` presente

---

## 🔧 Troubleshooting

### Errore: "Composer autoload non trovato"
```powershell
cd "C:\Users\franc\OneDrive\Desktop\FP-Finance-Hub-1"
composer dump-autoload --optimize
```

### Errore: "Junction non funziona"
- Esegui script BAT come **amministratore**
- Verifica che la cartella LAB esista
- Verifica permessi cartella plugins

### Errore: "Plugin non visibile"
- Verifica che la JUNCTION sia creata correttamente
- Controlla che `fp-finance-hub.php` esista nella LAB
- Verifica permessi file

---

## 📝 Prossimi Passi

1. ✅ Setup base completato
2. [ ] Implementare Database Schema
3. [ ] Creare Admin Menus
4. [ ] Implementare Import CSV/OFX
5. [ ] Implementare Categorizzazione Intelligente

---

**Setup completato! Il plugin è pronto per lo sviluppo.** 🚀
