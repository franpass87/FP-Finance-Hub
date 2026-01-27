<?php
/**
 * Bank Connections Page
 * 
 * UI collegamento Open Banking (Yapily OAuth)
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Integration\OpenBanking\YapilyService;
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
        if (isset($_GET['yapily_callback']) && isset($_GET['consentToken'])) {
            self::handle_oauth_callback();
        }
        
        $setup_service = SetupService::get_instance();
        $yapily_configured = $setup_service->is_yapily_configured();
        
        $yapily = new YapilyService();
        
        // Ottieni lista banche italiane disponibili solo se le credenziali sono configurate
        $institutions = null;
        if ($yapily_configured && $yapily->is_configured()) {
            $institutions = $yapily->get_institutions('IT');
        } elseif (!$yapily_configured) {
            $institutions = new \WP_Error('not_configured', 'Credenziali Yapily non configurate nelle Impostazioni.');
        } else {
            $institutions = new \WP_Error('not_configured', 'Credenziali Yapily non valide. Verifica Application ID e Application Secret.');
        }
        
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
            <?php if (!$yapily_configured) : ?>
                <div class="fp-fh-help-banner fp-fh-help-banner-error">
                    <div class="fp-fh-help-banner-header">
                        <strong>⚠️ Credenziali Yapily Mancanti</strong>
                    </div>
                    <div class="fp-fh-help-banner-message">
                        Prima di collegare un conto bancario, devi configurare le credenziali Yapily nelle Impostazioni. 
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=yapily'); ?>">Segui la guida passo-passo</a> per ottenerle.
                    </div>
                    <div class="fp-fh-help-banner-actions">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-settings'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-sm">
                            Vai alle Impostazioni →
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=yapily'); ?>" class="fp-fh-btn fp-fh-btn-secondary fp-fh-btn-sm">
                            Apri Guida Setup →
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="fp-fh-notice fp-fh-notice-info fp-fh-mb-6">
                <div class="fp-fh-notice-icon">ℹ️</div>
                <div class="fp-fh-notice-content">
                    <div class="fp-fh-notice-title">🔗 Yapily Open Banking</div>
                    <div class="fp-fh-notice-message">Collega i tuoi conti bancari tramite Open Banking. Account gratuito per sviluppatori disponibile.</div>
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
                    
                    <?php if ($yapily_configured) : ?>
                        <div class="fp-fh-guide-tip fp-fh-mb-4">
                            <strong>🔒 Come Funziona:</strong> Quando clicchi "Collega Conto", verrai reindirizzato al sito della tua banca per autorizzare l'accesso. 
                            È un processo sicuro e autorizzato dalla banca stessa tramite Open Banking. 
                            Non salviamo mai le tue credenziali bancarie.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!is_wp_error($institutions) && !empty($institutions)) : ?>
                        <form method="post" action="" class="fp-fh-mt-4">
                            <?php wp_nonce_field('fp_finance_hub_yapily_connect'); ?>
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
                            check_admin_referer('fp_finance_hub_yapily_connect');
                            
                            $redirect_uri = admin_url('admin.php?page=fp-finance-hub-bank-connections&yapily_callback=1');
                            $callback_uri = $redirect_uri;
                            
                            $oauth_result = $yapily->create_consent(
                                sanitize_text_field($_POST['institution_id']),
                                $redirect_uri,
                                $callback_uri
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
                                <div class="fp-fh-notice-title">❌ Errore nel caricamento banche disponibili</div>
                                <div class="fp-fh-notice-message">
                                    <?php if (is_wp_error($institutions)) : ?>
                                        <strong>Dettagli errore:</strong> <?php echo esc_html($institutions->get_error_message()); ?>
                                        <br><br>
                                        <strong>Possibili cause:</strong>
                                        <ul style="margin: 10px 0; padding-left: 20px;">
                                            <li>Credenziali Yapily non valide o scadute</li>
                                            <li>Application ID o Application Secret errati</li>
                                            <li>Problema di connessione con l'API Yapily</li>
                                            <li>Account Yapily non attivo o in modalità sandbox</li>
                                        </ul>
                                        <br>
                                        <strong>Come risolvere:</strong>
                                        <ol style="margin: 10px 0; padding-left: 20px;">
                                            <li>Verifica le credenziali Yapily in <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-settings'); ?>">Impostazioni</a></li>
                                            <li>Assicurati che Application ID e Application Secret siano corretti</li>
                                            <li>Controlla che l'account Yapily sia attivo su <a href="https://console.yapily.com" target="_blank">console.yapily.com</a></li>
                                            <li>Se necessario, rigenera le credenziali su Yapily Console</li>
                                        </ol>
                                    <?php else : ?>
                                        Nessuna banca disponibile. Verifica le credenziali Yapily nelle <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-settings'); ?>">Impostazioni</a>.
                                    <?php endif; ?>
                                </div>
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
        
        // Yapily può restituire consentToken o consent_id nel callback
        $consent_token = isset($_GET['consentToken']) ? sanitize_text_field($_GET['consentToken']) : null;
        $consent_id_param = isset($_GET['consent']) ? sanitize_text_field($_GET['consent']) : null;
        
        $yapily = new YapilyService();
        
        // Prova prima con consent_id dal transient (salvato durante creazione)
        $consent_id = get_transient('fp_finance_hub_yapily_consent_' . get_current_user_id());
        
        // Se non trovato, prova con parametro URL
        if (!$consent_id && $consent_id_param) {
            $consent_id = $consent_id_param;
        }
        
        // Se ancora non trovato, usa consentToken come fallback (potrebbe essere il consent_id stesso)
        if (!$consent_id && $consent_token) {
            // Prova a usare consentToken come consent_id
            $consent_id = $consent_token;
        }
        
        if (!$consent_id) {
            wp_die('Errore: Consent ID non trovato. Riprova a collegare il conto.');
        }
        
        // Verifica che il consent sia autorizzato
        $consent = $yapily->get_consent($consent_id);
        if (is_wp_error($consent)) {
            wp_die('Errore: Impossibile verificare il consent. ' . $consent->get_error_message());
        }
        
        if (!isset($consent['status']) || $consent['status'] !== 'AUTHORIZED') {
            wp_die('Errore: Consent non autorizzato (stato: ' . ($consent['status'] ?? 'sconosciuto') . '). Riprova a collegare il conto.');
        }
        
        // Ottieni account collegati
        $accounts = $yapily->get_accounts($consent_id);
        
        if (is_wp_error($accounts) || empty($accounts)) {
            wp_die('Errore: Nessun conto trovato');
        }
        
        // Salva ogni conto nel database
        $table = $wpdb->prefix . 'fp_finance_hub_bank_connections';
        
        foreach ($accounts as $account) {
            $account_id = $account['id'] ?? null;
            if (!$account_id) {
                continue;
            }
            
            $details = $yapily->get_account_details($account_id, $consent_id);
            $balance = $yapily->get_balance($account_id, $consent_id);
            
            if (is_wp_error($details) || is_wp_error($balance)) {
                continue;
            }
            
            // Estrai informazioni account
            $account_name = $details['nickname'] ?? $details['name'] ?? null;
            $iban = $details['identifications'][0]['identification'] ?? null;
            $currency = $balance['currency'] ?? 'EUR';
            $bank_name = $details['institutionId'] ?? 'Yapily';
            
            $wpdb->insert($table, [
                'user_id' => get_current_user_id(),
                'provider' => 'yapily',
                'connection_id' => EncryptionService::encrypt($consent_id),
                'account_id' => $account_id,
                'bank_name' => $bank_name,
                'account_type' => $details['type'] ?? null,
                'account_name' => $account_name,
                'iban' => $iban,
                'currency' => $currency,
                'access_token' => '', // Yapily usa consent_id invece di access_token
                'refresh_token' => EncryptionService::encrypt($consent_id),
                'token_expires_at' => isset($consent['expiresAt']) ? date('Y-m-d H:i:s', strtotime($consent['expiresAt'])) : date('Y-m-d H:i:s', strtotime('+90 days')),
                'next_sync_at' => current_time('mysql'),
            ]);
        }
        
        delete_transient('fp_finance_hub_yapily_consent_' . get_current_user_id());
        
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
