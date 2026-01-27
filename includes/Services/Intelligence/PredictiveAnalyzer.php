<?php
/**
 * Predictive Analyzer
 *
 * Analisi predittiva avanzata basata su trend, pattern e volatilità storica.
 * Usa fattori stagionali da dati reali (PatternAnalyzer) e scenari da std_dev.
 */

namespace FP\FinanceHub\Services\Intelligence;

use FP\FinanceHub\Services\StatsService;
use FP\FinanceHub\Services\ProjectionService;

if (!defined('ABSPATH')) {
    exit;
}

class PredictiveAnalyzer {

    /** @var PatternAnalyzer|null */
    private $pattern_analyzer;

    /**
     * @param PatternAnalyzer|null $pattern_analyzer Se null, creato al volo.
     */
    public function __construct(PatternAnalyzer $pattern_analyzer = null) {
        $this->pattern_analyzer = $pattern_analyzer;
    }

    private function get_pattern_analyzer() {
        if ($this->pattern_analyzer === null) {
            $this->pattern_analyzer = new PatternAnalyzer();
        }
        return $this->pattern_analyzer;
    }

    /**
     * Genera predizioni per giorni futuri
     */
    public function generate_predictions($days_ahead = 30) {
        $predictions = [
            'period_days' => $days_ahead,
            'generated_at' => time(),
            'income' => [],
            'expenses' => [],
            'cashflow' => [],
            'scenarios' => [],
        ];
        
        // Predizioni entrate
        $predictions['income'] = $this->predict_income($days_ahead);
        
        // Predizioni uscite
        $predictions['expenses'] = $this->predict_expenses($days_ahead);
        
        // Predizioni cash flow
        $predictions['cashflow'] = $this->predict_cashflow($days_ahead);
        
        // Scenari multipli
        $predictions['scenarios'] = $this->generate_scenarios($days_ahead);
        
        return $predictions;
    }
    
    /**
     * Predici entrate future (trend + stagionalità da dati + intervallo)
     */
    private function predict_income($days_ahead) {
        $months_data = $this->get_monthly_series(6, 'income');
        $trend = $this->calculate_linear_trend($months_data);
        $current_month_income = !empty($months_data) ? end($months_data) : 0;
        $count = count($months_data);
        $avg_monthly_income = $count > 0 ? array_sum($months_data) / $count : 0;
        $std_income = $this->std_dev($months_data);

        $months_ahead = max(0.1, $days_ahead / 30);
        $predicted_income = $current_month_income * (1 + $trend * $months_ahead);

        $factors = $this->get_pattern_analyzer()->get_seasonal_factors_from_data();
        $next_month = (int) date('n', strtotime("+{$days_ahead} days"));
        $seasonal_factor = $factors['income'][$next_month] ?? 1.0;
        $predicted_income *= $seasonal_factor;
        $predicted_income = max(0, $predicted_income);

        $k = 1.5;
        $interval_low = max(0, $predicted_income - $k * ($std_income * $months_ahead));
        $interval_high = $predicted_income + $k * ($std_income * $months_ahead);

        $month_name = date_i18n('F', mktime(0, 0, 0, $next_month, 1));
        $explanation = sprintf(
            __('Basato su trend ultimi 6 mesi: %+.1f%%, fattore stagionale %s: %.1f%%, volatilità storica: €%.2f', 'fp-finance-hub'),
            $trend * 100,
            $month_name,
            ($seasonal_factor - 1) * 100,
            $std_income
        );
        
        return [
            'predicted' => $predicted_income,
            'prediction_interval' => ['low' => $interval_low, 'high' => $interval_high],
            'based_on_trend' => $trend * 100,
            'baseline' => $avg_monthly_income,
            'volatility' => $std_income,
            'seasonal_factor' => $seasonal_factor,
            'method' => 'linear_trend_seasonal_volatility',
            'explanation' => $explanation,
        ];
    }
    
