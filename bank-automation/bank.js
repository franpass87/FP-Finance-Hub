const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const readline = require('readline');

// Setup readline per attendere ENTER
const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

// Funzione helper per attendere ENTER
function waitForEnter(message) {
    return new Promise((resolve) => {
        console.log('\n' + message);
        rl.question('', () => {
            console.clear();
            resolve();
        });
    });
}

// Funzione helper per trovare e cliccare elemento con selettori multipli
async function clickWithMultipleSelectors(page, selectors, timeout = 30000) {
    for (const selector of selectors) {
        try {
            await page.waitForSelector(selector, { timeout: 5000 });
            await page.click(selector);
            return true;
        } catch (e) {
            // Prova prossimo selettore
            continue;
        }
    }
    throw new Error(`Nessun selettore valido trovato: ${selectors.join(', ')}`);
}

// Funzione helper per navigare e attendere caricamento
async function navigateAndWait(page, url) {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForLoadState('networkidle');
}

// Funzione per scaricare CSV ING
async function downloadINGCSV(page) {
    console.log('Opening ING...');
    
    try {
        // Naviga alla pagina login
        await navigateAndWait(page, 'https://www.ing.it/area-riservata');
        
        console.log('Waiting ING login...');
        await waitForEnter('Login to ING and complete 2FA, then press ENTER');
        
        // Attendi che l'utente sia loggato (cerca elementi tipici della dashboard)
        console.log('Navigating to ING transactions...');
        
        // Prova diverse URL possibili per i movimenti
        const possibleUrls = [
            'https://www.ing.it/area-riservata/movimenti',
            'https://www.ing.it/area-riservata/operazioni',
            'https://www.ing.it/area-riservata/transazioni',
            'https://www.ingdirect.it/area-riservata/movimenti'
        ];
        
        let transactionsPageFound = false;
        for (const url of possibleUrls) {
            try {
                await navigateAndWait(page, url);
                // Verifica se siamo nella pagina giusta cercando elementi comuni
                const hasExportButton = await page.locator('text=/esporta|export|scarica|download/i').count() > 0;
                if (hasExportButton) {
                    transactionsPageFound = true;
                    break;
                }
            } catch (e) {
                continue;
            }
        }
        
        if (!transactionsPageFound) {
            // Prova a cercare link "Movimenti" o "Operazioni" nella pagina corrente
            const movementSelectors = [
                'a:has-text("Movimenti")',
                'a:has-text("Operazioni")',
                'a:has-text("Transazioni")',
                '[href*="movimenti"]',
                '[href*="operazioni"]',
                '[href*="transazioni"]'
            ];
            
            for (const selector of movementSelectors) {
                try {
                    await page.waitForSelector(selector, { timeout: 5000 });
                    await page.click(selector);
                    await page.waitForLoadState('networkidle');
                    break;
                } catch (e) {
                    continue;
                }
            }
        }
        
        console.log('Downloading ING CSV...');
        
        // Selettori multipli per il bottone/link export CSV
        const exportSelectors = [
            'button:has-text("Esporta")',
            'button:has-text("Export")',
            'a:has-text("Esporta")',
            'a:has-text("Export")',
            'button:has-text("Scarica CSV")',
            'a:has-text("Scarica CSV")',
            'button:has-text("Download CSV")',
            'a:has-text("Download CSV")',
            '[data-action="export"]',
            '[data-export="csv"]',
            'button[type="button"]:has-text("CSV")',
            'a[href*="csv"]',
            'a[href*="export"]'
        ];
        
        // Attendi download prima di cliccare
        const downloadPromise = page.waitForEvent('download', { timeout: 60000 });
        
        // Clicca export
        await clickWithMultipleSelectors(page, exportSelectors);
        
        // Attendi download
        const download = await downloadPromise;
        
        // Salva file
        const projectRoot = __dirname;
        const outputPath = path.join(projectRoot, 'ing.csv');
        
        // Rimuovi file esistente se presente
        if (fs.existsSync(outputPath)) {
            fs.unlinkSync(outputPath);
        }
        
        await download.saveAs(outputPath);
        console.log(`ING CSV saved to: ${outputPath}`);
        
        return true;
    } catch (error) {
        console.error('Error downloading ING CSV:', error.message);
        throw error;
    }
}

