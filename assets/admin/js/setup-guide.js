/**
 * FP Finance Hub - Setup Guide
 * 
 * Logica pagina setup guide
 */

(function($) {
    'use strict';

    const SetupGuide = {
        
        /**
         * Initialize setup guide
         */
        init: function() {
            // Auto-check progress ogni 10 secondi (già gestito da wizard.js)
            
            // Aggiungi handler per link esterni
            $('.fp-fh-wizard-actions a[target="_blank"]').on('click', function() {
                // Quando utente apre pagina esterna, mostra messaggio
                const $refresh = $('.fp-fh-guide-refresh');
                if ($refresh.length) {
                    $refresh.fadeIn();
                }
            });
            
            // Auto-refresh se step completato
            this.checkStepCompletion();
        },
        
        /**
         * Verifica se step corrente è completato
         */
        checkStepCompletion: function() {
            const currentUrl = window.location.href;
            const urlParams = new URLSearchParams(window.location.search);
            const currentStep = urlParams.get('step');
            
            if (!currentStep) {
                return;
            }
            
            // Per step che richiedono configurazione, verifica ogni 5 secondi
            if (['nordigen', 'aruba'].includes(currentStep)) {
                setInterval(function() {
                    SetupGuide.verifyStepCompletion(currentStep);
                }, 5000);
            }
        },
        
        /**
         * Verifica completamento step specifico
         */
        verifyStepCompletion: function(step) {
            $.ajax({
                url: fpFinanceHub.ajaxUrl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'fp_finance_hub_check_setup_progress',
                    nonce: fpFinanceHub.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const progress = response.data;
                        
                        if (step === 'nordigen' && progress.steps.nordigen_configured.completed) {
                            // Mostra messaggio di successo e suggerimento per continuare
                            SetupGuide.showStepCompleted('nordigen');
                        }
                        
                        if (step === 'aruba' && progress.steps.aruba_configured.completed) {
                            SetupGuide.showStepCompleted('aruba');
                        }
                    }
                }
            });
        },
        
        /**
         * Mostra messaggio step completato
         */
        showStepCompleted: function(step) {
            const $stepCard = $('.fp-fh-wizard-step-card');
            if ($stepCard.find('.fp-fh-guide-success').length === 0) {
                const message = step === 'nordigen' 
                    ? '✅ Credenziali Nordigen configurate correttamente! Ricarica la pagina per continuare.'
                    : '✅ Credenziali Aruba configurate correttamente! Ricarica la pagina per continuare.';
                
                const $success = $('<div class="fp-fh-guide-success"><p>' + message + '</p></div>');
                $stepCard.find('.fp-fh-guide-step').append($success);
                $success.hide().fadeIn();
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SetupGuide.init();
    });

    // Expose globally
    window.SetupGuide = SetupGuide;

})(jQuery);
