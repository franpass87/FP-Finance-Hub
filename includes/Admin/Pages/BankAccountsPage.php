<?php
/**
 * Bank Accounts Page
 * 
 * Gestione conti bancari e import CSV/OFX
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Services\BankService;
use FP\FinanceHub\Import\Importer;

if (!defined('ABSPATH')) {
    exit;
}

class BankAccountsPage {
    
    /**
     * Render pagina conti bancari
     */
    public static function render() {
        $bank_service = BankService::get_instance();
        
        // Gestione upload CSV/OFX
        if (isset($_POST['import_file']) && isset($_FILES['csv_file'])) {
            self::handle_file_import();
        }
        
        $accounts = $bank_service->get_active_accounts();
        $total_balance = $bank_service->get_total_balance();
        
        ?>
        <div class="wrap fp-fh-wrapper">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p>Gestisci i tuoi conti bancari e importa i movimenti</p>
                </div>
            </div>
            
            <?php if (!empty($accounts)) : ?>
                <div class="fp-fh-card fp-fh-mb-6">
                    <div class="fp-fh-card-header">
                        <h2 class="fp-fh-card-title">💰 Saldo Totale</h2>
                    </div>
                    <div class="fp-fh-card-body">
                        <div class="fp-total-balance">
                            <?php echo esc_html(number_format($total_balance, 2, ',', '.') . ' €'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="fp-accounts-grid">
                    <?php foreach ($accounts as $account) : ?>
                        <div class="fp-account-card fp-fh-card fp-fh-account-card">
                            <div class="fp-account-card-header">
                                <h3 class="fp-account-card-name"><?php echo esc_html($account->name); ?></h3>
                            </div>
                            <div class="fp-account-card-balance">
                                <?php echo esc_html(number_format($account->current_balance, 2, ',', '.') . ' €'); ?>
                            </div>
                            <div class="fp-account-card-footer">
                                <span>Aggiornato: <?php echo esc_html($account->last_balance_date ?: 'Mai'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="fp-fh-card fp-fh-mb-6">
                    <div class="fp-fh-card-body fp-fh-text-center fp-fh-p-8">
                        <div style="font-size: 4rem; margin-bottom: var(--fp-fh-spacing-4); opacity: 0.5;">🏦</div>
                        <h3 class="fp-clients-empty-title">Nessun conto bancario configurato</h3>
                        <p class="fp-clients-empty-text">Inizia collegando un conto bancario o importando i dati manualmente.</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="fp-import-section fp-fh-card">
                <h2 class="fp-fh-card-title">📥 Import Saldi e Movimenti</h2>
                <div class="fp-fh-card-body">
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('fp_finance_hub_import'); ?>
                        <div class="fp-fh-form-group">
                            <label for="account_id" class="fp-fh-form-label">Conto</label>
                            <select name="account_id" id="account_id" class="fp-fh-select" required>
                                <?php foreach ($accounts as $account) : ?>
                                    <option value="<?php echo esc_attr($account->id); ?>">
                                        <?php echo esc_html($account->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fp-fh-form-group">
                            <label for="csv_file" class="fp-fh-form-label">File CSV/OFX</label>
                            <input type="file" name="csv_file" id="csv_file" class="fp-fh-file-input" accept=".csv,.ofx" required>
                            <p class="fp-fh-form-description">Formati supportati: CSV PostePay, CSV ING, OFX</p>
                        </div>
                        <div class="fp-fh-card-footer">
                            <button type="submit" name="import_file" class="fp-fh-btn fp-fh-btn-primary">
                                Importa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="fp-open-banking-section fp-fh-card">
                <div class="fp-fh-card-body">
                    <h2 class="fp-fh-card-title">🔗 Collegamento Automatico (GoCardless)</h2>
                    <p>Collega i tuoi conti bancari automaticamente tramite Open Banking (gratuito).</p>
                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-connections'); ?>" class="fp-fh-btn fp-fh-btn-primary">
                        Collega Conto Bancario
                    </a>
                </div>
            </div>
        </div>
        
        <?php self::render_toast_script(); ?>
        <?php
    }
    
    /**
     * Gestisce import file CSV/OFX
     */
    private static function handle_file_import() {
        check_admin_referer('fp_finance_hub_import');
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die('Errore upload file');
        }
        
        // Verifica capability
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        // Validazione tipo file
        $allowed_types = ['text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/ofx', 'text/xml', 'application/xml'];
        $allowed_extensions = ['csv', 'ofx'];
        $file_name = sanitize_file_name($_FILES['csv_file']['name']);
        $file_type = $_FILES['csv_file']['type'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Verifica estensione
        if (!in_array($file_extension, $allowed_extensions)) {
            wp_die('Formato file non supportato. Utilizzare CSV o OFX.');
        }
        
        // Verifica dimensione file (max 10MB)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($_FILES['csv_file']['size'] > $max_size) {
            wp_die('File troppo grande. Dimensione massima: 10MB.');
        }
        
        $account_id = absint($_POST['account_id']);
        $file_path = $_FILES['csv_file']['tmp_name'];
        
        // Verifica che il file sia dentro directory temporanea di upload (sicurezza path traversal)
        $upload_dir = wp_upload_dir();
        $tmp_dir = sys_get_temp_dir();
        $real_file_path = realpath($file_path);
        $real_tmp_dir = realpath($tmp_dir);
        
        if (!$real_file_path || strpos($real_file_path, $real_tmp_dir) !== 0) {
            wp_die('Percorso file non valido.');
        }
        
        $importer = new Importer();
        $result = $importer->import_file($account_id, $file_path, 'auto');
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        // Redirect con messaggio successo
        wp_redirect(add_query_arg([
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'import_success' => '1',
        ], admin_url('admin.php?page=fp-finance-hub-bank-accounts')));
        exit;
    }
    
    /**
     * Render script toast dopo import
     */
    private static function render_toast_script() {
        if (isset($_GET['import_success'])) {
            $imported = absint($_GET['imported'] ?? 0);
            $skipped = absint($_GET['skipped'] ?? 0);
            ?>
            <script>
            jQuery(document).ready(function($) {
                if (typeof fpToast !== 'undefined') {
                    var message = '<?php echo esc_js(sprintf(__('Import completato: %d movimenti importati, %d saltati.', 'fp-finance-hub'), $imported, $skipped)); ?>';
                    fpToast.success(message);
                    // Rimuovi parametri dall'URL
                    if (window.history && window.history.replaceState) {
                        var url = new URL(window.location);
                        url.searchParams.delete('import_success');
                        url.searchParams.delete('imported');
                        url.searchParams.delete('skipped');
                        window.history.replaceState({}, document.title, url);
                    }
                }
            });
            </script>
            <?php
        }
    }
}
