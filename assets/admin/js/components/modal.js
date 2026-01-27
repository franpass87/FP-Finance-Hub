/**
 * FP Finance Hub - Modal Component
 * 
 * Modal dialogs JavaScript
 */

(function($) {
    'use strict';

    const FPModal = {
        
        /**
         * Open modal
         */
        open: function(modalId) {
            const $modal = $('#' + modalId);
            if (!$modal.length) {
                return;
            }

            const $backdrop = $('<div class="fp-fh-modal-backdrop"></div>');
            $backdrop.append($modal);
            $('body').append($backdrop);
            
            // Trigger animation
            setTimeout(function() {
                $backdrop.addClass('active');
            }, 10);

            // Close on backdrop click
            $backdrop.on('click', function(e) {
                if ($(e.target).hasClass('fp-fh-modal-backdrop')) {
                    FPModal.close(modalId);
                }
            });

            // Close on ESC key
            $(document).on('keydown.modal', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    FPModal.close(modalId);
                }
            });
        },

        /**
         * Close modal
         */
        close: function(modalId) {
            const $backdrop = $('.fp-fh-modal-backdrop');
            const $modal = $('#' + modalId);
            
            $backdrop.removeClass('active');
            
            setTimeout(function() {
                $modal.detach();
                $backdrop.remove();
            }, 300);

            $(document).off('keydown.modal');
        },

        /**
         * Initialize modal triggers
         */
        init: function() {
            // Open modal on data-modal click
            $(document).on('click', '[data-modal]', function(e) {
                e.preventDefault();
                const modalId = $(this).data('modal');
                FPModal.open(modalId);
            });

            // Close modal on close button click
            $(document).on('click', '.fp-fh-modal-close', function(e) {
                e.preventDefault();
                const $modal = $(this).closest('.fp-fh-modal');
                const modalId = $modal.attr('id');
                FPModal.close(modalId);
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FPModal.init();
    });

    // Expose globally
    window.FPModal = FPModal;

})(jQuery);
