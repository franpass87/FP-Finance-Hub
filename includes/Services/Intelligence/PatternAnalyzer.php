<?php
/**
 * Pattern Analyzer
 * 
 * Analizza pattern e trend nei dati finanziari
 */

namespace FP\FinanceHub\Services\Intelligence;

use FP\FinanceHub\Services\StatsService;

if (!defined('ABSPATH')) {
    exit;
}

class PatternAnalyzer {
    
    /**
     * Analizza pattern per periodo
     */
    public function analyze_patterns($start_date, $end_date) {
        $patterns = [];
        
        // Pattern stagionali
        $patterns = array_merge($patterns, $this->detect_seasonal_patterns($start_date, $end_date));
        
        // Pattern ciclici
        $patterns = array_merge($patterns, $this->detect_cyclic_patterns($start_date, $end_date));
        
        // Trend identification
        $patterns = array_merge($patterns, $this->identify_trends($start_date, $end_date));
        
        // Pattern categorie
        $patterns = array_merge($patterns, $this->analyze_category_patterns($start_date, $end_date));
        
        // Pattern temporali
        $patterns = array_merge($patterns, $this->analyze_temporal_patterns($start_date, $end_date));
        
        // Transazioni ricorrenti (abbonamenti)
        $patterns = array_merge($patterns, $this->detect_recurring_transactions($start_date, $end_date));
        
        return $patterns;
    }
    
    /**
     * Rileva pattern stagionali
     */
    private function detect_seasonal_patterns($start_date, $end_date) {
        $patterns = [];
        
        // Analizza ultimi 12 mesi per stagionalità
        $months_data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} months"));
            $month_start = date('Y-m-01', strtotime($date));
            $month_end = date('Y-m-t', strtotime($date));
            
