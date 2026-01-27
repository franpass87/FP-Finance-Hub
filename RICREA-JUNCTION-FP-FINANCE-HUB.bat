@echo off
echo ========================================
echo RICREA JUNCTION FP-FINANCE-HUB
echo ========================================
echo.

set PLUGINS=C:\Users\franc\Local Sites\fp-development\app\public\wp-content\plugins
set PLUGIN_NAME=FP-Finance-Hub
set LAB=C:\Users\franc\OneDrive\Desktop\FP-Finance-Hub-1

echo 1. Verifico se esiste una cartella normale...
if exist "%PLUGINS%\%PLUGIN_NAME%\" (
    echo    Trovata cartella: %PLUGIN_NAME%
    
    REM Verifica se è una junction
    dir /al "%PLUGINS%\%PLUGIN_NAME%" | find "<JUNCTION>" > nul
    if errorlevel 1 (
        echo    ^> E' una CARTELLA NORMALE, la elimino...
        rd /s /q "%PLUGINS%\%PLUGIN_NAME%"
        echo    OK Cartella eliminata
    ) else (
        echo    ^> E' gia' una JUNCTION, elimino e ricreo...
        rd "%PLUGINS%\%PLUGIN_NAME%"
        echo    OK Junction eliminata
    )
) else (
    echo    Nessuna cartella esistente
)

echo.
echo 2. Verifico che esista la cartella LAB...
if not exist "%LAB%\" (
    echo    ERRORE: La cartella LAB non esiste!
    echo    Percorso atteso: %LAB%
    echo.
    echo    Verifica che la cartella LAB esista prima di continuare.
    pause
    exit /b 1
) else (
    echo    OK Cartella LAB trovata: %LAB%
)

echo.
echo 3. Creo la JUNCTION...
mklink /J "%PLUGINS%\%PLUGIN_NAME%" "%LAB%"

if errorlevel 1 (
    echo    ERRORE nella creazione della junction!
    echo    Assicurati di eseguire come AMMINISTRATORE!
    pause
    exit /b 1
)

echo.
echo ========================================
echo OK JUNCTION CREATA CON SUCCESSO!
echo ========================================
echo.
echo Dettagli:
echo   Junction: %PLUGIN_NAME%
echo   Target:   %LAB%
echo.
echo Ora:
echo   1. Vai in WordPress -^> Plugin
echo   2. Attiva il plugin FP Finance Hub
echo   3. Esegui composer install nella cartella LAB
echo   4. Verifica che il plugin funzioni correttamente
echo.
pause
