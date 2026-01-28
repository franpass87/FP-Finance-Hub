/**
 * FP Finance Hub - Help Modal Component
 * 
 * Modal riutilizzabile per guide dettagliate
 */

(function($) {
    'use strict';

    const FPHelpModal = {
        
        /**
         * Mostra modal help
         */
        show: function(title, content) {
            // Crea modal se non esiste
            let $modal = $('#fp-fh-help-modal');
            
            if (!$modal.length) {
                $modal = $('<div id="fp-fh-help-modal" class="fp-fh-help-modal">' +
                    '<div class="fp-fh-help-modal-content">' +
                    '<div class="fp-fh-help-modal-header">' +
                    '<h2 class="fp-fh-help-modal-title"></h2>' +
                    '<button type="button" class="fp-fh-help-modal-close">&times;</button>' +
                    '</div>' +
                    '<div class="fp-fh-help-modal-body"></div>' +
                    '<div class="fp-fh-help-modal-footer">' +
                    '<button type="button" class="fp-fh-btn fp-fh-btn-secondary fp-fh-help-modal-close">Chiudi</button>' +
                    '</div>' +
                    '</div>' +
                    '</div>');
                
                $('body').append($modal);
                
                // Close handlers
                $modal.find('.fp-fh-help-modal-close').on('click', function() {
                    FPHelpModal.hide();
                });
                
                // Close on backdrop click
                $modal.on('click', function(e) {
                    if ($(e.target).is($modal)) {
                        FPHelpModal.hide();
                    }
                });
                
                // Close on Escape key
                $(document).on('keydown.fpHelpModal', function(e) {
                    if (e.key === 'Escape' && $modal.hasClass('active')) {
                        FPHelpModal.hide();
                    }
                });
            }
            
            // Popola contenuto
            $modal.find('.fp-fh-help-modal-title').text(title);
            $modal.find('.fp-fh-help-modal-body').html(content);
            
            // Mostra modal
            $modal.addClass('active');
            $('body').addClass('fp-fh-modal-open');
        },
        
        /**
         * Nascondi modal
         */
        hide: function() {
            const $modal = $('#fp-fh-help-modal');
            $modal.removeClass('active');
            $('body').removeClass('fp-fh-modal-open');
        },
        
        /**
         * @deprecated Yapily non è più supportato. Usa Import CSV/OFX invece.
         */
        showYapilyGuide: function() {
            const content = `
                <div class="fp-fh-guide-modal-content">
                    <div class="fp-fh-guide-warning fp-fh-mb-4">
                        <strong>⚠️ Yapily Open Banking non è più disponibile</strong>
                    </div>
                    <p>Per importare movimenti bancari, usa la funzionalità <strong>Import CSV/OFX</strong> dalla pagina Conti Bancari.</p>
                    <p class="fp-fh-mt-2">Vai su <strong>Conti Bancari</strong> → <strong>Import Dati</strong> per caricare i file CSV o OFX dalla tua banca.</p>
                </div>
            `;
            
            FPHelpModal.show('Open Banking non disponibile', content);
        },
        
        /**
         * @deprecated Nordigen non è più supportato. Usa Import CSV/OFX invece.
         */
        showNordigenGuide: function() {
            this.showYapilyGuide();
        },
        
        /**
         * Mostra guida Aruba
         */
        showArubaGuide: function() {
            const content = `
                <div class="fp-fh-guide-modal-content">
                    <h3>Come Ottenere le Credenziali Aruba</h3>
                    
                    <div class="fp-fh-guide-substep fp-fh-mb-4">
                        <h4>Step 1: Accedi al Pannello Aruba</h4>
                        <ol class="fp-fh-list fp-fh-list-ordered">
                            <li>Vai su <a href="https://www.fatturazione-elettronica-aruba.it" target="_blank" rel="noopener">fatturazione-elettronica-aruba.it</a></li>
                            <li>Accedi con le tue credenziali Aruba</li>
                            <li>Vai alla sezione "API" o "Integrazioni"</li>
                        </ol>
                    </div>
                    
                    <div class="fp-fh-guide-substep fp-fh-mb-4">
                        <h4>Step 2: Ottieni API Key</h4>
                        <ol class="fp-fh-list fp-fh-list-ordered">
                            <li>Nella sezione API, cerca "API Key" o "Chiave API"</li>
                            <li>Se non hai ancora una API Key, clicca su "Genera nuova API Key"</li>
                            <li>Copia l'<strong>API Key</strong> generata</li>
                            <li>Salvala in un posto sicuro</li>
                        </ol>
                    </div>
                    
                    <div class="fp-fh-guide-substep fp-fh-mb-4">
                        <h4>Step 3: Trova il Tuo Username</h4>
                        <p>L'Username è lo stesso che usi per accedere al pannello Aruba Fatturazione Elettronica:</p>
                        <ul class="fp-fh-list fp-fh-list-check fp-fh-mt-2">
                            <li>È l'indirizzo email con cui ti registri ad Aruba</li>
                            <li>Oppure il codice utente visibile nel profilo</li>
                        </ul>
                    </div>
                    
                    <div class="fp-fh-guide-tip fp-fh-mt-4">
                        <strong>💡 Nota:</strong> L'API Key è necessaria per permettere al plugin di accedere alle tue fatture e clienti.
                    </div>
                </div>
            `;
            
            FPHelpModal.show('Guida Configurazione Aruba', content);
        },
        
        /**
         * Initialize help modal
         */
        init: function() {
            // Handler per link guida completa
            $(document).on('click', '.fp-fh-help-link[data-guide]', function(e) {
                e.preventDefault();
                const guide = $(this).data('guide');
                
                if (guide === 'yapily' || guide === 'nordigen') {
                    // Deprecato: mostra messaggio che Yapily/Nordigen non sono più disponibili
                    FPHelpModal.showYapilyGuide();
                } else if (guide === 'aruba') {
                    FPHelpModal.showArubaGuide();
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FPHelpModal.init();
    });

    // Expose globally
    window.FPHelpModal = FPHelpModal;

})(jQuery);
