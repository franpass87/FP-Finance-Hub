<?php
/**
 * Setup Guide Page
 * 
 * Guida step-by-step per configurazione iniziale plugin
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Services\SetupService;

if (!defined('ABSPATH')) {
    exit;
}

class SetupGuidePage {
    
    /**
     * Render pagina guida setup
     */
    public static function render() {
        $setup_service = SetupService::get_instance();
        $progress = $setup_service->get_setup_progress();
        $next_step = $setup_service->get_next_step();
        
        // Step corrente (default: primo non completato)
        $current_step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : null;
        if (!$current_step) {
            if ($next_step) {
                $step_mapping = [
                    'bank_data' => 'bank-connection',
                    'aruba_configured' => 'aruba',
                    'aruba_synced' => 'aruba-sync'
                ];
                $current_step = isset($step_mapping[$next_step['key']]) ? $step_mapping[$next_step['key']] : 'welcome';
            } else {
                $current_step = 'welcome';
            }
        }
        
        ?>
        <div class="wrap fp-fh-wrapper fp-fh-setup-guide">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1>🎯 Guida Setup FP Finance Hub</h1>
                    <p>Configura il plugin in pochi semplici passi</p>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="fp-fh-card fp-fh-mb-6">
                <div class="fp-fh-card-body">
                    <div class="fp-fh-setup-progress">
                        <div class="fp-fh-progress-header">
                            <h3 class="fp-fh-text-lg fp-fh-font-semibold">Progresso Setup</h3>
                            <span class="fp-fh-progress-percentage"><?php echo esc_html(round($progress['percentage'])); ?>%</span>
                        </div>
                        <div class="fp-fh-progress-bar">
                            <div class="fp-fh-progress-fill" style="width: <?php echo esc_attr($progress['percentage']); ?>%"></div>
                        </div>
                        <p class="fp-fh-text-sm fp-fh-text-muted fp-fh-mt-2">
                            <?php echo esc_html($progress['completed_count']); ?> di <?php echo esc_html($progress['total_count']); ?> step completati
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Wizard Navigation -->
            <div class="fp-fh-wizard-navigation">
                <ul class="fp-fh-wizard-steps">
                    <li class="fp-fh-wizard-step <?php echo $current_step === 'welcome' ? 'active' : ''; ?> completed" data-step="welcome">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=welcome'); ?>" class="fp-fh-wizard-step-link">
                            <span class="fp-fh-wizard-step-number">1</span>
                            <span class="fp-fh-wizard-step-label">Benvenuto</span>
                        </a>
                    </li>
                    <li class="fp-fh-wizard-step <?php echo $current_step === 'bank-connection' ? 'active' : ''; ?> <?php echo $progress['steps']['bank_data']['completed'] ? 'completed' : ''; ?>" data-step="bank-connection">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=bank-connection'); ?>" class="fp-fh-wizard-step-link">
                            <span class="fp-fh-wizard-step-number">2</span>
                            <span class="fp-fh-wizard-step-label">Conti Bancari</span>
                        </a>
                    </li>
                    <li class="fp-fh-wizard-step <?php echo $current_step === 'aruba' ? 'active' : ''; ?> <?php echo $progress['steps']['aruba_configured']['completed'] ? 'completed' : ''; ?>" data-step="aruba">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba'); ?>" class="fp-fh-wizard-step-link">
                            <span class="fp-fh-wizard-step-number">3</span>
                            <span class="fp-fh-wizard-step-label">Aruba</span>
                        </a>
                    </li>
                    <li class="fp-fh-wizard-step <?php echo $current_step === 'aruba-sync' ? 'active' : ''; ?> <?php echo $progress['steps']['aruba_synced']['completed'] ? 'completed' : ''; ?>" data-step="aruba-sync">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba-sync'); ?>" class="fp-fh-wizard-step-link">
                            <span class="fp-fh-wizard-step-number">4</span>
                            <span class="fp-fh-wizard-step-label">Sincronizza</span>
                        </a>
                    </li>
                    <li class="fp-fh-wizard-step <?php echo $current_step === 'complete' ? 'active' : ''; ?> <?php echo $progress['is_complete'] ? 'completed' : ''; ?>" data-step="complete">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=complete'); ?>" class="fp-fh-wizard-step-link">
                            <span class="fp-fh-wizard-step-number">5</span>
                            <span class="fp-fh-wizard-step-label">Completato</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Wizard Content -->
            <div class="fp-fh-wizard-content">
                <?php
                switch ($current_step) {
                    case 'welcome':
                        self::render_welcome_step($progress);
                        break;
                    case 'bank-connection':
                        self::render_bank_connection_step($progress);
                        break;
                    case 'aruba':
                        self::render_aruba_step($progress);
                        break;
                    case 'aruba-sync':
                        self::render_aruba_sync_step($progress);
                        break;
                    case 'complete':
                        self::render_complete_step($progress);
                        break;
                    default:
                        self::render_welcome_step($progress);
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render step Benvenuto
     */
    private static function render_welcome_step($progress) {
        ?>
        <div class="fp-fh-card fp-fh-wizard-step-card">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">🎉 Benvenuto in FP Finance Hub!</h2>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-guide-intro">
                    <p class="fp-fh-text-lg">Questa guida ti aiuterà a configurare il plugin in pochi semplici passi.</p>
                    
                    <div class="fp-fh-guide-features fp-fh-grid fp-fh-grid-cols-2 fp-fh-gap-4 fp-fh-mt-6">
                        <div class="fp-fh-feature-card">
                            <div class="fp-fh-feature-icon">🏦</div>
                            <h3 class="fp-fh-feature-title">Conti Bancari</h3>
                            <p class="fp-fh-feature-description">Importa movimenti e saldi tramite CSV (ING, PostePay, OFX) da Conti Bancari e Import Dati.</p>
                        </div>
                        
                        <div class="fp-fh-feature-card">
                            <div class="fp-fh-feature-icon">📄</div>
                            <h3 class="fp-fh-feature-title">Aruba Fatturazione</h3>
                            <p class="fp-fh-feature-description">Sincronizza automaticamente fatture e clienti dal tuo pannello Aruba Fatturazione Elettronica.</p>
                        </div>
                    </div>
                    
                    <div class="fp-fh-guide-info fp-fh-mt-6">
                        <h3 class="fp-fh-text-lg fp-fh-font-semibold fp-fh-mb-4">Cosa ti serve:</h3>
                        <ul class="fp-fh-list fp-fh-list-check">
                            <li>Credenziali Aruba Fatturazione Elettronica (API Key e Username)</li>
                            <li>Export CSV o OFX dalla tua banca (es. ING, PostePay) per i movimenti</li>
                            <li>Circa 10-15 minuti del tuo tempo</li>
                        </ul>
                    </div>
                    
                    <div class="fp-fh-wizard-actions fp-fh-mt-6">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=bank-connection'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-lg">
                            Inizia Setup →
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render step Conti Bancari / Import
     */
    private static function render_bank_connection_step($progress) {
        $is_done = $progress['steps']['bank_data']['completed'];
        
        ?>
        <div class="fp-fh-card fp-fh-wizard-step-card">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">🏦 Conti Bancari e Import Movimenti</h2>
                <?php if ($is_done) : ?>
                    <span class="fp-fh-badge fp-fh-badge-success">✅ Completato</span>
                <?php endif; ?>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-guide-step">
                    <p class="fp-fh-text-base fp-fh-mb-6">Importa movimenti e saldi tramite CSV (ING, PostePay Evolution, OFX) dalla pagina Conti Bancari e Import Dati.</p>
                    
                    <div class="fp-fh-guide-substep fp-fh-mb-6">
                        <div class="fp-fh-guide-substep-header">
                            <span class="fp-fh-guide-substep-number">1</span>
                            <h3 class="fp-fh-guide-substep-title">Scarica i CSV dalla tua banca</h3>
                        </div>
                        <div class="fp-fh-guide-substep-content">
                            <p>Usa lo script Playwright nella cartella <code>bank-automation</code> del plugin (se disponibile) oppure esporta manualmente CSV/OFX dal sito della tua banca (ING, PostePay, ecc.).</p>
                        </div>
                    </div>
                    
                    <div class="fp-fh-guide-substep fp-fh-mb-6">
                        <div class="fp-fh-guide-substep-header">
                            <span class="fp-fh-guide-substep-number">2</span>
                            <h3 class="fp-fh-guide-substep-title">Carica i file</h3>
                        </div>
                        <div class="fp-fh-guide-substep-content">
                            <?php if (!$is_done) : ?>
                                <ol class="fp-fh-list fp-fh-list-ordered">
                                    <li>Vai alla pagina <strong>Conti Bancari</strong> per creare/gestire i conti</li>
                                    <li>Vai alla scheda <strong>Import Dati → Conti Bancari</strong> per caricare CSV/OFX</li>
                                    <li>Dopo l'import, i movimenti saranno disponibili per analisi e proiezioni</li>
                                </ol>
                                <div class="fp-fh-wizard-actions fp-fh-mt-4">
                                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-accounts'); ?>" class="fp-fh-btn fp-fh-btn-primary" target="_blank">
                                        Apri Conti Bancari →
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-import&tab=bank'); ?>" class="fp-fh-btn fp-fh-btn-secondary" target="_blank">
                                        Import Dati →
                                    </a>
                                </div>
                            <?php else : ?>
                                <div class="fp-fh-guide-success">
                                    <p>✅ Hai già conti o movimenti importati.</p>
                                    <p class="fp-fh-text-sm fp-fh-text-muted fp-fh-mt-2">Puoi aggiungere altri CSV dalla pagina Conti Bancari e Import Dati.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="fp-fh-wizard-actions fp-fh-mt-6">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=welcome'); ?>" class="fp-fh-btn fp-fh-btn-secondary">
                            ← Indietro
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba'); ?>" class="fp-fh-btn fp-fh-btn-primary">
                            Prossimo: Configura Aruba →
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render step Configurazione Aruba
     */
    private static function render_aruba_step($progress) {
        $is_configured = $progress['steps']['aruba_configured']['completed'];
        
        ?>
        <div class="fp-fh-card fp-fh-wizard-step-card">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">📄 Configurazione Aruba Fatturazione Elettronica</h2>
                <?php if ($is_configured) : ?>
                    <span class="fp-fh-badge fp-fh-badge-success">✅ Completato</span>
                <?php endif; ?>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-guide-step">
                    <p class="fp-fh-text-base fp-fh-mb-6">Collega Aruba Fatturazione Elettronica per sincronizzare automaticamente fatture e clienti.</p>
                    
                    <!-- Step 1: Accedere al pannello -->
                    <div class="fp-fh-guide-substep fp-fh-mb-6">
                        <div class="fp-fh-guide-substep-header">
                            <span class="fp-fh-guide-substep-number">1</span>
                            <h3 class="fp-fh-guide-substep-title">Accedi al Pannello Aruba</h3>
                        </div>
                        <div class="fp-fh-guide-substep-content">
                            <ol class="fp-fh-list fp-fh-list-ordered">
                                <li>Vai su <a href="https://www.fatturazione-elettronica-aruba.it" target="_blank" rel="noopener">fatturazione-elettronica-aruba.it</a></li>
                                <li>Accedi con le tue credenziali Aruba</li>
                                <li>Vai alla sezione "API" o "Integrazioni"</li>
                            </ol>
                        </div>
                    </div>
                    
                    <!-- Step 2: Ottenere API Key -->
                    <div class="fp-fh-guide-substep fp-fh-mb-6">
                        <div class="fp-fh-guide-substep-header">
                            <span class="fp-fh-guide-substep-number">2</span>
                            <h3 class="fp-fh-guide-substep-title">Ottieni API Key</h3>
                        </div>
                        <div class="fp-fh-guide-substep-content">
                            <ol class="fp-fh-list fp-fh-list-ordered">
                                <li>Nella sezione API, cerca "API Key" o "Chiave API"</li>
                                <li>Se non hai ancora una API Key, clicca su "Genera nuova API Key"</li>
                                <li>Copia la <strong>API Key</strong> generata</li>
                                <li>Salvala in un posto sicuro</li>
                            </ol>
                            <div class="fp-fh-guide-tip fp-fh-mt-4">
                                <strong>💡 Nota:</strong> L'API Key è necessaria per permettere al plugin di accedere alle tue fatture e clienti.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Trovare Username -->
                    <div class="fp-fh-guide-substep fp-fh-mb-6">
                        <div class="fp-fh-guide-substep-header">
                            <span class="fp-fh-guide-substep-number">3</span>
                            <h3 class="fp-fh-guide-substep-title">Trova il Tuo Username</h3>
                        </div>
                        <div class="fp-fh-guide-substep-content">
                            <p>L'Username è lo stesso che usi per accedere al pannello Aruba Fatturazione Elettronica.</p>
                            <ul class="fp-fh-list fp-fh-list-check fp-fh-mt-4">
                                <li>È l'indirizzo email con cui ti registri ad Aruba</li>
                                <li>Oppure il codice utente visibile nel profilo</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Step 4: Inserire Credenziali -->
                    <div class="fp-fh-guide-substep fp-fh-mb-6">
                        <div class="fp-fh-guide-substep-header">
                            <span class="fp-fh-guide-substep-number">4</span>
                            <h3 class="fp-fh-guide-substep-title">Inserisci le Credenziali nelle Impostazioni</h3>
                        </div>
                        <div class="fp-fh-guide-substep-content">
                            <?php if (!$is_configured) : ?>
                                <ol class="fp-fh-list fp-fh-list-ordered">
                                    <li>Vai alla pagina <strong>Impostazioni</strong> del plugin</li>
                                    <li>Nella sezione "Integrazione Aruba"</li>
                                    <li>Incolla l'<strong>API Key</strong> nel campo "API Key"</li>
                                    <li>Incolla lo <strong>Username</strong> nel campo "Username"</li>
                                    <li>Clicca su "Salva Impostazioni"</li>
                                </ol>
                                <div class="fp-fh-wizard-actions fp-fh-mt-4">
                                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-settings'); ?>" class="fp-fh-btn fp-fh-btn-primary" target="_blank">
                                        Apri Impostazioni →
                                    </a>
                                </div>
                                <div class="fp-fh-guide-refresh fp-fh-mt-4">
                                    <p class="fp-fh-text-sm fp-fh-text-muted">
                                        <strong>Dopo aver salvato:</strong> Ricarica questa pagina per verificare che la configurazione sia completata.
                                    </p>
                                </div>
                            <?php else : ?>
                                <div class="fp-fh-guide-success">
                                    <p>✅ Credenziali Aruba configurate correttamente!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <div class="fp-fh-wizard-actions fp-fh-mt-6">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=bank-connection'); ?>" class="fp-fh-btn fp-fh-btn-secondary">
                            ← Indietro
                        </a>
                        <?php if ($is_configured) : ?>
                            <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba-sync'); ?>" class="fp-fh-btn fp-fh-btn-primary">
                                Prossimo: Sincronizza Aruba →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render step Sincronizza Aruba
     */
    private static function render_aruba_sync_step($progress) {
        $is_configured = $progress['steps']['aruba_configured']['completed'];
        $is_synced = $progress['steps']['aruba_synced']['completed'];
        
        if (!$is_configured) {
            ?>
            <div class="fp-fh-card fp-fh-wizard-step-card">
                <div class="fp-fh-card-body">
                    <div class="fp-fh-guide-error">
                        <p>⚠️ Prima devi configurare le credenziali Aruba. Vai allo step precedente.</p>
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-mt-4">
                            Configura Aruba →
                        </a>
                    </div>
                </div>
            </div>
            <?php
            return;
        }
        
        ?>
        <div class="fp-fh-card fp-fh-wizard-step-card">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">🔄 Sincronizza Dati da Aruba</h2>
                <?php if ($is_synced) : ?>
                    <span class="fp-fh-badge fp-fh-badge-success">✅ Completato</span>
                <?php endif; ?>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-guide-step">
                    <p class="fp-fh-text-base fp-fh-mb-6">Ora puoi sincronizzare fatture e clienti da Aruba Fatturazione Elettronica.</p>
                    
                    <!-- Cosa viene importato -->
                    <div class="fp-fh-guide-substep fp-fh-mb-6">
                        <div class="fp-fh-guide-substep-header">
                            <span class="fp-fh-guide-substep-number">1</span>
                            <h3 class="fp-fh-guide-substep-title">Cosa Viene Sincronizzato</h3>
                        </div>
                        <div class="fp-fh-guide-substep-content">
                            <p>Durante la sincronizzazione, il plugin importa automaticamente:</p>
                            <ul class="fp-fh-list fp-fh-list-check fp-fh-mt-4">
                                <li><strong>Fatture emesse</strong> - Tutte le fatture presenti su Aruba</li>
                                <li><strong>Clienti</strong> - Anagrafica clienti con P.IVA, email, telefono</li>
                                <li><strong>Stati fatture</strong> - Inviata, Accettata, Rifiutata, ecc.</li>
                                <li><strong>Importi</strong> - Totale, IVA, importo netto</li>
                                <li><strong>Date</strong> - Data emissione, scadenza, pagamento</li>
                            </ul>
                            <div class="fp-fh-guide-tip fp-fh-mt-4">
                                <strong>💡 Importante:</strong> Le fatture già importate vengono aggiornate automaticamente, non duplicate.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Prima sincronizzazione -->
                    <div class="fp-fh-guide-substep fp-fh-mb-6">
                        <div class="fp-fh-guide-substep-header">
                            <span class="fp-fh-guide-substep-number">2</span>
                            <h3 class="fp-fh-guide-substep-title">Esegui la Prima Sincronizzazione</h3>
                        </div>
                        <div class="fp-fh-guide-substep-content">
                            <?php if (!$is_synced) : ?>
                                <ol class="fp-fh-list fp-fh-list-ordered">
                                    <li>Vai alla pagina <strong>"Import Dati"</strong></li>
                                    <li>Assicurati di essere nel tab <strong>"Aruba"</strong></li>
                                    <li>Clicca sul pulsante <strong>"Sincronizza Fatture da Aruba"</strong></li>
                                    <li>Attendi qualche secondo - la sincronizzazione potrebbe richiedere tempo</li>
                                    <li>Riceverai un messaggio di conferma con il numero di fatture importate</li>
                                </ol>
                                <div class="fp-fh-wizard-actions fp-fh-mt-4">
                                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-import&tab=aruba'); ?>" class="fp-fh-btn fp-fh-btn-primary" target="_blank">
                                        Apri Import Dati →
                                    </a>
                                </div>
                                <div class="fp-fh-guide-info fp-fh-mt-4">
                                    <p class="fp-fh-text-sm"><strong>Nota:</strong> Dopo la sincronizzazione, ricarica questa pagina per verificare il completamento.</p>
                                </div>
                            <?php else : ?>
                                <div class="fp-fh-guide-success">
                                    <p>✅ Sincronizzazione Aruba completata!</p>
                                    <p class="fp-fh-text-sm fp-fh-text-muted fp-fh-mt-2">Le tue fatture e clienti sono ora disponibili nel plugin. La sincronizzazione continuerà automaticamente ogni giorno.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Sincronizzazione automatica -->
                    <div class="fp-fh-guide-substep fp-fh-mb-6">
                        <div class="fp-fh-guide-substep-header">
                            <span class="fp-fh-guide-substep-number">3</span>
                            <h3 class="fp-fh-guide-substep-title">Sincronizzazione Automatica</h3>
                        </div>
                        <div class="fp-fh-guide-substep-content">
                            <p>Dopo la prima sincronizzazione manuale:</p>
                            <ul class="fp-fh-list fp-fh-list-check fp-fh-mt-4">
                                <li>Il plugin sincronizza <strong>automaticamente ogni giorno</strong></li>
                                <li>Nuove fatture e aggiornamenti vengono importati automaticamente</li>
                                <li>Non devi fare nulla - tutto avviene in background</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <div class="fp-fh-wizard-actions fp-fh-mt-6">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=aruba'); ?>" class="fp-fh-btn fp-fh-btn-secondary">
                            ← Indietro
                        </a>
                        <?php if ($is_synced) : ?>
                            <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=complete'); ?>" class="fp-fh-btn fp-fh-btn-primary">
                                Completa Setup →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render step Completato
     */
    private static function render_complete_step($progress) {
        $is_complete = $progress['is_complete'];
        
        ?>
        <div class="fp-fh-card fp-fh-wizard-step-card">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">🎉 Setup Completato!</h2>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-guide-complete">
                    <?php if ($is_complete) : ?>
                        <div class="fp-fh-complete-icon">✅</div>
                        <h3 class="fp-fh-text-2xl fp-fh-font-bold fp-fh-text-center fp-fh-mb-6">Ottimo! Tutto è configurato correttamente</h3>
                        
                        <div class="fp-fh-complete-summary fp-fh-grid fp-fh-grid-cols-2 fp-fh-gap-4 fp-fh-mb-6">
                            <div class="fp-fh-summary-card">
                                <div class="fp-fh-summary-icon">🏦</div>
                                <h4>Conti Bancari</h4>
                                <p><?php echo $progress['steps']['bank_connected']['completed'] ? '✅ Collegati' : '⚠️ Non collegati'; ?></p>
                            </div>
                            
                            <div class="fp-fh-summary-card">
                                <div class="fp-fh-summary-icon">📄</div>
                                <h4>Aruba</h4>
                                <p><?php echo $progress['steps']['aruba_synced']['completed'] ? '✅ Sincronizzato' : '⚠️ Non sincronizzato'; ?></p>
                            </div>
                        </div>
                        
                        <div class="fp-fh-complete-next fp-fh-mb-6">
                            <h3 class="fp-fh-text-lg fp-fh-font-semibold fp-fh-mb-4">Prossimi Passi:</h3>
                            <ul class="fp-fh-list fp-fh-list-check">
                                <li>Vai alla <strong>Dashboard</strong> per vedere la panoramica finanziaria</li>
                                <li>Consulta le <strong>Analisi Finanziarie</strong> per proiezioni e statistiche</li>
                                <li>Aggiungi altri conti bancari se necessario</li>
                                <li>Configura <strong>Alert</strong> per soglie di sicurezza</li>
                            </ul>
                        </div>
                        
                        <div class="fp-fh-wizard-actions fp-fh-mt-6">
                            <a href="<?php echo admin_url('admin.php?page=fp-finance-hub'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-lg">
                                Vai alla Dashboard →
                            </a>
                        </div>
                    <?php else : ?>
                        <div class="fp-fh-guide-warning">
                            <p class="fp-fh-text-lg">⚠️ Alcuni step non sono ancora completati.</p>
                            <p class="fp-fh-mt-4">Completa tutti gli step per utilizzare al meglio il plugin.</p>
                            
                            <div class="fp-fh-wizard-actions fp-fh-mt-6">
                                <?php
                                $next = $setup_service->get_next_step();
                                if ($next) :
                                    $step_mapping = [
                                        'bank_data' => 'bank-connection',
                                        'aruba_configured' => 'aruba',
                                        'aruba_synced' => 'aruba-sync'
                                    ];
                                    $wizard_step = isset($step_mapping[$next['key']]) ? $step_mapping[$next['key']] : $next['key'];
                                ?>
                                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide&step=' . esc_attr($wizard_step)); ?>" class="fp-fh-btn fp-fh-btn-primary">
                                        Completa Step: <?php echo esc_html($next['step']['name']); ?> →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
