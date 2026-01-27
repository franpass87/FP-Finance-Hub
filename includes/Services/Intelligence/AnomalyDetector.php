<?php
/**
 * Anomaly Detector
 * 
 * Rileva anomalie nei dati finanziari usando statistica
 */

namespace FP\FinanceHub\Services\Intelligence;

use FP\FinanceHub\Services\StatsService;
use FP\FinanceHub\Services\BankService;
use FP\FinanceHub\Services\InvoiceService;

if (!defined('ABSPATH')) {
    exit;
}

class AnomalyDetector {
    
    /**
     * Rileva tutte le anomalie per periodo
     */
    public function detect_all($start_date, $end_date) {
        $anomalies = [];
        
        // Anomalie spese
        $anomalies = array_merge($anomalies, $this->detect_expense_anomalies($start_date, $end_date));
        
        // Anomalie entrate
        $anomalies = array_merge($anomalies, $this->detect_income_anomalies($start_date, $end_date));
        
        // Anomalie cash flow
        $anomalies = array_merge($anomalies, $this->detect_cashflow_anomalies($start_date, $end_date));
        
        // Anomalie fatturazione
        $anomalies = array_merge($anomalies, $this->detect_invoice_anomalies($start_date, $end_date));
        
        // Ordina per severità e confidence
        usort($anomalies, function($a, $b) {
            $severity_order = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            $a_severity = $severity_order[$a['severity'] ?? 'low'] ?? 1;
            $b_severity = $severity_order[$b['severity'] ?? 'low'] ?? 1;
            
            if ($a_severity !== $b_severity) {
                return $b_severity - $a_severity;
            }
            
            return ($b['confidence'] ?? 0) - ($a['confidence'] ?? 0);
        });
        
        return $anomalies;
    }
    
    /**
     * Rileva anomalie nelle spese
     */
    private function detect_expense_anomalies($start_date, $end_date) {
        global $wpdb;
        
        $anomalies = [];
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        // Ottieni dati periodo corrente
        $current_stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
        $current_expenses = floatval($current_stats->total_expenses ?? 0);
        
        // Ottieni dati storici (ultimi 3 periodi equivalenti)
        $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;
        $historical_expenses = [];
        
        for ($i = 1; $i <= 3; $i++) {
            $hist_start = date('Y-m-d', strtotime($start_date . " -{$i} {$period_days} days"));
            $hist_end = date('Y-m-d', strtotime($end_date . " -{$i} {$period_days} days"));
            $hist_stats = StatsService::get_instance()->calculate_period_stats($hist_start, $hist_end);
            $historical_expenses[] = floatval($hist_stats->total_expenses ?? 0);
        }
        
        if (!empty($historical_expenses)) {
            $stats = $this->calculate_statistics($historical_expenses);
            $z_threshold = floatval(get_option('fp_finance_hub_intelligence_zscore_threshold', 2.0));
            $is_z_anomaly = $stats['std_dev'] > 0 && $current_expenses > $stats['mean'] + ($z_threshold * $stats['std_dev']);
            $is_iqr_anomaly = $this->is_iqr_outlier($historical_expenses, $current_expenses, 'high');

            if ($is_z_anomaly || $is_iqr_anomaly) {
                $z_score = $stats['std_dev'] > 0 ? abs($current_expenses - $stats['mean']) / $stats['std_dev'] : 0;
                $severity = $this->calculate_severity($current_expenses, $stats['mean'], max($stats['std_dev'], 1));
                $confidence = $this->calculate_confidence($current_expenses, $stats);
                $method = $is_z_anomaly && $is_iqr_anomaly ? 'z_score_and_iqr' : ($is_iqr_anomaly ? 'iqr' : 'z_score');

                $anomalies[] = [
                    'type' => 'expense_anomaly',
                    'category' => 'expense',
                    'severity' => $severity,
                    'confidence' => $confidence,
                    'message' => sprintf(
                        'Spese anomale: €%.2f (media storica: €%.2f) - %.1fx la media',
                        $current_expenses,
                        $stats['mean'],
                        $current_expenses / ($stats['mean'] > 0 ? $stats['mean'] : 1)
                    ),
                    'current_value' => $current_expenses,
                    'historical_mean' => $stats['mean'],
                    'historical_std_dev' => $stats['std_dev'],
                    'z_score' => $z_score,
                    'detection_method' => $method,
                ];
            }
            
            // Rileva anomalie per categoria
            $category_anomalies = $this->detect_category_expense_anomalies($start_date, $end_date);
            $anomalies = array_merge($anomalies, $category_anomalies);
        }

        // Outlier su singole transazioni (importi eccezionali)
        $tx_outliers = $this->detect_single_transaction_outliers($start_date, $end_date);
        $anomalies = array_merge($anomalies, $tx_outliers);
        return $anomalies;
    }

