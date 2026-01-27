<?php
/**
 * Stats Service
 * 
 * Calcolo statistiche e trend
 */

namespace FP\FinanceHub\Services;

use FP\FinanceHub\Database\Models\Transaction as TransactionModel;
use FP\FinanceHub\Database\Models\Invoice as InvoiceModel;
use FP\FinanceHub\Services\CacheService;

if (!defined('ABSPATH')) {
    exit;
}

class StatsService {
    
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
     * Calcola statistiche entrate/uscite per periodo
     */
    public function calculate_period_stats($start_date, $end_date, $account_id = null, $type = null) {
        global $wpdb;
        
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        $where = [
            "transaction_date BETWEEN %s AND %s",
        ];
        
        $values = [$start_date, $end_date];
        
        if ($account_id) {
            $where[] = "account_id = %d";
            $values[] = $account_id;
        }
        
        if ($type === 'business') {
            $where[] = "is_business = 1";
        } elseif ($type === 'personal') {
            $where[] = "is_personal = 1";
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = $wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expenses,
                COUNT(*) as transaction_count
            FROM {$transactions_table}
            WHERE {$where_clause}",
            $values
        );
        
        $result = $wpdb->get_row($sql);
        
        // Restituisce oggetto con valori default se null
        if (!$result) {
            return (object)[
                'total_income' => 0,
                'total_expenses' => 0,
                'transaction_count' => 0,
            ];
        }
        
        return $result;
    }
    
