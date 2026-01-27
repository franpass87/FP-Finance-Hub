<?php
/**
 * Recommendations Engine
 * 
 * Genera raccomandazioni intelligenti basate sui dati finanziari
 */

namespace FP\FinanceHub\Services\Intelligence;

use FP\FinanceHub\Services\StatsService;
use FP\FinanceHub\Services\BankService;
use FP\FinanceHub\Services\InvoiceService;

if (!defined('ABSPATH')) {
    exit;
}

class RecommendationsEngine {
    
    /**
     * Genera raccomandazioni per periodo
     */
    public function generate_recommendations($start_date, $end_date) {
        $recommendations = [];
        
        // Raccomandazioni risparmio
        $recommendations = array_merge($recommendations, $this->generate_savings_recommendations($start_date, $end_date));
        
        // Raccomandazioni entrate
        $recommendations = array_merge($recommendations, $this->generate_income_recommendations($start_date, $end_date));
        
        // Raccomandazioni liquidità
        $recommendations = array_merge($recommendations, $this->generate_liquidity_recommendations($start_date, $end_date));
        
        // Raccomandazioni fatturazione
        $recommendations = array_merge($recommendations, $this->generate_invoice_recommendations($start_date, $end_date));
        
        // Raccomandazioni categorie
        $recommendations = array_merge($recommendations, $this->generate_category_recommendations($start_date, $end_date));
        
        // Ordina per priorità, poi per impatto (potential_savings / total_amount / cashflow)
        usort($recommendations, function ($a, $b) {
            $priority_order = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            $a_priority = $priority_order[$a['priority'] ?? 'low'] ?? 1;
            $b_priority = $priority_order[$b['priority'] ?? 'low'] ?? 1;
            if ($a_priority !== $b_priority) {
                return $b_priority - $a_priority;
            }
            $a_impact = $this->recommendation_impact($a);
            $b_impact = $this->recommendation_impact($b);
            return $b_impact <=> $a_impact;
        });

        return $recommendations;
    }

    /**
     * Impatto stimato per ordinamento (maggiore = prima)
     */
    private function recommendation_impact(array $r) {
        if (isset($r['impact']) && $r['impact'] > 0) {
            return (float) $r['impact'];
        }
        if (isset($r['potential_savings']) && $r['potential_savings'] > 0) {
            return (float) $r['potential_savings'];
        }
        if (isset($r['total_amount']) && $r['total_amount'] > 0) {
            return (float) $r['total_amount'];
        }
        if (isset($r['amount']) && $r['amount'] > 0) {
            return (float) $r['amount'];
        }
        if (isset($r['cashflow']) && $r['cashflow'] < 0) {
            return (float) abs($r['cashflow']);
        }
        if (isset($r['variation_percentage'])) {
            return (float) abs($r['variation_percentage']);
        }
        return 0;
    }
    
