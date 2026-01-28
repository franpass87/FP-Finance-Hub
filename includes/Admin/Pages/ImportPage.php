<?php
/**
 * Import Page
 * 
 * Import dati (Aruba, CSV/OFX, manuale)
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Integration\Aruba\ArubaSync;
use FP\FinanceHub\Services\SetupService;

if (!defined('ABSPATH')) {
    exit;
}

class ImportPage {
    
    public static function render() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'aruba';
        $setup_service = SetupService::get_instance();
        $aruba_configured = $setup_service->is_aruba_configured();
        
        ?>
        <div class="wrap fp-fh-wrapper">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p>Importa dati da Aruba e conti bancari</p>
                </div>
            </div>
            
            <!-- Help Banner se credenziali Aruba mancanti e tab aruba attivo -->
            <?php if ($tab === 'aruba' && !$aruba_configured) : ?>
                <div class="fp-fh-help-banner fp-fh-help-banner-error">
                    <div class="fp-fh-help-banner-header">
                        <strong>⚠️ Credenziali Aruba Mancanti</strong>
                    </div>
                    <div class="fp-fh-help-banner-message">
                        Prima di sincronizzare le fatture da Aruba, devi configurare le credenziali nelle Impostazioni. 
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba'); ?>">Segui la guida passo-passo</a> per ottenerle.
                    </div>
                    <div class="fp-fh-help-banner-actions">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-settings'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-sm">
                            Vai alle Impostazioni →
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba'); ?>" class="fp-fh-btn fp-fh-btn-secondary fp-fh-btn-sm">
                            Apri Guida Setup →
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="fp-fh-tabs" data-tab-group="import">
                <ul class="fp-fh-tabs-list">
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=fp-finance-hub-import&tab=aruba')); ?>" class="fp-fh-tab <?php echo $tab === 'aruba' ? 'active' : ''; ?>">
                            Aruba
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=fp-finance-hub-import&tab=bank')); ?>" class="fp-fh-tab <?php echo $tab === 'bank' ? 'active' : ''; ?>">
                            Conti Bancari
                        </a>
                    </li>
                </ul>
            </div>
            
            <?php if ($tab === 'aruba') : ?>
                <div id="aruba-tab" class="fp-fh-tab-content <?php echo $tab === 'aruba' ? 'active' : ''; ?>">
                    <!-- Sincronizzazione Automatica -->
                    <div class="fp-fh-card fp-fh-mb-6">
                        <div class="fp-fh-card-header">
                            <h2 class="fp-fh-card-title">Sincronizzazione Automatica da Aruba</h2>
                            <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba-sync'); ?>" class="fp-fh-help-link" target="_blank">
                                📖 Guida Completa →
                            </a>
                        </div>
                        <form method="post">
                            <?php wp_nonce_field('fp_finance_hub_aruba_sync'); ?>
                            <div class="fp-fh-card-body">
                                <p>Sincronizza automaticamente le fatture emesse da Aruba Fatturazione Elettronica tramite API.</p>
                                
                                <?php if ($aruba_configured) : ?>
                                    <div class="fp-fh-guide-info fp-fh-mb-4">
                                        <strong>📄 Cosa viene sincronizzato:</strong>
                                        <ul class="fp-fh-list fp-fh-list-check fp-fh-mt-2">
                                            <li>Fatture emesse con tutti i dettagli</li>
                                            <li>Anagrafica clienti (nome, P.IVA, email, telefono)</li>
                                            <li>Stati fatture (Inviata, Accettata, Rifiutata, ecc.)</li>
                                            <li>Importi, IVA, date di emissione e scadenza</li>
                                        </ul>
                                        <p class="fp-fh-text-sm fp-fh-mt-2">
                                            <strong>💡 Nota:</strong> Dopo la prima sincronizzazione manuale, il plugin sincronizza automaticamente ogni giorno. 
                                            Le fatture già importate vengono aggiornate, non duplicate.
                                        </p>
                                    </div>
                                <?php else : ?>
                                    <div class="fp-fh-alert fp-fh-alert-warning">
                                        <strong>⚠️ Richiede Account Premium:</strong> La sincronizzazione automatica richiede un account Premium Aruba o un account base collegato a Premium tramite delega.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="fp-fh-card-footer">
                                <button type="submit" name="sync_aruba" class="fp-fh-btn fp-fh-btn-primary" <?php echo !$aruba_configured ? 'disabled' : ''; ?>>
                                    🔄 Sincronizza Fatture da Aruba
                                </button>
                            </div>
                        </form>
                            
                            <?php
                            if (isset($_POST['sync_aruba'])) {
                                check_admin_referer('fp_finance_hub_aruba_sync');
                                
                                $aruba_sync = new ArubaSync();
                                $result = $aruba_sync->sync_invoices();
                                
                                if (!is_wp_error($result)) {
                                    echo '<div class="fp-fh-notice fp-fh-notice-success fp-fh-mt-4">';
                                    echo '<div class="fp-fh-notice-content">';
                                    echo '<div class="fp-fh-notice-message">Sincronizzate ' . $result['imported'] . ' fatture, aggiornate ' . $result['updated'] . '.</div>';
                                    echo '</div>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="fp-fh-notice fp-fh-notice-error fp-fh-mt-4">';
                                    echo '<div class="fp-fh-notice-content">';
                                    echo '<div class="fp-fh-notice-message">' . esc_html($result->get_error_message()) . '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            }
                            ?>
                    </div>
                    
                    <!-- Import Manuale File XML -->
                    <div class="fp-fh-card">
                        <div class="fp-fh-card-header">
                            <h2 class="fp-fh-card-title">Import Manuale File XML</h2>
                        </div>
                        <div class="fp-fh-card-body">
                            <p><strong>Soluzione per account base:</strong> Esporta le fatture dal pannello Aruba e importale manualmente.</p>
                            <ol class="fp-fh-list fp-fh-mt-3" style="margin-left: 1.5rem;">
                                <li><strong>Esporta dal pannello Aruba:</strong> Accedi a <a href="https://fatturazioneelettronica.aruba.it" target="_blank">fatturazioneelettronica.aruba.it</a>, vai su "Fatture Inviate" e scarica i file XML delle fatture (singoli file o ZIP con più fatture).</li>
                                <li><strong>Importa qui:</strong> Carica i file XML qui sotto. Il plugin estrae automaticamente tutti i dati (fatture, clienti, importi, IVA).</li>
                            </ol>
                            <p class="fp-fh-mt-4 fp-fh-mb-0">
                                <strong>Formati supportati:</strong> 
                                <ul class="fp-fh-list fp-fh-mt-2" style="margin-left: 1.5rem;">
                                    <li>File XML singoli (.xml, .xml.p7m)</li>
                                    <li>Archivi ZIP contenenti più file XML</li>
                                    <li>File Excel riepilogo (.xls, .xlsx) - <strong>Richiede PhpSpreadsheet</strong></li>
                                </ul>
                            </p>
                            <div class="fp-fh-alert fp-fh-alert-info fp-fh-mt-3">
                                <strong>📋 Per file Excel:</strong> Se esporti un riepilogo Excel da Aruba, installa PhpSpreadsheet eseguendo 
                                <code>composer require phpoffice/phpspreadsheet</code> nella cartella del plugin. 
                                In alternativa, esporta da Excel come CSV e importa il CSV.
                            </div>
                        </div>
                        <form method="post" enctype="multipart/form-data">
                            <?php wp_nonce_field('fp_finance_hub_aruba_import_xml'); ?>
                            <div class="fp-fh-card-body">
                                <div class="fp-fh-form-group">
                                    <label for="aruba_xml_files" class="fp-fh-label">
                                        <strong>Seleziona file XML o ZIP:</strong>
                                    </label>
                                    <input type="file" 
                                           id="aruba_xml_files" 
                                           name="aruba_xml_files[]" 
                                           accept=".xml,.p7m,.zip,.xls,.xlsx"
                                           multiple
                                           class="fp-fh-input"
                                           required>
                                    <p class="fp-fh-text-sm fp-fh-mt-2" style="color: #666;">
                                        Puoi selezionare più file contemporaneamente. Dimensione massima: 50MB per file.
                                    </p>
                                </div>
                            </div>
                            <div class="fp-fh-card-footer">
                                <button type="submit" name="import_aruba_xml" class="fp-fh-btn fp-fh-btn-primary">
                                    📥 Importa File XML
                                </button>
                            </div>
                        </form>
                        
                        <?php
                        if (isset($_POST['import_aruba_xml'])) {
                            check_admin_referer('fp_finance_hub_aruba_import_xml');
                            
                            require_once __DIR__ . '/../../Integration/Aruba/ArubaManualImporter.php';
                            $importer = new \FP\FinanceHub\Integration\Aruba\ArubaManualImporter();
                            $result = $importer->import_files($_FILES['aruba_xml_files'] ?? []);
                            
                            if (!is_wp_error($result)) {
                                echo '<div class="fp-fh-notice fp-fh-notice-success fp-fh-mt-4">';
                                echo '<div class="fp-fh-notice-content">';
                                echo '<div class="fp-fh-notice-message">';
                                echo '<strong>✅ Import completato!</strong><br>';
                                echo 'Fatture importate: ' . $result['imported'] . '<br>';
                                echo 'Fatture aggiornate: ' . $result['updated'] . '<br>';
                                if ($result['errors'] > 0) {
                                    echo 'Errori: ' . $result['errors'] . '<br>';
                                }
                                if (!empty($result['error_details'])) {
                                    echo '<details class="fp-fh-mt-2"><summary>Dettagli errori</summary><ul>';
                                    foreach ($result['error_details'] as $error) {
                                        echo '<li>' . esc_html($error) . '</li>';
                                    }
                                    echo '</ul></details>';
                                }
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            } else {
                                echo '<div class="fp-fh-notice fp-fh-notice-error fp-fh-mt-4">';
                                echo '<div class="fp-fh-notice-content">';
                                echo '<div class="fp-fh-notice-message">' . esc_html($result->get_error_message()) . '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            <?php elseif ($tab === 'bank') : ?>
                <div id="bank-tab" class="fp-fh-tab-content <?php echo $tab === 'bank' ? 'active' : ''; ?>">
                    <div class="fp-fh-card fp-fh-mb-6">
                        <div class="fp-fh-card-header">
                            <h2 class="fp-fh-card-title">Import Conti Bancari (ING, PostePay)</h2>
                        </div>
                        <div class="fp-fh-card-body">
                            <p><strong>Soluzione consigliata per privati:</strong> Import CSV/OFX (gratuito, funziona con tutte le banche).</p>
                            <ol class="fp-fh-list fp-fh-mt-3" style="margin-left: 1.5rem;">
                                <li><strong>Scarica i CSV</strong> – Esegui lo script Playwright nella cartella <code>bank-automation/</code> del plugin: <code>node bank.js</code>. Fai login manuale a ING e PostePay quando richiesto; i file <code>ing.csv</code> e <code>postepay.csv</code> verranno scaricati automaticamente.</li>
                                <li><strong>Importa nel plugin</strong> – Vai alla pagina <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-accounts'); ?>">Conti Bancari</a>, seleziona il conto (o creane uno per ING e uno per PostePay) e carica i file CSV.</li>
                            </ol>
                            <p class="fp-fh-mt-4 fp-fh-mb-0">Il plugin riconosce automaticamente il formato (ING o PostePay), importa movimenti e saldi e applica la categorizzazione.</p>
                            <div class="fp-fh-card-footer fp-fh-mt-4">
                                <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-accounts'); ?>" class="fp-fh-btn fp-fh-btn-primary">Vai a Conti Bancari e Import CSV</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
