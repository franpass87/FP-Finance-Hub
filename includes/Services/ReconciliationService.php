<?php
/**
 * Service Riconciliazione
 * 
 * Riconciliazione automatica fatture ↔ movimenti bancari
 */

namespace FP\FinanceHub\Services;

use FP\FinanceHub\Database\Models\Invoice as InvoiceModel;
use FP\FinanceHub\Database\Models\Transaction as TransactionModel;

if (!defined('ABSPATH')) {
    exit;
}

class ReconciliationService {
    
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
     * Riconcilia tutte le fatture non pagate
     */
    public function reconcile_all($tolerance = 0.01, $days_range = 7) {
        $unpaid_invoices = InvoiceModel::get_unpaid();
        
        $reconciled_count = 0;
        
        foreach ($unpaid_invoices as $invoice) {
            $result = $this->reconcile_invoice($invoice->id, $tolerance, $days_range);
            if ($result['status'] === 'reconciled') {
                $reconciled_count++;
            }
        }
        
        return [
            'total' => count($unpaid_invoices),
            'reconciled' => $reconciled_count,
        ];
    }
    
    /**
     * Riconcilia singola fattura
     */
    public function reconcile_invoice($invoice_id, $tolerance = 0.01, $days_range = 7) {
        $invoice = InvoiceModel::get($invoice_id);
        
        if (!$invoice) {
            return ['status' => 'error', 'message' => 'Fattura non trovata'];
        }
        
        // Cerca movimenti corrispondenti
        $matching_transactions = TransactionModel::find_matching(
            $invoice->total_amount,
            $invoice->due_date ?: $invoice->issue_date,
            $tolerance,
            $days_range
        );
        
        if (empty($matching_transactions)) {
            return ['status' => 'no_match', 'message' => 'Nessun movimento corrispondente'];
        }
        
        if (count($matching_transactions) === 1) {
            // Match unico → riconcilia automatica
            $transaction = $matching_transactions[0];
            
            TransactionModel::reconcile($transaction->id, $invoice_id);
            InvoiceModel::update($invoice_id, [
                'status' => 'paid',
                'paid_date' => $transaction->transaction_date,
            ]);
            
            return [
                'status' => 'reconciled',
                'transaction_id' => $transaction->id,
                'invoice_id' => $invoice_id,
            ];
        } else {
            // Match multipli → suggerimenti
            return [
                'status' => 'suggestions',
                'suggestions' => array_map(function($tx) {
                    return [
                        'id' => $tx->id,
                        'date' => $tx->transaction_date,
                        'amount' => $tx->amount,
                        'description' => $tx->description,
                    ];
                }, $matching_transactions),
            ];
        }
    }
    
    /**
     * Riconcilia manualmente fattura con movimento
     */
    public function reconcile_manual($invoice_id, $transaction_id) {
        $invoice = InvoiceModel::get($invoice_id);
        $transaction = TransactionModel::get($transaction_id);
        
        if (!$invoice || !$transaction) {
            return new \WP_Error('not_found', 'Fattura o movimento non trovato');
        }
        
        // Riconcilia
        TransactionModel::reconcile($transaction_id, $invoice_id);
        InvoiceModel::update($invoice_id, [
            'status' => 'paid',
            'paid_date' => $transaction->transaction_date,
        ]);
        
        return true;
    }
}
