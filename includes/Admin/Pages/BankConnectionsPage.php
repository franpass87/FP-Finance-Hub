<?php
/**
 * Bank Connections Page
 * 
 * UI collegamento Open Banking (GoCardless Bank Account Data OAuth)
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Integration\OpenBanking\NordigenService;
use FP\FinanceHub\Integration\OpenBanking\EncryptionService;
use FP\FinanceHub\Services\SetupService;

if (!defined('ABSPATH')) {
    exit;
}

class BankConnectionsPage {
    
    /**
     * Render pagina collegamento conti
     */
    public static function render() {
        // Handle OAuth callback
        if (isset($_GET['nordigen_callback']) && isset($_GET['ref'])) {
            self::handle_oauth_callback();
        }
        
        $setup_service = SetupService::get_instance();
        $nordigen_configured = $setup_service->is_nordigen_configured();
        
        $nordigen = new NordigenService();
        
        // Ottieni lista banche italiane disponibili
        $institutions = $nordigen->get_institutions('IT');
        
        // Ottieni connessioni esistenti
        $connections = self::get_user_connections();
        
        ?>
        <div class="wrap fp-fh-wrapper">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1>Collega Conti Bancari (GRATUITO)</h1>
                    <p>Collega i tuoi conti bancari tramite Open Banking</p>
                </div>
            </div>
            
            <!-- Help Banner se credenziali mancanti -->
            <?php if (!$nordigen_configured) : ?>
                <div class="fp-fh-help-banner fp-fh-help-banner-error">
                    <div class="fp-fh-help-banner-header">
                        <strong>⚠️ Credenziali GoCardless Mancanti</strong>
                    </div>
                    <div class="fp-fh-help-banner-message">
                        Prima di collegare un conto bancario, devi configurare le credenziali GoCardless Bank Account Data nelle Impostazioni. 
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=nordigen'); ?>">Segui la guida passo-passo</a> per ottenerle gratuitamente.
                    </div>
                    <div class="fp-fh-help-banner-actions">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-settings'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-sm">
                            Vai alle Impostazioni →
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=nordigen'); ?>" class="fp-fh-btn fp-fh-btn-secondary fp-fh-btn-sm">
                            Apri Guida Setup →
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="fp-fh-notice fp-fh-notice-info fp-fh-mb-6">
                <div class="fp-fh-notice-icon">ℹ️</div>
                <div class="fp-fh-notice-content">
                    <div class="fp-fh-notice-title">🆓 GoCardless Bank Account Data Gratuito</div>
                    <div class="fp-fh-notice-message">Sincronizzazione automatica fino a 4 volte al giorno, completamente gratuita per sempre!</div>
                </div>
            </div>
            
            <div class="fp-fh-card fp-fh-mb-6">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">Collega Nuovo Conto</h2>
                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=bank-connection'); ?>" class="fp-fh-help-link" target="_blank">
                        📖 Guida Completa →
                    </a>
                </div>
                <div class="fp-fh-card-body">
                    <p>Seleziona la tua banca per iniziare il collegamento:</p>
                    
                    <?php if ($nordigen_configured) : ?>
                        <div class="fp-fh-guide-tip fp-fh-mb-4">
                            <strong>🔒 Come Funziona:</strong> Quando clicchi "Collega Conto", verrai reindirizzato al sito della tua banca per autorizzare l'accesso. 
                            È un processo sicuro e autorizzato dalla banca stessa tramite Open Banking. 
                            Non salviamo mai le tue credenziali bancarie.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!is_wp_error($institutions) && !empty($institutions)) : ?>
                        <form method="post" action="" class="fp-fh-mt-4">
                            <?php wp_nonce_field('fp_finance_hub_nordigen_connect'); ?>
                            <div class="fp-fh-form-group">
                                <label for="institution_id" class="fp-fh-form-label">Banca</label>
                                <select name="institution_id" id="institution_id" class="fp-fh-select" required>
                                    <option value="">-- Seleziona Banca --</option>
                                    <?php foreach ($institutions as $inst) : ?>
                                        <option value="<?php echo esc_attr($inst['id']); ?>">
                                            <?php echo esc_html($inst['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fp-fh-card-footer">
                                <button type="submit" class="fp-fh-btn fp-fh-btn-primary">
                                    🔗 Collega Conto
                                </button>
                            </div>
                        </form>
                        
                        <?php if (isset($_POST['institution_id'])) : ?>
                            <?php
                            check_admin_referer('fp_finance_hub_nordigen_connect');
                            
                            $redirect_uri = admin_url('admin.php?page=fp-finance-hub-bank-connections&nordigen_callback=1');
                            $oauth_result = $nordigen->get_oauth_url(
                                sanitize_text_field($_POST['institution_id']),
                                $redirect_uri
                            );
                            
                            if (!is_wp_error($oauth_result) && isset($oauth_result['url'])) {
                                wp_redirect($oauth_result['url']);
                                exit;
                            } else {
                                echo '<div class="fp-fh-notice fp-fh-notice-error fp-fh-mt-4">';
                                echo '<div class="fp-fh-notice-content">';
                                echo '<div class="fp-fh-notice-message">Errore: ' . esc_html(is_wp_error($oauth_result) ? $oauth_result->get_error_message() : 'Impossibile ottenere URL OAuth') . '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                            ?>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="fp-fh-notice fp-fh-notice-error">
                            <div class="fp-fh-notice-content">
                                <div class="fp-fh-notice-message">Errore nel caricamento banche disponibili.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($connections)) : ?>
                <div class="fp-fh-card">
                    <div class="fp-fh-card-header">
                        <h2 class="fp-fh-card-title">Conti Collegati</h2>
                    </div>
                    <div class="fp-fh-card-body">
                        <div class="fp-fh-table-wrapper">
                            <table class="fp-fh-table fp-fh-table-striped">
                                <thead>
                                    <tr>
                                        <th>Banca</th>
                                        <th>Conto</th>
                                        <th>IBAN</th>
                                        <th>Ultima Sync</th>
                                        <th class="fp-fh-table-actions">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($connections as $conn) : ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($conn->bank_name); ?></strong></td>
                                            <td><?php echo esc_html($conn->account_name); ?></td>
                                            <td><?php echo esc_html($conn->iban ?: '-'); ?></td>
                                            <td><?php echo esc_html($conn->last_sync_at ?: 'Mai'); ?></td>
                                            <td class="fp-fh-table-actions">
                                                <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-connections&action=sync&id=' . $conn->id); ?>" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-ghost">
                                                    🔄 Sincronizza Ora
                                                </a>
                                                <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-connections&action=disconnect&id=' . $conn->id); ?>" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-error" data-confirm="Sei sicuro di voler disconnettere questo conto?">
                                                    ❌ Disconnetti
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle OAuth callback
     */
    private static function handle_oauth_callback() {
        global $wpdb;
        
        $requisition_id = get_transient('fp_finance_hub_nordigen_requisition_' . get_current_user_id());
        
        if (!$requisition_id) {
            wp_die('Errore: Requisition ID non trovato');
        }
        
        $nordigen = new NordigenService();
        
        // Ottieni account collegati
        $account_ids = $nordigen->get_accounts($requisition_id);
        
        if (is_wp_error($account_ids) || empty($account_ids)) {
            wp_die('Errore: Nessun conto trovato');
        }
        
        // Salva ogni conto nel database
        $table = $wpdb->prefix . 'fp_finance_hub_bank_connections';
        
        foreach ($account_ids as $account_id) {
            $details = $nordigen->get_account_details($account_id);
            $balance = $nordigen->get_balance($account_id);
            
            if (is_wp_error($details) || is_wp_error($balance)) {
                continue;
            }
            
            $wpdb->insert($table, [
                'user_id' => get_current_user_id(),
                'provider' => 'nordigen',
                'connection_id' => EncryptionService::encrypt($requisition_id),
                'account_id' => $account_id,
                'bank_name' => $details['institutionId'] ?? 'GoCardless',
                'account_type' => $details['cashAccountType'] ?? null,
                'account_name' => $details['name'] ?? null,
                'iban' => $details['iban'] ?? null,
                'currency' => $balance['balanceAmount']['currency'] ?? 'EUR',
                'access_token' => '', // GoCardless non usa access_token per account
                'refresh_token' => EncryptionService::encrypt($requisition_id),
                'token_expires_at' => date('Y-m-d H:i:s', strtotime('+90 days')),
                'next_sync_at' => current_time('mysql'),
            ]);
        }
        
        delete_transient('fp_finance_hub_nordigen_requisition_' . get_current_user_id());
        
        wp_redirect(admin_url('admin.php?page=fp-finance-hub-bank-connections&connected=1'));
        exit;
    }
    
    /**
     * Ottieni conti collegati utente
     */
    private static function get_user_connections() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_connections';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND is_active = 1",
            get_current_user_id()
        ));
    }
}
