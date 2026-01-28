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
                        <a href="#" class="fp-fh-tab <?php echo $tab === 'aruba' ? 'active' : ''; ?>" data-tab="aruba-tab">
                            Aruba
                        </a>
                    </li>
                    <li>
                        <a href="#" class="fp-fh-tab <?php echo $tab === 'bank' ? 'active' : ''; ?>" data-tab="bank-tab">
                            Conti Bancari
                        </a>
                    </li>
                </ul>
            </div>
            
            <?php if ($tab === 'aruba') : ?>
                <div id="aruba-tab" class="fp-fh-tab-content <?php echo $tab === 'aruba' ? 'active' : ''; ?>">
                    <div class="fp-fh-card">
                        <div class="fp-fh-card-header">
                            <h2 class="fp-fh-card-title">Import da Aruba</h2>
                            <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba-sync'); ?>" class="fp-fh-help-link" target="_blank">
                                📖 Guida Completa →
                            </a>
                        </div>
                        <div class="fp-fh-card-body">
                            <form method="post">
                                <?php wp_nonce_field('fp_finance_hub_aruba_sync'); ?>
                                <p>Sincronizza automaticamente le fatture emesse da Aruba Fatturazione Elettronica.</p>
                                
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
                                <?php endif; ?>
                                <div class="fp-fh-card-footer">
                                    <button type="submit" name="sync_aruba" class="fp-fh-btn fp-fh-btn-primary">
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
