<?php
/**
 * Riconciliazione Aruba
 * 
 * Riconcilia fatture Aruba con movimenti bancari
 */

namespace FP\FinanceHub\Integration\Aruba;

use FP\FinanceHub\Services\ReconciliationService;

if (!defined('ABSPATH')) {
    exit;
}

class ArubaReconciliation {
    
    private $reconciliation_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->reconciliation_service = ReconciliationService::get_instance();
    }
    
    /**
     * Riconcilia fatture Aruba con movimenti bancari
     */
    public function reconcile_invoices_with_transactions($tolerance = 0.01, $days_range = 7) {
        return $this->reconciliation_service->reconcile_all($tolerance, $days_range);
    }
    
    /**
     * Marca fattura come pagata
     */
    public function mark_invoice_as_paid($invoice_id, $transaction_id) {
        return $this->reconciliation_service->reconcile_manual($invoice_id, $transaction_id);
    }
    
    /**
     * Calcola potenziale entrate (fatture non pagate)
     */
    public function calculate_potential_income() {
        $invoice_service = \FP\FinanceHub\Services\InvoiceService::get_instance();
        return $invoice_service->calculate_potential_income(['Inviata', 'Accettata', 'Consegnata']);
    }
}