    /**
     * Outlier IQR: valore oltre Q1 - 1.5*IQR (low) o Q3 + 1.5*IQR (high)
     */
    private function is_iqr_outlier(array $historical, $current, $direction = 'high') {
        if (count($historical) < 4) {
            return false;
        }
        $sorted = $historical;
        sort($sorted, SORT_NUMERIC);
        $n = count($sorted);
        $q1 = $sorted[(int) floor($n * 0.25)];
        $q3 = $sorted[(int) floor($n * 0.75)];
        $iqr = $q3 - $q1;
        if ($iqr <= 0) {
            return false;
        }
        $k = floatval(get_option('fp_finance_hub_intelligence_iqr_factor', 1.5));
        if ($direction === 'high') {
            return $current > $q3 + $k * $iqr;
        }
        return $current < $q1 - $k * $iqr;
    }
    
    /**
     * Rileva anomalie spese per categoria
     */
    private function detect_category_expense_anomalies($start_date, $end_date) {
        global $wpdb;
        
        $anomalies = [];
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        // Ottieni spese per categoria periodo corrente
        $sql = $wpdb->prepare(
            "SELECT category, SUM(ABS(amount)) as total
            FROM {$transactions_table}
            WHERE transaction_date BETWEEN %s AND %s
            AND amount < 0
            AND category IS NOT NULL
            AND category != ''
            GROUP BY category",
            $start_date,
            $end_date
        );
        
        $current_categories = $wpdb->get_results($sql);
        
        if (!empty($current_categories) && is_array($current_categories)) {
            foreach ($current_categories as $cat) {
                $cat_name = $cat->category;
                $hist_start = date('Y-m-01', strtotime('-6 months'));
                $hist_end = date('Y-m-t', strtotime('-1 day'));
                $monthly_totals = [];
                for ($m = 5; $m >= 0; $m--) {
                    $ms = date('Y-m-01', strtotime("-{$m} months"));
                    $me = date('Y-m-t', strtotime($ms));
                    $s = $wpdb->prepare(
                        "SELECT COALESCE(SUM(ABS(amount)), 0) as t FROM {$transactions_table}
                        WHERE transaction_date BETWEEN %s AND %s AND amount < 0 AND category = %s",
                        $ms,
                        $me,
                        $cat_name
                    );
                    $row = $wpdb->get_row($s);
                    $monthly_totals[] = floatval($row->t ?? 0);
                }
                $monthly_totals = array_filter($monthly_totals, function ($v) { return $v > 0; });
                if (empty($monthly_totals)) {
                    continue;
                }
                $stats_cat = $this->calculate_statistics(array_values($monthly_totals));
                $hist_avg = $stats_cat['mean'];
                $std_cat = max($stats_cat['std_dev'], $hist_avg * 0.1);

                $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;
                $period_months = max(0.5, $period_days / 30);
                $expected = $hist_avg * $period_months;
                $current_total = floatval($cat->total ?? 0);
                
                $z_threshold = floatval(get_option('fp_finance_hub_intelligence_zscore_threshold', 2.0));
                $is_z = $expected > 0 && $std_cat > 0 && $current_total > $expected + $z_threshold * $std_cat * sqrt($period_months);
                $is_iqr = $this->is_iqr_outlier($monthly_totals, $current_total / max(0.5, $period_months), 'high');

                if ($is_z || $is_iqr || ($expected > 0 && $current_total > $expected * 2)) {
                    $z_score = $std_cat > 0 ? abs($current_total - $expected) / ($std_cat * sqrt($period_months)) : 0;
                    $severity = $this->calculate_severity($current_total, $expected, $std_cat * sqrt($period_months));
                    $anomalies[] = [
                        'type' => 'category_expense_anomaly',
                        'category' => 'expense',
                        'severity' => $severity,
                        'confidence' => min(0.95, 0.5 + $z_score / 4),
                        'message' => sprintf(
                            "Spesa insolita di €%.2f in categoria '%s' - %.1fx la media mensile (std reale)",
                            $current_total,
                            $cat_name,
                            $current_total / ($expected / $period_months)
                        ),
                        'category_name' => $cat_name,
                        'current_value' => $current_total,
                        'historical_mean' => $expected,
                        'historical_std_dev' => $std_cat,
                    ];
                }
            }
        }
        
        return $anomalies;
    }
    
