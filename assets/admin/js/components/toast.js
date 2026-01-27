/**
 * FP Finance Hub - Toast Notifications
 * Sistema di notifiche toast moderno e accessibile
 */

(function($) {
    'use strict';
    
    // Crea container toast se non esiste
    function ensureToastContainer() {
        if (!$('#fp-fh-toast-container').length) {
            // Aggiungi ARIA live region per screen readers
            $('body').append('<div id="fp-fh-toast-container" class="fp-fh-toast-container" role="status" aria-live="polite" aria-atomic="true"></div>');
        }
    }
    
    /**
     * Mostra toast notification
     * @param {string} message - Messaggio da mostrare
     * @param {string} type - success|error|warning|info
     * @param {number} duration - Durata in ms (0 = permanente, default 4000)
     */
    window.fpToast = function(message, type, duration) {
        type = type || 'info';
        duration = duration !== undefined ? duration : 4000;
        
        ensureToastContainer();
        
        var icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        // Determina aria-live priority in base al tipo
        var ariaLive = (type === 'error') ? 'assertive' : 'polite';
        
        var $toast = $('<div class="fp-fh-toast fp-fh-toast-' + type + '" role="alert" aria-live="' + ariaLive + '" aria-atomic="true">' +
            '<span class="fp-fh-toast-icon" aria-hidden="true">' + icons[type] + '</span>' +
            '<span class="fp-fh-toast-message">' + message + '</span>' +
            '<button class="fp-fh-toast-close" aria-label="' + (typeof fpFinanceHub !== 'undefined' && fpFinanceHub.i18n ? fpFinanceHub.i18n.close : 'Chiudi notifica') + '">×</button>' +
        '</div>');
        
        $('#fp-fh-toast-container').append($toast);
        
        // Animazione entrata
        setTimeout(function() {
            $toast.addClass('fp-fh-toast-show');
        }, 10);
        
        // Auto-chiusura
        if (duration > 0) {
            setTimeout(function() {
                closeToast($toast);
            }, duration);
        }
        
        // Click per chiudere
        $toast.find('.fp-fh-toast-close').on('click', function() {
            closeToast($toast);
        });
        
        return $toast;
    };
    
    function closeToast($toast) {
        $toast.removeClass('fp-fh-toast-show');
        setTimeout(function() {
            $toast.remove();
        }, 300);
    }
    
    // Shorthand methods
    window.fpToast.success = function(message, duration) {
        return fpToast(message, 'success', duration);
    };
    
    window.fpToast.error = function(message, duration) {
        return fpToast(message, 'error', duration);
    };
    
    window.fpToast.warning = function(message, duration) {
        return fpToast(message, 'warning', duration);
    };
    
    window.fpToast.info = function(message, duration) {
        return fpToast(message, 'info', duration);
    };
    
})(jQuery);