            $stats = StatsService::get_instance()->calculate_period_stats($month_start, $month_end);
            $months_data[] = [
                'month' => (int)date('n', strtotime($date)),
                'income' => floatval($stats->total_income ?? 0),
                'expenses' => floatval($stats->total_expenses ?? 0),
            ];
        }
        
        // Raggruppa per mese (1-12) per trovare stagionalità
        $monthly_avg = [];
        foreach ($months_data as $data) {
            $month = $data['month'];
            if (!isset($monthly_avg[$month])) {
                $monthly_avg[$month] = ['income' => [], 'expenses' => []];
            }
            $monthly_avg[$month]['income'][] = $data['income'];
            $monthly_avg[$month]['expenses'][] = $data['expenses'];
        }
        
        // Calcola medie mensili
        $monthly_averages = [];
        foreach ($monthly_avg as $month => $data) {
            $monthly_averages[$month] = [
                'income' => !empty($data['income']) ? array_sum($data['income']) / count($data['income']) : 0,
                'expenses' => !empty($data['expenses']) ? array_sum($data['expenses']) / count($data['expenses']) : 0,
            ];
        }
        
        // Calcola media globale
        $global_avg_income = !empty($monthly_averages) ? array_sum(array_column($monthly_averages, 'income')) / count($monthly_averages) : 0;
        $global_avg_expenses = !empty($monthly_averages) ? array_sum(array_column($monthly_averages, 'expenses')) / count($monthly_averages) : 0;
        
        // Identifica mesi con variazioni significative (>15%)
        foreach ($monthly_averages as $month => $avg) {
            $month_name = date_i18n('F', mktime(0, 0, 0, $month, 1));
            
            if ($global_avg_income > 0 && abs($avg['income'] - $global_avg_income) / $global_avg_income > 0.15) {
                $variation = (($avg['income'] - $global_avg_income) / $global_avg_income) * 100;
                $patterns[] = [
                    'type' => 'seasonal_pattern',
                    'category' => 'income',
                    'pattern' => sprintf(
                        'Pattern stagionale: Entrate aumentano del %.1f%% in %s',
                        abs($variation),
                        $month_name
                    ),
                    'month' => $month,
                    'month_name' => $month_name,
                    'variation_percentage' => $variation,
                    'confidence' => 0.7,
                ];
            }
            
            if ($global_avg_expenses > 0 && abs($avg['expenses'] - $global_avg_expenses) / $global_avg_expenses > 0.15) {
                $variation = (($avg['expenses'] - $global_avg_expenses) / $global_avg_expenses) * 100;
                $patterns[] = [
                    'type' => 'seasonal_pattern',
                    'category' => 'expense',
                    'pattern' => sprintf(
                        'Pattern stagionale: Uscite aumentano del %.1f%% in %s',
                        abs($variation),
                        $month_name
                    ),
                    'month' => $month,
                    'month_name' => $month_name,
                    'variation_percentage' => $variation,
                    'confidence' => 0.7,
                ];
            }
        }
        
        return $patterns;
    }
    
    /**
     * Rileva pattern ciclici (ricorrenti)
     */
    private function detect_cyclic_patterns($start_date, $end_date) {
        global $wpdb;
        
        $patterns = [];
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        // Analizza pattern per giorno della settimana
        $sql = $wpdb->prepare(
            "SELECT 
                DAYOFWEEK(transaction_date) as day_of_week,
                AVG(ABS(amount)) as avg_amount,
                COUNT(*) as count
            FROM {$transactions_table}
            WHERE transaction_date BETWEEN %s AND %s
            AND amount < 0
            GROUP BY DAYOFWEEK(transaction_date)
            HAVING count > 5",
            $start_date,
            $end_date
        );
        
        $daily_patterns = $wpdb->get_results($sql);
        
        if (!empty($daily_patterns) && is_array($daily_patterns)) {
            $count = count($daily_patterns);
            $avg_all = $count > 0 ? array_sum(array_column($daily_patterns, 'avg_amount')) / $count : 0;
            
            foreach ($daily_patterns as $pattern) {
                $day_num = intval($pattern->day_of_week);
                $day_names = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
                $day_name = $day_names[$day_num - 1] ?? 'Unknown';
                
                $avg_day = floatval($pattern->avg_amount);
                
                if ($avg_all > 0 && abs($avg_day - $avg_all) / $avg_all > 0.2) {
                    $variation = (($avg_day - $avg_all) / $avg_all) * 100;
                    $patterns[] = [
                        'type' => 'cyclic_pattern',
                        'category' => 'expense',
                        'pattern' => sprintf(
                            'Ciclo ricorrente: Spese elevate ogni %s (%.1f%% sopra media)',
                            $day_name,
                            abs($variation)
                        ),
                        'day_of_week' => $day_num,
                        'day_name' => $day_name,
                        'variation_percentage' => $variation,
                        'confidence' => 0.6,
                    ];
                }
            }
        }
        
        return $patterns;
    }
    
    /**
     * Identifica trend (crescente/decrescente/stabile)
     */
    private function identify_trends($start_date, $end_date) {
        $patterns = [];
        
        // Analizza trend ultimi 6 mesi
        $months_data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} months"));
            $month_start = date('Y-m-01', strtotime($date));
            $month_end = date('Y-m-t', strtotime($date));
            
            $stats = StatsService::get_instance()->calculate_period_stats($month_start, $month_end);
            $months_data[] = [
                'income' => floatval($stats->total_income ?? 0),
                'expenses' => floatval($stats->total_expenses ?? 0),
            ];
        }
        
        // Calcola trend lineare per entrate
        $income_trend = $this->calculate_linear_trend(array_column($months_data, 'income'));
        if (abs($income_trend) > 0.05) { // >5% variazione
            $patterns[] = [
                'type' => 'trend',
                'category' => 'income',
                'pattern' => sprintf(
                    'Trend %s: Entrate %s del %.1f%% negli ultimi 6 mesi',
                    $income_trend > 0 ? 'crescente' : 'decrescente',
                    $income_trend > 0 ? 'aumentate' : 'diminuite',
                    abs($income_trend) * 100
                ),
                'trend_direction' => $income_trend > 0 ? 'up' : 'down',
                'trend_percentage' => $income_trend * 100,
                'confidence' => 0.75,
            ];
        }
        
        // Calcola trend lineare per uscite
        $expenses_values = !empty($months_data) ? array_column($months_data, 'expenses') : [];
        $expenses_trend = !empty($expenses_values) ? $this->calculate_linear_trend($expenses_values) : 0;
        if (abs($expenses_trend) > 0.05) {
            $patterns[] = [
                'type' => 'trend',
                'category' => 'expense',
                'pattern' => sprintf(
                    'Trend %s: Uscite %s del %.1f%% negli ultimi 6 mesi',
                    $expenses_trend > 0 ? 'crescente' : 'decrescente',
                    $expenses_trend > 0 ? 'aumentate' : 'diminuite',
                    abs($expenses_trend) * 100
                ),
                'trend_direction' => $expenses_trend > 0 ? 'up' : 'down',
                'trend_percentage' => $expenses_trend * 100,
                'confidence' => 0.75,
            ];
        }
        
        return $patterns;
    }
    
    /**
     * Analizza pattern per categoria
     */
    private function analyze_category_patterns($start_date, $end_date) {
        global $wpdb;
        
        $patterns = [];
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        // Ottieni categorie con trend crescente/decrescente
        $categories = StatsService::get_instance()->calculate_category_stats($start_date, $end_date);
        
        // Analizza trend per ciascuna categoria (ultimi 6 mesi vs precedenti 6 mesi)
        $period_days = defined('DAY_IN_SECONDS') ? (strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS : (strtotime($end_date) - strtotime($start_date)) / 86400;
        $previous_start = date('Y-m-d', strtotime($start_date . " -{$period_days} days"));
        $previous_end = $start_date;
        
        $previous_categories = StatsService::get_instance()->calculate_category_stats($previous_start, $previous_end);
        
        $previous_map = [];
        foreach ($previous_categories as $cat) {
            $previous_map[$cat->category] = floatval($cat->total_expenses ?? 0);
        }
        
        foreach ($categories as $cat) {
            $category_name = $cat->category;
            $current_total = floatval($cat->total_expenses ?? 0);
            $previous_total = $previous_map[$category_name] ?? 0;
            
            if ($previous_total > 0) {
                $variation = (($current_total - $previous_total) / $previous_total) * 100;
                
                if (abs($variation) > 20) { // >20% variazione
                    $patterns[] = [
                        'type' => 'category_trend',
                        'category' => 'expense',
                        'pattern' => sprintf(
                            "Categoria '%s': %s del %.1f%%",
                            $category_name,
                            $variation > 0 ? 'aumento' : 'riduzione',
                            abs($variation)
                        ),
                        'category_name' => $category_name,
                        'variation_percentage' => $variation,
                        'confidence' => 0.7,
                    ];
                }
            }
        }
        
        return $patterns;
    }
    
    /**
     * Analizza pattern temporali (giorno del mese, settimana del mese)
     */
    private function analyze_temporal_patterns($start_date, $end_date) {
        global $wpdb;

        $patterns = [];
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';

        // Spese per giorno del mese (1-31)
        $sql = $wpdb->prepare(
            "SELECT 
                DAY(transaction_date) as day_of_month,
                SUM(ABS(amount)) as total,
                COUNT(*) as cnt
            FROM {$transactions_table}
            WHERE transaction_date BETWEEN %s AND %s
            AND amount < 0
            GROUP BY day_of_month
            HAVING cnt >= 3",
            $start_date,
            $end_date
        );
        $daily = $wpdb->get_results($sql);

        if (!empty($daily) && is_array($daily)) {
            $totals = array_map(function ($r) { return floatval($r->total ?? 0); }, $daily);
            $avg = !empty($totals) ? array_sum($totals) / count($totals) : 0;
            foreach ($daily as $row) {
                $tot = floatval($row->total ?? 0);
                if ($avg > 0 && ($tot / $avg) > 1.4) {
                    $d = (int) $row->day_of_month;
                    $label = $d <= 10 ? 'inizio mese' : ($d >= 25 ? 'fine mese' : 'metà mese');
                    $patterns[] = [
                        'type' => 'day_of_month_pattern',
                        'category' => 'expense',
                        'pattern' => sprintf(
                            'Spese concentrate il giorno %d del mese (%s): %.1f%% sopra la media',
                            $d,
                            $label,
                            (($tot / $avg) - 1) * 100
                        ),
                        'day_of_month' => $d,
                        'label' => $label,
                        'variation_percentage' => (($tot / $avg) - 1) * 100,
                        'confidence' => min(0.85, 0.5 + (floatval($row->cnt ?? 0) / 20)),
                    ];
                }
            }
        }

        return $patterns;
    }

    /**
     * Fattori stagionali da dati reali (12 mesi). Media = 1.0.
     * Usato da PredictiveAnalyzer per predizioni più intelligenti.
     *
     * @return array{income: array<int, float>, expense: array<int, float>}
     */
    public function get_seasonal_factors_from_data() {
        $months_data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} months"));
            $month_start = date('Y-m-01', strtotime($date));
            $month_end = date('Y-m-t', strtotime($date));
            $stats = StatsService::get_instance()->calculate_period_stats($month_start, $month_end);
            $months_data[] = [
                'month' => (int) date('n', strtotime($date)),
                'income' => floatval($stats->total_income ?? 0),
                'expenses' => floatval($stats->total_expenses ?? 0),
            ];
        }

        $by_month = ['income' => [], 'expense' => []];
        foreach ($months_data as $d) {
            $m = $d['month'];
            if (!isset($by_month['income'][$m])) {
                $by_month['income'][$m] = [];
                $by_month['expense'][$m] = [];
            }
            $by_month['income'][$m][] = $d['income'];
            $by_month['expense'][$m][] = $d['expenses'];
        }

        $factors = ['income' => [], 'expense' => []];
        foreach (['income', 'expense'] as $key) {
            $glob = [];
            foreach ($by_month[$key] as $vals) {
                $glob = array_merge($glob, $vals);
            }
            $global_avg = !empty($glob) ? array_sum($glob) / count($glob) : 1.0;
            if ($global_avg <= 0) {
                $global_avg = 1.0;
            }
            for ($m = 1; $m <= 12; $m++) {
                $vals = $by_month[$key][$m] ?? [0];
                $avg = array_sum($vals) / count($vals);
                $factors[$key][$m] = $global_avg > 0 ? ($avg / $global_avg) : 1.0;
            }
        }

        return $factors;
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
        
        // Calcola pendenza (slope)
        $denominator = ($n * $sum_x2) - ($sum_x * $sum_x);
        if ($denominator == 0) {
            return 0;
        }
        
        $slope = (($n * $sum_xy) - ($sum_x * $sum_y)) / $denominator;
        
        // Calcola primo e ultimo valore
        $first_value = $values[0];
        $last_value = $values[$n - 1];
        
        if ($first_value == 0) {
            return 0;
        }
        
        // Ritorna variazione percentuale media
        $avg_change = $slope / $first_value;
        
        return $avg_change;
    }
    
    /**
     * Rileva transazioni ricorrenti (possibili abbonamenti/costi fissi)
     * Cerca transazioni con stesso importo (±5%), stessa controparte, intervallo ~mensile
     */
    private function detect_recurring_transactions($start_date, $end_date) {
        global $wpdb;
        
        $patterns = [];
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        // Ottieni transazioni negative (uscite) con descrizione non vuota
        $sql = $wpdb->prepare(
            "SELECT id, transaction_date, amount, description, category
            FROM {$transactions_table}
            WHERE transaction_date BETWEEN %s AND %s
            AND amount < 0
            AND description IS NOT NULL
            AND description != ''
            ORDER BY transaction_date ASC",
            $start_date,
            $end_date
        );
        
        $transactions = $wpdb->get_results($sql);
        
        if (empty($transactions) || !is_array($transactions) || count($transactions) < 3) {
            return $patterns;
        }
        
        // Raggruppa per descrizione normalizzata (primi 20 caratteri) e importo simile (±5%)
        $groups = [];
        foreach ($transactions as $tx) {
            $amount = abs(floatval($tx->amount));
            $desc_key = substr(trim($tx->description), 0, 20);
            
            $found_group = false;
            foreach ($groups as $key => &$group) {
                $avg_amount = $group['total_amount'] / $group['count'];
                $tolerance = $avg_amount * 0.05; // ±5%
                
                // Stessa descrizione e importo simile
                if (strpos($desc_key, $group['desc_key']) !== false || strpos($group['desc_key'], $desc_key) !== false) {
                    if (abs($amount - $avg_amount) <= $tolerance) {
                        $group['transactions'][] = $tx;
                        $group['total_amount'] += $amount;
                        $group['count']++;
                        $found_group = true;
                        break;
                    }
                }
            }
            
            if (!$found_group) {
                $groups[] = [
                    'desc_key' => $desc_key,
                    'transactions' => [$tx],
                    'total_amount' => $amount,
                    'count' => 1,
                ];
            }
        }
        
        // Analizza gruppi con almeno 3 transazioni e intervallo ~mensile
        foreach ($groups as $group) {
            if ($group['count'] < 3) {
                continue;
            }
            
            $txs = $group['transactions'];
            usort($txs, function ($a, $b) {
                return strtotime($a->transaction_date) - strtotime($b->transaction_date);
            });
            
            // Calcola intervalli tra transazioni
            $intervals = [];
            for ($i = 1; $i < count($txs); $i++) {
                $days = (strtotime($txs[$i]->transaction_date) - strtotime($txs[$i - 1]->transaction_date)) / 86400;
                $intervals[] = $days;
            }
            
            // Verifica se intervalli sono simili (~mensile: 25-35 giorni)
            $avg_interval = array_sum($intervals) / count($intervals);
            $is_monthly = $avg_interval >= 25 && $avg_interval <= 35;
            
            // Verifica anche intervalli più lunghi (bimestrale, trimestrale)
            $is_recurring = $is_monthly || ($avg_interval >= 55 && $avg_interval <= 65) || ($avg_interval >= 85 && $avg_interval <= 95);
            
            if ($is_recurring) {
                $avg_amount = $group['total_amount'] / $group['count'];
                $desc = $txs[0]->description;
                if (strlen($desc) > 40) {
                    $desc = substr($desc, 0, 37) . '...';
                }
                
                $patterns[] = [
                    'type' => 'recurring_transaction',
                    'category' => 'expense',
                    'pattern' => sprintf(
                        __('Possibile abbonamento: €%.2f ogni %.0f giorni - %s', 'fp-finance-hub'),
                        $avg_amount,
                        round($avg_interval),
                        $desc
                    ),
                    'amount' => $avg_amount,
                    'interval_days' => round($avg_interval),
                    'transaction_count' => $group['count'],
                    'description' => $desc,
                    'confidence' => min(0.9, 0.5 + ($group['count'] / 10)),
                ];
            }
        }
        
        return $patterns;
    }
}
