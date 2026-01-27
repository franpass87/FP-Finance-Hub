<?php
/**
 * Dashboard Page
 * 
 * Dashboard finanziario principale
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Services\BankService;
use FP\FinanceHub\Services\InvoiceService;
use FP\FinanceHub\Services\StatsService;
use FP\FinanceHub\Services\SetupService;

if (!defined('ABSPATH')) {
    exit;
}

class DashboardPage {
    
    /**
     * Render pagina dashboard
     */
    public static function render() {
        $bank_service = BankService::get_instance();
        $invoice_service = InvoiceService::get_instance();
        $stats_service = StatsService::get_instance();
        $setup_service = SetupService::get_instance();
        
        // Dati dashboard
        $total_balance = $bank_service->get_total_balance();
        $potential_income = $invoice_service->calculate_potential_income();
        $active_alerts = \FP\FinanceHub\Database\Models\Alert::count_active_unacknowledged();
        
        // Cash Flow mese corrente
        $current_month_start = date('Y-m-01');
        $current_month_end = date('Y-m-t');
        $current_month_stats = $stats_service->calculate_period_stats($current_month_start, $current_month_end);
        $current_cashflow = floatval($current_month_stats->total_income ?? 0) - floatval($current_month_stats->total_expenses ?? 0);
        
        // Mese precedente per trend
        $previous_month_start = date('Y-m-01', strtotime('-1 month'));
        $previous_month_end = date('Y-m-t', strtotime('-1 month'));
        $previous_month_stats = $stats_service->calculate_period_stats($previous_month_start, $previous_month_end);
        $previous_cashflow = floatval($previous_month_stats->total_income ?? 0) - floatval($previous_month_stats->total_expenses ?? 0);
        $cashflow_trend = $previous_cashflow != 0 ? (($current_cashflow - $previous_cashflow) / abs($previous_cashflow)) * 100 : 0;
        
        // Fatture da incassare (non pagate)
        $unpaid_invoices = $invoice_service->get_unpaid();
        $unpaid_count = count($unpaid_invoices);
        $unpaid_total = 0;
        $oldest_unpaid_days = 0;
        foreach ($unpaid_invoices as $inv) {
            $unpaid_total += floatval($inv->total_amount ?? 0);
            if ($inv->issue_date) {
                $days = (strtotime('today') - strtotime($inv->issue_date)) / 86400;
                if ($days > $oldest_unpaid_days) {
                    $oldest_unpaid_days = $days;
                }
            }
        }
        
        // Prossimi pagamenti (fatture scadenti nei prossimi 7 giorni)
        $next_7_days = date('Y-m-d', strtotime('+7 days'));
        $upcoming_invoices = [];
        foreach ($unpaid_invoices as $inv) {
            if ($inv->due_date && $inv->due_date <= $next_7_days && $inv->due_date >= date('Y-m-d')) {
                $upcoming_invoices[] = $inv;
            }
        }
        $upcoming_total = 0;
        foreach ($upcoming_invoices as $inv) {
            $upcoming_total += floatval($inv->total_amount ?? 0);
        }
        
        // Verifica setup
        $setup_complete = $setup_service->is_setup_complete();
        $setup_progress = $setup_service->get_setup_progress();
        $next_step = $setup_service->get_next_step();
        
        ?>
        <div class="wrap fp-finance-hub-dashboard fp-fh-wrapper">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p>Panoramica finanziaria completa</p>
                </div>
            </div>
            
            <!-- Setup Incomplete Banner -->
            <?php if (!$setup_complete) : ?>
                <div class="fp-fh-help-banner fp-fh-help-banner-warning fp-fh-mb-6">
                    <div class="fp-fh-help-banner-header">
                        <strong>🎯 Completa il Setup</strong>
                    </div>
                    <div class="fp-fh-help-banner-message">
                        Configura il plugin per iniziare a usare FP Finance Hub. 
                        Progresso: <?php echo esc_html($setup_progress['completed_count']); ?> di <?php echo esc_html($setup_progress['total_count']); ?> step completati (<?php echo esc_html(round($setup_progress['percentage'])); ?>%)
                    </div>
                    <div class="fp-fh-help-banner-actions">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-setup-guide'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-sm">
                            Vai alla Guida Setup →
                        </a>
                        <?php if ($next_step) : ?>
                            <a href="<?php echo esc_url($next_step['step']['url']); ?>" class="fp-fh-btn fp-fh-btn-secondary fp-fh-btn-sm">
                                Completa: <?php echo esc_html($next_step['step']['name']); ?> →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="fp-dashboard-widgets fp-fh-grid fp-fh-grid-cols-3 fp-fh-mb-6">
                <!-- Saldi Conti -->
                <div class="fp-widget fp-widget-balances fp-fh-card fp-fh-card-financial">
                    <div class="fp-fh-card-header">
                        <h2 class="fp-fh-card-title">💰 Saldi Conti</h2>
                        <div class="fp-fh-metric-card-icon">💰</div>
                    </div>
                    <div class="fp-fh-card-body">
                        <div class="fp-balance-total">
                            <span class="fp-label">Totale:</span>
                            <span class="fp-amount"><?php echo esc_html(number_format($total_balance, 2, ',', '.') . ' €'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Cash Flow Mese Corrente -->
                <div class="fp-widget fp-widget-cashflow fp-fh-card fp-fh-card-financial <?php echo $current_cashflow >= 0 ? 'fp-fh-income-card' : 'fp-fh-alert-card'; ?>">
                    <div class="fp-fh-card-header">
                        <h2 class="fp-fh-card-title">💸 Cash Flow Mese</h2>
                        <div class="fp-fh-metric-card-icon">💸</div>
                    </div>
                    <div class="fp-fh-card-body">
                        <div class="fp-cashflow-amount">
                            <span class="fp-amount <?php echo $current_cashflow >= 0 ? 'fp-financial-amount-positive' : 'fp-financial-amount-negative'; ?>">
                                <?php echo esc_html(number_format($current_cashflow, 2, ',', '.') . ' €'); ?>
                            </span>
                            <?php if ($previous_cashflow != 0) : ?>
                                <div class="fp-cashflow-trend">
                                    <?php
                                    $trend_icon = $cashflow_trend >= 0 ? '↑' : '↓';
                                    $trend_class = $cashflow_trend >= 0 ? 'fp-trend-positive' : 'fp-trend-negative';
                                    ?>
                                    <span class="fp-trend <?php echo esc_attr($trend_class); ?>">
                                        <?php echo esc_html($trend_icon); ?> <?php echo esc_html(number_format(abs($cashflow_trend), 1)); ?>%
                                    </span>
                                    <span class="fp-trend-label">vs mese precedente</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Potenziale Entrate -->
                <div class="fp-widget fp-widget-potential fp-fh-card fp-fh-card-financial fp-fh-income-card">
                    <div class="fp-fh-card-header">
                        <h2 class="fp-fh-card-title">📈 Potenziale Entrate</h2>
                        <div class="fp-fh-metric-card-icon">📈</div>
                    </div>
                    <div class="fp-fh-card-body">
                        <div class="fp-potential-income">
                            <span class="fp-amount"><?php echo esc_html(number_format($potential_income, 2, ',', '.') . ' €'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="fp-dashboard-widgets fp-fh-grid fp-fh-grid-cols-3 fp-fh-mb-6">
                <!-- Fatture da Incassare -->
                <div class="fp-widget fp-widget-invoices fp-fh-card fp-fh-card-financial">
                    <div class="fp-fh-card-header">
                        <h2 class="fp-fh-card-title">📄 Fatture da Incassare</h2>
                        <div class="fp-fh-metric-card-icon">📄</div>
                    </div>
                    <div class="fp-fh-card-body">
                        <div class="fp-invoices-info">
                            <div class="fp-invoices-amount">
                                <span class="fp-amount"><?php echo esc_html(number_format($unpaid_total, 2, ',', '.') . ' €'); ?></span>
                            </div>
                            <div class="fp-invoices-details">
                                <span class="fp-count"><?php echo esc_html($unpaid_count); ?> fatture</span>
                                <?php if ($oldest_unpaid_days > 0) : ?>
                                    <span class="fp-days">• <?php echo esc_html(round($oldest_unpaid_days)); ?> giorni (più vecchia)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Prossimi Pagamenti -->
                <div class="fp-widget fp-widget-upcoming fp-fh-card fp-fh-card-financial">
                    <div class="fp-fh-card-header">
                        <h2 class="fp-fh-card-title">📅 Prossimi Pagamenti</h2>
                        <div class="fp-fh-metric-card-icon">📅</div>
                    </div>
                    <div class="fp-fh-card-body">
                        <div class="fp-upcoming-info">
                            <div class="fp-upcoming-amount">
                                <span class="fp-amount"><?php echo esc_html(number_format($upcoming_total, 2, ',', '.') . ' €'); ?></span>
                            </div>
                            <div class="fp-upcoming-details">
                                <span class="fp-count"><?php echo esc_html(count($upcoming_invoices)); ?> fatture</span>
                                <span class="fp-period">nei prossimi 7 giorni</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Alert Attivi -->
                <div class="fp-widget fp-widget-alerts fp-fh-card fp-fh-card-financial fp-fh-alert-card">
                    <div class="fp-fh-card-header">
                        <h2 class="fp-fh-card-title">⚠️ Alert Attivi</h2>
                        <div class="fp-fh-metric-card-icon">⚠️</div>
                    </div>
                    <div class="fp-fh-card-body">
                        <div class="fp-alert-count">
                            <span class="fp-count"><?php echo esc_html($active_alerts); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="fp-fh-card fp-fh-mb-6">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">⚡ Quick Actions</h2>
                </div>
                <div class="fp-fh-card-body">
                    <div class="fp-quick-actions fp-fh-grid fp-fh-grid-cols-4 fp-fh-gap-4">
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-accounts'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-block">
                            📥 Import File
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-invoices&action=new'); ?>" class="fp-fh-btn fp-fh-btn-primary fp-fh-btn-block">
                            📄 Nuova Fattura
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-bank-connections'); ?>" class="fp-fh-btn fp-fh-btn-secondary fp-fh-btn-block">
                            🔗 Collega Conto
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-analytics&tab=ai-insights'); ?>" class="fp-fh-btn fp-fh-btn-secondary fp-fh-btn-block">
                            🧠 AI Insights
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Grafico Trend -->
            <div class="fp-widget fp-widget-chart fp-fh-card">
                <div class="fp-fh-card-header">
                    <h2 class="fp-fh-card-title">📊 Trend 12 Mesi</h2>
                </div>
                <div class="fp-fh-card-body">
                    <div class="fp-fh-chart-container fp-fh-chart-container-lg">
                        <canvas id="fp-finance-trend-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
