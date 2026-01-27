<?php
/**
 * Projection Service
 * 
 * Calcolo proiezioni entrate/uscite
 */

namespace FP\FinanceHub\Services;

use FP\FinanceHub\Database\Models\Projection as ProjectionModel;
use FP\FinanceHub\Database\Models\Invoice as InvoiceModel;
use FP\FinanceHub\Database\Models\Transaction as TransactionModel;
use FP\FinanceHub\Services\StatsService;

if (!defined('ABSPATH')) {
    exit;
}

class ProjectionService {
    
    private static $instance = null;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Calcola proiezioni entrate per mese
     */
    public function calculate_income_projections($month, $year) {
        // Fatture non pagate (potenziale entrata)
        $unpaid_invoices = InvoiceModel::get_unpaid();
        
        $total_unpaid = 0;
        if (!empty($unpaid_invoices) && is_array($unpaid_invoices)) {
            foreach ($unpaid_invoices as $invoice) {
                if (isset($invoice->total_amount)) {
                    $total_unpaid += floatval($invoice->total_amount);
                }
            }
        }
        
        // Scenari
        $scenarios = [
            'optimistic' => $total_unpaid * 1.0,  // 100% incassato
            'realistic' => $total_unpaid * 0.8,   // 80% incassato
            'pessimistic' => $total_unpaid * 0.6, // 60% incassato
        ];
        
        // Salva proiezioni
        try {
            foreach ($scenarios as $scenario => $projected) {
                if (class_exists('FP\FinanceHub\Database\Models\Projection')) {
                    ProjectionModel::create_or_update([
                        'month' => $month,
                        'year' => $year,
                        'projected_income' => $projected,
                        'scenario' => $scenario,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Ignora errori di salvataggio, restituisce comunque scenari
        }
        
        return $scenarios;
    }
    
    /**
     * Calcola proiezioni uscite per mese
     */
    public function calculate_expense_projections($month, $year) {
        // Calcola media uscite ultimi 3 mesi per proiezione uscite
        $start_date = date('Y-m-01', strtotime("-3 months"));
        $end_date = date('Y-m-t', strtotime("-1 month"));
        
        $stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
        $avg_expenses = floatval($stats->total_expenses ?: 0) / 3;
        
        return [
            'optimistic' => $avg_expenses * 0.9,  // 10% riduzione
            'realistic' => $avg_expenses * 1.0,   // Media
            'pessimistic' => $avg_expenses * 1.1, // 10% aumento
        ];
    }
    
    /**
     * Ottieni summary proiezioni per mese/anno
     */
    public function get_projections_summary($month, $year) {
        $income_projections = $this->calculate_income_projections($month, $year);
        $expense_projections = $this->calculate_expense_projections($month, $year);
        
        $summary = [];
        foreach (['optimistic', 'realistic', 'pessimistic'] as $scenario) {
            $summary[$scenario] = [
                'projected_income' => $income_projections[$scenario] ?? 0,
                'projected_expenses' => $expense_projections[$scenario] ?? 0,
                'projected_net' => ($income_projections[$scenario] ?? 0) - ($expense_projections[$scenario] ?? 0),
            ];
        }
        
        return $summary;
    }
    
    /**
     * Confronta proiezioni con dati reali
     */
    public function compare_with_actual($month, $year) {
        $projection = ProjectionModel::get($month, $year, 'realistic');
        
        if (!$projection) {
            return null;
        }
        
        // Calcola dati reali per il mese
        $month_start = sprintf('%04d-%02d-01', $year, $month);
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $actual_stats = StatsService::get_instance()->calculate_period_stats($month_start, $month_end);
        $actual_income = floatval($actual_stats->total_income ?: 0);
        
        $projected_income = floatval($projection->projected_income ?: 0);
        
        return [
            'projected' => $projected_income,
            'actual' => $actual_income,
            'difference' => $actual_income - $projected_income,
            'percentage_diff' => $projected_income > 0 ? (($actual_income - $projected_income) / $projected_income) * 100 : 0,
        ];
    }
}
