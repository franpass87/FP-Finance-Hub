<?php
/**
 * Insights Generator
 * 
 * Genera insights automatici dai dati finanziari
 */

namespace FP\FinanceHub\Services\Intelligence;

use FP\FinanceHub\Services\StatsService;
use FP\FinanceHub\Services\BankService;
use FP\FinanceHub\Services\InvoiceService;

if (!defined('ABSPATH')) {
    exit;
}

class InsightsGenerator {
    
    /**
     * Genera tutti gli insights per periodo
     */
    public function generate_all($start_date, $end_date) {
        $insights = [];
        
        // Insights entrate
        $insights = array_merge($insights, $this->generate_income_insights($start_date, $end_date));
        
        // Insights uscite
        $insights = array_merge($insights, $this->generate_expense_insights($start_date, $end_date));
        
        // Insights cash flow
        $insights = array_merge($insights, $this->generate_cashflow_insights($start_date, $end_date));
        
        // Insights fatturazione
        $insights = array_merge($insights, $this->generate_invoice_insights($start_date, $end_date));
        
        // Insights comparativi
        $insights = array_merge($insights, $this->generate_comparative_insights($start_date, $end_date));
        
        return $insights;
    }
    
    /**
     * Genera insights per entrate
     */
    private function generate_income_insights($start_date, $end_date) {
        $insights = [];
        
        // Statistiche periodo corrente
        $current_stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
        $current_income = floatval($current_stats->total_income ?? 0);
        
        // Confronto con periodo precedente equivalente
        $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;
        $previous_start = date('Y-m-d', strtotime($start_date . " -{$period_days} days"));
        $previous_end = $start_date;
        
        $previous_stats = StatsService::get_instance()->calculate_period_stats($previous_start, $previous_end);
        $previous_income = floatval($previous_stats->total_income ?? 0);
        
        if ($previous_income > 0) {
            $variation = (($current_income - $previous_income) / $previous_income) * 100;
            
            if (abs($variation) > 10) {
                $insights[] = [
                    'type' => 'income_variation',
                    'category' => 'income',
                    'message' => sprintf(
                        'Entrate %s del %.1f%% rispetto al periodo precedente (€%.2f vs €%.2f)',
                        $variation > 0 ? 'aumentate' : 'diminuite',
                        abs($variation),
                        $current_income,
                        $previous_income
                    ),
                    'variation_percentage' => $variation,
                    'current_value' => $current_income,
                    'previous_value' => $previous_income,
                    'severity' => abs($variation) > 30 ? 'high' : 'medium',
                    'confidence' => 0.9,
                ];
            }
        }
        
        // Insight su fatture non pagate
        $unpaid_invoices = InvoiceService::get_instance()->get_unpaid();
        $total_unpaid = InvoiceService::get_instance()->calculate_potential_income();

        if ($total_unpaid > 0) {
            $unpaid_count = count($unpaid_invoices);
            $insights[] = [
                'type' => 'unpaid_invoices',
                'category' => 'income',
                'message' => sprintf(
                    '%d fatture non pagate per un totale di €%.2f in attesa di incasso',
                    $unpaid_count,
                    $total_unpaid
                ),
                'invoice_count' => $unpaid_count,
                'total_amount' => $total_unpaid,
                'severity' => $total_unpaid > 5000 ? 'high' : 'medium',
                'confidence' => $this->confidence_from_samples($unpaid_count, 10),
            ];
        }

        // DSO (età media crediti): giorni medi da emissione per fatture non pagate
        if (!empty($unpaid_invoices)) {
            $days_sum = 0;
            $with_date = 0;
            foreach ($unpaid_invoices as $inv) {
                $d = $inv->issue_date ?? $inv->due_date ?? null;
                if ($d) {
                    $days_sum += (strtotime('today') - strtotime($d)) / 86400;
                    $with_date++;
                }
            }
            if ($with_date > 0) {
                $dso = round($days_sum / $with_date);
                $insights[] = [
                    'type' => 'dso',
                    'category' => 'income',
                    'message' => sprintf(
                        __('DSO: crediti non incassati in media da %d giorni (%d fatture)', 'fp-finance-hub'),
                        $dso,
                        $with_date
                    ),
                    'dso_days' => $dso,
                    'invoice_count' => $with_date,
                    'severity' => $dso > 60 ? 'high' : ($dso > 30 ? 'medium' : 'low'),
                    'confidence' => $this->confidence_from_samples($with_date, 5),
                ];
            }
        }
        
        return $insights;
    }
    
