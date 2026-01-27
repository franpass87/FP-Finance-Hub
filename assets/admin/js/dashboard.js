/**
 * FP Finance Hub - Dashboard JavaScript
 * 
 * Chart.js initialization, dashboard interactions, real-time updates
 */

(function($) {
    'use strict';

    const FPDashboard = {
        
        chartInstances: {},
        
        /**
         * Initialize
         */
        init: function() {
            this.initTrendChart();
            this.initAutoRefresh();
        },

        /**
         * Initialize trend chart
         */
        initTrendChart: function() {
            const canvas = document.getElementById('fp-finance-trend-chart');
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            const ctx = canvas.getContext('2d');
            const $chartContainer = $(canvas).closest('.fp-fh-chart-container');
            
            // Mostra skeleton durante caricamento
            if (typeof FPLoading !== 'undefined' && $chartContainer.length) {
                FPLoading.showSkeleton($chartContainer, 'chart');
            }
            
            // Fetch data from API
            this.fetchTrendData().then(function(data) {
                // Nascondi skeleton
                if (typeof FPLoading !== 'undefined' && $chartContainer.length) {
                    FPLoading.hideSkeleton($chartContainer);
                }
                FPDashboard.createTrendChart(ctx, data);
            }).catch(function(error) {
                console.error('Error fetching trend data:', error);
                // Nascondi skeleton anche in caso di errore
                if (typeof FPLoading !== 'undefined' && $chartContainer.length) {
                    FPLoading.hideSkeleton($chartContainer);
                }
            });
        },

        /**
         * Fetch trend data from REST API
         */
        fetchTrendData: function() {
            return $.ajax({
                url: fpFinanceHub.apiUrl + 'stats/trend',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fpFinanceHub.nonce);
                }
            }).then(function(response) {
                return response.data || {
                    labels: [],
                    datasets: []
                };
            });
        },

        /**
         * Create trend chart
         */
        createTrendChart: function(ctx, data) {
            if (this.chartInstances.trend) {
                this.chartInstances.trend.destroy();
            }

            this.chartInstances.trend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [
                        {
                            label: 'Entrate',
                            data: data.income || [],
                            borderColor: '#00a32a',
                            backgroundColor: 'rgba(0, 163, 42, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Uscite',
                            data: data.expenses || [],
                            borderColor: '#d63638',
                            backgroundColor: 'rgba(214, 54, 56, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Saldo',
                            data: data.balance || [],
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34, 113, 177, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + 
                                           FPFinanceHub.formatCurrency(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return FPFinanceHub.formatCurrency(value);
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        },

        /**
         * Initialize auto refresh
         */
        initAutoRefresh: function() {
            // Refresh every 5 minutes
            setInterval(function() {
                FPDashboard.refreshDashboard();
            }, 5 * 60 * 1000);
        },

        /**
         * Refresh dashboard data
         */
        refreshDashboard: function() {
            // Reload page or update specific widgets via AJAX
            location.reload();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('body').hasClass('toplevel_page_fp-finance-hub')) {
            FPDashboard.init();
        }
    });

    // Expose globally
    window.FPDashboard = FPDashboard;

})(jQuery);