    /**
     * Predici uscite future (trend + stagionalità da dati + intervallo)
     */
    private function predict_expenses($days_ahead) {
        $months_data = $this->get_monthly_series(6, 'expenses');
        $trend = $this->calculate_linear_trend($months_data);
        $current_month_expenses = !empty($months_data) ? end($months_data) : 0;
        $count = count($months_data);
        $avg_monthly_expenses = $count > 0 ? array_sum($months_data) / $count : 0;
        $std_expenses = $this->std_dev($months_data);

        $months_ahead = max(0.1, $days_ahead / 30);
        $predicted_expenses = $current_month_expenses * (1 + $trend * $months_ahead);

        $factors = $this->get_pattern_analyzer()->get_seasonal_factors_from_data();
        $next_month = (int) date('n', strtotime("+{$days_ahead} days"));
        $seasonal_factor = $factors['expense'][$next_month] ?? 1.0;
        $predicted_expenses *= $seasonal_factor;
        $predicted_expenses = max(0, $predicted_expenses);

        $k = 1.5;
        $interval_low = max(0, $predicted_expenses - $k * ($std_expenses * $months_ahead));
        $interval_high = $predicted_expenses + $k * ($std_expenses * $months_ahead);

        $month_name = date_i18n('F', mktime(0, 0, 0, $next_month, 1));
        $explanation = sprintf(
            __('Basato su trend ultimi 6 mesi: %+.1f%%, fattore stagionale %s: %.1f%%, volatilità storica: €%.2f', 'fp-finance-hub'),
            $trend * 100,
            $month_name,
            ($seasonal_factor - 1) * 100,
            $std_expenses
        );
        
        return [
            'predicted' => $predicted_expenses,
            'prediction_interval' => ['low' => $interval_low, 'high' => $interval_high],
            'based_on_trend' => $trend * 100,
            'baseline' => $avg_monthly_expenses,
            'volatility' => $std_expenses,
            'seasonal_factor' => $seasonal_factor,
            'method' => 'linear_trend_seasonal_volatility',
            'explanation' => $explanation,
        ];
    }
    
    /**
     * Predici cash flow futuro (con intervallo)
     */
    private function predict_cashflow($days_ahead) {
        $income_prediction = $this->predict_income($days_ahead);
        $expenses_prediction = $this->predict_expenses($days_ahead);

        $predicted = $income_prediction['predicted'] - $expenses_prediction['predicted'];
        $il = $income_prediction['prediction_interval'] ?? [];
        $el = $expenses_prediction['prediction_interval'] ?? [];
        $interval_low = ($il['low'] ?? $income_prediction['predicted']) - ($el['high'] ?? $expenses_prediction['predicted']);
        $interval_high = ($il['high'] ?? $income_prediction['predicted']) - ($el['low'] ?? $expenses_prediction['predicted']);

        $months_data = $this->get_monthly_series(6, 'cashflow');
        $cashflow_trend = $this->calculate_linear_trend($months_data);
        
        $explanation = sprintf(
            __('Differenza tra entrate previste (trend %+.1f%%) e uscite previste (trend %+.1f%%)', 'fp-finance-hub'),
            ($income_prediction['based_on_trend'] ?? 0),
            ($expenses_prediction['based_on_trend'] ?? 0)
        );

        return [
            'predicted' => $predicted,
            'prediction_interval' => ['low' => $interval_low, 'high' => $interval_high],
            'trend' => $cashflow_trend * 100,
            'risk_level' => $predicted < 0 ? 'high' : ($predicted < 1000 ? 'medium' : 'low'),
            'method' => 'income_expenses_difference',
            'explanation' => $explanation,
        ];
    }
    
