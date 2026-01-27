/**
 * FP Finance Hub - Wizard Component
 * 
 * Componente wizard multi-step per setup guide
 */

(function($) {
    'use strict';

    const FPWizard = {
        
        /**
         * Avvia wizard step
         */
        init: function() {
            // Auto-check setup progress every 10 seconds
            if ($('.fp-fh-setup-guide').length) {
                setInterval(function() {
                    FPWizard.checkSetupProgress();
                }, 10000); // Check every 10 seconds
            }
            
            // Highlight active step
            $('.fp-fh-wizard-step.active').each(function() {
                $(this).addClass('active');
            });
        },
        
        /**
         * Verifica progresso setup e aggiorna UI
         */
        checkSetupProgress: function() {
            // Solo se siamo nella pagina setup guide
            if (!$('.fp-fh-setup-guide').length) {
                return;
            }
            
            // Fai chiamata AJAX per verificare progresso
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
                        
                        // Aggiorna progress bar
                        $('.fp-fh-progress-fill').css('width', progress.percentage + '%');
                        $('.fp-fh-progress-percentage').text(Math.round(progress.percentage) + '%');
                        
                        // Aggiorna step completati
                        // Mapping tra chiavi progress e data-step
                        const stepMapping = {
                            'yapily_configured': 'yapily',
                            'bank_connected': 'bank-connection',
                            'aruba_configured': 'aruba',
                            'aruba_synced': 'aruba-sync'
                        };
                        
                        $.each(progress.steps, function(key, step) {
                            const stepKey = stepMapping[key] || key;
                            const stepEl = $('.fp-fh-wizard-step[data-step="' + stepKey + '"]');
                            if (step.completed && stepEl.length && !stepEl.hasClass('completed')) {
                                stepEl.addClass('completed');
                            } else if (!step.completed && stepEl.length && stepEl.hasClass('completed') && stepKey !== 'welcome') {
                                // Rimuovi completed solo se non è welcome (sempre completato)
                                stepEl.removeClass('completed');
                            }
                        });
                        
                        // Se setup completato e siamo sullo step "complete", ricarica per mostrare successo
                        if (progress.is_complete && window.location.search.includes('step=complete')) {
                            const currentStep = new URLSearchParams(window.location.search).get('step');
                            if (currentStep === 'complete') {
                                // Ricarica per mostrare stato completo
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1000);
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Naviga a step successivo
         */
        goToNextStep: function() {
            const currentStep = $('.fp-fh-wizard-step.active');
            const nextStep = currentStep.next('.fp-fh-wizard-step');
            
            if (nextStep.length) {
                const nextUrl = nextStep.find('a').attr('href');
                if (nextUrl) {
                    window.location.href = nextUrl;
                }
            }
        },
        
        /**
         * Naviga a step precedente
         */
        goToPreviousStep: function() {
            const currentStep = $('.fp-fh-wizard-step.active');
            const prevStep = currentStep.prev('.fp-fh-wizard-step');
            
            if (prevStep.length) {
                const prevUrl = prevStep.find('a').attr('href');
                if (prevUrl) {
                    window.location.href = prevUrl;
                }
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FPWizard.init();
    });

    // Expose globally
    window.FPWizard = FPWizard;

})(jQuery);
