/**
 * FP Finance Hub - Admin JavaScript
 * 
 * Utilities generali, form handlers, AJAX helpers
 */

(function($) {
    'use strict';

    const FPFinanceHub = {
        
        /**
         * Initialize
         */
        init: function() {
            this.initTooltips();
            this.initConfirmDialogs();
            this.initAjaxForms();
            this.initAutoSubmit();
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                const $el = $(this);
                const tooltip = $el.data('tooltip');
                
                $el.on('mouseenter', function() {
                    const $tooltip = $('<div class="fp-fh-tooltip">' + tooltip + '</div>');
                    $('body').append($tooltip);
                    
                    const offset = $el.offset();
                    const width = $el.outerWidth();
                    const height = $el.outerHeight();
                    const tooltipHeight = $tooltip.outerHeight();
                    
                    $tooltip.css({
                        top: offset.top - tooltipHeight - 10,
                        left: offset.left + (width / 2) - ($tooltip.outerWidth() / 2)
                    });
                });
                
                $el.on('mouseleave', function() {
                    $('.fp-fh-tooltip').remove();
                });
            });
        },

        /**
         * Initialize confirm dialogs
         */
        initConfirmDialogs: function() {
            $('[data-confirm]').on('click', function(e) {
                const message = $(this).data('confirm');
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Initialize AJAX forms
         */
        initAjaxForms: function() {
            $('form[data-ajax]').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $button = $form.find('button[type="submit"]');
                const originalText = $button.text();
                
                // Disable button and show loading
                $button.prop('disabled', true).text('Caricamento...');
                
                $.ajax({
                    url: $form.attr('action') || (typeof fpFinanceHub !== 'undefined' ? fpFinanceHub.ajaxUrl : null) || ajaxurl || '/wp-admin/admin-ajax.php',
                    method: $form.attr('method') || 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        FPFinanceHub.showNotice('success', response.data?.message || 'Operazione completata con successo.');
                        $form.trigger('reset');
                    },
                    error: function(xhr) {
                        const message = xhr.responseJSON?.data?.message || 'Si è verificato un errore.';
                        FPFinanceHub.showNotice('error', message);
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
        },

        /**
         * Initialize auto-submit on change
         */
        initAutoSubmit: function() {
            $('[data-auto-submit]').on('change', function() {
                $(this).closest('form').submit();
            });
        },

        /**
         * Show notice/toast
         */
        showNotice: function(type, message) {
            const $notice = $('<div class="fp-fh-toast fp-fh-notice fp-fh-notice-' + type + '">' +
                '<div class="fp-fh-notice-content">' +
                '<div class="fp-fh-notice-message">' + message + '</div>' +
                '</div>' +
                '</div>');
            
            $('body').append($notice);
            
            setTimeout(function() {
                $notice.addClass('fp-fh-toast-out');
                setTimeout(function() {
                    $notice.remove();
                }, 300);
            }, 5000);
        },

        /**
         * Format currency
         */
        formatCurrency: function(amount, currency = 'EUR') {
            return new Intl.NumberFormat('it-IT', {
                style: 'currency',
                currency: currency
            }).format(amount);
        },

        /**
         * Format date
         */
        formatDate: function(date, format = 'short') {
            const d = new Date(date);
            const options = format === 'short' 
                ? { day: '2-digit', month: '2-digit', year: 'numeric' }
                : { day: '2-digit', month: 'long', year: 'numeric' };
            return d.toLocaleDateString('it-IT', options);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FPFinanceHub.init();
    });

    // Expose globally
    window.FPFinanceHub = FPFinanceHub;

})(jQuery);