// Funzione per scaricare CSV PostePay
async function downloadPostePayCSV(page) {
    console.log('Opening PostePay...');
    
    try {
        // Naviga al portale PostePay Evolution
        const postepayUrls = [
            'https://www.postepay.it/',
            'https://www.poste.it/',
            'https://www.posteitaliane.it/'
        ];
        
        let loginPageFound = false;
        for (const url of postepayUrls) {
            try {
                await navigateAndWait(page, url);
                // Cerca link per login o area riservata
                const hasLoginLink = await page.locator('text=/accedi|login|area riservata/i').count() > 0;
                if (hasLoginLink) {
                    loginPageFound = true;
                    break;
                }
            } catch (e) {
                continue;
            }
        }
        
        if (!loginPageFound) {
            // Prova a cercare e cliccare link "Accedi" o "Login"
            const loginSelectors = [
                'a:has-text("Accedi")',
                'a:has-text("Login")',
                'a:has-text("Area Riservata")',
                '[href*="login"]',
                '[href*="accedi"]',
                'button:has-text("Accedi")'
            ];
            
            for (const selector of loginSelectors) {
                try {
                    await page.waitForSelector(selector, { timeout: 5000 });
                    await page.click(selector);
                    await page.waitForLoadState('networkidle');
                    break;
                } catch (e) {
                    continue;
                }
            }
        }
        
        console.log('Waiting PostePay login...');
        await waitForEnter('Login to Postepay Evolution and complete 2FA, then press ENTER');
        
        // Naviga alla pagina movimenti
        console.log('Navigating to PostePay transactions...');
        
        const postepayTransactionUrls = [
            'https://www.postepay.it/area-riservata/movimenti',
            'https://www.postepay.it/area-riservata/operazioni',
            'https://www.poste.it/area-riservata/movimenti',
            'https://www.posteitaliane.it/area-riservata/movimenti'
        ];
        
        let postepayTransactionsFound = false;
        for (const url of postepayTransactionUrls) {
            try {
                await navigateAndWait(page, url);
                const hasExportButton = await page.locator('text=/esporta|export|scarica|download/i').count() > 0;
                if (hasExportButton) {
                    postepayTransactionsFound = true;
                    break;
                }
            } catch (e) {
                continue;
            }
        }
        
        if (!postepayTransactionsFound) {
            // Cerca link "Movimenti" o "Operazioni"
            const movementSelectors = [
                'a:has-text("Movimenti")',
                'a:has-text("Operazioni")',
                'a:has-text("Transazioni")',
                '[href*="movimenti"]',
                '[href*="operazioni"]'
            ];
            
            for (const selector of movementSelectors) {
                try {
                    await page.waitForSelector(selector, { timeout: 5000 });
                    await page.click(selector);
                    await page.waitForLoadState('networkidle');
                    break;
                } catch (e) {
                    continue;
                }
            }
        }
        
        console.log('Downloading PostePay CSV...');
        
        // Selettori multipli per export CSV
        const exportSelectors = [
            'button:has-text("Esporta")',
            'button:has-text("Export")',
            'a:has-text("Esporta")',
            'a:has-text("Export")',
            'button:has-text("Scarica CSV")',
            'a:has-text("Scarica CSV")',
            'button:has-text("Download CSV")',
            'a:has-text("Download CSV")',
            '[data-action="export"]',
            '[data-export="csv"]',
            'button[type="button"]:has-text("CSV")',
            'a[href*="csv"]',
            'a[href*="export"]'
        ];
        
        // Attendi download
        const downloadPromise = page.waitForEvent('download', { timeout: 60000 });
        
        // Clicca export
        await clickWithMultipleSelectors(page, exportSelectors);
        
        // Attendi download
        const download = await downloadPromise;
        
        // Salva file
        const projectRoot = __dirname;
        const outputPath = path.join(projectRoot, 'postepay.csv');
        
        // Rimuovi file esistente se presente
        if (fs.existsSync(outputPath)) {
            fs.unlinkSync(outputPath);
        }
        
        await download.saveAs(outputPath);
        console.log(`PostePay CSV saved to: ${outputPath}`);
        
        return true;
    } catch (error) {
        console.error('Error downloading PostePay CSV:', error.message);
        throw error;
    }
}

// Funzione principale
async function main() {
    console.clear();
    console.log('Bank CSV Downloader - Starting...\n');
    
    let browser = null;
    
    try {
        // Avvia browser (Chromium da Playwright; su Windows funziona senza Chrome installato)
        let launchOptions = { headless: false };
        try {
            browser = await chromium.launch({ ...launchOptions, channel: 'chrome' });
        } catch (e) {
            browser = await chromium.launch(launchOptions);
        }
        
        const context = await browser.newContext({
            acceptDownloads: true,
            viewport: { width: 1280, height: 720 }
        });
        
        const page = await context.newPage();
        
        // Download ING CSV
        await downloadINGCSV(page);
        
        // Download PostePay CSV (usa stessa pagina o nuova tab)
        await downloadPostePayCSV(page);
        
        console.log('\nDone');
        console.log('Files saved:');
        console.log('  - ing.csv');
        console.log('  - postepay.csv');
        
    } catch (error) {
        console.error('\nError:', error.message);
        process.exit(1);
    } finally {
        // Chiudi browser
        if (browser) {
            await browser.close();
        }
        rl.close();
    }
}

// Esegui
main().catch((error) => {
    console.error('Fatal error:', error);
    process.exit(1);
});
