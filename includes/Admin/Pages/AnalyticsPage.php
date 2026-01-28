<?php
/**
 * Analytics Page
 * 
 * Pagina unificata per Analisi Finanziarie
 * Include: Proiezioni, Monitoraggio, Statistiche
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Services\ProjectionService;
use FP\FinanceHub\Services\StatsService;
use FP\FinanceHub\Services\BankService;
use FP\FinanceHub\Services\InvoiceService;
use FP\FinanceHub\Services\AlertService;
use FP\FinanceHub\Services\Intelligence\IntelligenceAnalysisService;
use FP\FinanceHub\Database\Models\Projection as ProjectionModel;
use FP\FinanceHub\Database\Models\Transaction as TransactionModel;
use FP\FinanceHub\Database\Models\Alert as AlertModel;

if (!defined('ABSPATH')) {
    exit;
}

class AnalyticsPage {
    
    private static $projection_service;
    private static $stats_service;
    private static $bank_service;
    private static $invoice_service;
    private static $alert_service;
    private static $intelligence_service;
    
    /**
     * Render pagina analytics
     */
    public static function render() {
        self::$projection_service = ProjectionService::get_instance();
        self::$stats_service = StatsService::get_instance();
        self::$bank_service = BankService::get_instance();
        self::$invoice_service = InvoiceService::get_instance();
        self::$alert_service = AlertService::get_instance();
        self::$intelligence_service = IntelligenceAnalysisService::get_instance();
        
        // Tab attivo (default: proiezioni)
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'projections';
        
        // Filtri periodo (default: mese corrente)
        $current_month = isset($_GET['month']) ? absint($_GET['month']) : date('n');
        $current_year = isset($_GET['year']) ? absint($_GET['year']) : date('Y');
        
        // Prepara dati per JavaScript (disponibili per tutti i tab)
        $projections = [];
        $trend_12_months = [];
        $category_stats = [];
        $top_income_categories = [];
        $top_expense_categories = [];
        $business_vs_personal = [];
        
        try {
            // Proiezioni
            if (self::$projection_service && method_exists(self::$projection_service, 'calculate_income_projections')) {
                $projections = self::$projection_service->calculate_income_projections($current_month, $current_year);
                if (!is_array($projections)) {
                    $projections = [];
                }
            } else {
                $projections = [];
            }
        } catch (\Throwable $e) {
            // Gestisci tutte le eccezioni e errori
            $projections = [];
        }
        
        try {
            // Statistiche
            $start_date = date('Y-01-01');
            $end_date = date('Y-m-t');
            
            if (method_exists(self::$stats_service, 'calculate_trend_12_months')) {
                $trend_12_months = self::$stats_service->calculate_trend_12_months(null, null);
                if (!is_array($trend_12_months)) {
                    $trend_12_months = [];
                }
            } else {
                $trend_12_months = [];
            }
            
            if (method_exists(self::$stats_service, 'calculate_category_stats')) {
                $category_stats = self::$stats_service->calculate_category_stats($start_date, $end_date, null);
                if (!is_array($category_stats) && $category_stats !== null) {
                    $category_stats = [];
                }
            } else {
                $category_stats = [];
            }
            
            if (method_exists(self::$stats_service, 'calculate_business_vs_personal')) {
                $business_vs_personal = self::$stats_service->calculate_business_vs_personal($start_date, $end_date);
                if (!is_array($business_vs_personal) && $business_vs_personal !== null) {
                    $business_vs_personal = [];
                }
            } else {
                $business_vs_personal = [];
            }
            
            if (method_exists(self::$stats_service, 'get_top_categories')) {
                $top_income_categories = self::$stats_service->get_top_categories($start_date, $end_date, 5, 'income');
                if (!is_array($top_income_categories) && $top_income_categories !== null) {
                    $top_income_categories = [];
                }
                
                $top_expense_categories = self::$stats_service->get_top_categories($start_date, $end_date, 5, 'expense');
                if (!is_array($top_expense_categories) && $top_expense_categories !== null) {
                    $top_expense_categories = [];
                }
            } else {
                $top_income_categories = [];
                $top_expense_categories = [];
            }
        } catch (\Throwable $e) {
            // Gestisci tutte le eccezioni e errori
        }
        
        ?>
        <div class="wrap fp-fh-wrapper">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1>Analisi Finanziarie</h1>
                    <p>Proiezioni, monitoraggio e statistiche complete</p>
                </div>
            </div>
            
            <!-- Tabs Navigation -->
            <div class="fp-fh-tabs" data-tab-group="analytics">
                <ul class="fp-fh-tabs-list">
                    <li>
                        <a href="#" class="fp-fh-tab <?php echo $active_tab === 'projections' ? 'active' : ''; ?>" data-tab="projections-tab">
                            📈 Proiezioni
                        </a>
                    </li>
                    <li>
                        <a href="#" class="fp-fh-tab <?php echo $active_tab === 'monitoring' ? 'active' : ''; ?>" data-tab="monitoring-tab">
                            📉 Monitoraggio
                        </a>
                    </li>
                    <li>
                        <a href="#" class="fp-fh-tab <?php echo $active_tab === 'stats' ? 'active' : ''; ?>" data-tab="stats-tab">
                            📊 Statistiche
                        </a>
                    </li>
                    <li>
                        <a href="#" class="fp-fh-tab <?php echo $active_tab === 'ai-insights' ? 'active' : ''; ?>" data-tab="ai-insights-tab">
                            🤖 Insights AI
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Tab Content: Proiezioni -->
            <div id="projections-tab" class="fp-fh-tab-content <?php echo $active_tab === 'projections' ? 'active' : ''; ?>">
                <?php self::render_projections_tab($current_month, $current_year); ?>
            </div>
            
            <!-- Tab Content: Monitoraggio -->
            <div id="monitoring-tab" class="fp-fh-tab-content <?php echo $active_tab === 'monitoring' ? 'active' : ''; ?>">
                <?php self::render_monitoring_tab(); ?>
            </div>
            
            <!-- Tab Content: Statistiche -->
            <div id="stats-tab" class="fp-fh-tab-content <?php echo $active_tab === 'stats' ? 'active' : ''; ?>">
                <?php self::render_stats_tab(); ?>
            </div>
            
            <!-- Tab Content: AI Insights -->
            <div id="ai-insights-tab" class="fp-fh-tab-content <?php echo $active_tab === 'ai-insights' ? 'active' : ''; ?>">
                <?php self::render_ai_insights_tab(); ?>
            </div>
            
            <!-- Dati per JavaScript -->
            <script type="text/javascript">
            window.fpAnalyticsData = <?php echo wp_json_encode([
                'projections' => is_array($projections) ? $projections : [],
                'trend12Months' => is_array($trend_12_months) ? $trend_12_months : [],
                'categoryStats' => is_array($category_stats) ? $category_stats : [],
                'topIncomeCategories' => is_array($top_income_categories) ? array_map(function($item) {
                    return is_object($item) ? [
                        'category' => $item->category ?? '',
                        'total' => floatval($item->total ?? 0)
                    ] : $item;
                }, $top_income_categories) : [],
                'topExpenseCategories' => is_array($top_expense_categories) ? array_map(function($item) {
                    return is_object($item) ? [
                        'category' => $item->category ?? '',
                        'total' => floatval($item->total ?? 0)
                    ] : $item;
                }, $top_expense_categories) : [],
                'businessVsPersonal' => is_array($business_vs_personal) ? $business_vs_personal : []
            ]); ?>;
            </script>
        </div>
        <?php
    }
    
    /**
     * Render tab Proiezioni
     */
    private static function render_projections_tab($month, $year) {
        // Calcola proiezioni per il mese selezionato
        if (!self::$projection_service) {
            echo '<p>Servizio proiezioni non disponibile.</p>';
            return;
        }
        
        try {
            $projections = self::$projection_service->calculate_income_projections($month, $year);
            if (!is_array($projections)) {
                $projections = [];
            }
        } catch (\Throwable $e) {
            $projections = [];
        }
        
        // Ottieni proiezioni per periodo (3 mesi: mese corrente, precedente, successivo)
        $start_month = $month - 1;
        $start_year = $year;
        if ($start_month < 1) {
            $start_month = 12;
            $start_year--;
        }
        $end_month = $month + 1;
        $end_year = $year;
        if ($end_month > 12) {
            $end_month = 1;
            $end_year++;
        }
        
        try {
            $realistic_projections = ProjectionModel::get_by_period($start_month, $start_year, $end_month, $end_year, 'realistic');
            if (!is_array($realistic_projections)) {
                $realistic_projections = [];
            }
        } catch (\Throwable $e) {
            $realistic_projections = [];
        }
        
        // Ottieni fatture non pagate per calcolo potenziale
        try {
            if (self::$invoice_service) {
                $unpaid_invoices = self::$invoice_service->get_unpaid();
                if (!is_array($unpaid_invoices)) {
                    $unpaid_invoices = [];
                }
                $potential_income = self::$invoice_service->calculate_potential_income();
                if (!is_numeric($potential_income)) {
                    $potential_income = 0;
                }
            } else {
                $unpaid_invoices = [];
                $potential_income = 0;
            }
        } catch (\Throwable $e) {
            $unpaid_invoices = [];
            $potential_income = 0;
        }
        
        ?>
        <!-- Filters -->
        <div class="fp-analytics-filters fp-fh-card fp-fh-mb-6">
            <div class="fp-fh-card-body">
                <form method="get" class="fp-fh-flex fp-fh-items-center fp-fh-gap-4">
                    <input type="hidden" name="page" value="fp-finance-hub-analytics">
                    <input type="hidden" name="tab" value="projections">
                    
                    <div class="fp-fh-form-group">
                        <label for="month" class="fp-fh-form-label">Mese</label>
                        <select name="month" id="month" class="fp-fh-select">
                            <?php for ($m = 1; $m <= 12; $m++) : ?>
                                <option value="<?php echo $m; ?>" <?php selected($month, $m); ?>>
                                    <?php echo date_i18n('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label for="year" class="fp-fh-form-label">Anno</label>
                        <select name="year" id="year" class="fp-fh-select">
                            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++) : ?>
                                <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <button type="submit" class="fp-fh-btn fp-fh-btn-primary">
                            Aggiorna
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="fp-projections-summary fp-fh-grid fp-fh-grid-cols-3 fp-fh-mb-6">
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">Ottimistico</div>
                </div>
                <div class="fp-fh-metric-card-value fp-financial-amount fp-financial-amount-positive">
                    <?php echo number_format($projections['optimistic'] ?? 0, 2, ',', '.') . ' €'; ?>
                </div>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">Realistico</div>
                </div>
                <div class="fp-fh-metric-card-value fp-financial-amount fp-financial-amount-positive">
                    <?php echo number_format($projections['realistic'] ?? 0, 2, ',', '.') . ' €'; ?>
                </div>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">Pessimistico</div>
                </div>
                <div class="fp-fh-metric-card-value fp-financial-amount fp-financial-amount-positive">
                    <?php echo number_format($projections['pessimistic'] ?? 0, 2, ',', '.') . ' €'; ?>
                </div>
            </div>
        </div>
        
        <!-- Grafico Proiezioni -->
        <div class="fp-fh-card fp-fh-mb-6">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">Proiezioni Entrate per Scenario</h2>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-chart-container fp-fh-chart-container-lg">
                    <canvas id="fp-projections-chart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tabella Proiezioni -->
        <div class="fp-fh-card">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">Dettaglio Proiezioni</h2>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-table-wrapper">
                    <table class="fp-fh-table fp-fh-table-striped">
                        <thead>
                            <tr>
                                <th>Mese</th>
                                <th>Scenario</th>
                                <th>Proiezione Entrate</th>
                                <th>Entrate Reali</th>
                                <th>Differenza</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($realistic_projections)) : ?>
                                <?php foreach ($realistic_projections as $proj) : ?>
                                    <tr>
                                        <td><?php echo date_i18n('F Y', mktime(0, 0, 0, $proj->month, 1, $proj->year)); ?></td>
                                        <td><span class="fp-fh-badge fp-fh-badge-soft-info"><?php echo esc_html($proj->scenario); ?></span></td>
                                        <td class="fp-financial-amount"><?php echo number_format(floatval($proj->projected_income), 2, ',', '.') . ' €'; ?></td>
                                        <td class="fp-financial-amount"><?php echo number_format(floatval($proj->actual_income ?: 0), 2, ',', '.') . ' €'; ?></td>
                                        <td class="fp-financial-amount <?php echo floatval($proj->actual_income ?: 0) > 0 ? 'fp-financial-amount-positive' : ''; ?>">
                                            <?php 
                                            $diff = floatval($proj->projected_income) - floatval($proj->actual_income ?: 0);
                                            echo ($diff >= 0 ? '+' : '') . number_format($diff, 2, ',', '.') . ' €';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5" class="fp-fh-table-empty">Nessuna proiezione disponibile per questo periodo.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tab Monitoraggio
     */
    private static function render_monitoring_tab() {
        // Dati monitoraggio
        $total_balance = self::$bank_service->get_total_balance();
        $accounts = self::$bank_service->get_active_accounts();
        
        // Cash flow mese corrente
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        $cashflow_data = self::$bank_service->calculate_totals(null, $month_start, $month_end);
        $cashflow = floatval($cashflow_data->total_income ?? 0) - floatval($cashflow_data->total_expenses ?? 0);
        
        // Fatture non pagate
        $unpaid_invoices = self::$invoice_service->get_unpaid();
        $unpaid_count = count($unpaid_invoices);
        $potential_income = self::$invoice_service->calculate_potential_income();
        
        // Alert attivi
        $active_alerts = AlertModel::get_active(['acknowledged' => false]);
        $alerts_count = count($active_alerts);
        
        // Movimenti recenti
        $recent_transactions = self::$bank_service->get_recent_transactions(null, 20);
        
        ?>
        <!-- KPI Cards -->
        <div class="fp-monitoring-kpis fp-fh-grid fp-fh-grid-cols-4 fp-fh-mb-6">
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">💰 Saldo Totale</div>
                    <div class="fp-fh-metric-card-icon">💰</div>
                </div>
                <div class="fp-fh-metric-card-value fp-financial-amount">
                    <?php echo number_format($total_balance, 2, ',', '.') . ' €'; ?>
                </div>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">💸 Cash Flow Mese</div>
                    <div class="fp-fh-metric-card-icon">💸</div>
                </div>
                <div class="fp-fh-metric-card-value fp-financial-amount <?php echo $cashflow >= 0 ? 'fp-financial-amount-positive' : 'fp-financial-amount-negative'; ?>">
                    <?php echo ($cashflow >= 0 ? '+' : '') . number_format($cashflow, 2, ',', '.') . ' €'; ?>
                </div>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">📄 Fatture Non Pagate</div>
                    <div class="fp-fh-metric-card-icon">📄</div>
                </div>
                <div class="fp-fh-metric-card-value">
                    <?php echo $unpaid_count; ?>
                </div>
                <div class="fp-fh-metric-card-footer">
                    <span class="fp-fh-metric-card-trend">Totale: <?php echo number_format($potential_income, 2, ',', '.') . ' €'; ?></span>
                </div>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card fp-fh-alert-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">⚠️ Alert Attivi</div>
                    <div class="fp-fh-metric-card-icon">⚠️</div>
                </div>
                <div class="fp-fh-metric-card-value">
                    <?php echo $alerts_count; ?>
                </div>
            </div>
        </div>
        
        <!-- Grafico Saldo -->
        <div class="fp-fh-card fp-fh-mb-6">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">Saldo Ultimi 30 Giorni</h2>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-chart-container fp-fh-chart-container-lg">
                    <canvas id="fp-balance-chart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Movimenti Recenti e Alert -->
        <div class="fp-fh-grid fp-fh-grid-cols-2 fp-fh-gap-6">
            <!-- Movimenti Recenti -->
            <div class="fp-fh-card">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">Movimenti Recenti</h2>
                </div>
                <div class="fp-fh-card-body">
                    <div class="fp-fh-table-wrapper">
                        <?php if (!empty($recent_transactions)) : ?>
                            <table class="fp-fh-table fp-fh-table-striped">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Descrizione</th>
                                        <th>Importo</th>
                                        <th>Categoria</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_transactions as $transaction) : ?>
                                        <tr>
                                            <td><?php echo date_i18n('d/m/Y', strtotime($transaction->transaction_date)); ?></td>
                                            <td><?php echo esc_html(mb_substr($transaction->description, 0, 40)); ?><?php echo mb_strlen($transaction->description) > 40 ? '...' : ''; ?></td>
                                            <td class="fp-financial-amount <?php echo floatval($transaction->amount) >= 0 ? 'fp-financial-amount-positive' : 'fp-financial-amount-negative'; ?>">
                                                <?php echo number_format(floatval($transaction->amount), 2, ',', '.') . ' €'; ?>
                                            </td>
                                            <td>
                                                <?php if ($transaction->category) : ?>
                                                    <span class="fp-fh-badge fp-fh-badge-soft-info"><?php echo esc_html($transaction->category); ?></span>
                                                <?php else : ?>
                                                    <span class="fp-fh-badge">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <div class="fp-fh-table-empty">
                                Nessun movimento recente disponibile.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Alert Attivi -->
            <div class="fp-fh-card">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">Alert Attivi</h2>
                </div>
                <div class="fp-fh-card-body">
                    <?php if (!empty($active_alerts)) : ?>
                        <div class="fp-alerts-list">
                            <?php foreach ($active_alerts as $alert) : ?>
                                <div class="fp-fh-notice fp-fh-notice-<?php echo esc_attr($alert->severity); ?> fp-fh-mb-3">
                                    <div class="fp-fh-notice-icon">⚠️</div>
                                    <div class="fp-fh-notice-content">
                                        <div class="fp-fh-notice-title"><?php echo esc_html($alert->alert_type); ?></div>
                                        <div class="fp-fh-notice-message"><?php echo esc_html($alert->message); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="fp-fh-text-center fp-fh-p-8">
                            <div style="font-size: 4rem; margin-bottom: var(--fp-fh-spacing-4); opacity: 0.5;">✅</div>
                            <h3 class="fp-clients-empty-title">Nessun alert attivo</h3>
                            <p class="fp-clients-empty-text">Tutto procede secondo i piani!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tab Statistiche
     */
    private static function render_stats_tab() {
        // Periodo default: tutti i dati disponibili (nessun limite)
        // Se l'utente vuole vedere tutti i dati o non ha specificato date, mostra tutto
        if (isset($_GET['all_data']) && $_GET['all_data'] === '1') {
            $start_date = '2000-01-01'; // Data molto lontana per includere tutti i dati
            $end_date = date('Y-m-d', strtotime('+1 year')); // Anno futuro per includere tutto
        } elseif (isset($_GET['start_date']) || isset($_GET['end_date'])) {
            // Se l'utente ha specificato date, usa quelle
            $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '2000-01-01';
            $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d', strtotime('+1 year'));
        } else {
            // Default: mostra tutti i dati (nessun limite)
            $start_date = '2000-01-01';
            $end_date = date('Y-m-d', strtotime('+1 year'));
        }
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : null;
        $account_id = isset($_GET['account_id']) ? absint($_GET['account_id']) : null;
        
        if (!self::$stats_service) {
            echo '<p>Servizio statistiche non disponibile.</p>';
            return;
        }
        
        try {
            // Statistiche periodo corrente
            $current_stats = self::$stats_service->calculate_period_stats($start_date, $end_date, $account_id, $type);
            if (!$current_stats) {
                $current_stats = (object)['total_income' => 0, 'total_expenses' => 0, 'transaction_count' => 0];
            }
            
            // Trend 12 mesi
            $trend_12_months = self::$stats_service->calculate_trend_12_months($account_id, $type);
            if (!is_array($trend_12_months)) {
                $trend_12_months = [];
            }
            
            // Statistiche categorie
            $category_stats = self::$stats_service->calculate_category_stats($start_date, $end_date, $type);
            if (!is_array($category_stats) && $category_stats !== null) {
                $category_stats = [];
            }
            
            // Business vs Personal
            $business_vs_personal = self::$stats_service->calculate_business_vs_personal($start_date, $end_date);
            if (!is_array($business_vs_personal) && $business_vs_personal !== null) {
                $business_vs_personal = [];
            }
            
            // Top categorie
            $top_income_categories = self::$stats_service->get_top_categories($start_date, $end_date, 5, 'income');
            if (!is_array($top_income_categories) && $top_income_categories !== null) {
                $top_income_categories = [];
            }
            
            $top_expense_categories = self::$stats_service->get_top_categories($start_date, $end_date, 5, 'expense');
            if (!is_array($top_expense_categories) && $top_expense_categories !== null) {
                $top_expense_categories = [];
            }
            
            // Calcolo variazione
            $previous_start = date('Y-m-d', strtotime($start_date . ' -1 year'));
            $previous_end = date('Y-m-d', strtotime($end_date . ' -1 year'));
            $previous_stats = self::$stats_service->calculate_period_stats($previous_start, $previous_end, $account_id, $type);
            if (!$previous_stats) {
                $previous_stats = (object)['total_income' => 0, 'total_expenses' => 0, 'transaction_count' => 0];
            }
        } catch (\Throwable $e) {
            $current_stats = (object)['total_income' => 0, 'total_expenses' => 0, 'transaction_count' => 0];
            $trend_12_months = [];
            $category_stats = [];
            $business_vs_personal = [];
            $top_income_categories = [];
            $top_expense_categories = [];
            $previous_stats = (object)['total_income' => 0, 'total_expenses' => 0, 'transaction_count' => 0];
        }
        
        $income_variation = 0;
        $expense_variation = 0;
        if ($previous_stats && floatval($previous_stats->total_income ?: 0) > 0) {
            $income_variation = ((floatval($current_stats->total_income ?: 0) - floatval($previous_stats->total_income ?: 0)) / floatval($previous_stats->total_income ?: 0)) * 100;
        }
        if ($previous_stats && floatval($previous_stats->total_expenses ?: 0) > 0) {
            $expense_variation = ((floatval($current_stats->total_expenses ?: 0) - floatval($previous_stats->total_expenses ?: 0)) / floatval($previous_stats->total_expenses ?: 0)) * 100;
        }
        
        $net = floatval($current_stats->total_income ?: 0) - floatval($current_stats->total_expenses ?: 0);
        
        ?>
        <!-- Filters -->
        <div class="fp-analytics-filters fp-fh-card fp-fh-mb-6">
            <div class="fp-fh-card-body">
                <form method="get" class="fp-fh-flex fp-fh-items-center fp-fh-gap-4 fp-fh-flex-wrap">
                    <input type="hidden" name="page" value="fp-finance-hub-analytics">
                    <input type="hidden" name="tab" value="stats">
                    
                    <div class="fp-fh-form-group">
                        <label for="start_date" class="fp-fh-form-label">Data Inizio</label>
                        <input type="date" name="start_date" id="start_date" class="fp-fh-input" value="<?php echo esc_attr($start_date); ?>">
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label for="end_date" class="fp-fh-form-label">Data Fine</label>
                        <input type="date" name="end_date" id="end_date" class="fp-fh-input" value="<?php echo esc_attr($end_date); ?>">
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label class="fp-fh-form-label">
                            <input type="checkbox" name="all_data" value="1" <?php checked(isset($_GET['all_data']) && $_GET['all_data'] === '1'); ?>>
                            Mostra tutti i dati (ignora filtri data)
                        </label>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label for="type" class="fp-fh-form-label">Tipo</label>
                        <select name="type" id="type" class="fp-fh-select">
                            <option value="">Tutti</option>
                            <option value="business" <?php selected($type, 'business'); ?>>Business</option>
                            <option value="personal" <?php selected($type, 'personal'); ?>>Personal</option>
                        </select>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <button type="submit" class="fp-fh-btn fp-fh-btn-primary">
                            Aggiorna
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="fp-stats-summary fp-fh-grid fp-fh-grid-cols-4 fp-fh-mb-6">
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">💰 Entrate</div>
                </div>
                <div class="fp-fh-metric-card-value fp-financial-amount fp-financial-amount-positive">
                    <?php echo number_format(floatval($current_stats->total_income ?: 0), 2, ',', '.') . ' €'; ?>
                </div>
                <?php if ($income_variation != 0) : ?>
                    <div class="fp-fh-metric-card-footer">
                        <span class="fp-fh-metric-card-trend <?php echo $income_variation >= 0 ? 'fp-fh-metric-card-trend-up' : 'fp-fh-metric-card-trend-down'; ?>">
                            <?php echo $income_variation >= 0 ? '↑' : '↓'; ?> <?php echo number_format(abs($income_variation), 1); ?>%
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">💸 Uscite</div>
                </div>
                <div class="fp-fh-metric-card-value fp-financial-amount fp-financial-amount-negative">
                    <?php echo number_format(floatval($current_stats->total_expenses ?: 0), 2, ',', '.') . ' €'; ?>
                </div>
                <?php if ($expense_variation != 0) : ?>
                    <div class="fp-fh-metric-card-footer">
                        <span class="fp-fh-metric-card-trend <?php echo $expense_variation >= 0 ? 'fp-fh-metric-card-trend-up' : 'fp-fh-metric-card-trend-down'; ?>">
                            <?php echo $expense_variation >= 0 ? '↑' : '↓'; ?> <?php echo number_format(abs($expense_variation), 1); ?>%
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">📊 Net</div>
                </div>
                <div class="fp-fh-metric-card-value fp-financial-amount <?php echo $net >= 0 ? 'fp-financial-amount-positive' : 'fp-financial-amount-negative'; ?>">
                    <?php echo number_format($net, 2, ',', '.') . ' €'; ?>
                </div>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">📝 Transazioni</div>
                </div>
                <div class="fp-fh-metric-card-value">
                    <?php echo absint($current_stats->transaction_count ?: 0); ?>
                </div>
            </div>
        </div>
        
        <!-- Grafico Trend 12 Mesi -->
        <div class="fp-fh-card fp-fh-mb-6">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">Trend 12 Mesi</h2>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-chart-container fp-fh-chart-container-lg">
                    <canvas id="fp-stats-trend-chart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Grafici Categorie e Business vs Personal -->
        <div class="fp-fh-grid fp-fh-grid-cols-2 fp-fh-gap-6 fp-fh-mb-6">
            <!-- Grafico Categorie Entrate -->
            <div class="fp-fh-card">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">Top 5 Categorie Entrate</h2>
                </div>
                <div class="fp-fh-card-body">
                    <div class="fp-fh-chart-container fp-fh-chart-container-sm">
                        <canvas id="fp-income-categories-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Grafico Categorie Uscite -->
            <div class="fp-fh-card">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">Top 5 Categorie Uscite</h2>
                </div>
                <div class="fp-fh-card-body">
                    <div class="fp-fh-chart-container fp-fh-chart-container-sm">
                        <canvas id="fp-expense-categories-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Grafico Business vs Personal -->
        <div class="fp-fh-card fp-fh-mb-6">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">Business vs Personal</h2>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-chart-container fp-fh-chart-container-lg">
                    <canvas id="fp-business-personal-chart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tabella Statistiche Dettagliate -->
        <div class="fp-fh-card">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">Statistiche per Periodo</h2>
            </div>
            <div class="fp-fh-card-body">
                <div class="fp-fh-table-wrapper">
                    <table class="fp-fh-table fp-fh-table-striped">
                        <thead>
                            <tr>
                                <th>Mese</th>
                                <th>Entrate</th>
                                <th>Uscite</th>
                                <th>Net</th>
                                <th>Transazioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($trend_12_months)) : ?>
                                <?php foreach ($trend_12_months as $month_data) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($month_data['month']); ?></strong></td>
                                        <td class="fp-financial-amount fp-financial-amount-positive"><?php echo number_format($month_data['income'], 2, ',', '.') . ' €'; ?></td>
                                        <td class="fp-financial-amount fp-financial-amount-negative"><?php echo number_format($month_data['expenses'], 2, ',', '.') . ' €'; ?></td>
                                        <td class="fp-financial-amount <?php echo $month_data['net'] >= 0 ? 'fp-financial-amount-positive' : 'fp-financial-amount-negative'; ?>">
                                            <?php echo number_format($month_data['net'], 2, ',', '.') . ' €'; ?>
                                        </td>
                                        <td>-</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5" class="fp-fh-table-empty">Nessuna statistica disponibile per questo periodo.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tab AI Insights
     */
    private static function render_ai_insights_tab() {
        // Periodo: da date range o default ultimi 30 giorni
        $date_start = isset($_GET['date_start']) ? sanitize_text_field($_GET['date_start']) : date('Y-m-d', strtotime('-30 days'));
        $date_end = isset($_GET['date_end']) ? sanitize_text_field($_GET['date_end']) : date('Y-m-d');
        $period_days = (strtotime($date_end) - strtotime($date_start)) / 86400;
        $period_days = max(1, round($period_days));
        
        // Filtri
        $filter_severity = isset($_GET['filter_severity']) ? sanitize_text_field($_GET['filter_severity']) : '';
        $filter_unseen = isset($_GET['filter_unseen']) && $_GET['filter_unseen'] === '1';
        
        // Genera report intelligenza
        try {
            if (!isset(self::$intelligence_service) || !self::$intelligence_service) {
                self::$intelligence_service = IntelligenceAnalysisService::get_instance();
            }
            $report = self::$intelligence_service->generate_intelligence_report($period_days);
        } catch (\Throwable $e) {
            $report = [
                'summary' => [],
                'anomalies' => [],
                'patterns' => [],
                'insights' => [],
                'recommendations' => [],
                'predictions' => [],
            ];
        }
        
        $summary = $report['summary'] ?? [];
        $anomalies = $report['anomalies'] ?? [];
        $patterns = $report['patterns'] ?? [];
        $insights = $report['insights'] ?? [];
        $recommendations = $report['recommendations'] ?? [];
        $predictions = $report['predictions'] ?? [];
        
        ?>
        <!-- Filters -->
        <div class="fp-analytics-filters fp-fh-card fp-fh-mb-6">
            <div class="fp-fh-card-body">
                <form method="get" id="fp-intelligence-filters-form" class="fp-fh-flex fp-fh-items-center fp-fh-gap-4 fp-fh-flex-wrap">
                    <input type="hidden" name="page" value="fp-finance-hub-analytics">
                    <input type="hidden" name="tab" value="ai-insights">
                    
                    <div class="fp-fh-form-group">
                        <label for="fp-date-start" class="fp-fh-form-label"><?php echo esc_html__('Da', 'fp-finance-hub'); ?></label>
                        <input type="date" name="date_start" id="fp-date-start" class="fp-fh-input" 
                               value="<?php echo esc_attr(isset($_GET['date_start']) ? sanitize_text_field($_GET['date_start']) : date('Y-m-d', strtotime("-{$period_days} days"))); ?>">
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label for="fp-date-end" class="fp-fh-form-label"><?php echo esc_html__('A', 'fp-finance-hub'); ?></label>
                        <input type="date" name="date_end" id="fp-date-end" class="fp-fh-input" 
                               value="<?php echo esc_attr(isset($_GET['date_end']) ? sanitize_text_field($_GET['date_end']) : date('Y-m-d')); ?>">
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label for="fp-filter-severity" class="fp-fh-form-label"><?php echo esc_html__('Severity', 'fp-finance-hub'); ?></label>
                        <select name="filter_severity" id="fp-filter-severity" class="fp-fh-select">
                            <option value=""><?php echo esc_html__('Tutte', 'fp-finance-hub'); ?></option>
                            <option value="critical" <?php selected(isset($_GET['filter_severity']) ? $_GET['filter_severity'] : '', 'critical'); ?>><?php echo esc_html__('Critical', 'fp-finance-hub'); ?></option>
                            <option value="high" <?php selected(isset($_GET['filter_severity']) ? $_GET['filter_severity'] : '', 'high'); ?>><?php echo esc_html__('High', 'fp-finance-hub'); ?></option>
                            <option value="medium" <?php selected(isset($_GET['filter_severity']) ? $_GET['filter_severity'] : '', 'medium'); ?>><?php echo esc_html__('Medium', 'fp-finance-hub'); ?></option>
                            <option value="low" <?php selected(isset($_GET['filter_severity']) ? $_GET['filter_severity'] : '', 'low'); ?>><?php echo esc_html__('Low', 'fp-finance-hub'); ?></option>
                        </select>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <label class="fp-fh-form-label">
                            <input type="checkbox" name="filter_unseen" id="fp-filter-unseen" value="1" 
                                   <?php checked(isset($_GET['filter_unseen'])); ?>>
                            <?php echo esc_html__('Solo non visti', 'fp-finance-hub'); ?>
                        </label>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <button type="submit" class="fp-fh-btn fp-fh-btn-primary">
                            <?php echo esc_html__('Aggiorna', 'fp-finance-hub'); ?>
                        </button>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <button type="button" class="fp-fh-btn fp-fh-btn-secondary" id="fp-reset-filters">
                            <?php echo esc_html__('Reset', 'fp-finance-hub'); ?>
                        </button>
                    </div>
                    
                    <div class="fp-fh-form-group">
                        <button type="button" class="fp-fh-btn fp-fh-btn-secondary" id="fp-refresh-intelligence">
                            🔄 <?php echo esc_html__('Refresh Analisi', 'fp-finance-hub'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="fp-intelligence-summary fp-fh-grid fp-fh-grid-cols-6 fp-fh-mb-6">
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">⚠️ Anomalie</div>
                </div>
                <div class="fp-fh-metric-card-value">
                    <?php echo absint($summary['total_anomalies'] ?? 0); ?>
                </div>
                <?php if (($summary['critical_anomalies'] ?? 0) > 0) : ?>
                    <div class="fp-fh-metric-card-footer">
                        <span class="fp-fh-badge fp-fh-badge-error"><?php echo absint($summary['critical_anomalies']); ?> critiche</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">📈 Pattern</div>
                </div>
                <div class="fp-fh-metric-card-value">
                    <?php echo absint($summary['total_patterns'] ?? 0); ?>
                </div>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">💡 Insights</div>
                </div>
                <div class="fp-fh-metric-card-value">
                    <?php echo absint($summary['total_insights'] ?? 0); ?>
                </div>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">✅ Raccomandazioni</div>
                </div>
                <div class="fp-fh-metric-card-value">
                    <?php echo absint($summary['total_recommendations'] ?? 0); ?>
                </div>
                <?php if (($summary['high_priority_recommendations'] ?? 0) > 0) : ?>
                    <div class="fp-fh-metric-card-footer">
                        <span class="fp-fh-badge fp-fh-badge-warning"><?php echo absint($summary['high_priority_recommendations']); ?> prioritarie</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">🔮 Predizioni</div>
                </div>
                <div class="fp-fh-metric-card-value">
                    ✓ Attive
                </div>
            </div>
            
            <div class="fp-fh-card fp-fh-metric-card">
                <div class="fp-fh-metric-card-header">
                    <div class="fp-fh-metric-card-title">
                        🧠 <?php echo esc_html__('Intelligence Score', 'fp-finance-hub'); ?>
                        <span class="fp-fh-tooltip">
                            <span class="fp-fh-help-icon" title="<?php echo esc_attr__('Score 0-100 basato su confidence insights e penalità anomalie', 'fp-finance-hub'); ?>">?</span>
                            <span class="fp-fh-tooltip-content"><?php echo esc_html__('Score 0-100 che combina la qualità degli insights (confidence) e penalità per anomalie rilevate. Più alto è meglio.', 'fp-finance-hub'); ?></span>
                        </span>
                    </div>
                </div>
                <div class="fp-fh-metric-card-value">
                    <?php
                    $intelligence_score = absint($summary['intelligence_score'] ?? 0);
                    $score_class = 'fp-fh-badge-success';
                    if ($intelligence_score < 50) {
                        $score_class = 'fp-fh-badge-error';
                    } elseif ($intelligence_score < 75) {
                        $score_class = 'fp-fh-badge-warning';
                    }
                    ?>
                    <div class="fp-intelligence-gauge" data-score="<?php echo esc_attr($intelligence_score); ?>">
                        <svg class="fp-gauge-svg" viewBox="0 0 120 120" width="120" height="120">
                            <!-- Background arc -->
                            <circle class="fp-gauge-background" cx="60" cy="60" r="50" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                            <!-- Progress arc -->
                            <?php
                            $circumference = 2 * M_PI * 50;
                            $offset = $circumference * (1 - $intelligence_score / 100);
                            $stroke_color = $intelligence_score < 50 ? '#dc2626' : ($intelligence_score < 75 ? '#d97706' : '#059669');
                            ?>
                            <circle class="fp-gauge-progress" cx="60" cy="60" r="50" fill="none" 
                                    stroke="<?php echo esc_attr($stroke_color); ?>" 
                                    stroke-width="8" 
                                    stroke-dasharray="<?php echo esc_attr($circumference); ?>" 
                                    stroke-dashoffset="<?php echo esc_attr($offset); ?>"
                                    stroke-linecap="round"
                                    transform="rotate(-90 60 60)"
                                    style="transition: stroke-dashoffset 0.5s ease;"/>
                            <!-- Score text -->
                            <text class="fp-gauge-text" x="60" y="68" text-anchor="middle" font-size="28" font-weight="bold" fill="<?php echo esc_attr($stroke_color); ?>">
                                <?php echo esc_html($intelligence_score); ?>
                            </text>
                            <text class="fp-gauge-label" x="60" y="85" text-anchor="middle" font-size="10" fill="#6b7280">
                                <?php echo esc_html__('Score', 'fp-finance-hub'); ?>
                            </text>
                        </svg>
                    </div>
                </div>
                <div class="fp-fh-metric-card-footer">
                    <span class="fp-fh-text-xs fp-fh-text-muted">
                        <?php
                        if ($intelligence_score >= 75) {
                            echo esc_html__('Eccellente', 'fp-finance-hub');
                        } elseif ($intelligence_score >= 50) {
                            echo esc_html__('Buono', 'fp-finance-hub');
                        } else {
                            echo esc_html__('Da migliorare', 'fp-finance-hub');
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Anomalie -->
        <div class="fp-fh-card fp-fh-mb-6">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">⚠️ Anomalie Rilevate</h2>
            </div>
            <div class="fp-fh-card-body">
                <?php 
                // Applica filtri
                $filtered_anomalies = $anomalies;
                if ($filter_severity) {
                    $filtered_anomalies = array_filter($filtered_anomalies, function($a) use ($filter_severity) {
                        return ($a['severity'] ?? 'low') === $filter_severity;
                    });
                }
                if ($filter_unseen) {
                    // Filtra per anomalie non viste (usando localStorage lato client)
                    // Per ora mostra tutte, il filtro sarà applicato via JS
                }
                $filtered_anomalies = array_values($filtered_anomalies);
                ?>
                <?php if (!empty($filtered_anomalies)) : ?>
                    <div class="fp-anomalies-list" data-filter-severity="<?php echo esc_attr($filter_severity); ?>" data-filter-unseen="<?php echo $filter_unseen ? '1' : '0'; ?>">
                        <?php foreach (array_slice($filtered_anomalies, 0, 10) as $idx => $anomaly) : ?>
                            <div class="fp-fh-notice fp-fh-notice-<?php echo esc_attr($anomaly['severity'] ?? 'low'); ?> fp-fh-mb-3 fp-anomaly-item" 
                                 data-severity="<?php echo esc_attr($anomaly['severity'] ?? 'low'); ?>"
                                 data-anomaly-id="<?php echo esc_attr($idx); ?>">
                                <div class="fp-fh-notice-icon">⚠️</div>
                                <div class="fp-fh-notice-content">
                                    <div class="fp-fh-notice-title">
                                        <?php echo esc_html($anomaly['message'] ?? ''); ?>
                                        <?php if (isset($anomaly['confidence'])) : ?>
                                            <span class="fp-fh-badge fp-fh-badge-soft-info">Confidence: <?php echo number_format($anomaly['confidence'] * 100, 0); ?>%</span>
                                        <?php endif; ?>
                                        <span class="fp-fh-badge fp-fh-badge-new fp-anomaly-new" style="display: none;"><?php echo esc_html__('Nuovo', 'fp-finance-hub'); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="fp-fh-text-center fp-fh-p-8">
                        <div style="font-size: 4rem; margin-bottom: var(--fp-fh-spacing-4); opacity: 0.5;">✅</div>
                        <h3 class="fp-clients-empty-title">Nessuna anomalia rilevata</h3>
                        <p class="fp-clients-empty-text">Tutto procede normalmente!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Raccomandazioni e Insights -->
        <div class="fp-fh-grid fp-fh-grid-cols-2 fp-fh-gap-6 fp-fh-mb-6">
            <!-- Raccomandazioni -->
            <div class="fp-fh-card">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">✅ Raccomandazioni</h2>
                </div>
                <div class="fp-fh-card-body">
                    <?php if (!empty($recommendations)) : ?>
                        <div class="fp-recommendations-list">
                            <?php foreach (array_slice($recommendations, 0, 5) as $idx => $rec) : ?>
                                <div class="fp-recommendation-item fp-fh-mb-4 fp-fh-p-4" 
                                     style="border-left: 4px solid var(--fp-fh-color-<?php echo esc_attr($rec['priority'] ?? 'low'); ?>); background: var(--fp-fh-color-bg-soft);"
                                     data-recommendation-id="<?php echo esc_attr($idx); ?>">
                                    <div class="fp-fh-flex fp-fh-items-center fp-fh-gap-2 fp-fh-mb-2">
                                        <h4 class="fp-fh-text-lg fp-fh-font-semibold"><?php echo esc_html($rec['title'] ?? ''); ?></h4>
                                        <span class="fp-fh-badge fp-fh-badge-<?php echo esc_attr($rec['priority'] ?? 'low'); ?>"><?php echo esc_html(ucfirst($rec['priority'] ?? 'low')); ?></span>
                                        <?php if (!empty($rec['trigger_type'])) : ?>
                                            <span class="fp-fh-badge fp-fh-badge-soft-info fp-fh-text-xs">
                                                <?php echo esc_html__('da:', 'fp-finance-hub'); ?> <?php echo esc_html(self::translate_trigger_type($rec['trigger_type'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="fp-fh-text-sm fp-fh-text-muted"><?php echo esc_html($rec['message'] ?? ''); ?></p>
                                    <div class="fp-recommendation-actions">
                                        <button type="button" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-secondary fp-mark-resolved" 
                                                data-rec-id="<?php echo esc_attr($idx); ?>">
                                            ✓ <?php echo esc_html__('Segna come risolta', 'fp-finance-hub'); ?>
                                        </button>
                                        <button type="button" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-secondary fp-ignore-recommendation" 
                                                data-rec-id="<?php echo esc_attr($idx); ?>">
                                            ⊗ <?php echo esc_html__('Ignora 30 giorni', 'fp-finance-hub'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="fp-fh-text-center fp-fh-p-8">
                            <p class="fp-clients-empty-text">Nessuna raccomandazione disponibile.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Insights -->
            <div class="fp-fh-card">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">💡 Insights Principali</h2>
                </div>
                <div class="fp-fh-card-body">
                    <?php 
                // Applica filtri insights
                $filtered_insights = $insights;
                if ($filter_severity) {
                    $filtered_insights = array_filter($filtered_insights, function($i) use ($filter_severity) {
                        return ($i['severity'] ?? 'low') === $filter_severity;
                    });
                }
                $filtered_insights = array_values($filtered_insights);
                ?>
                <?php if (!empty($filtered_insights)) : ?>
                        <div class="fp-insights-list" data-filter-severity="<?php echo esc_attr($filter_severity); ?>">
                            <?php foreach (array_slice($filtered_insights, 0, 5) as $idx => $insight) : ?>
                                <div class="fp-insight-item fp-fh-mb-3 fp-fh-p-3 fp-insight-item-filtered" 
                                     data-severity="<?php echo esc_attr($insight['severity'] ?? 'low'); ?>"
                                     style="background: var(--fp-fh-color-bg-soft); border-radius: var(--fp-fh-radius);">
                                    <p class="fp-fh-text-sm">
                                        <?php echo esc_html($insight['message'] ?? ''); ?>
                                        <?php if (isset($insight['severity'])) : ?>
                                            <span class="fp-fh-badge fp-fh-badge-soft-<?php echo esc_attr($insight['severity']); ?> fp-fh-ml-2"><?php echo esc_html(ucfirst($insight['severity'])); ?></span>
                                        <?php endif; ?>
                                        <span class="fp-fh-badge fp-fh-badge-new fp-insight-new" style="display: none;"><?php echo esc_html__('Nuovo', 'fp-finance-hub'); ?></span>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="fp-fh-text-center fp-fh-p-8">
                            <p class="fp-clients-empty-text">Nessun insight disponibile.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Pattern -->
        <div class="fp-fh-card fp-fh-mb-6">
            <div class="fp-fh-card-header">
                <h2 class="fp-fh-card-title">📈 Pattern Identificati</h2>
            </div>
            <div class="fp-fh-card-body">
                <?php if (!empty($patterns)) : ?>
                    <div class="fp-patterns-list">
                        <?php foreach (array_slice($patterns, 0, 8) as $pattern) : ?>
                            <div class="fp-pattern-item fp-fh-mb-3 fp-fh-p-3" style="background: var(--fp-fh-color-bg-soft); border-radius: var(--fp-fh-radius);">
                                <p class="fp-fh-text-sm">
                                    <?php echo esc_html($pattern['pattern'] ?? ''); ?>
                                    <?php if (isset($pattern['confidence'])) : ?>
                                        <span class="fp-fh-badge fp-fh-badge-soft-info fp-fh-ml-2">Confidence: <?php echo number_format($pattern['confidence'] * 100, 0); ?>%</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="fp-fh-text-center fp-fh-p-8">
                        <p class="fp-clients-empty-text">Nessun pattern identificato.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Predizioni -->
        <?php if (!empty($predictions) && isset($predictions['scenarios'])) : ?>
            <div class="fp-fh-card">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">🔮 Predizioni Future</h2>
                    <div class="fp-predictions-controls">
                        <label for="fp-predictions-days-slider" class="fp-fh-form-label fp-fh-text-sm">
                            <?php echo esc_html__('Giorni:', 'fp-finance-hub'); ?> 
                            <span id="fp-predictions-days-value"><?php echo absint($predictions['period_days'] ?? 30); ?></span>
                        </label>
                        <input type="range" id="fp-predictions-days-slider" 
                               min="7" max="180" step="7" 
                               value="<?php echo absint($predictions['period_days'] ?? 30); ?>"
                               class="fp-predictions-slider">
                        <button type="button" id="fp-update-predictions" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-primary">
                            <?php echo esc_html__('Aggiorna', 'fp-finance-hub'); ?>
                        </button>
                    </div>
                </div>
                <div class="fp-fh-card-body" id="fp-predictions-content">
                    <div class="fp-fh-grid fp-fh-grid-cols-3 fp-fh-gap-6">
                        <?php foreach (['optimistic', 'realistic', 'pessimistic'] as $scenario) : ?>
                            <?php $scenario_data = $predictions['scenarios'][$scenario] ?? []; ?>
                            <div class="fp-fh-card fp-fh-metric-card">
                                <div class="fp-fh-metric-card-header">
                                    <div class="fp-fh-metric-card-title"><?php echo esc_html(ucfirst($scenario)); ?></div>
                                </div>
                                <div class="fp-fh-metric-card-value fp-financial-amount <?php echo ($scenario_data['cashflow'] ?? 0) >= 0 ? 'fp-financial-amount-positive' : 'fp-financial-amount-negative'; ?>">
                                    Cash Flow: <?php echo number_format($scenario_data['cashflow'] ?? 0, 2, ',', '.') . ' €'; ?>
                                </div>
                                <div class="fp-fh-metric-card-footer">
                                    <div class="fp-fh-text-xs fp-fh-text-muted">
                                        Entrate: <?php echo number_format($scenario_data['income'] ?? 0, 2, ',', '.') . ' €'; ?><br>
                                        Uscite: <?php echo number_format($scenario_data['expenses'] ?? 0, 2, ',', '.') . ' €'; ?>
                                    </div>
                                    <?php if (!empty($scenario_data['explanation'])) : ?>
                                        <div class="fp-fh-text-xs fp-fh-text-muted fp-fh-mt-2" style="font-style: italic; opacity: 0.8;">
                                            <?php echo esc_html($scenario_data['explanation']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!empty($predictions['cashflow']) && isset($predictions['cashflow']['prediction_interval'])) : ?>
                        <?php $interval = $predictions['cashflow']['prediction_interval']; ?>
                        <div class="fp-fh-mt-4 fp-fh-p-4" style="background: var(--fp-fh-color-bg-soft); border-radius: var(--fp-fh-radius);">
                            <div class="fp-fh-text-sm fp-fh-font-semibold fp-fh-mb-2">
                                <?php echo esc_html__('Intervallo Previsto Cash Flow', 'fp-finance-hub'); ?>
                            </div>
                            <div class="fp-fh-text-base">
                                <?php echo esc_html(number_format($interval['low'] ?? 0, 2, ',', '.') . ' €'); ?>
                                <span class="fp-fh-text-muted"> - </span>
                                <?php echo esc_html(number_format($interval['high'] ?? 0, 2, ',', '.') . ' €'); ?>
                            </div>
                            <?php if (isset($predictions['income']['volatility']) || isset($predictions['expenses']['volatility'])) : ?>
                                <div class="fp-fh-text-xs fp-fh-text-muted fp-fh-mt-2">
                                    <?php
                                    $vol_i = $predictions['income']['volatility'] ?? 0;
                                    $vol_e = $predictions['expenses']['volatility'] ?? 0;
                                    if ($vol_i > 0 || $vol_e > 0) {
                                        echo esc_html__('Volatilità storica: ', 'fp-finance-hub');
                                        if ($vol_i > 0) {
                                            echo esc_html(sprintf(__('Entrate ±€%.2f', 'fp-finance-hub'), $vol_i));
                                        }
                                        if ($vol_i > 0 && $vol_e > 0) {
                                            echo ', ';
                                        }
                                        if ($vol_e > 0) {
                                            echo esc_html(sprintf(__('Uscite ±€%.2f', 'fp-finance-hub'), $vol_e));
                                        }
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Traduce trigger_type in label leggibili
     */
    private static function translate_trigger_type($trigger_type) {
        $translations = [
            'expense_concentration' => __('concentrazione spese', 'fp-finance-hub'),
            'expense_growth' => __('crescita spese', 'fp-finance-hub'),
            'unpaid_invoices' => __('fatture non pagate', 'fp-finance-hub'),
            'income_decline' => __('calo entrate', 'fp-finance-hub'),
            'liquidity_warning' => __('liquidità', 'fp-finance-hub'),
            'cashflow_negative' => __('cash flow negativo', 'fp-finance-hub'),
            'overdue_invoices' => __('fatture scadute', 'fp-finance-hub'),
            'category_trend' => __('trend categoria', 'fp-finance-hub'),
        ];
        
        return $translations[$trigger_type] ?? $trigger_type;
    }
}