    /**
     * Genera insights per uscite
     */
    private function generate_expense_insights($start_date, $end_date) {
        $insights = [];
        
        // Statistiche periodo corrente
        $current_stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
        $current_expenses = floatval($current_stats->total_expenses ?? 0);
        
        // Top 5 categorie spesa
        $top_categories = StatsService::get_instance()->get_top_categories($start_date, $end_date, 5, 'expense');
        
        if (!empty($top_categories)) {
            $total_top = 0;
            foreach ($top_categories as $cat) {
                if (isset($cat->total)) {
                    $total_top += floatval($cat->total ?? 0);
                }
            }
            
            $percentage = $current_expenses > 0 ? ($total_top / $current_expenses) * 100 : 0;
            
            if ($percentage > 50) {
                $category_names = array_map(function ($cat) { return isset($cat->category) ? $cat->category : ''; }, $top_categories);
                $category_names = array_filter($category_names);
                $n_cats = count($category_names);
                $insights[] = [
                    'type' => 'expense_concentration',
                    'category' => 'expense',
                    'message' => sprintf(
                        'Top 5 categorie rappresentano il %.1f%% delle spese totali: %s',
                        $percentage,
                        implode(', ', $category_names)
                    ),
                    'top_categories' => $category_names,
                    'percentage' => $percentage,
                    'severity' => 'low',
                    'confidence' => $this->confidence_from_samples($n_cats, 3),
                ];
            }
        }
        
        // Insight su crescita spese
        $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;
        $previous_start = date('Y-m-d', strtotime($start_date . " -{$period_days} days"));
        $previous_end = $start_date;
        
        $previous_stats = StatsService::get_instance()->calculate_period_stats($previous_start, $previous_end);
        $previous_expenses = floatval($previous_stats->total_expenses ?? 0);
        
        if ($previous_expenses > 0) {
            $variation = (($current_expenses - $previous_expenses) / $previous_expenses) * 100;
            
            if ($variation > 15) {
                $insights[] = [
                    'type' => 'expense_growth',
                    'category' => 'expense',
                    'message' => sprintf(
                        'Spese aumentate del %.1f%% rispetto al periodo precedente - opportunità di ottimizzazione',
                        $variation
                    ),
                    'variation_percentage' => $variation,
                    'current_value' => $current_expenses,
                    'previous_value' => $previous_expenses,
                    'severity' => 'medium',
                    'confidence' => 0.9,
                ];
            }
        }
        
        return $insights;
    }
    
    /**
     * Genera insights per cash flow
     */
    private function generate_cashflow_insights($start_date, $end_date) {
        $insights = [];
        
        // Statistiche periodo corrente
        $current_stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
        $current_income = floatval($current_stats->total_income ?? 0);
        $current_expenses = floatval($current_stats->total_expenses ?? 0);
        $current_cashflow = $current_income - $current_expenses;
        
        // Insight su cash flow positivo/negativo
        if ($current_cashflow > 0) {
            $margin_percentage = $current_income > 0 ? ($current_cashflow / $current_income) * 100 : 0;
            
            $insights[] = [
                'type' => 'cashflow_positive',
                'category' => 'cashflow',
                'message' => sprintf(
                    'Cash flow positivo: €%.2f (margine %.1f%%)',
                    $current_cashflow,
                    $margin_percentage
                ),
                'cashflow' => $current_cashflow,
                'margin_percentage' => $margin_percentage,
                'severity' => $margin_percentage > 20 ? 'low' : 'medium',
                'confidence' => 1.0,
            ];
        } else {
            $insights[] = [
                'type' => 'cashflow_negative',
                'category' => 'cashflow',
                'message' => sprintf(
                    'Cash flow negativo: €%.2f - Attenzione alla liquidità',
                    abs($current_cashflow)
                ),
                'cashflow' => $current_cashflow,
                'severity' => abs($current_cashflow) > 2000 ? 'high' : 'medium',
                'confidence' => 1.0,
            ];
        }
        
        // Insight su liquidità attuale
        $total_balance = BankService::get_instance()->get_total_balance();
        
        if ($total_balance > 0) {
            // Calcola mesi di copertura basati su spese medie
            $avg_monthly_expenses = $current_expenses;
            $months_coverage = $avg_monthly_expenses > 0 ? $total_balance / $avg_monthly_expenses : 0;
            
            if ($months_coverage < 3) {
                $insights[] = [
                    'type' => 'liquidity_warning',
                    'category' => 'cashflow',
                    'message' => sprintf(
                        'Liquidità attuale copre %.1f mesi di spese - considera aumentare riserve',
                        $months_coverage
                    ),
                    'balance' => $total_balance,
                    'months_coverage' => $months_coverage,
                    'severity' => $months_coverage < 1 ? 'critical' : ($months_coverage < 2 ? 'high' : 'medium'),
                    'confidence' => 0.8,
                ];
            }
        }
        
        return $insights;
    }
    