    /**
     * Genera scenari multipli da volatilità storica (std_dev)
     * Ottimistico: +1.5σ entrate, -1.5σ uscite. Pessimistico: -1.5σ entrate, +1.5σ uscite.
     */
    private function generate_scenarios($days_ahead) {
        $income_prediction = $this->predict_income($days_ahead);
        $expenses_prediction = $this->predict_expenses($days_ahead);

        $bi = $income_prediction['predicted'];
        $be = $expenses_prediction['predicted'];
        $vol_i = $income_prediction['volatility'] ?? 0;
        $vol_e = $expenses_prediction['volatility'] ?? 0;
        $months_ahead = max(0.1, $days_ahead / 30);
        $k = 1.5;
        $shift_i = $k * $vol_i * $months_ahead;
        $shift_e = $k * $vol_e * $months_ahead;

        $optimistic_income = max(0, $bi + $shift_i);
        $optimistic_expenses = max(0, $be - $shift_e);
        $optimistic_cashflow = $optimistic_income - $optimistic_expenses;

        $realistic_income = max(0, $bi);
        $realistic_expenses = max(0, $be);
        $realistic_cashflow = $realistic_income - $realistic_expenses;

        $pessimistic_income = max(0, $bi - $shift_i);
        $pessimistic_expenses = max(0, $be + $shift_e);
        $pessimistic_cashflow = $pessimistic_income - $pessimistic_expenses;

        return [
            'optimistic' => [
                'income' => $optimistic_income,
                'expenses' => $optimistic_expenses,
                'cashflow' => $optimistic_cashflow,
                'based_on' => 'mean_plus_minus_volatility',
                'explanation' => sprintf(
                    __('Ottimistico: +%.1fσ entrate, -%.1fσ uscite (basato su volatilità storica)', 'fp-finance-hub'),
                    $k,
                    $k
                ),
            ],
            'realistic' => [
                'income' => $realistic_income,
                'expenses' => $realistic_expenses,
                'cashflow' => $realistic_cashflow,
                'based_on' => 'baseline',
                'explanation' => __('Realistico: baseline basato su trend e stagionalità', 'fp-finance-hub'),
            ],
            'pessimistic' => [
                'income' => $pessimistic_income,
                'expenses' => $pessimistic_expenses,
                'cashflow' => $pessimistic_cashflow,
                'based_on' => 'mean_minus_plus_volatility',
                'explanation' => sprintf(
                    __('Pessimistico: -%.1fσ entrate, +%.1fσ uscite (basato su volatilità storica)', 'fp-finance-hub'),
                    $k,
                    $k
                ),
            ],
        ];
    }
    
    /**
     * Serie mensile ultimi N mesi (income, expenses o cashflow)
     *
     * @param int    $n_months
     * @param string $type 'income'|'expenses'|'cashflow'
     * @return array<float>
     */
    private function get_monthly_series($n_months, $type) {
        $out = [];
        for ($i = $n_months - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} months"));
            $month_start = date('Y-m-01', strtotime($date));
            $month_end = date('Y-m-t', strtotime($date));
            $stats = StatsService::get_instance()->calculate_period_stats($month_start, $month_end);
            $inc = floatval($stats->total_income ?? 0);
            $exp = floatval($stats->total_expenses ?? 0);
            if ($type === 'income') {
                $out[] = $inc;
            } elseif ($type === 'expenses') {
                $out[] = $exp;
            } else {
                $out[] = $inc - $exp;
            }
        }
        return $out;
    }

    /**
     * Deviazione standard (campione)
     */
    private function std_dev(array $values) {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return ($x - $mean) ** 2;
        }, $values)) / ($n - 1);
        return $variance > 0 ? sqrt($variance) : 0.0;
    }

    /**
     * Calcola trend lineare (regressione semplice)
     */
    private function calculate_linear_trend(array $values) {
        if (count($values) < 2) {
            return 0;
        }

        $n = count($values);
        $sum_x = 0;
        $sum_y = 0;
        $sum_xy = 0;
        $sum_x2 = 0;

        foreach ($values as $i => $y) {
            $x = $i + 1;
            $sum_x += $x;
            $sum_y += $y;
            $sum_xy += $x * $y;
            $sum_x2 += $x * $x;
        }

        $denominator = ($n * $sum_x2) - ($sum_x * $sum_x);
        if ($denominator == 0) {
            return 0;
        }

        $slope = (($n * $sum_xy) - ($sum_x * $sum_y)) / $denominator;
        $first_value = $values[0];
        if ($first_value == 0) {
            return 0;
        }
        return $slope / $first_value;
    }
}