    /**
     * Rileva anomalie nelle entrate
     */
    private function detect_income_anomalies($start_date, $end_date) {
        $anomalies = [];
        
        // Ottieni dati periodo corrente
        $current_stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
        $current_income = floatval($current_stats->total_income ?? 0);
        
        // Ottieni dati storici
        $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;
        $historical_income = [];
        
        for ($i = 1; $i <= 3; $i++) {
            $hist_start = date('Y-m-d', strtotime($start_date . " -{$i} {$period_days} days"));
            $hist_end = date('Y-m-d', strtotime($end_date . " -{$i} {$period_days} days"));
            $hist_stats = StatsService::get_instance()->calculate_period_stats($hist_start, $hist_end);
            $historical_income[] = floatval($hist_stats->total_income ?? 0);
        }
        
        if (!empty($historical_income)) {
            $stats = $this->calculate_statistics($historical_income);
            
            // Rileva anomalia se entrate < media - z_threshold deviazioni standard (entrate mancanti)
            $z_threshold = floatval(get_option('fp_finance_hub_intelligence_zscore_threshold', 2.0));
            if ($current_income < $stats['mean'] - ($z_threshold * $stats['std_dev']) && $stats['std_dev'] > 0) {
                $z_score = abs($current_income - $stats['mean']) / $stats['std_dev'];
                $severity = $this->calculate_severity($stats['mean'], $current_income, $stats['std_dev']);
                
                $anomalies[] = [
                    'type' => 'income_anomaly',
                    'category' => 'income',
                    'severity' => $severity,
                    'confidence' => $this->calculate_confidence($current_income, $stats),
                    'message' => sprintf(
                        'Entrate inferiori al previsto: €%.2f (media storica: €%.2f) - %.1f%% della media',
                        $current_income,
                        $stats['mean'],
                        ($current_income / ($stats['mean'] > 0 ? $stats['mean'] : 1)) * 100
                    ),
                    'current_value' => $current_income,
                    'historical_mean' => $stats['mean'],
                    'historical_std_dev' => $stats['std_dev'],
                    'z_score' => $z_score,
                ];
            }
        }
        
        return $anomalies;
    }
    
    /**
     * Rileva anomalie nel cash flow
     */
    private function detect_cashflow_anomalies($start_date, $end_date) {
        $anomalies = [];
        
        // Calcola cash flow periodo corrente
        $current_stats = StatsService::get_instance()->calculate_period_stats($start_date, $end_date);
        $current_cashflow = floatval($current_stats->total_income ?? 0) - floatval($current_stats->total_expenses ?? 0);
        
        // Ottieni cash flow storici
        $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;
        $historical_cashflow = [];
        
        for ($i = 1; $i <= 3; $i++) {
            $hist_start = date('Y-m-d', strtotime($start_date . " -{$i} {$period_days} days"));
            $hist_end = date('Y-m-d', strtotime($end_date . " -{$i} {$period_days} days"));
            $hist_stats = StatsService::get_instance()->calculate_period_stats($hist_start, $hist_end);
            $hist_cashflow = floatval($hist_stats->total_income ?? 0) - floatval($hist_stats->total_expenses ?? 0);
            $historical_cashflow[] = $hist_cashflow;
        }
        
        if (!empty($historical_cashflow)) {
            $stats = $this->calculate_statistics($historical_cashflow);
            
            // Rileva cash flow negativo anomalo
            if ($current_cashflow < 0 && $stats['mean'] > 0) {
                $severity = abs($current_cashflow) > abs($stats['mean']) ? 'high' : 'medium';
                
                $anomalies[] = [
                    'type' => 'cashflow_negative',
                    'category' => 'cashflow',
                    'severity' => $severity,
                    'confidence' => 0.8,
                    'message' => sprintf(
                        'Cash flow negativo: €%.2f (media storica: €%.2f)',
                        $current_cashflow,
                        $stats['mean']
                    ),
                    'current_value' => $current_cashflow,
                    'historical_mean' => $stats['mean'],
                ];
            }
        }
        
        return $anomalies;
    }
    