    /**
     * Genera insights per fatturazione
     */
    private function generate_invoice_insights($start_date, $end_date) {
        $insights = [];
        
        // Fatture scadute
        $overdue_invoices = InvoiceService::get_instance()->get_overdue_invoices();
        
        if (!empty($overdue_invoices)) {
            $total_overdue = 0;
            $avg_days_overdue = 0;
            
            foreach ($overdue_invoices as $invoice) {
                $total_overdue += floatval($invoice->total_amount ?? 0);
                if (!empty($invoice->due_date)) {
                    $days_overdue = defined('DAY_IN_SECONDS') ? (strtotime('now') - strtotime($invoice->due_date)) / DAY_IN_SECONDS : (strtotime('now') - strtotime($invoice->due_date)) / 86400;
                    $avg_days_overdue += $days_overdue;
                }
            }
            
            $avg_days_overdue = count($overdue_invoices) > 0 ? $avg_days_overdue / count($overdue_invoices) : 0;
            
            $insights[] = [
                'type' => 'overdue_invoices',
                'category' => 'invoices',
                'message' => sprintf(
                    '%d fatture scadute (media %.0f giorni) per €%.2f - azione richiesta',
                    count($overdue_invoices),
                    $avg_days_overdue,
                    $total_overdue
                ),
                'invoice_count' => count($overdue_invoices),
                'total_amount' => $total_overdue,
                'avg_days_overdue' => round($avg_days_overdue),
                'severity' => $avg_days_overdue > 30 ? 'high' : 'medium',
                'confidence' => 1.0,
            ];
        }
        
        return $insights;
    }
    
    /**
     * Genera insights comparativi
     */
    private function generate_comparative_insights($start_date, $end_date) {
        $insights = [];
        
        // Confronto anno precedente (se periodo >= 1 mese)
        $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;

        if ($period_days >= 30) {
            $previous_year_start = date('Y-m-d', strtotime($start_date . ' -1 year'));
            $previous_year_end = date('Y-m-d', strtotime($end_date . ' -1 year'));

            $current_stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
            $previous_stats = StatsService::get_instance()->calculate_period_stats($previous_year_start, $previous_year_end);

            $current_income = floatval($current_stats->total_income ?? 0);
            $previous_income = floatval($previous_stats->total_income ?? 0);

            if ($previous_income > 0) {
                $year_variation = (($current_income - $previous_income) / $previous_income) * 100;

                if (abs($year_variation) > 10) {
                    $insights[] = [
                        'type' => 'year_comparison',
                        'category' => 'income',
                        'message' => sprintf(
                            'Confronto anno precedente: Entrate %s del %.1f%%',
                            $year_variation > 0 ? 'aumentate' : 'diminuite',
                            abs($year_variation)
                        ),
                        'variation_percentage' => $year_variation,
                        'current_value' => $current_income,
                        'previous_value' => $previous_income,
                        'severity' => abs($year_variation) > 20 ? 'medium' : 'low',
                        'confidence' => 0.9,
                    ];
                }
            }
        }

        // Best/worst mese (ultimi 12 mesi)
        $insights = array_merge($insights, $this->generate_best_worst_month_insights());

        return $insights;
    }

    /**
     * Miglior/peggior mese per entrate e spese (ultimi 12 mesi)
     */
    private function generate_best_worst_month_insights() {
        $insights = [];
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} months"));
            $ms = date('Y-m-01', strtotime($date));
            $me = date('Y-m-t', strtotime($date));
            $stats = StatsService::get_instance()->calculate_period_stats($ms, $me);
            $months[] = [
                'label' => date_i18n('F Y', strtotime($date)),
                'income' => floatval($stats->total_income ?? 0),
                'expenses' => floatval($stats->total_expenses ?? 0),
            ];
        }
        if (empty($months)) {
            return $insights;
        }
        $best_income = $months[0];
        $worst_expenses = $months[0];
        foreach ($months as $m) {
            if ($m['income'] > $best_income['income']) {
                $best_income = $m;
            }
            if ($m['expenses'] > $worst_expenses['expenses']) {
                $worst_expenses = $m;
            }
        }
        if ($best_income['income'] > 0) {
            $insights[] = [
                'type' => 'best_month_income',
                'category' => 'income',
                'message' => sprintf(
                    __('Miglior mese per entrate: %s (€%.2f)', 'fp-finance-hub'),
                    $best_income['label'],
                    $best_income['income']
                ),
                'month_label' => $best_income['label'],
                'amount' => $best_income['income'],
                'severity' => 'low',
                'confidence' => 0.85,
            ];
        }
        if ($worst_expenses['expenses'] > 0) {
            $insights[] = [
                'type' => 'worst_month_expenses',
                'category' => 'expense',
                'message' => sprintf(
                    __('Peggior mese per spese: %s (€%.2f)', 'fp-finance-hub'),
                    $worst_expenses['label'],
                    $worst_expenses['expenses']
                ),
                'month_label' => $worst_expenses['label'],
                'amount' => $worst_expenses['expenses'],
                'severity' => 'low',
                'confidence' => 0.85,
            ];
        }
        return $insights;
    }

    /**
     * Confidence in base a numero di campioni (min_samples per 0.7, scala verso 0.95)
     */
    private function confidence_from_samples($n, $min_samples = 5) {
        if ($n <= 0) {
            return 0.5;
        }
        return min(0.95, 0.5 + 0.45 * min(1, $n / max(1, $min_samples)));
    }
}
