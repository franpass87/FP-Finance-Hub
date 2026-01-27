# SCRIPT - RICREA JUNCTION FP-FINANCE-HUB
# Esegui come amministratore: Click destro → Esegui come amministratore

$PLUGINS = "C:\Users\franc\Local Sites\fp-development\app\public\wp-content\plugins"
$PLUGIN_NAME = "FP-Finance-Hub"
$LAB = "C:\Users\franc\OneDrive\Desktop\FP-Finance-Hub-1"
$TARGET = Join-Path $PLUGINS $PLUGIN_NAME

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "RICREA JUNCTION FP-FINANCE-HUB" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# STEP 1: Elimina cartella normale o junction esistente
Write-Host "STEP 1: Elimino cartella/junction esistente..." -ForegroundColor Yellow

if (Test-Path $TARGET) {
    $item = Get-Item $TARGET -ErrorAction SilentlyContinue
    if ($item.LinkType -eq "Junction") {
        Write-Host "   Trovata JUNCTION esistente, elimino..." -ForegroundColor Gray
    } else {
        Write-Host "   Trovata CARTELLA NORMALE, elimino..." -ForegroundColor Gray
    }
    Remove-Item -Path $TARGET -Recurse -Force -ErrorAction Stop
    Write-Host "   ✅ Eliminata" -ForegroundColor Green
} else {
    Write-Host "   ⚠️  Nessuna cartella/junction esistente" -ForegroundColor Gray
}

Start-Sleep -Seconds 1

# STEP 2: Verifica cartella LAB
Write-Host ""
Write-Host "STEP 2: Verifico cartella LAB..." -ForegroundColor Yellow

if (-not (Test-Path $LAB)) {
    Write-Host "   ❌ ERRORE: La cartella LAB non esiste!" -ForegroundColor Red
    Write-Host "   Percorso atteso: $LAB" -ForegroundColor Red
    Write-Host ""
    Write-Host "   Verifica che la cartella LAB esista prima di continuare." -ForegroundColor Yellow
    Read-Host "Premi INVIO per uscire"
    exit 1
} else {
    Write-Host "   ✅ Cartella LAB trovata: $LAB" -ForegroundColor Green
}

Start-Sleep -Seconds 1

# STEP 3: Crea JUNCTION
Write-Host ""
Write-Host "STEP 3: Creo JUNCTION alla LAB..." -ForegroundColor Yellow

try {
    $null = New-Item -ItemType Junction -Path $TARGET -Target $LAB -Force
    Write-Host "   ✅ JUNCTION creata con successo!" -ForegroundColor Green
} catch {
    Write-Host "   ❌ ERRORE nella creazione della junction!" -ForegroundColor Red
    Write-Host "   Assicurati di eseguire come AMMINISTRATORE!" -ForegroundColor Red
    Write-Host "   Errore: $($_.Exception.Message)" -ForegroundColor Red
    Read-Host "Premi INVIO per uscire"
    exit 1
}

Start-Sleep -Seconds 1

# STEP 4: Verifica
Write-Host ""
Write-Host "STEP 4: Verifico junction..." -ForegroundColor Yellow

if (Test-Path $TARGET) {
    $item = Get-Item $TARGET
    if ($item.LinkType -eq "Junction") {
        Write-Host "   ✅ JUNCTION verificata correttamente!" -ForegroundColor Green
        Write-Host "   Target: $($item.Target)" -ForegroundColor Gray
    } else {
        Write-Host "   ⚠️  Attenzione: non è una junction!" -ForegroundColor Yellow
    }
} else {
    Write-Host "   ❌ JUNCTION non trovata!" -ForegroundColor Red
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "✅ SETUP COMPLETATO!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Prossimi passi:" -ForegroundColor Yellow
Write-Host "   1. Vai in WordPress Admin → Plugin" -ForegroundColor White
Write-Host "   2. Verifica che ci sia 'FP Finance Hub'" -ForegroundColor White
Write-Host "   3. Attiva il plugin" -ForegroundColor White
Write-Host "   4. Verifica che non ci siano errori" -ForegroundColor White
Write-Host ""
Read-Host "Premi INVIO per uscire"
