<?php
/**
 * Service Gestione Fatture
 * 
 * Logica business per gestione fatture, calcolo potenziale entrate
 */

namespace FP\FinanceHub\Services;

use FP\FinanceHub\Database\Models\Invoice as InvoiceModel;
use FP\FinanceHub\Database\Models\Client as ClientModel;
use FP\FinanceHub\Services\CacheService;

if (!defined('ABSPATH')) {
    exit;
}

class InvoiceService {
    
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
     * Crea nuova fattura
     */
    public function create($data) {
        return InvoiceModel::create($data);
    }
    
    /**
     * Ottieni fattura per ID
     */
    public function get($id) {
        return InvoiceModel::get($id);
    }
    
    /**
     * Ottieni fatture non pagate
     */
    public function get_unpaid($args = []) {
        return InvoiceModel::get_unpaid($args);
    }
    
    /**
     * Calcola potenziale entrate (fatture non pagate) - con cache
     */
    public function calculate_potential_income($aruba_statuses = ['Inviata', 'Accettata', 'Consegnata']) {
        $cache_service = CacheService::get_instance();
        $cache_key = 'potential_income_' . md5(serialize($aruba_statuses));
        
        // Cache per 10 minuti
        return $cache_service->remember($cache_key, function() use ($aruba_statuses) {
            return InvoiceModel::calculate_potential_income($aruba_statuses);
        }, 600);
    }
    
    /**
     * Aggiorna fattura
     */
    public function update($id, $data) {
        return InvoiceModel::update($id, $data);
    }
    
    /**
     * Crea o aggiorna fattura da Aruba
     */
    public function import_from_aruba($aruba_invoice_data, $invoice_xml_data) {
        $invoice_data = [
            'invoice_number' => $invoice_xml_data['number'] ?? '',
            'issue_date' => $invoice_xml_data['date'] ?? current_time('Y-m-d'),
            'total_amount' => $invoice_xml_data['total'] ?? 0,
            'amount' => $invoice_xml_data['subtotal'] ?? $invoice_xml_data['total'] ?? 0,
            'tax_amount' => $invoice_xml_data['tax'] ?? 0,
            'status' => 'pending',
            'aruba_id' => $aruba_invoice_data['id'] ?? null,
            'aruba_sdi_id' => $aruba_invoice_data['idSdi'] ?? null,
            'aruba_status' => $aruba_invoice_data['status'] ?? null,
            'aruba_sent_at' => isset($aruba_invoice_data['sentAt']) ? date('Y-m-d H:i:s', strtotime($aruba_invoice_data['sentAt'])) : null,
        ];
        
        // Trova cliente per P.IVA
        if (!empty($invoice_xml_data['receiver']['vatCode'])) {
            $client = ClientModel::find_by_vat($invoice_xml_data['receiver']['vatCode']);
            if ($client) {
                $invoice_data['client_id'] = $client->id;
            }
        }
        
        $result = InvoiceModel::create_or_update($invoice_data);
        
        // Invalida cache potenziale entrate
        $cache_service = CacheService::get_instance();
        $cache_service->invalidate_pattern('potential_income_*');
        
        return $result;
    }
    
    /**
     * Marca fattura come pagata (invalida cache)
     */
    public function mark_as_paid($invoice_id, $paid_date = null) {
        $result = InvoiceModel::update($invoice_id, [
            'status' => 'paid',
            'paid_date' => $paid_date ?: current_time('Y-m-d'),
        ]);
        
        // Invalida cache potenziale entrate
        $cache_service = CacheService::get_instance();
        $cache_service->invalidate_pattern('potential_income_*');
        
        return $result;
    }
    
    /**
     * Ottieni fatture scadute (non pagate con scadenza passata)
     */
    public function get_overdue_invoices() {
        global $wpdb;
        
        $invoices_table = $wpdb->prefix . 'fp_finance_hub_invoices';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$invoices_table}
            WHERE status != 'paid'
            AND due_date IS NOT NULL
            AND due_date < %s
            ORDER BY due_date ASC",
            current_time('Y-m-d')
        );
        
        return $wpdb->get_results($sql);
    }
}
