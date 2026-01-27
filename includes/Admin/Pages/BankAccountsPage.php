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
        
        // Gestione creazione conto
        if (isset($_POST['create_account'])) {
            self::handle_create_account();
        }
        
        // Gestione upload CSV/OFX
        if (isset($_POST['import_file']) && isset($_FILES['csv_file'])) {
            self::handle_file_import();
        }
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
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
                        <p class="fp-clients-empty-text">Crea il tuo primo conto bancario per iniziare a importare i movimenti.</p>
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-accounts&action=add'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-mt-4">
                            ➕ Crea Primo Conto Bancario
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'add') : ?>
                <div class="fp-fh-card fp-fh-mb-6">
                    <div class="fp-fh-card-header">
                        <h2 class="fp-fh-card-title">➕ Aggiungi Nuovo Conto Bancario</h2>
                    </div>
                    <div class="fp-fh-card-body">
                        <form method="post">
                            <?php wp_nonce_field('fp_finance_hub_create_account'); ?>
                            <div class="fp-fh-form-group">
                                <label for="account_name" class="fp-fh-form-label">Nome Conto *</label>
                                <input type="text" name="account_name" id="account_name" class="fp-fh-input" 
                                       placeholder="es. ING Direct, PostePay Evolution" required>
                                <p class="fp-fh-form-description">Un nome descrittivo per identificare questo conto</p>
                            </div>
                            <div class="fp-fh-form-group">
                                <label for="iban" class="fp-fh-form-label">IBAN</label>
                                <input type="text" name="iban" id="iban" class="fp-fh-input" 
                                       placeholder="IT60 X054 2811 1010 0000 0123 456" maxlength="34">
                                <p class="fp-fh-form-description">IBAN del conto (opzionale ma consigliato)</p>
                            </div>
                            <div class="fp-fh-form-group">
                                <label for="bank_name" class="fp-fh-form-label">Banca</label>
                                <input type="text" name="bank_name" id="bank_name" class="fp-fh-input" 
                                       placeholder="es. ING Direct, PostePay, Unicredit">
                                <p class="fp-fh-form-description">Nome della banca (opzionale)</p>
                            </div>
                            <div class="fp-fh-form-group">
                                <label for="initial_balance" class="fp-fh-form-label">Saldo Iniziale</label>
                                <input type="number" name="initial_balance" id="initial_balance" class="fp-fh-input" 
                                       step="0.01" placeholder="0.00">
                                <p class="fp-fh-form-description">Saldo attuale del conto (verrà aggiornato automaticamente dopo il primo import)</p>
                            </div>
                            <div class="fp-fh-card-footer">
                                <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-accounts'); ?>" class="fp-fh-btn fp-fh-btn-secondary">
                                    Annulla
                                </a>
                                <button type="submit" name="create_account" class="fp-fh-btn fp-fh-btn-primary">
                                    ➕ Crea Conto
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="fp-import-section fp-fh-card fp-fh-mb-6">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">📥 Import Movimenti da File CSV/OFX</h2>
                    <span class="fp-fh-badge fp-fh-badge-success">⭐ Soluzione Consigliata</span>
                </div>
                <div class="fp-fh-card-body">
                    <div class="fp-fh-guide-info fp-fh-mb-4" style="background: #e7f3ff; border-color: #b3d9ff; padding: 12px; border-radius: 4px;">
                        <strong>✅ Funziona Subito - Nessuna Configurazione Complessa</strong>
                        <p class="fp-fh-mt-2">Scarica il file CSV/OFX dal tuo home banking (ING o PostePay) e importalo qui. Il sistema riconosce automaticamente il formato e importa tutti i movimenti.</p>
                        <ul class="fp-fh-mt-2" style="margin-left: 20px;">
                            <li><strong>PostePay Evolution:</strong> Esporta CSV dal sito PostePay</li>
                            <li><strong>ING Direct:</strong> Esporta CSV o OFX dall'area clienti ING</li>
                            <li><strong>Altri conti:</strong> Supporta formato OFX standard</li>
                        </ul>
                    </div>
                    
                    <?php if (empty($accounts)) : ?>
                        <div class="fp-fh-guide-warning fp-fh-mb-4" style="background: #fff3cd; border-color: #ffeaa7; padding: 12px; border-radius: 4px;">
                            <strong>⚠️ Nessun conto configurato</strong>
                            <p class="fp-fh-mt-2">Prima di importare, devi creare un conto bancario. Usa il pulsante qui sotto per aggiungere un nuovo conto.</p>
                            <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-accounts&action=add'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-mt-2">
                                ➕ Aggiungi Nuovo Conto
                            </a>
                        </div>
                    <?php else : ?>
                        <form method="post" enctype="multipart/form-data">
                            <?php wp_nonce_field('fp_finance_hub_import'); ?>
                            <div class="fp-fh-form-group">
                                <label for="account_id" class="fp-fh-form-label">Conto Bancario</label>
                                <select name="account_id" id="account_id" class="fp-fh-select" required>
                                    <?php foreach ($accounts as $account) : ?>
                                        <option value="<?php echo esc_attr($account->id); ?>">
                                            <?php echo esc_html($account->name); ?> 
                                            <?php if ($account->iban) : ?>
                                                (<?php echo esc_html($account->iban); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fp-fh-form-group">
                                <label for="csv_file" class="fp-fh-form-label">File CSV/OFX</label>
                                <input type="file" name="csv_file" id="csv_file" class="fp-fh-file-input" accept=".csv,.ofx" required>
                                <p class="fp-fh-form-description">
                                    <strong>Formati supportati:</strong> CSV PostePay, CSV ING Direct, OFX standard
                                    <br>
                                    <strong>Come ottenere il file:</strong>
                                    <ul style="margin: 8px 0 0 20px; font-size: 13px;">
                                        <li><strong>PostePay:</strong> Area Clienti → Movimenti → Esporta CSV</li>
                                        <li><strong>ING:</strong> Area Clienti → Conti → Esporta movimenti (CSV o OFX)</li>
                                    </ul>
                                </p>
                            </div>
                            <div class="fp-fh-card-footer">
                                <button type="submit" name="import_file" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-lg">
                                    📥 Importa Movimenti
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="fp-open-banking-section fp-fh-card">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">🔗 Collegamento Automatico (Yapily - Opzionale)</h2>
                </div>
                <div class="fp-fh-card-body">
                    <div class="fp-fh-guide-info fp-fh-mb-4" style="background: #f8f9fa; border-color: #dee2e6; padding: 12px; border-radius: 4px;">
                        <p><strong>⚠️ Nota:</strong> Yapily Open Banking richiede configurazione e potrebbe non essere disponibile per tutte le banche italiane in modalità sandbox.</p>
                        <p class="fp-fh-mt-2">Per ING e PostePay, <strong>l'import CSV/OFX è la soluzione più semplice e immediata</strong>.</p>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-connections'); ?>" class="fp-fh-btn fp-fh-btn-secondary">
                        Collega Conto via Yapily (Opzionale)
                    </a>
                </div>
            </div>
        </div>
        
        <?php self::render_toast_script(); ?>
        <?php
    }
    
    /**
     * Gestisce creazione nuovo conto bancario
     */
    private static function handle_create_account() {
        check_admin_referer('fp_finance_hub_create_account');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        $account_name = sanitize_text_field($_POST['account_name'] ?? '');
        $iban = sanitize_text_field($_POST['iban'] ?? '');
        $bank_name = sanitize_text_field($_POST['bank_name'] ?? '');
        $initial_balance = floatval($_POST['initial_balance'] ?? 0);
        
        if (empty($account_name)) {
            wp_die('Nome conto obbligatorio');
        }
        
        $bank_service = BankService::get_instance();
        
        $account_data = [
            'name' => $account_name,
            'iban' => $iban,
            'bank_name' => $bank_name,
            'current_balance' => $initial_balance,
            'currency' => 'EUR',
            'is_active' => true,
        ];
        
        $result = $bank_service->create_account($account_data);
        
        if (is_wp_error($result)) {
            wp_die('Errore creazione conto: ' . $result->get_error_message());
        }
        
        wp_redirect(add_query_arg([
            'account_created' => '1',
            'account_id' => $result,
        ], admin_url('admin.php?page=fp-finance-hub-bank-accounts')));
        exit;
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
     * Render script toast dopo import o creazione conto
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
        
        if (isset($_GET['account_created'])) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                if (typeof fpToast !== 'undefined') {
                    fpToast.success('<?php echo esc_js(__('Conto bancario creato con successo! Ora puoi importare i movimenti.', 'fp-finance-hub')); ?>');
                    // Rimuovi parametri dall'URL
                    if (window.history && window.history.replaceState) {
                        var url = new URL(window.location);
                        url.searchParams.delete('account_created');
                        url.searchParams.delete('account_id');
                        window.history.replaceState({}, document.title, url);
                    }
                }
            });
            </script>
            <?php
        }
    }
}
