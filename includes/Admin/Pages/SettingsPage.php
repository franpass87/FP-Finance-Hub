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
        $aruba_api_key = get_option('fp_finance_hub_aruba_api_key', '');
        $aruba_username = get_option('fp_finance_hub_aruba_username', '');
        $nordigen_secret_id = get_option('fp_finance_hub_nordigen_secret_id', '');
        $nordigen_secret_key = get_option('fp_finance_hub_nordigen_secret_key', '');
        
        $nordigen_configured = $setup_service->is_nordigen_configured();
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
            <?php if (!$nordigen_configured || !$aruba_configured) : ?>
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
            
            <form method="post" class="fp-fh-card">
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
                        <label for="aruba_api_key" class="fp-fh-form-label">
                            API Key
                            <span class="fp-fh-tooltip">
                                <span class="fp-fh-help-icon" title="L'API Key si trova nel pannello Aruba Fatturazione Elettronica nella sezione API. Se non ce l'hai, devi generarne una nuova.">?</span>
                                <span class="fp-fh-tooltip-content">L'API Key permette al plugin di accedere alle tue fatture e clienti da Aruba. La trovi nel pannello Aruba nella sezione "API" o "Integrazioni".</span>
                            </span>
                        </label>
                        <input type="text" name="aruba_api_key" id="aruba_api_key" 
                               value="<?php echo esc_attr($aruba_api_key); ?>" class="fp-fh-input">
                        <p class="fp-fh-form-description">Inserisci la tua API Key di Aruba Fatturazione Elettronica</p>
                        <?php if (!$aruba_configured) : ?>
                            <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba'); ?>" class="fp-fh-help-link">Come ottenerla? →</a>
                        <?php endif; ?>
                    </div>
                    <div class="fp-fh-form-group">
                        <label for="aruba_username" class="fp-fh-form-label">
                            Username
                            <span class="fp-fh-tooltip">
                                <span class="fp-fh-help-icon" title="Lo Username è l'indirizzo email con cui ti registri ad Aruba o il codice utente nel profilo.">?</span>
                                <span class="fp-fh-tooltip-content">Lo Username è l'indirizzo email che usi per accedere ad Aruba Fatturazione Elettronica, oppure il codice utente visibile nel tuo profilo.</span>
                            </span>
                        </label>
                        <input type="text" name="aruba_username" id="aruba_username" 
                               value="<?php echo esc_attr($aruba_username); ?>" class="fp-fh-input">
                        <p class="fp-fh-form-description">Inserisci il tuo username Aruba</p>
                    </div>
                </div>
                
                <div class="fp-fh-card-header fp-fh-mt-6">
                    <h2 class="fp-fh-card-title">Integrazione GoCardless Bank Account Data (Open Banking)</h2>
                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=nordigen'); ?>" class="fp-fh-help-link" target="_blank">
                        📖 Guida Completa →
                    </a>
                </div>
                <div class="fp-fh-card-body">
                    <?php if (!$nordigen_configured) : ?>
                        <div class="fp-fh-guide-tip fp-fh-mb-4">
                            <strong>💡 GoCardless Bank Account Data è completamente gratuito!</strong> 
                            Se non hai ancora un account, <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=nordigen'); ?>">segui la guida</a> per registrarti e ottenere le credenziali.
                        </div>
                    <?php endif; ?>
                    
                    <div class="fp-fh-form-group">
                        <label for="nordigen_secret_id" class="fp-fh-form-label">
                            Secret ID
                            <span class="fp-fh-tooltip">
                                <span class="fp-fh-help-icon" title="Il Secret ID viene generato automaticamente quando crei le credenziali su bankaccountdata.gocardless.com. Lo trovi nel dashboard GoCardless nella sezione API.">?</span>
                                <span class="fp-fh-tooltip-content">Il Secret ID è una credenziale generata automaticamente da GoCardless quando crei le credenziali API. Lo trovi nel dashboard GoCardless Bank Account Data dopo la registrazione gratuita.</span>
                            </span>
                        </label>
                        <input type="text" name="nordigen_secret_id" id="nordigen_secret_id" 
                               value="<?php echo esc_attr($nordigen_secret_id); ?>" class="fp-fh-input">
                        <p class="fp-fh-form-description">Inserisci il tuo Secret ID GoCardless (gratuito)</p>
                        <?php if (!$nordigen_configured) : ?>
                            <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=nordigen'); ?>" class="fp-fh-help-link">Come ottenerlo? →</a>
                        <?php endif; ?>
                    </div>
                    <div class="fp-fh-form-group">
                        <label for="nordigen_secret_key" class="fp-fh-form-label">
                            Secret Key
                            <span class="fp-fh-tooltip">
                                <span class="fp-fh-help-icon" title="⚠️ IMPORTANTE: La Secret Key viene mostrata solo una volta quando la generi. Copiala subito e salvala in un posto sicuro!">?</span>
                                <span class="fp-fh-tooltip-content">La Secret Key viene generata insieme al Secret ID, ma viene mostrata solo una volta. Se la perdi, dovrai generarne una nuova. Salvala in un password manager.</span>
                            </span>
                        </label>
                        <input type="password" name="nordigen_secret_key" id="nordigen_secret_key" 
                               value="<?php echo esc_attr($nordigen_secret_key); ?>" class="fp-fh-input">
                        <p class="fp-fh-form-description">Inserisci la tua Secret Key GoCardless</p>
                        <?php if (!$nordigen_configured) : ?>
                            <div class="fp-fh-guide-warning fp-fh-mt-2">
                                <strong>⚠️ Attenzione:</strong> La Secret Key viene mostrata solo una volta. Se non l'hai salvata, devi generarne una nuova su bankaccountdata.gocardless.com
                            </div>
                        <?php endif; ?>
                    </div>
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
                    <button type="submit" name="save_settings" class="fp-fh-btn fp-fh-btn-primary">
                        Salva Impostazioni
                    </button>
                </div>
            </form>
            
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
        
        if (isset($_POST['aruba_api_key'])) {
            update_option('fp_finance_hub_aruba_api_key', sanitize_text_field($_POST['aruba_api_key']));
        }
        if (isset($_POST['aruba_username'])) {
            update_option('fp_finance_hub_aruba_username', sanitize_text_field($_POST['aruba_username']));
        }
        if (isset($_POST['nordigen_secret_id'])) {
            update_option('fp_finance_hub_nordigen_secret_id', sanitize_text_field($_POST['nordigen_secret_id']));
        }
        if (isset($_POST['nordigen_secret_key'])) {
            update_option('fp_finance_hub_nordigen_secret_key', sanitize_text_field($_POST['nordigen_secret_key']));
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
