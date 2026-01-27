/**
 * FP Finance Hub - Lazy Loading Component
 * 
 * Infinite scroll e lazy loading per liste lunghe
 */

(function($) {
    'use strict';

    const FPLazyLoading = {
        
        /**
         * Initialize lazy loading
         */
        init: function() {
            // Infinite scroll per tabelle
            this.initInfiniteScroll();
            
            // Lazy load immagini
            this.initImageLazyLoad();
            
            // Intersection Observer per elementi
            this.initIntersectionObserver();
        },
        
        /**
         * Infinite scroll per liste
         */
        initInfiniteScroll: function() {
            const $containers = $('.fp-fh-lazy-list');
            
            if (!$containers.length) {
                return;
            }
            
            $containers.each(function() {
                const $container = $(this);
                const $loader = $container.find('.fp-fh-lazy-loader');
                const loadMoreUrl = $container.data('load-more-url');
                const currentPage = parseInt($container.data('current-page') || 1);
                const totalPages = parseInt($container.data('total-pages') || 1);
                let isLoading = false;
                let page = currentPage;
                
                if (page >= totalPages) {
                    $loader.hide();
                    return;
                }
                
                // Observer per scroll
                const observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting && !isLoading && page < totalPages) {
                            FPLazyLoading.loadMore($container, loadMoreUrl, page + 1, function() {
                                page++;
                                if (page >= totalPages) {
                                    $loader.hide();
                                    observer.disconnect();
                                }
                            });
                        }
                    });
                }, {
                    rootMargin: '200px' // Carica 200px prima che diventi visibile
                });
                
                if ($loader.length) {
                    observer.observe($loader[0]);
                }
            });
        },
        
        /**
         * Carica più elementi
         */
        loadMore: function($container, url, page, callback) {
            if (typeof url === 'undefined' || !url) {
                return;
            }
            
            const $loader = $container.find('.fp-fh-lazy-loader');
            $loader.addClass('loading');
            
            $.ajax({
                url: url,
                type: 'GET',
                data: {
                    page: page,
                    action: 'fp_finance_hub_load_more',
                    nonce: fpFinanceHub.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Aggiungi nuovi elementi
                        const $newItems = $(response.data.html);
                        $container.find('.fp-fh-lazy-list-items').append($newItems);
                        
                        // Aggiorna paginazione
                        if (response.data.has_more === false) {
                            $loader.hide();
                        }
                        
                        // Callback
                        if (typeof callback === 'function') {
                            callback();
                        }
                        
                        // Trigger event
                        $(document).trigger('fp_finance_hub_lazy_loaded', [page, response.data]);
                    }
                },
                error: function() {
                    $loader.removeClass('loading').addClass('error');
                },
                complete: function() {
                    $loader.removeClass('loading');
                }
            });
        },
        
        /**
         * Lazy load immagini
         */
        initImageLazyLoad: function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                                imageObserver.unobserve(img);
                            }
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(function(img) {
                    imageObserver.observe(img);
                });
            }
        },
        
        /**
         * Intersection Observer per elementi generici
         */
        initIntersectionObserver: function() {
            if (!('IntersectionObserver' in window)) {
                return;
            }
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('fp-fh-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            
            document.querySelectorAll('.fp-fh-lazy-element').forEach(function(el) {
                observer.observe(el);
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FPLazyLoading.init();
    });

    // Expose globally
    window.FPLazyLoading = FPLazyLoading;

})(jQuery);
