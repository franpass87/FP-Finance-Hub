<?php
/**
 * Alerts Page
 * 
 * Gestione soglie e alert
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Database\Models\Alert as AlertModel;

if (!defined('ABSPATH')) {
    exit;
}

class AlertsPage {
    
    public static function render() {
        $alerts = AlertModel::get_active(['acknowledged' => false]);
        
        ?>
        <div class="wrap fp-fh-wrapper">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p>Monitora gli alert e le soglie di sicurezza finanziaria</p>
                </div>
            </div>
            
            <div class="fp-alerts-list">
                <?php if (empty($alerts)) : ?>
                    <div class="fp-fh-card">
                        <div class="fp-fh-card-body fp-fh-text-center fp-fh-p-8">
                            <div style="font-size: 4rem; margin-bottom: var(--fp-fh-spacing-4); opacity: 0.5;">✅</div>
                            <h3 class="fp-clients-empty-title">Nessun alert attivo</h3>
                            <p class="fp-clients-empty-text">Tutto procede secondo i piani! Non ci sono alert da segnalare.</p>
                        </div>
                    </div>
                <?php else : ?>
                    <?php foreach ($alerts as $alert) : ?>
                        <div class="fp-fh-notice fp-fh-notice-<?php echo esc_attr($alert->severity); ?> fp-fh-mb-4">
                            <div class="fp-fh-notice-icon">⚠️</div>
                            <div class="fp-fh-notice-content">
                                <div class="fp-fh-notice-title"><?php echo esc_html($alert->alert_type); ?></div>
                                <div class="fp-fh-notice-message"><?php echo esc_html($alert->message); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
