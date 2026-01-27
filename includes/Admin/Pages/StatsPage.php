<?php
/**
 * Stats Page
 * 
 * Statistiche e analisi
 */

namespace FP\FinanceHub\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

class StatsPage {
    
    public static function render() {
        ?>
        <div class="wrap fp-fh-wrapper">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p>Statistiche dettagliate e analisi finanziarie</p>
                </div>
            </div>
            
            <div class="fp-fh-card">
                <div class="fp-fh-card-body fp-fh-text-center fp-fh-p-8">
                    <div style="font-size: 4rem; margin-bottom: var(--fp-fh-spacing-4); opacity: 0.5;">📊</div>
                    <h3 class="fp-clients-empty-title">Statistiche e analisi</h3>
                    <p class="fp-clients-empty-text">Questa funzionalità è in fase di implementazione.</p>
                </div>
            </div>
        </div>
        <?php
    }
}
