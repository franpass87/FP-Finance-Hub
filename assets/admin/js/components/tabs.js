/**
 * FP Finance Hub - Tabs Component
 * 
 * Tab switching logic and URL hash support
 */

(function($) {
    'use strict';

    const FPTabs = {
        
        /**
         * Switch tab
         */
        switchTab: function(tabId, tabGroup) {
            const $group = tabGroup ? $('[data-tab-group="' + tabGroup + '"]') : $('.fp-fh-tabs');
            const $tab = $group.find('[data-tab="' + tabId + '"]');
            const $content = $('#' + tabId);

            if (!$tab.length) {
                console.warn('FPTabs: Tab not found:', tabId);
                return;
            }
            
            if (!$content.length) {
                console.warn('FPTabs: Tab content not found:', tabId);
                return;
            }

            // Remove active class from all tabs in group
            $group.find('.fp-fh-tab').removeClass('active');
            
            // Remove active class from all tab contents (search globally, not just in group)
            // because tab contents are typically outside the tabs container
            $('.fp-fh-tab-content').removeClass('active');

            // Add active class to selected tab and content
            $tab.addClass('active');
            $content.addClass('active');

            // Update URL hash if supported
            if (history.pushState) {
                const hash = tabGroup ? tabGroup + '-' + tabId : tabId;
                history.pushState(null, null, '#' + hash);
            }
        },

        /**
         * Initialize tabs
         */
        init: function() {
            // Tab click handler
            $(document).on('click', '.fp-fh-tab[data-tab]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $tab = $(this);
                const tabId = $tab.data('tab');
                const $tabsContainer = $tab.closest('.fp-fh-tabs');
                const tabGroup = $tabsContainer.length ? $tabsContainer.data('tab-group') : null;
                
                // Chiama switchTab
                FPTabs.switchTab(tabId, tabGroup);
                
                // Prevent default link behavior
                return false;
            });

            // Check URL hash on load
            if (window.location.hash) {
                const hash = window.location.hash.replace('#', '');
                if (hash) {
                    const parts = hash.split('-');
                    const tabGroup = parts.length > 1 ? parts[0] : null;
                    const tabId = parts.length > 1 ? parts.slice(1).join('-') : hash;
                    
                    // Aspetta che il DOM sia pronto
                    setTimeout(function() {
                        FPTabs.switchTab(tabId, tabGroup);
                    }, 100);
                }
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FPTabs.init();
    });

    // Expose globally
    window.FPTabs = FPTabs;

})(jQuery);