    /**
     * Calcola trend 12 mesi (con cache)
     */
    public function calculate_trend_12_months($account_id = null, $type = null) {
        $cache_service = CacheService::get_instance();
        $cache_key = sprintf('trend_12m_%s_%s', $account_id ?: 'all', $type ?: 'all');
        
        // Cache per 1 ora (dati storici)
        return $cache_service->remember($cache_key, function() use ($account_id, $type) {
            $months = [];
            
            for ($i = 11; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} months"));
                $month_start = date('Y-m-01', strtotime($date));
                $month_end = date('Y-m-t', strtotime($date));
                
                $stats = $this->calculate_period_stats($month_start, $month_end, $account_id, $type);
                
                if (!$stats) {
                    $stats = (object)['total_income' => 0, 'total_expenses' => 0];
                }
                
                $months[] = [
                    'month' => date('Y-m', strtotime($date)),
                    'income' => floatval($stats->total_income ?? 0),
                    'expenses' => floatval($stats->total_expenses ?? 0),
                    'net' => floatval($stats->total_income ?? 0) - floatval($stats->total_expenses ?? 0),
                ];
            }
            
            return $months;
        }, 3600);
    }
    
    /**
     * Calcola statistiche aggregate per categoria
     */
    public function calculate_category_stats($start_date, $end_date, $type = null) {
        global $wpdb;
        
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        $where = [
            "transaction_date BETWEEN %s AND %s",
            "category IS NOT NULL",
            "category != ''",
        ];
        
        $values = [$start_date, $end_date];
        
        if ($type === 'business') {
            $where[] = "is_business = 1";
        } elseif ($type === 'personal') {
            $where[] = "is_personal = 1";
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = $wpdb->prepare(
            "SELECT 
                category,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expenses,
                COUNT(*) as transaction_count
            FROM {$transactions_table}
            WHERE {$where_clause}
            GROUP BY category
            ORDER BY (total_income + total_expenses) DESC",
            $values
        );
        
        $results = $wpdb->get_results($sql);
        return is_array($results) ? $results : [];
    }
    
    /**
     * Calcola confronto Business vs Personal
     */
    public function calculate_business_vs_personal($start_date, $end_date) {
        global $wpdb;
        
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        $sql = $wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN is_business = 1 AND amount > 0 THEN amount ELSE 0 END) as business_income,
                SUM(CASE WHEN is_business = 1 AND amount < 0 THEN ABS(amount) ELSE 0 END) as business_expenses,
                SUM(CASE WHEN is_personal = 1 AND amount > 0 THEN amount ELSE 0 END) as personal_income,
                SUM(CASE WHEN is_personal = 1 AND amount < 0 THEN ABS(amount) ELSE 0 END) as personal_expenses
            FROM {$transactions_table}
            WHERE transaction_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        );
        
        $result = $wpdb->get_row($sql);
        
        if (!$result) {
            return [
                'business' => ['income' => 0, 'expenses' => 0, 'net' => 0],
                'personal' => ['income' => 0, 'expenses' => 0, 'net' => 0],
            ];
        }
        
        return [
            'business' => [
                'income' => floatval($result->business_income ?? 0),
                'expenses' => floatval($result->business_expenses ?? 0),
                'net' => floatval($result->business_income ?? 0) - floatval($result->business_expenses ?? 0),
            ],
            'personal' => [
                'income' => floatval($result->personal_income ?? 0),
                'expenses' => floatval($result->personal_expenses ?? 0),
                'net' => floatval($result->personal_income ?? 0) - floatval($result->personal_expenses ?? 0),
            ],
        ];
    }
    
    /**
     * Calcola confronto tra periodi
     */
    public function calculate_period_comparison($current_start, $current_end, $previous_start, $previous_end) {
        $current_stats = $this->calculate_period_stats($current_start, $current_end);
        $previous_stats = $this->calculate_period_stats($previous_start, $previous_end);
        
        $current_income = floatval($current_stats->total_income ?: 0);
        $previous_income = floatval($previous_stats->total_income ?: 0);
        $current_expenses = floatval($current_stats->total_expenses ?: 0);
        $previous_expenses = floatval($previous_stats->total_expenses ?: 0);
        
        $income_variation = $previous_income > 0 ? (($current_income - $previous_income) / $previous_income) * 100 : 0;
        $expense_variation = $previous_expenses > 0 ? (($current_expenses - $previous_expenses) / $previous_expenses) * 100 : 0;
        
        return [
            'current' => [
                'income' => $current_income,
                'expenses' => $current_expenses,
                'net' => $current_income - $current_expenses,
            ],
            'previous' => [
                'income' => $previous_income,
                'expenses' => $previous_expenses,
                'net' => $previous_income - $previous_expenses,
            ],
            'variation' => [
                'income' => $income_variation,
                'expenses' => $expense_variation,
                'net' => (($current_income - $current_expenses) - ($previous_income - $previous_expenses)) / ($previous_income - $previous_expenses > 0 ? ($previous_income - $previous_expenses) : 1) * 100,
            ],
        ];
    }
    
    /**
     * Ottieni top N categorie per entrate o uscite
     */
    public function get_top_categories($start_date, $end_date, $limit = 5, $transaction_type = 'income') {
        global $wpdb;
        
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        $where = [
            "transaction_date BETWEEN %s AND %s",
            "category IS NOT NULL",
            "category != ''",
        ];
        
        $values = [$start_date, $end_date];
        
        if ($transaction_type === 'income') {
            $where[] = "amount > 0";
            $order_by = "total_income DESC";
            $select = "SUM(amount) as total";
        } else {
            $where[] = "amount < 0";
            $order_by = "total_expenses DESC";
            $select = "SUM(ABS(amount)) as total";
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = $wpdb->prepare(
            "SELECT 
                category,
                {$select} as total
            FROM {$transactions_table}
            WHERE {$where_clause}
            GROUP BY category
            ORDER BY total DESC
            LIMIT %d",
            array_merge($values, [$limit])
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Calcola statistiche per array di valori
     */
    public function calculate_statistics(array $values) {
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
     * Rileva outliers usando Z-score
     */
    public function detect_outliers(array $values, $threshold = 2.0) {
        if (empty($values)) {
            return [];
        }
        
        $stats = $this->calculate_statistics($values);
        
        if ($stats['std_dev'] == 0) {
            return [];
        }
        
        $outliers = [];
        foreach ($values as $index => $value) {
            $z_score = abs($value - $stats['mean']) / $stats['std_dev'];
            if ($z_score > $threshold) {
                $outliers[] = [
                    'index' => $index,
                    'value' => $value,
                    'z_score' => $z_score,
                ];
            }
        }
        
        return $outliers;
    }
}
