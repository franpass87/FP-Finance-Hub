/**
 * FP Finance Hub - Touch Gestures Component
 * 
 * Supporto touch gestures per mobile (swipe, pull to refresh)
 */

(function($) {
    'use strict';

    const FPTouchGestures = {
        
        touchStartX: 0,
        touchStartY: 0,
        touchEndX: 0,
        touchEndY: 0,
        minSwipeDistance: 50,
        
        /**
         * Initialize touch gestures
         */
        init: function() {
            // Swipe per tabelle/card
            this.initSwipe();
            
            // Pull to refresh
            this.initPullToRefresh();
            
            // Tap gestures
            this.initTapGestures();
        },
        
        /**
         * Swipe gestures
         */
        initSwipe: function() {
            const $swipeable = $('.fp-fh-swipeable');
            
            if (!$swipeable.length) {
                return;
            }
            
            $swipeable.on('touchstart', function(e) {
                const touch = e.originalEvent.touches[0];
                FPTouchGestures.touchStartX = touch.clientX;
                FPTouchGestures.touchStartY = touch.clientY;
            });
            
            $swipeable.on('touchend', function(e) {
                const touch = e.originalEvent.changedTouches[0];
                FPTouchGestures.touchEndX = touch.clientX;
                FPTouchGestures.touchEndY = touch.clientY;
                
                const deltaX = FPTouchGestures.touchEndX - FPTouchGestures.touchStartX;
                const deltaY = FPTouchGestures.touchEndY - FPTouchGestures.touchStartY;
                
                // Determina direzione swipe
                if (Math.abs(deltaX) > Math.abs(deltaY)) {
                    // Swipe orizzontale
                    if (Math.abs(deltaX) > FPTouchGestures.minSwipeDistance) {
                        if (deltaX > 0) {
                            $(this).trigger('swiperight');
                        } else {
                            $(this).trigger('swipeleft');
                        }
                    }
                } else {
                    // Swipe verticale
                    if (Math.abs(deltaY) > FPTouchGestures.minSwipeDistance) {
                        if (deltaY > 0) {
                            $(this).trigger('swipedown');
                        } else {
                            $(this).trigger('swipeup');
                        }
                    }
                }
            });
            
            // Handler per swipe left (mostra azioni)
            $swipeable.on('swipeleft', function() {
                const $row = $(this);
                if ($row.hasClass('fp-fh-table-row')) {
                    $row.addClass('fp-fh-swipe-open');
                    $row.find('.fp-fh-swipe-actions').addClass('visible');
                }
            });
            
            // Handler per swipe right (nascondi azioni)
            $swipeable.on('swiperight', function() {
                const $row = $(this);
                $row.removeClass('fp-fh-swipe-open');
                $row.find('.fp-fh-swipe-actions').removeClass('visible');
            });
        },
        
        /**
         * Pull to refresh
         */
        initPullToRefresh: function() {
            const $refreshable = $('.fp-fh-pull-refresh');
            
            if (!$refreshable.length) {
                return;
            }
            
            let startY = 0;
            let currentY = 0;
            let isPulling = false;
            let pullDistance = 0;
            
            $refreshable.on('touchstart', function(e) {
                if ($(window).scrollTop() === 0) {
                    startY = e.originalEvent.touches[0].clientY;
                    isPulling = true;
                }
            });
            
            $refreshable.on('touchmove', function(e) {
                if (!isPulling) {
                    return;
                }
                
                currentY = e.originalEvent.touches[0].clientY;
                pullDistance = currentY - startY;
                
                if (pullDistance > 0 && $(window).scrollTop() === 0) {
                    e.preventDefault();
                    
                    const $indicator = $('.fp-fh-pull-indicator');
                    if (!$indicator.length) {
                        $('<div class="fp-fh-pull-indicator">⬇️ Trascina per aggiornare</div>')
                            .prependTo($refreshable);
                    }
                    
                    // Mostra indicatore
                    $('.fp-fh-pull-indicator')
                        .css('transform', 'translateY(' + Math.min(pullDistance, 80) + 'px)')
                        .show();
                    
                    if (pullDistance > 80) {
                        $('.fp-fh-pull-indicator').addClass('ready');
                    }
                }
            });
            
            $refreshable.on('touchend', function() {
                if (isPulling && pullDistance > 80) {
                    // Trigger refresh
                    $(this).trigger('pullrefresh');
                    FPTouchGestures.handlePullRefresh($(this));
                }
                
                // Reset
                $('.fp-fh-pull-indicator').hide().removeClass('ready');
                isPulling = false;
                pullDistance = 0;
            });
        },
        
        /**
         * Gestisce pull to refresh
         */
        handlePullRefresh: function($container) {
            const refreshUrl = $container.data('refresh-url') || window.location.href;
            
            $container.addClass('fp-fh-refreshing');
            
            $.ajax({
                url: refreshUrl,
                type: 'GET',
                data: {
                    action: 'fp_finance_hub_refresh',
                    nonce: fpFinanceHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Ricarica contenuto
                        if (response.data.html) {
                            $container.find('.fp-fh-refresh-content').html(response.data.html);
                        } else {
                            location.reload();
                        }
                    }
                },
                complete: function() {
                    $container.removeClass('fp-fh-refreshing');
                }
            });
        },
        
        /**
         * Tap gestures (double tap, long press)
         */
        initTapGestures: function() {
            let tapTimer = null;
            let lastTap = 0;
            
            $(document).on('touchstart', '.fp-fh-tappable', function(e) {
                const $el = $(this);
                const touch = e.originalEvent.touches[0];
                
                // Long press
                tapTimer = setTimeout(function() {
                    $el.trigger('longpress', [touch]);
                }, 500);
            });
            
            $(document).on('touchend', '.fp-fh-tappable', function(e) {
                clearTimeout(tapTimer);
                
                const currentTime = new Date().getTime();
                const tapLength = currentTime - lastTap;
                
                if (tapLength < 300 && tapLength > 0) {
                    // Double tap
                    $(this).trigger('doubletap');
                    lastTap = 0;
                } else {
                    lastTap = currentTime;
                }
            });
            
            $(document).on('touchmove', '.fp-fh-tappable', function() {
                clearTimeout(tapTimer);
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Solo su dispositivi touch
        if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
            FPTouchGestures.init();
        }
    });

    // Expose globally
    window.FPTouchGestures = FPTouchGestures;

})(jQuery);