    /**
     * Genera raccomandazioni per risparmio
     */
    private function generate_savings_recommendations($start_date, $end_date) {
        $recommendations = [];
        
        // Analizza categorie spesa per opportunità di risparmio
        $category_stats = StatsService::get_instance()->calculate_category_stats($start_date, $end_date);
        
        $total_expenses = 0;
        $category_map = [];
        
        foreach ($category_stats as $cat) {
            $total_expenses += floatval($cat->total_expenses ?? 0);
            $category_map[$cat->category] = floatval($cat->total_expenses ?? 0);
        }
        
        // Identifica categorie con spese elevate (>10% del totale)
        foreach ($category_map as $category => $amount) {
            $percentage = $total_expenses > 0 ? ($amount / $total_expenses) * 100 : 0;
            
            if ($percentage > 10 && $amount > 200) {
                $recommendations[] = [
                    'type' => 'savings_opportunity',
                    'trigger_type' => 'expense_concentration',
                    'category' => 'expense',
                    'priority' => $percentage > 20 ? 'high' : 'medium',
                    'title' => sprintf("Riduzione spese '%s'", $category),
                    'message' => sprintf(
                        "La categoria '%s' rappresenta il %.1f%% delle spese totali (€%.2f). Valuta opportunità di risparmio.",
                        $category,
                        $percentage,
                        $amount
                    ),
                    'category_name' => $category,
                    'amount' => $amount,
                    'percentage' => $percentage,
                    'potential_savings' => $amount * 0.1,
                    'action' => 'review_category',
                ];
            }
        }
        
        // Raccomandazione basata su crescita spese
        $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;
        $previous_start = date('Y-m-d', strtotime($start_date . " -{$period_days} days"));
        $previous_end = $start_date;
        
        $current_stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
        $previous_stats = StatsService::get_instance()->calculate_period_stats($previous_start, $previous_end);
        
        $current_expenses = floatval($current_stats->total_expenses ?? 0);
        $previous_expenses = floatval($previous_stats->total_expenses ?? 0);
        
        if ($previous_expenses > 0) {
            $variation = (($current_expenses - $previous_expenses) / $previous_expenses) * 100;
            
            if ($variation > 15) {
                $recommendations[] = [
                    'type' => 'expense_reduction',
                    'trigger_type' => 'expense_growth',
                    'category' => 'expense',
                    'priority' => 'high',
                    'title' => 'Riduzione spese necessaria',
                    'message' => sprintf(
                        'Spese aumentate del %.1f%% rispetto al periodo precedente. Analizza le cause e identifica aree di ottimizzazione.',
                        $variation
                    ),
                    'variation_percentage' => $variation,
                    'current_value' => $current_expenses,
                    'previous_value' => $previous_expenses,
                    'action' => 'analyze_expenses',
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Genera raccomandazioni per entrate
     */
    private function generate_income_recommendations($start_date, $end_date) {
        $recommendations = [];
        
        // Fatture non pagate
        $unpaid_invoices = InvoiceService::get_instance()->get_unpaid();
        $total_unpaid = InvoiceService::get_instance()->calculate_potential_income();
        
        if ($total_unpaid > 0) {
            $unpaid_count = count($unpaid_invoices);
            
            $recommendations[] = [
                'type' => 'collect_invoices',
                'trigger_type' => 'unpaid_invoices',
                'category' => 'income',
                'priority' => $total_unpaid > 5000 ? 'high' : 'medium',
                'title' => 'Incasare fatture non pagate',
                'message' => sprintf(
                    '%d fatture per €%.2f in attesa di pagamento. Contatta i clienti per accelerare gli incassi.',
                    $unpaid_count,
                    $total_unpaid
                ),
                'invoice_count' => $unpaid_count,
                'total_amount' => $total_unpaid,
                'action' => 'contact_clients',
            ];
        }
        
        // Insight su entrate in calo
        $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;
        $previous_start = date('Y-m-d', strtotime($start_date . " -{$period_days} days"));
        $previous_end = $start_date;
        
        $current_stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
        $previous_stats = StatsService::get_instance()->calculate_period_stats($previous_start, $previous_end);
        
        $current_income = floatval($current_stats->total_income ?? 0);
        $previous_income = floatval($previous_stats->total_income ?? 0);
        
        if ($previous_income > 0) {
            $variation = (($current_income - $previous_income) / $previous_income) * 100;
            
            if ($variation < -10) {
            $recommendations[] = [
                'type' => 'increase_income',
                'trigger_type' => 'income_decline',
                'category' => 'income',
                'priority' => 'medium',
                'title' => 'Entrate in calo',
                'message' => sprintf(
                    'Entrate diminuite del %.1f%% rispetto al periodo precedente. Valuta strategie per aumentare le entrate.',
                    abs($variation)
                ),
                'variation_percentage' => $variation,
                'current_value' => $current_income,
                'previous_value' => $previous_income,
                'action' => 'develop_strategy',
            ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Genera raccomandazioni per liquidità
     */
    private function generate_liquidity_recommendations($start_date, $end_date) {
        $recommendations = [];
        
        // Analizza cash flow
        $current_stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
        $current_cashflow = floatval($current_stats->total_income ?? 0) - floatval($current_stats->total_expenses ?? 0);
        
        $total_balance = BankService::get_instance()->get_total_balance();
        $avg_monthly_expenses = floatval($current_stats->total_expenses ?? 0);
        
        if ($avg_monthly_expenses > 0) {
            $months_coverage = $total_balance / $avg_monthly_expenses;
            
            if ($months_coverage < 3) {
                $recommendations[] = [
                    'type' => 'increase_liquidity',
                    'trigger_type' => 'liquidity_warning',
                    'category' => 'cashflow',
                    'priority' => $months_coverage < 1 ? 'critical' : ($months_coverage < 2 ? 'high' : 'medium'),
                    'title' => 'Aumenta riserve di liquidità',
                    'message' => sprintf(
                        'Liquidità attuale copre solo %.1f mesi di spese. Consigliato avere almeno 3-6 mesi di riserve.',
                        $months_coverage
                    ),
                    'balance' => $total_balance,
                    'months_coverage' => $months_coverage,
                    'recommended_months' => 6,
                    'impact' => (3 - $months_coverage) * $avg_monthly_expenses,
                    'action' => 'save_more',
                ];
            }
        }
        
        // Raccomandazione per cash flow negativo
        if ($current_cashflow < 0) {
            $recommendations[] = [
                'type' => 'improve_cashflow',
                'trigger_type' => 'cashflow_negative',
                'category' => 'cashflow',
                'priority' => abs($current_cashflow) > 2000 ? 'critical' : 'high',
                'title' => 'Migliora cash flow',
                'message' => sprintf(
                    'Cash flow negativo di €%.2f. Considera: ridurre spese, accelerare incassi, o cercare finanziamenti.',
                    abs($current_cashflow)
                ),
                'cashflow' => $current_cashflow,
                'action' => 'improve_cashflow',
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Genera raccomandazioni per fatturazione
     */
    private function generate_invoice_recommendations($start_date, $end_date) {
        $recommendations = [];
        
        // Fatture scadute
        $overdue_invoices = InvoiceService::get_instance()->get_overdue_invoices();
        
        if (!empty($overdue_invoices)) {
            $total_overdue = 0;
            $max_days_overdue = 0;
            
            foreach ($overdue_invoices as $invoice) {
                $total_overdue += floatval($invoice->total_amount ?? 0);
                if (!empty($invoice->due_date)) {
                    $days_overdue = defined('DAY_IN_SECONDS') ? (strtotime('now') - strtotime($invoice->due_date)) / DAY_IN_SECONDS : (strtotime('now') - strtotime($invoice->due_date)) / 86400;
                    $max_days_overdue = max($max_days_overdue, $days_overdue);
                }
            }
            
            $recommendations[] = [
                'type' => 'invoice_collection',
                'trigger_type' => 'overdue_invoices',
                'category' => 'invoices',
                'priority' => $max_days_overdue > 30 ? 'critical' : 'high',
                'title' => 'Raccogli fatture scadute',
                'message' => sprintf(
                    '%d fatture scadute per €%.2f (più vecchia: %.0f giorni). Implementa processo di follow-up automatico.',
                    count($overdue_invoices),
                    $total_overdue,
                    $max_days_overdue
                ),
                'invoice_count' => count($overdue_invoices),
                'total_amount' => $total_overdue,
                'max_days_overdue' => round($max_days_overdue),
                'action' => 'implement_followup',
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Genera raccomandazioni per categorie
     */
    private function generate_category_recommendations($start_date, $end_date) {
        $recommendations = [];
        
        // Analizza categorie in crescita
        $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;
        $previous_start = date('Y-m-d', strtotime($start_date . " -{$period_days} days"));
        $previous_end = $start_date;
        
        $current_categories = StatsService::get_instance()->calculate_category_stats($start_date, $end_date);
        $previous_categories = StatsService::get_instance()->calculate_category_stats($previous_start, $previous_end);
        
        $previous_map = [];
        if (!empty($previous_categories) && is_array($previous_categories)) {
            foreach ($previous_categories as $cat) {
                if (isset($cat->category) && isset($cat->total_expenses)) {
                    $previous_map[$cat->category] = floatval($cat->total_expenses ?? 0);
                }
            }
        }
        
        if (!empty($current_categories) && is_array($current_categories)) {
            foreach ($current_categories as $cat) {
                if (!isset($cat->category) || !isset($cat->total_expenses)) {
                    continue;
                }
                $category_name = $cat->category;
                $current_total = floatval($cat->total_expenses ?? 0);
                $previous_total = $previous_map[$category_name] ?? 0;
            
            if ($previous_total > 0) {
                $variation = (($current_total - $previous_total) / $previous_total) * 100;
                
                if ($variation > 30 && $current_total > 500) {
                    $recommendations[] = [
                        'type' => 'category_review',
                        'trigger_type' => 'category_trend',
                        'category' => 'expense',
                        'priority' => 'medium',
                        'title' => sprintf("Rivedi categoria '%s'", $category_name),
                        'message' => sprintf(
                            "La categoria '%s' è aumentata del %.1f%% (€%.2f). Analizza le cause e valuta ottimizzazioni.",
                            $category_name,
                            $variation,
                            $current_total
                        ),
                        'category_name' => $category_name,
                        'variation_percentage' => $variation,
                        'current_value' => $current_total,
                        'previous_value' => $previous_total,
                        'potential_savings' => ($current_total - $previous_total) * 0.1,
                        'action' => 'review_category',
                    ];
                }
            }
            }
        }
        
        return $recommendations;
    }
}
