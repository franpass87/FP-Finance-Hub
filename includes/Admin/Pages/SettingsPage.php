<?php
/**
 * Settings Page
 * 
 * Impostazioni plugin
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Services\CategorizationEngine;
use FP\FinanceHub\Services\SetupService;
use FP\FinanceHub\Database\Models\CategorizationLearning;
use FP\FinanceHub\Database\Models\CategorizationRule;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage {
    
    public static function render() {
        // Gestione salvataggio impostazioni
        if (isset($_POST['save_settings'])) {
            self::save_settings();
        }
        
        $setup_service = SetupService::get_instance();
        $aruba_username = get_option('fp_finance_hub_aruba_username', '');
        $aruba_password = get_option('fp_finance_hub_aruba_password', '');
        $aruba_environment = get_option('fp_finance_hub_aruba_environment', 'production');
        $aruba_country_sender = get_option('fp_finance_hub_aruba_country_sender', '');
        $aruba_vatcode_sender = get_option('fp_finance_hub_aruba_vatcode_sender', '');
        $aruba_configured = $setup_service->is_aruba_configured();
        
        ?>
        <div class="wrap fp-fh-wrapper">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p>Configura le integrazioni e le impostazioni del plugin</p>
                </div>
            </div>
            
            <!-- Help Banner -->
            <?php if (!$aruba_configured) : ?>
                <div class="fp-fh-help-banner fp-fh-help-banner-warning">
                    <div class="fp-fh-help-banner-header">
                        <strong>💡 Serve Aiuto?</strong>
                    </div>
                    <div class="fp-fh-help-banner-message">
                        Se non sai come configurare le credenziali, usa la <strong>Guida Setup</strong> per istruzioni passo-passo.
                    </div>
                    <div class="fp-fh-help-banner-actions">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-sm">
                            Apri Guida Setup →
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="fp-fh-card">
            <form method="post" action="" id="fp-fh-settings-form">
                <?php wp_nonce_field('fp_finance_hub_settings'); ?>
                
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">Integrazione Aruba</h2>
                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba'); ?>" class="fp-fh-help-link" target="_blank">
                        📖 Guida Completa →
                    </a>
                </div>
                <div class="fp-fh-card-body">
                    <?php if (!$aruba_configured) : ?>
                        <div class="fp-fh-guide-tip fp-fh-mb-4">
                            <strong>💡 Non sai dove trovare le credenziali?</strong> 
                            <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba'); ?>">Segui la guida passo-passo</a>
                        </div>
                    <?php endif; ?>
                    
                        <div class="fp-fh-form-group">
                            <label for="aruba_username" class="fp-fh-form-label">
                                Username
                                <span class="fp-fh-tooltip">
                                    <span class="fp-fh-help-icon" title="Lo Username è l'indirizzo email con cui ti registri ad Aruba o il codice utente nel profilo.">?</span>
                                    <span class="fp-fh-tooltip-content">Lo Username è l'indirizzo email che usi per accedere ad Aruba Fatturazione Elettronica, oppure il codice utente visibile nel tuo profilo.</span>
                                </span>
                            </label>
                            <input type="text" name="aruba_username" id="aruba_username" 
                                   value="<?php echo esc_attr($aruba_username); ?>" class="fp-fh-input" required>
                            <p class="fp-fh-form-description">Inserisci il tuo username Aruba (email o codice utente)</p>
                        </div>
                        <div class="fp-fh-form-group">
                            <label for="aruba_password" class="fp-fh-form-label">
                                Password
                                <span class="fp-fh-tooltip">
                                    <span class="fp-fh-help-icon" title="La password è quella che usi per accedere al pannello web di Aruba Fatturazione Elettronica.">?</span>
                                    <span class="fp-fh-tooltip-content">La password è quella che usi per accedere al pannello web di Aruba Fatturazione Elettronica (https://fatturazioneelettronica.aruba.it).</span>
                                </span>
                            </label>
                            <input type="password" name="aruba_password" id="aruba_password" 
                                   value="<?php echo esc_attr($aruba_password); ?>" class="fp-fh-input" required>
                            <p class="fp-fh-form-description">Inserisci la tua password Aruba (stessa del pannello web)</p>
                        </div>
                        <div class="fp-fh-form-group">
                            <label for="aruba_environment" class="fp-fh-form-label">
                                Ambiente
                                <span class="fp-fh-tooltip">
                                    <span class="fp-fh-help-icon" title="Scegli 'Produzione' per usare le API reali, 'Demo' per testare in ambiente di prova.">?</span>
                                    <span class="fp-fh-tooltip-content">Ambiente Produzione: usa le API reali con i tuoi dati. Ambiente Demo: ambiente di test temporaneo per sviluppatori.</span>
                                </span>
                            </label>
                            <select name="aruba_environment" id="aruba_environment" class="fp-fh-select">
                                <option value="production" <?php selected($aruba_environment, 'production'); ?>>Produzione</option>
                                <option value="demo" <?php selected($aruba_environment, 'demo'); ?>>Demo (Test)</option>
                            </select>
                            <p class="fp-fh-form-description">Scegli l'ambiente Aruba da utilizzare</p>
                        </div>
                        <?php if ($aruba_username && $aruba_password) : ?>
                            <div class="fp-fh-form-group">
                                <button type="button" id="test-aruba-connection" class="fp-fh-btn fp-fh-btn-secondary">
                                    🔍 Test Connessione
                                </button>
                                <p class="fp-fh-form-description">Verifica che le credenziali siano corrette</p>
                            </div>
                        <?php endif; ?>
                </div>
                
                <div class="fp-fh-card-header fp-fh-mt-6">
                    <h2 class="fp-fh-card-title">🧠 Intelligence</h2>
                </div>
                <div class="fp-fh-card-body">
                    <div class="fp-fh-form-group">
                        <label for="intelligence_cache_ttl" class="fp-fh-form-label">
                            <?php echo esc_html__('Cache TTL', 'fp-finance-hub'); ?>
                            <span class="fp-fh-tooltip">
                                <span class="fp-fh-help-icon" title="<?php echo esc_attr__('Durata cache report Intelligence', 'fp-finance-hub'); ?>">?</span>
                                <span class="fp-fh-tooltip-content"><?php echo esc_html__('Quanto tempo mantenere in cache i report Intelligence. Cache più lunga = meno calcoli ma dati meno aggiornati.', 'fp-finance-hub'); ?></span>
                            </span>
                        </label>
                        <select name="intelligence_cache_ttl" id="intelligence_cache_ttl" class="fp-fh-select">
                            <?php
                            $current_ttl = absint(get_option('fp_finance_hub_intelligence_cache_ttl', 2 * HOUR_IN_SECONDS));
                            $ttl_options = [
                                1 * HOUR_IN_SECONDS => __('1 ora', 'fp-finance-hub'),
                                2 * HOUR_IN_SECONDS => __('2 ore', 'fp-finance-hub'),
                                4 * HOUR_IN_SECONDS => __('4 ore', 'fp-finance-hub'),
                                8 * HOUR_IN_SECONDS => __('8 ore', 'fp-finance-hub'),
                                0 => __('Disabilitata', 'fp-finance-hub'),
                            ];
                            foreach ($ttl_options as $value => $label) {
                                $selected = ($current_ttl == $value) ? 'selected' : '';
                                echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="fp-fh-form-description"><?php echo esc_html__('Durata cache report Intelligence (default: 2 ore)', 'fp-finance-hub'); ?></p>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label for="intelligence_score_threshold" class="fp-fh-form-label">
                            <?php echo esc_html__('Soglia Intelligence Score', 'fp-finance-hub'); ?>
                            <span class="fp-fh-tooltip">
                                <span class="fp-fh-help-icon" title="<?php echo esc_attr__('Soglia per alert automatico', 'fp-finance-hub'); ?>">?</span>
                                <span class="fp-fh-tooltip-content"><?php echo esc_html__('Se l\'Intelligence Score scende sotto questa soglia, viene creato un alert automatico.', 'fp-finance-hub'); ?></span>
                            </span>
                        </label>
                        <input type="number" name="intelligence_score_threshold" id="intelligence_score_threshold" 
                               value="<?php echo esc_attr(get_option('fp_finance_hub_intelligence_score_threshold', 40)); ?>" 
                               class="fp-fh-input" min="0" max="100" step="1">
                        <p class="fp-fh-form-description"><?php echo esc_html__('Soglia per alert automatico (0-100, default: 40)', 'fp-finance-hub'); ?></p>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label class="fp-fh-form-label">
                            <input type="checkbox" name="intelligence_alert_critical" value="1" 
                                   <?php checked(get_option('fp_finance_hub_intelligence_alert_critical', true)); ?>>
                            <?php echo esc_html__('Alert per anomalie critical', 'fp-finance-hub'); ?>
                        </label>
                        <p class="fp-fh-form-description"><?php echo esc_html__('Crea automaticamente alert quando vengono rilevate anomalie critical', 'fp-finance-hub'); ?></p>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label for="intelligence_iqr_factor" class="fp-fh-form-label">
                            <?php echo esc_html__('Fattore IQR', 'fp-finance-hub'); ?>
                            <span class="fp-fh-tooltip">
                                <span class="fp-fh-help-icon" title="<?php echo esc_attr__('Fattore per rilevamento anomalie IQR', 'fp-finance-hub'); ?>">?</span>
                                <span class="fp-fh-tooltip-content"><?php echo esc_html__('Fattore moltiplicativo per IQR (Interquartile Range). Valori più alti = meno sensibile. Default: 1.5', 'fp-finance-hub'); ?></span>
                            </span>
                        </label>
                        <input type="number" name="intelligence_iqr_factor" id="intelligence_iqr_factor" 
                               value="<?php echo esc_attr(get_option('fp_finance_hub_intelligence_iqr_factor', 1.5)); ?>" 
                               class="fp-fh-input" min="0.5" max="3" step="0.1">
                        <p class="fp-fh-form-description"><?php echo esc_html__('Fattore IQR per rilevamento anomalie (default: 1.5)', 'fp-finance-hub'); ?></p>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label for="intelligence_zscore_threshold" class="fp-fh-form-label">
                            <?php echo esc_html__('Z-Score Threshold', 'fp-finance-hub'); ?>
                            <span class="fp-fh-tooltip">
                                <span class="fp-fh-help-icon" title="<?php echo esc_attr__('Soglia z-score per anomalie', 'fp-finance-hub'); ?>">?</span>
                                <span class="fp-fh-tooltip-content"><?php echo esc_html__('Soglia z-score per considerare un valore anomalo. Valori più alti = meno sensibile. Default: 2.0', 'fp-finance-hub'); ?></span>
                            </span>
                        </label>
                        <input type="number" name="intelligence_zscore_threshold" id="intelligence_zscore_threshold" 
                               value="<?php echo esc_attr(get_option('fp_finance_hub_intelligence_zscore_threshold', 2.0)); ?>" 
                               class="fp-fh-input" min="1" max="5" step="0.1">
                        <p class="fp-fh-form-description"><?php echo esc_html__('Soglia z-score per anomalie (default: 2.0)', 'fp-finance-hub'); ?></p>
                    </div>
                </div>
                
                <div class="fp-fh-card-footer">
                    <button type="submit" name="save_settings" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-lg">
                        💾 Salva Impostazioni
                    </button>
                </div>
            </form>
            </div>
            
            <!-- FASE 5: Dashboard Learning e Feedback -->
            <div class="fp-fh-card fp-fh-mt-6">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">🤖 Sistema AI Categorizzazione</h2>
                    <p class="fp-fh-text-sm">Visualizza i pattern appresi e l'accuratezza del sistema</p>
                </div>
                <div class="fp-fh-card-body">
                    <?php self::render_learning_dashboard(); ?>
                </div>
            </div>
        </div>
        
        <?php self::render_toast_script(); ?>
        <?php self::render_aruba_test_script(); ?>
        <?php
    }
    
    /**
     * FASE 5: Render dashboard learning
     */
    private static function render_learning_dashboard() {
        $categorization_engine = CategorizationEngine::get_instance();
        
        // Ottieni statistiche generali
        global $wpdb;
        $learning_table = $wpdb->prefix . 'fp_finance_hub_categorization_learning';
        $rules_table = $wpdb->prefix . 'fp_finance_hub_categorization_rules';
        
        $total_patterns = $wpdb->get_var("SELECT COUNT(*) FROM {$learning_table}");
        $total_rules = $wpdb->get_var("SELECT COUNT(*) FROM {$rules_table} WHERE is_active = 1");
        $avg_confidence = $wpdb->get_var("SELECT AVG(confidence) FROM {$learning_table}");
        
        ?>
        <div class="fp-fh-grid fp-fh-grid-cols-3 fp-fh-gap-4 fp-fh-mb-6">
            <div class="fp-fh-card" style="background: var(--fp-fh-color-bg-soft);">
                <div class="fp-fh-card-body">
                    <h3 class="fp-fh-text-lg fp-fh-font-semibold">Pattern Appresi</h3>
                    <p class="fp-fh-text-3xl fp-fh-font-bold fp-fh-mt-2"><?php echo esc_html(number_format($total_patterns)); ?></p>
                    <p class="fp-fh-text-sm fp-fh-text-muted">Transazioni categorizzate e apprese</p>
                </div>
            </div>
            
            <div class="fp-fh-card" style="background: var(--fp-fh-color-bg-soft);">
                <div class="fp-fh-card-body">
                    <h3 class="fp-fh-text-lg fp-fh-font-semibold">Regole Attive</h3>
                    <p class="fp-fh-text-3xl fp-fh-font-bold fp-fh-mt-2"><?php echo esc_html(number_format($total_rules)); ?></p>
                    <p class="fp-fh-text-sm fp-fh-text-muted">Pattern promossi a regole permanenti</p>
                </div>
            </div>
            
            <div class="fp-fh-card" style="background: var(--fp-fh-color-bg-soft);">
                <div class="fp-fh-card-body">
                    <h3 class="fp-fh-text-lg fp-fh-font-semibold">Confidence Media</h3>
                    <p class="fp-fh-text-3xl fp-fh-font-bold fp-fh-mt-2"><?php echo esc_html(number_format($avg_confidence * 100, 1)); ?>%</p>
                    <p class="fp-fh-text-sm fp-fh-text-muted">Accuratezza media del sistema</p>
                </div>
            </div>
        </div>
        
        <!-- Top Pattern per Categoria -->
        <div class="fp-fh-card fp-fh-mb-6">
            <div class="fp-fh-card-header">
                <h3 class="fp-fh-card-title">Top Pattern Appresi</h3>
            </div>
            <div class="fp-fh-card-body">
                <?php
                // Ottieni pattern più frequenti
                $top_patterns = $wpdb->get_results(
                    "SELECT 
                        normalized_description,
                        assigned_category_id,
                        COUNT(*) as frequency,
                        AVG(confidence) as avg_confidence
                    FROM {$learning_table}
                    GROUP BY normalized_description, assigned_category_id
                    HAVING frequency >= 2
                    ORDER BY frequency DESC, avg_confidence DESC
                    LIMIT 10"
                );
                
                if (!empty($top_patterns) && is_array($top_patterns)) {
                    ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Pattern</th>
                                <th>Categoria ID</th>
                                <th>Frequenza</th>
                                <th>Confidence Media</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_patterns as $pattern) : ?>
                                <tr>
                                    <td><code><?php echo esc_html($pattern->normalized_description); ?></code></td>
                                    <td><?php echo esc_html($pattern->assigned_category_id); ?></td>
                                    <td><?php echo esc_html($pattern->frequency); ?></td>
                                    <td><?php echo esc_html(number_format(floatval($pattern->avg_confidence) * 100, 1)); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                } else {
                    echo '<p>Nessun pattern appreso ancora. Categorizza alcune transazioni per iniziare l\'apprendimento automatico.</p>';
                }
                ?>
            </div>
        </div>
        
        <!-- Keywords Estratte per Categoria -->
        <div class="fp-fh-card">
            <div class="fp-fh-card-header">
                <h3 class="fp-fh-card-title">Keywords Estratte (TF-IDF)</h3>
                <p class="fp-fh-text-sm">Keywords più significative estratte automaticamente per categoria</p>
            </div>
            <div class="fp-fh-card-body">
                <?php
                // Ottieni categorie con pattern appresi
                $categories_with_patterns = $wpdb->get_results(
                    "SELECT DISTINCT assigned_category_id 
                    FROM {$learning_table} 
                    ORDER BY assigned_category_id 
                    LIMIT 5"
                );
                
                if (!empty($categories_with_patterns) && is_array($categories_with_patterns)) {
                    foreach ($categories_with_patterns as $cat_row) {
                        $category_id = $cat_row->assigned_category_id;
                        $keywords = CategorizationLearning::get_keywords_for_category($category_id, 10);
                        
                        if (!empty($keywords)) {
                            ?>
                            <div class="fp-fh-mb-4" style="padding: 1rem; background: var(--fp-fh-color-bg-soft); border-radius: var(--fp-fh-radius);">
                                <h4 class="fp-fh-font-semibold fp-fh-mb-2">Categoria ID: <?php echo esc_html($category_id); ?></h4>
                                <div class="fp-fh-flex fp-fh-flex-wrap fp-fh-gap-2">
                                    <?php foreach ($keywords as $keyword => $count) : ?>
                                        <span class="fp-fh-badge" style="font-size: 0.875rem;">
                                            <?php echo esc_html($keyword); ?> 
                                            <span class="fp-fh-text-muted">(<?php echo esc_html($count); ?>)</span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php
                        }
                    }
                } else {
                    echo '<p>Nessuna keyword estratta ancora. Categorizza più transazioni per generare keywords automaticamente.</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Salva impostazioni
     */
    private static function save_settings() {
        check_admin_referer('fp_finance_hub_settings');
        
        if (isset($_POST['aruba_username'])) {
            update_option('fp_finance_hub_aruba_username', sanitize_text_field($_POST['aruba_username']));
        }
        if (isset($_POST['aruba_password'])) {
            // Salva password (non sanitizzare per preservare caratteri speciali)
            update_option('fp_finance_hub_aruba_password', $_POST['aruba_password']);
        }
        if (isset($_POST['aruba_environment'])) {
            update_option('fp_finance_hub_aruba_environment', sanitize_text_field($_POST['aruba_environment']));
        }
        
        // Parametri Premium (opzionali)
        if (isset($_POST['aruba_country_sender'])) {
            $country = strtoupper(sanitize_text_field($_POST['aruba_country_sender']));
            update_option('fp_finance_hub_aruba_country_sender', $country);
        }
        
        if (isset($_POST['aruba_vatcode_sender'])) {
            $vatcode = sanitize_text_field($_POST['aruba_vatcode_sender']);
            update_option('fp_finance_hub_aruba_vatcode_sender', $vatcode);
        }
        
        // Impostazioni Intelligence
        if (isset($_POST['intelligence_cache_ttl'])) {
            update_option('fp_finance_hub_intelligence_cache_ttl', absint($_POST['intelligence_cache_ttl']));
        }
        if (isset($_POST['intelligence_score_threshold'])) {
            update_option('fp_finance_hub_intelligence_score_threshold', absint($_POST['intelligence_score_threshold']));
        }
        update_option('fp_finance_hub_intelligence_alert_critical', isset($_POST['intelligence_alert_critical']));
        if (isset($_POST['intelligence_iqr_factor'])) {
            update_option('fp_finance_hub_intelligence_iqr_factor', floatval($_POST['intelligence_iqr_factor']));
        }
        if (isset($_POST['intelligence_zscore_threshold'])) {
            update_option('fp_finance_hub_intelligence_zscore_threshold', floatval($_POST['intelligence_zscore_threshold']));
        }
        
        // Redirect con parametro success per mostrare toast
        wp_redirect(add_query_arg('settings_saved', '1', admin_url('admin.php?page=fp-finance-hub-settings')));
        exit;
    }
    
    /**
     * Render script per test connessione Aruba
     */
    private static function render_aruba_test_script() {
        ?>
        <script>
        (function($) {
            $('#test-aruba-connection').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var originalText = $button.text();
                
                $button.prop('disabled', true).text('⏳ Test in corso...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fp_finance_hub_test_aruba_connection',
                        nonce: '<?php echo wp_create_nonce('fp_finance_hub_settings'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var message = '✅ Connessione riuscita!\n\n';
                            message += 'Username: ' + (data.username || 'N/A') + '\n';
                            message += 'PEC: ' + (data.pec || 'N/A') + '\n';
                            message += 'Ragione Sociale: ' + (data.userDescription || 'N/A') + '\n';
                            message += 'Partita IVA: ' + (data.vatCode || 'N/A');
                            
                            if (data.accountStatus) {
                                message += '\n\nStato Account: ' + (data.accountStatus.expired ? 'Scaduto' : 'Attivo');
                                if (data.accountStatus.expirationDate) {
                                    message += '\nScadenza: ' + data.accountStatus.expirationDate;
                                }
                            }
                            
                            alert(message);
                            $button.text('✅ Connessione OK');
                            setTimeout(function() {
                                $button.text(originalText);
                            }, 3000);
                        } else {
                            alert('❌ Errore: ' + (response.data.message || 'Errore sconosciuto'));
                            $button.text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('❌ Errore durante il test: ' + error);
                        $button.text(originalText);
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Render script toast dopo salvataggio
     */
    private static function render_toast_script() {
        if (isset($_GET['settings_saved'])) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                if (typeof fpToast !== 'undefined') {
                    fpToast.success('<?php echo esc_js(__('Impostazioni salvate con successo.', 'fp-finance-hub')); ?>');
                    // Rimuovi parametro dall'URL
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]settings_saved=1/, ''));
                    }
                }
            });
            </script>
            <?php
        }
    }
}
