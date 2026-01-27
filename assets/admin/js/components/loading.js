/**
 * FP Finance Hub - Loading States
 * Spinner overlay e skeleton screens per migliorare percezione performance
 */

(function($) {
    'use strict';
    
    const FPLoading = {
        
        /**
         * Mostra spinner overlay
         * @param {string} message - Messaggio opzionale da mostrare
         */
        show: function(message) {
            // Rimuovi overlay esistente se presente
            this.hide();
            
            var $overlay = $('<div class="fp-fh-loading-overlay">' +
                '<div class="fp-fh-loading-spinner">' +
                    '<div class="fp-fh-spinner"></div>' +
                    (message ? '<div class="fp-fh-loading-message">' + message + '</div>' : '') +
                '</div>' +
            '</div>');
            
            $('body').append($overlay);
            
            // Animazione entrata
            setTimeout(function() {
                $overlay.addClass('fp-fh-loading-active');
            }, 10);
        },
        
        /**
         * Nasconde spinner overlay
         */
        hide: function() {
            var $overlay = $('.fp-fh-loading-overlay');
            if ($overlay.length) {
                $overlay.removeClass('fp-fh-loading-active');
                setTimeout(function() {
                    $overlay.remove();
                }, 300);
            }
        },
        
        /**
         * Mostra skeleton screen su elemento
         * @param {jQuery|string} selector - Selettore o elemento jQuery
         * @param {string} type - Tipo skeleton: 'card', 'list', 'text', 'chart'
         */
        showSkeleton: function(selector, type) {
            type = type || 'card';
            var $el = typeof selector === 'string' ? $(selector) : selector;
            
            if (!$el.length) {
                return;
            }
            
            // Salva contenuto originale
            if (!$el.data('original-content')) {
                $el.data('original-content', $el.html());
            }
            
            var skeletonHTML = this.getSkeletonHTML(type);
            $el.addClass('fp-fh-skeleton-container').html(skeletonHTML);
        },
        
        /**
         * Nasconde skeleton screen e ripristina contenuto
         * @param {jQuery|string} selector - Selettore o elemento jQuery
         */
        hideSkeleton: function(selector) {
            var $el = typeof selector === 'string' ? $(selector) : selector;
            
            if (!$el.length) {
                return;
            }
            
            var originalContent = $el.data('original-content');
            if (originalContent) {
                $el.removeClass('fp-fh-skeleton-container').html(originalContent);
                $el.removeData('original-content');
            }
        },
        
        /**
         * Genera HTML skeleton in base al tipo
         */
        getSkeletonHTML: function(type) {
            var html = '';
            
            switch(type) {
                case 'card':
                    html = '<div class="fp-fh-skeleton fp-fh-skeleton-card">' +
                        '<div class="fp-fh-skeleton-line fp-fh-skeleton-title"></div>' +
                        '<div class="fp-fh-skeleton-line fp-fh-skeleton-text"></div>' +
                        '<div class="fp-fh-skeleton-line fp-fh-skeleton-text fp-fh-skeleton-short"></div>' +
                    '</div>';
                    break;
                    
                case 'list':
                    html = '';
                    for (var i = 0; i < 5; i++) {
                        html += '<div class="fp-fh-skeleton fp-fh-skeleton-list-item">' +
                            '<div class="fp-fh-skeleton-line fp-fh-skeleton-text"></div>' +
                        '</div>';
                    }
                    break;
                    
                case 'text':
                    html = '<div class="fp-fh-skeleton fp-fh-skeleton-text">' +
                        '<div class="fp-fh-skeleton-line"></div>' +
                        '<div class="fp-fh-skeleton-line"></div>' +
                        '<div class="fp-fh-skeleton-line fp-fh-skeleton-short"></div>' +
                    '</div>';
                    break;
                    
                case 'chart':
                    html = '<div class="fp-fh-skeleton fp-fh-skeleton-chart">' +
                        '<div class="fp-fh-skeleton-line fp-fh-skeleton-title"></div>' +
                        '<div class="fp-fh-skeleton-chart-area"></div>' +
                    '</div>';
                    break;
                    
                default:
                    html = '<div class="fp-fh-skeleton"><div class="fp-fh-skeleton-line"></div></div>';
            }
            
            return html;
        }
    };
    
    // Esponi globalmente
    window.FPLoading = FPLoading;
    
    // Helper globali per compatibilità
    window.showLoading = function(message) {
        FPLoading.show(message);
    };
    
    window.hideLoading = function() {
        FPLoading.hide();
    };
    
    window.showSkeleton = function(selector, type) {
        FPLoading.showSkeleton(selector, type);
    };
    
    window.hideSkeleton = function(selector) {
        FPLoading.hideSkeleton(selector);
    };
    
})(jQuery);
