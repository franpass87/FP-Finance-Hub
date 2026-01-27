/**
 * FP Finance Hub - Analytics JavaScript
 * 
 * Gestione tabs, grafici Chart.js, AJAX refresh per Analisi Finanziarie
 */

(function($) {
    'use strict';

    const FPAnalytics = {
        
        chartInstances: {},
        activeTab: 'projections',
        
        /**
         * Initialize
         */
        init: function() {
            // Inizializza tabs (già gestiti da tabs.js, ma assicuriamoci)
            this.initTabs();
            
            // Inizializza grafici quando tab diventa attivo
            this.initCharts();
            
            // Auto-refresh per monitoraggio
            this.initAutoRefresh();
        },
        
        /**
         * Initialize tabs
         */
        initTabs: function() {
            // Usa FPTabs se disponibile, altrimenti gestione custom
            if (typeof FPTabs !== 'undefined') {
                $(document).on('click', '.fp-fh-tab[data-tab]', function() {
                    const tabId = $(this).data('tab');
                    FPAnalytics.activeTab = tabId.replace('-tab', '');
                    
                    // Lazy load grafici quando tab diventa attivo
                    setTimeout(function() {
                        FPAnalytics.initChartForTab(FPAnalytics.activeTab);
                    }, 100);
                });
            }
        },
        
        /**
         * Initialize charts
         */
        initCharts: function() {
            // Inizializza grafico per tab attivo
            const hash = window.location.hash.replace('#', '');
            if (hash) {
                const parts = hash.split('-');
                if (parts.length > 1 && parts[0] === 'analytics') {
                    this.activeTab = parts[1];
                }
            }
            
            // Determina tab attivo dal DOM
            const $activeTab = $('.fp-fh-tab.active');
            if ($activeTab.length) {
                const tabId = $activeTab.data('tab');
                if (tabId) {
                    this.activeTab = tabId.replace('-tab', '');
                }
            }
            
            // Inizializza grafico per tab attivo
            this.initChartForTab(this.activeTab);
        },
        
        /**
         * Initialize chart for specific tab
         */
        initChartForTab: function(tab) {
            if (typeof Chart === 'undefined') {
                return;
            }
            
            switch(tab) {
                case 'projections':
                    this.initProjectionsChart();
                    break;
                case 'monitoring':
                    this.initMonitoringChart();
                    break;
                case 'stats':
                    this.initStatsCharts();
                    break;
            }
        },
        
        /**
         * Initialize projections chart
         */
        initProjectionsChart: function() {
            const canvas = document.getElementById('fp-projections-chart');
            if (!canvas) {
                return;
            }
            
            // Fetch data from API
            this.fetchProjectionsData().then(function(data) {
                FPAnalytics.createProjectionsChart(canvas, data);
            }).catch(function(error) {
                console.error('Error fetching projections data:', error);
            });
        },
        
        /**
         * Fetch projections data
         */
        fetchProjectionsData: function() {
            // Usa dati da window.fpAnalyticsData o dalla pagina
            let projections = {
                optimistic: 0,
                realistic: 0,
                pessimistic: 0
            };
            
            if (window.fpAnalyticsData && window.fpAnalyticsData.projections) {
                projections = window.fpAnalyticsData.projections;
            } else {
                // Fallback: parse dalla pagina
                const $summaryCards = $('.fp-projections-summary .fp-fh-metric-card-value');
                if ($summaryCards.length >= 3) {
                    projections.optimistic = parseFloat($summaryCards.eq(0).text().replace(/[€\s\.]/g, '').replace(',', '.')) || 0;
                    projections.realistic = parseFloat($summaryCards.eq(1).text().replace(/[€\s\.]/g, '').replace(',', '.')) || 0;
                    projections.pessimistic = parseFloat($summaryCards.eq(2).text().replace(/[€\s\.]/g, '').replace(',', '.')) || 0;
                }
            }
            
            return Promise.resolve({
                labels: ['Ottimistico', 'Realistico', 'Pessimistico'],
                data: [projections.optimistic, projections.realistic, projections.pessimistic]
            });
        },
        
        /**
         * Create projections chart
         */
        createProjectionsChart: function(canvas, data) {
            const ctx = canvas.getContext('2d');
            
            if (this.chartInstances.projections) {
                this.chartInstances.projections.destroy();
            }
            
            this.chartInstances.projections = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Proiezioni Entrate',
                        data: data.data || [],
                        backgroundColor: [
                            'rgba(0, 163, 42, 0.7)',
                            'rgba(34, 113, 177, 0.7)',
                            'rgba(214, 54, 56, 0.7)'
                        ],
                        borderColor: [
                            '#00a32a',
                            '#2271b1',
                            '#d63638'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return FPFinanceHub.formatCurrency(context.parsed.y);
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
                    }
                }
            });
        },
        
        /**
         * Initialize monitoring chart
         */
        initMonitoringChart: function() {
            const canvas = document.getElementById('fp-balance-chart');
            if (!canvas) {
                return;
            }
            
            // Fetch balance data for last 30 days
            this.fetchBalanceData().then(function(data) {
                FPAnalytics.createBalanceChart(canvas, data);
            }).catch(function(error) {
                console.error('Error fetching balance data:', error);
            });
        },
        
        /**
         * Fetch balance data (last 30 days)
         */
        fetchBalanceData: function() {
            // Per ora usa dati dalla pagina o fetch via API
            // Implementazione semplificata: usa dati disponibili
            const labels = [];
            const balances = [];
            
            // Genera labels ultimi 30 giorni
            for (let i = 29; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit' }));
                // TODO: Fetch dati reali via API
                balances.push(0);
            }
            
            return Promise.resolve({
                labels: labels,
                data: balances
            });
        },
        
        /**
         * Create balance chart
         */
        createBalanceChart: function(canvas, data) {
            const ctx = canvas.getContext('2d');
            
            if (this.chartInstances.balance) {
                this.chartInstances.balance.destroy();
            }
            
            this.chartInstances.balance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Saldo',
                        data: data.data || [],
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Saldo: ' + FPFinanceHub.formatCurrency(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            ticks: {
                                callback: function(value) {
                                    return FPFinanceHub.formatCurrency(value);
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize stats charts
         */
        initStatsCharts: function() {
            // Trend 12 mesi
            this.initTrendChart();
            
            // Category charts
            setTimeout(function() {
                FPAnalytics.initCategoryCharts();
                FPAnalytics.initBusinessPersonalChart();
            }, 200);
        },
        
        /**
         * Initialize trend chart
         */
        initTrendChart: function() {
            const canvas = document.getElementById('fp-stats-trend-chart');
            if (!canvas) {
                return;
            }
            
            // Usa dati da window.fpAnalyticsData o fetch via API
            if (window.fpAnalyticsData && window.fpAnalyticsData.trend12Months) {
                this.createTrendChart(canvas, window.fpAnalyticsData.trend12Months);
            } else if (typeof fpFinanceHub !== 'undefined' && fpFinanceHub.apiUrl) {
                $.ajax({
                    url: fpFinanceHub.apiUrl + 'stats/trend',
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', fpFinanceHub.nonce);
                    }
                }).then(function(response) {
                    if (response && response.data) {
                        FPAnalytics.createTrendChart(canvas, response.data);
                    }
                }).catch(function(error) {
                    console.error('Error fetching trend data:', error);
                });
            }
        },
        
        /**
         * Create trend chart
         */
        createTrendChart: function(canvas, data) {
            const ctx = canvas.getContext('2d');
            
            if (this.chartInstances.trend) {
                this.chartInstances.trend.destroy();
            }
            
            const labels = data.map(function(item) { return item.month; }) || [];
            
            this.chartInstances.trend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Entrate',
                            data: data.map(function(item) { return item.income; }),
                            borderColor: '#00a32a',
                            backgroundColor: 'rgba(0, 163, 42, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Uscite',
                            data: data.map(function(item) { return item.expenses; }),
                            borderColor: '#d63638',
                            backgroundColor: 'rgba(214, 54, 56, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Net',
                            data: data.map(function(item) { return item.net; }),
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
                    }
                }
            });
        },
        
        /**
         * Initialize category charts
         */
        initCategoryCharts: function() {
            // Income categories pie chart
            const incomeCanvas = document.getElementById('fp-income-categories-chart');
            if (incomeCanvas) {
                // Fetch data from page (dati potrebbero essere disponibili via data attributes)
                this.createCategoryChart(incomeCanvas, 'income');
            }
            
            // Expense categories pie chart
            const expenseCanvas = document.getElementById('fp-expense-categories-chart');
            if (expenseCanvas) {
                this.createCategoryChart(expenseCanvas, 'expense');
            }
        },
        
        /**
         * Create category pie chart
         */
        createCategoryChart: function(canvas, type) {
            const ctx = canvas.getContext('2d');
            const chartId = type === 'income' ? 'incomeCategories' : 'expenseCategories';
            
            if (this.chartInstances[chartId]) {
                this.chartInstances[chartId].destroy();
            }
            
            // Usa dati da window.fpAnalyticsData
            let categories = [];
            if (window.fpAnalyticsData) {
                if (type === 'income' && window.fpAnalyticsData.topIncomeCategories) {
                    categories = window.fpAnalyticsData.topIncomeCategories;
                } else if (type === 'expense' && window.fpAnalyticsData.topExpenseCategories) {
                    categories = window.fpAnalyticsData.topExpenseCategories;
                }
            }
            
            // Se non ci sono dati, usa array vuoto
            if (!categories || categories.length === 0) {
                categories = [];
            }
            
            const labels = categories.map(function(cat) { return cat.category || 'Senza categoria'; });
            const data = categories.map(function(cat) { return parseFloat(cat.total || 0); });
            
            if (labels.length === 0) {
                labels.push('Nessun dato disponibile');
                data.push(0);
            }
            
            this.chartInstances[chartId] = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            'rgba(34, 113, 177, 0.7)',
                            'rgba(0, 163, 42, 0.7)',
                            'rgba(219, 166, 23, 0.7)',
                            'rgba(214, 54, 56, 0.7)',
                            'rgba(138, 143, 148, 0.7)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    return label + ': ' + FPFinanceHub.formatCurrency(value);
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize business vs personal chart
         */
        initBusinessPersonalChart: function() {
            const canvas = document.getElementById('fp-business-personal-chart');
            if (!canvas) {
                return;
            }
            
            // Fetch data via API o dalla pagina
            this.createBusinessPersonalChart(canvas);
        },
        
        /**
         * Create business vs personal chart
         */
        createBusinessPersonalChart: function(canvas) {
            const ctx = canvas.getContext('2d');
            
            if (this.chartInstances.businessPersonal) {
                this.chartInstances.businessPersonal.destroy();
            }
            
            // Usa dati da window.fpAnalyticsData
            let businessData = [0, 0, 0];
            let personalData = [0, 0, 0];
            
            if (window.fpAnalyticsData && window.fpAnalyticsData.businessVsPersonal) {
                const bp = window.fpAnalyticsData.businessVsPersonal;
                businessData = [
                    parseFloat(bp.business.income || 0),
                    parseFloat(bp.business.expenses || 0),
                    parseFloat(bp.business.net || 0)
                ];
                personalData = [
                    parseFloat(bp.personal.income || 0),
                    parseFloat(bp.personal.expenses || 0),
                    parseFloat(bp.personal.net || 0)
                ];
            }
            
            this.chartInstances.businessPersonal = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Entrate', 'Uscite', 'Net'],
                    datasets: [
                        {
                            label: 'Business',
                            data: businessData,
                            backgroundColor: 'rgba(34, 113, 177, 0.7)',
                            borderColor: '#2271b1',
                            borderWidth: 2
                        },
                        {
                            label: 'Personal',
                            data: personalData,
                            backgroundColor: 'rgba(0, 163, 42, 0.7)',
                            borderColor: '#00a32a',
                            borderWidth: 2
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
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + FPFinanceHub.formatCurrency(context.parsed.y);
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
                    }
                }
            });
        },
        
        /**
         * Initialize auto refresh
         */
        initAutoRefresh: function() {
            // Auto-refresh monitoring tab ogni 5 minuti se attivo
            setInterval(function() {
                if (FPAnalytics.activeTab === 'monitoring') {
                    // Reload page o aggiorna dati via AJAX
                    // Per ora: reload page
                    // FPAnalytics.refreshMonitoringData();
                }
            }, 5 * 60 * 1000);
        },
        
        /**
         * Refresh monitoring data
         */
        refreshMonitoringData: function() {
            // TODO: Implement AJAX refresh
            location.reload();
        },
        
        /**
         * Initialize AI Insights tab
         */
        initAIInsights: function() {
            // Gestione filtri avanzati
            this.initIntelligenceFilters();
            
            // Gestione badge "Nuovo"
            this.initNewBadges();
            
            // Gestione slider predizioni
            this.initPredictionsSlider();
            
            // Gestione azioni raccomandazioni
            this.initRecommendationActions();
            
            // Gestione refresh intelligence
            $(document).on('click', '#fp-refresh-intelligence', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const originalText = $btn.html();
                const $intelligenceContent = $('.fp-intelligence-summary, .fp-fh-card').first().parent();
                
                $btn.prop('disabled', true).html('🔄 Aggiornamento...');
                
                // Mostra skeleton durante caricamento
                if (typeof FPLoading !== 'undefined' && $intelligenceContent.length) {
                    FPLoading.showSkeleton($intelligenceContent, 'card');
                } else if (typeof showLoading !== 'undefined') {
                    showLoading('Aggiornamento report Intelligence...');
                }
                
                if (typeof fpFinanceHub !== 'undefined' && fpFinanceHub.apiUrl) {
                    $.ajax({
                        url: fpFinanceHub.apiUrl + 'intelligence/analyze',
                        method: 'POST',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', fpFinanceHub.nonce);
                        },
                        data: {
                            period_days: $('#period_days').val() || 30
                        }
                    }).then(function(response) {
                        // Nascondi loading
                        if (typeof FPLoading !== 'undefined') {
                            FPLoading.hideSkeleton($intelligenceContent);
                        } else if (typeof hideLoading !== 'undefined') {
                            hideLoading();
                        }
                        
                        // Mostra toast successo
                        if (typeof fpToast !== 'undefined') {
                            fpToast.success('Report Intelligence aggiornato con successo.');
                        }
                        // Reload page per mostrare nuovi dati
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    }).catch(function(error) {
                        console.error('Error refreshing intelligence:', error);
                        
                        // Nascondi loading
                        if (typeof FPLoading !== 'undefined') {
                            FPLoading.hideSkeleton($intelligenceContent);
                        } else if (typeof hideLoading !== 'undefined') {
                            hideLoading();
                        }
                        
                        $btn.prop('disabled', false).html(originalText);
                        if (typeof fpToast !== 'undefined') {
                            fpToast.error('Errore durante l\'aggiornamento. Riprova.');
                        } else {
                            alert('Errore durante l\'aggiornamento. Riprova.');
                        }
                    });
                } else {
                    // Fallback: reload page
                    location.reload();
                }
            });
        },
        
        /**
         * Initialize intelligence filters
         */
        initIntelligenceFilters: function() {
            // Reset filtri
            $(document).on('click', '#fp-reset-filters', function(e) {
                e.preventDefault();
                const $form = $('#fp-intelligence-filters-form');
                const url = new URL(window.location);
                url.searchParams.delete('date_start');
                url.searchParams.delete('date_end');
                url.searchParams.delete('filter_severity');
                url.searchParams.delete('filter_unseen');
                url.searchParams.set('period_days', '30');
                window.location.href = url.toString();
            });
            
            // Salva preset filtri in localStorage
            $('#fp-intelligence-filters-form').on('submit', function() {
                const filters = {
                    date_start: $('#fp-date-start').val(),
                    date_end: $('#fp-date-end').val(),
                    filter_severity: $('#fp-filter-severity').val(),
                    filter_unseen: $('#fp-filter-unseen').is(':checked')
                };
                localStorage.setItem('fp_finance_hub_intelligence_filters', JSON.stringify(filters));
            });
            
            // Carica preset filtri se disponibile
            const savedFilters = localStorage.getItem('fp_finance_hub_intelligence_filters');
            if (savedFilters && !window.location.search.includes('date_start')) {
                try {
                    const filters = JSON.parse(savedFilters);
                    if (filters.date_start) $('#fp-date-start').val(filters.date_start);
                    if (filters.date_end) $('#fp-date-end').val(filters.date_end);
                    if (filters.filter_severity) $('#fp-filter-severity').val(filters.filter_severity);
                    if (filters.filter_unseen) $('#fp-filter-unseen').prop('checked', true);
                } catch (e) {
                    console.error('Error loading saved filters:', e);
                }
            }
            
            // Filtro "Solo non visti" lato client
            $(document).on('change', '#fp-filter-unseen', function() {
                const showUnseen = $(this).is(':checked');
                $('.fp-anomaly-item, .fp-insight-item-filtered').each(function() {
                    const $item = $(this);
                    const itemId = $item.data('anomaly-id') || $item.index();
                    const seen = localStorage.getItem('fp_finance_hub_seen_' + itemId);
                    
                    if (showUnseen && seen) {
                        $item.hide();
                    } else {
                        $item.show();
                    }
                });
            });
        },
        
        /**
         * Initialize "Nuovo" badges per anomalie/insights non visti
         */
        initNewBadges: function() {
            $('.fp-anomaly-item, .fp-insight-item-filtered').each(function() {
                const $item = $(this);
                const itemId = $item.data('anomaly-id') || $item.index();
                const seen = localStorage.getItem('fp_finance_hub_seen_' + itemId);
                
                if (!seen) {
                    $item.find('.fp-anomaly-new, .fp-insight-new').show();
                }
                
                // Marca come visto al click
                $item.on('click', function() {
                    localStorage.setItem('fp_finance_hub_seen_' + itemId, '1');
                    $item.find('.fp-anomaly-new, .fp-insight-new').fadeOut();
                });
            });
        },
        
        /**
         * Initialize predictions slider
         */
        initPredictionsSlider: function() {
            const $slider = $('#fp-predictions-days-slider');
            const $value = $('#fp-predictions-days-value');
            const $updateBtn = $('#fp-update-predictions');
            
            if (!$slider.length) {
                return;
            }
            
            // Update value display on slider change
            $slider.on('input', function() {
                $value.text($(this).val());
            });
            
            // Update predictions on button click
            $updateBtn.on('click', function() {
                const days = parseInt($slider.val());
                FPAnalytics.updatePredictions(days);
            });
        },
        
        /**
         * Update predictions via AJAX
         */
        updatePredictions: function(days) {
            const $content = $('#fp-predictions-content');
            const $btn = $('#fp-update-predictions');
            const originalText = $btn.html();
            
            // Show loading
            if (typeof FPLoading !== 'undefined') {
                FPLoading.showSkeleton($content, 'card');
            }
            
            $btn.prop('disabled', true).html('🔄 ' + (typeof fpFinanceHub !== 'undefined' && fpFinanceHub.i18n ? fpFinanceHub.i18n.loading : 'Caricamento...'));
            
            if (typeof fpFinanceHub !== 'undefined' && fpFinanceHub.apiUrl) {
                $.ajax({
                    url: fpFinanceHub.apiUrl + 'intelligence/predictions',
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', fpFinanceHub.nonce);
                    },
                    data: {
                        days_ahead: days
                    }
                }).then(function(response) {
                    if (response && response.data) {
                        // Update predictions display
                        FPAnalytics.renderPredictions(response.data, days);
                        
                        if (typeof fpToast !== 'undefined') {
                            fpToast.success('Predizioni aggiornate per ' + days + ' giorni.');
                        }
                    }
                }).catch(function(error) {
                    console.error('Error updating predictions:', error);
                    if (typeof fpToast !== 'undefined') {
                        fpToast.error('Errore durante l\'aggiornamento delle predizioni.');
                    }
                }).always(function() {
                    if (typeof FPLoading !== 'undefined') {
                        FPLoading.hideSkeleton($content);
                    }
                    $btn.prop('disabled', false).html(originalText);
                });
            }
        },
        
        /**
         * Render predictions in UI
         */
        renderPredictions: function(predictions, days) {
            const scenarios = predictions.scenarios || {};
            const $content = $('#fp-predictions-content');
            
            let html = '<div class="fp-fh-grid fp-fh-grid-cols-3 fp-fh-gap-6">';
            
            ['optimistic', 'realistic', 'pessimistic'].forEach(function(scenario) {
                const data = scenarios[scenario] || {};
                const cashflow = parseFloat(data.cashflow || 0);
                const cashflowClass = cashflow >= 0 ? 'fp-financial-amount-positive' : 'fp-financial-amount-negative';
                
                html += '<div class="fp-fh-card fp-fh-metric-card">';
                html += '<div class="fp-fh-metric-card-header">';
                html += '<div class="fp-fh-metric-card-title">' + scenario.charAt(0).toUpperCase() + scenario.slice(1) + '</div>';
                html += '</div>';
                html += '<div class="fp-fh-metric-card-value fp-financial-amount ' + cashflowClass + '">';
                html += 'Cash Flow: ' + cashflow.toFixed(2).replace('.', ',') + ' €';
                html += '</div>';
                html += '<div class="fp-fh-metric-card-footer">';
                html += '<div class="fp-fh-text-xs fp-fh-text-muted">';
                html += 'Entrate: ' + (parseFloat(data.income || 0).toFixed(2).replace('.', ',')) + ' €<br>';
                html += 'Uscite: ' + (parseFloat(data.expenses || 0).toFixed(2).replace('.', ',')) + ' €';
                html += '</div>';
                if (data.explanation) {
                    html += '<div class="fp-fh-text-xs fp-fh-text-muted fp-fh-mt-2" style="font-style: italic; opacity: 0.8;">';
                    html += data.explanation;
                    html += '</div>';
                }
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            
            // Show interval if available
            if (predictions.cashflow && predictions.cashflow.prediction_interval) {
                const interval = predictions.cashflow.prediction_interval;
                html += '<div class="fp-fh-mt-4 fp-fh-p-4" style="background: var(--fp-fh-color-bg-soft); border-radius: var(--fp-fh-radius);">';
                html += '<div class="fp-fh-text-sm fp-fh-font-semibold fp-fh-mb-2">';
                html += 'Intervallo Previsto Cash Flow';
                html += '</div>';
                html += '<div class="fp-fh-text-base">';
                html += (interval.low || 0).toFixed(2).replace('.', ',') + ' €';
                html += ' <span class="fp-fh-text-muted"> - </span> ';
                html += (interval.high || 0).toFixed(2).replace('.', ',') + ' €';
                html += '</div>';
                html += '</div>';
            }
            
            $content.html(html);
        },
        
        /**
         * Initialize recommendation actions
         */
        initRecommendationActions: function() {
            // Segna come risolta
            $(document).on('click', '.fp-mark-resolved', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const recId = $btn.data('rec-id');
                const $item = $btn.closest('.fp-recommendation-item');
                
                // Salva in localStorage
                localStorage.setItem('fp_finance_hub_rec_resolved_' + recId, '1');
                
                // Nascondi item con animazione
                $item.fadeOut(300, function() {
                    $item.remove();
                });
                
                if (typeof fpToast !== 'undefined') {
                    fpToast.success('Raccomandazione segnata come risolta.');
                }
            });
            
            // Ignora 30 giorni
            $(document).on('click', '.fp-ignore-recommendation', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const recId = $btn.data('rec-id');
                const $item = $btn.closest('.fp-recommendation-item');
                
                // Salva in localStorage con timestamp (30 giorni)
                const ignoreUntil = Date.now() + (30 * 24 * 60 * 60 * 1000);
                localStorage.setItem('fp_finance_hub_rec_ignored_' + recId, ignoreUntil.toString());
                
                // Nascondi item con animazione
                $item.fadeOut(300, function() {
                    $item.remove();
                });
                
                if (typeof fpToast !== 'undefined') {
                    fpToast.info('Raccomandazione ignorata per 30 giorni.');
                }
            });
            
            // Nascondi raccomandazioni risolte/ignorate al caricamento
            $('.fp-recommendation-item').each(function() {
                const $item = $(this);
                const recId = $item.data('recommendation-id');
                
                // Verifica se risolta
                if (localStorage.getItem('fp_finance_hub_rec_resolved_' + recId)) {
                    $item.hide();
                    return;
                }
                
                // Verifica se ignorata (e ancora valida)
                const ignoredUntil = localStorage.getItem('fp_finance_hub_rec_ignored_' + recId);
                if (ignoredUntil && Date.now() < parseInt(ignoredUntil)) {
                    $item.hide();
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Solo se siamo nella pagina analytics
        if ($('body').hasClass('admin_page_fp-finance-hub-analytics') || 
            $('.fp-fh-tabs[data-tab-group="analytics"]').length > 0) {
            FPAnalytics.init();
            FPAnalytics.initAIInsights();
        }
    });

    // Expose globally
    window.FPAnalytics = FPAnalytics;

})(jQuery);