    /**
     * Rileva anomalie nelle fatture
     */
    private function detect_invoice_anomalies($start_date, $end_date) {
        $anomalies = [];
        
        // Fatture scadute non pagate
        $overdue_invoices = InvoiceService::get_instance()->get_overdue_invoices();
        
        if (!empty($overdue_invoices)) {
            $total_overdue = 0;
            foreach ($overdue_invoices as $invoice) {
                $total_overdue += floatval($invoice->total_amount ?? 0);
            }
            
            if ($total_overdue > 0) {
                $anomalies[] = [
                    'type' => 'overdue_invoices',
                    'category' => 'invoices',
                    'severity' => $total_overdue > 5000 ? 'high' : 'medium',
                    'confidence' => 0.9,
                    'message' => sprintf(
                        '%d fatture scadute per un totale di €%.2f',
                        count($overdue_invoices),
                        $total_overdue
                    ),
                    'invoice_count' => count($overdue_invoices),
                    'total_amount' => $total_overdue,
                ];
            }
        }
        
        return $anomalies;
    }
    
    /**
     * Calcola statistiche (mean, std_dev, min, max)
     */
    private function calculate_statistics(array $values) {
        $count = count($values);
        if ($count === 0) {
            return ['mean' => 0, 'std_dev' => 0, 'min' => 0, 'max' => 0, 'count' => 0];
        }
        
        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / $count;
        $std_dev = sqrt($variance);
        
        return [
            'mean' => $mean,
            'std_dev' => $std_dev,
            'min' => min($values),
            'max' => max($values),
            'count' => $count,
        ];
    }
    
    /**
     * Calcola severità anomalia
     */
    private function calculate_severity($current_value, $mean, $std_dev) {
        if ($std_dev == 0) {
            return 'low';
        }
        
        $z_score = abs($current_value - $mean) / $std_dev;
        
        if ($z_score >= 3) {
            return 'critical';
        } elseif ($z_score >= 2.5) {
            return 'high';
        } elseif ($z_score >= 2) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Rileva outlier su singole transazioni (importi eccezionali)
     */
    private function detect_single_transaction_outliers($start_date, $end_date) {
        global $wpdb;

        $anomalies = [];
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';

        $sql = $wpdb->prepare(
            "SELECT id, amount, category, description, transaction_date
            FROM {$transactions_table}
            WHERE transaction_date BETWEEN %s AND %s AND amount < 0",
            $start_date,
            $end_date
        );
        $rows = $wpdb->get_results($sql);
        if (empty($rows) || !is_array($rows)) {
            return $anomalies;
        }

        $amounts = array_map(function ($r) { return abs(floatval($r->amount)); }, $rows);
        $median = $this->median($amounts);
        if ($median <= 0) {
            return $anomalies;
        }

        $threshold = max(500, $median * 3);
        foreach ($rows as $r) {
            $abs = abs(floatval($r->amount));
            if ($abs >= $threshold) {
                $anomalies[] = [
                    'type' => 'single_transaction_outlier',
                    'category' => 'expense',
                    'severity' => $abs >= $median * 5 ? 'high' : 'medium',
                    'confidence' => min(0.95, 0.6 + ($abs / ($median * 10))),
                    'message' => sprintf(
                        'Transazione eccezionale: €%.2f il %s - %s',
                        $abs,
                        date_i18n(get_option('date_format'), strtotime($r->transaction_date)),
                        $r->description ? wp_trim_words($r->description, 5) : ($r->category ?? 'senza categoria')
                    ),
                    'transaction_id' => $r->id ?? null,
                    'amount' => $abs,
                    'category_name' => $r->category ?? '',
                    'median_reference' => $median,
                ];
            }
        }

        return $anomalies;
    }

    private function median(array $values) {
        $values = array_values($values);
        sort($values, SORT_NUMERIC);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }
        $mid = (int) floor($n / 2);
        return $n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /**
     * Calcola confidence score
     */
    private function calculate_confidence($current_value, $stats) {
        if ($stats['std_dev'] == 0) {
            return 0.5;
        }
        
        $z_score = abs($current_value - $stats['mean']) / $stats['std_dev'];
        $base_confidence = min(0.95, $z_score / 3);
        $sample_factor = min(1.0, $stats['count'] / 10); // Più campioni = più confidence
        
        return $base_confidence * $sample_factor;
    }
}
