<?php
/**
 * Sincronizzazione Aruba
 * 
 * Sincronizza fatture e clienti da Aruba
 */

namespace FP\FinanceHub\Integration\Aruba;

use FP\FinanceHub\Services\InvoiceService;
use FP\FinanceHub\Services\ClientService;
use FP\FinanceHub\Database\Models\Invoice as InvoiceModel;
use FP\FinanceHub\Utils\Logger;
use FP\FinanceHub\Services\Intelligence\IntelligenceAnalysisService;

if (!defined('ABSPATH')) {
    exit;
}

class ArubaSync {
    
    private $api;
    private $xml_parser;
    private $invoice_service;
    private $client_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new ArubaAPI();
        $this->xml_parser = new ArubaXMLParser();
        $this->invoice_service = InvoiceService::get_instance();
        $this->client_service = ClientService::get_instance();
    }
    
    /**
     * Sincronizza fatture da Aruba
     * 
     * @param string|null $start_date Data inizio (YYYY-MM-DD)
     * @param string|null $end_date Data fine (YYYY-MM-DD)
     * @return array Risultato sincronizzazione
     */
    public function sync_invoices($start_date = null, $end_date = null) {
        // Default: ultimi 30 giorni
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        // Trova fatture da Aruba
        $aruba_invoices = $this->api->find_invoices([
            'startDate' => $start_date,
            'endDate' => $end_date,
        ]);
        
        if (is_wp_error($aruba_invoices)) {
            return $aruba_invoices;
        }
        
        $imported = 0;
        $updated = 0;
        $errors = 0;
        
        foreach ($aruba_invoices as $aruba_invoice) {
            try {
                // Download dettagli fattura
                $invoice_details = $this->api->get_invoice($aruba_invoice['id']);
                
                if (is_wp_error($invoice_details)) {
                    $errors++;
                    Logger::log('aruba_sync_error', 'Errore download fattura ' . $aruba_invoice['id'], [
                        'error' => $invoice_details->get_error_message(),
                    ]);
                    continue;
                }
                
                // Parse XML
                if (!empty($invoice_details['file'])) {
                    $xml_data = $this->xml_parser->parse($invoice_details['file']);
                    
                    if (is_wp_error($xml_data)) {
                        $errors++;
                        Logger::log('aruba_sync_error', 'Errore parsing XML fattura ' . $aruba_invoice['id'], [
                            'error' => $xml_data->get_error_message(),
                        ]);
                        continue;
                    }
                    
                    // Verifica se fattura già esistente
                    $existing = InvoiceModel::find_by_aruba_id($aruba_invoice['id']);
                    
                    // Importa/aggiorna fattura
                    $invoice_id = $this->invoice_service->import_from_aruba($aruba_invoice, $xml_data);
                    
                    if ($existing) {
                        $updated++;
                    } else {
                        $imported++;
                    }
                    
                    // Estrai e importa cliente
                    if (!empty($xml_data['receiver'])) {
                        $this->client_service->import_from_aruba($xml_data['receiver']);
                    }
                    
                    // Salva log operazione
                    Logger::log('aruba_sync_success', 'Fattura sincronizzata', [
                        'aruba_id' => $aruba_invoice['id'],
                        'invoice_id' => $invoice_id,
                    ]);
                }
            } catch (\Exception $e) {
                $errors++;
                Logger::log('aruba_sync_exception', 'Eccezione durante sync', [
                    'error' => $e->getMessage(),
                    'aruba_id' => $aruba_invoice['id'] ?? null,
                ]);
            }
        }
        
        // Invalida cache Intelligence se sync riuscito
        if ($imported > 0 || $updated > 0) {
            IntelligenceAnalysisService::invalidate_cache();
        }
        
        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'total' => count($aruba_invoices),
        ];
    }
    
    /**
     * Aggiorna stati fatture da Aruba
     */
    public function update_invoice_statuses() {
        // Sincronizza ultimi 90 giorni per aggiornare stati
        $result = $this->sync_invoices(
            date('Y-m-d', strtotime('-90 days')),
            date('Y-m-d')
        );
        
        return $result;
    }
    
    /**
     * Estrae e sincronizza solo clienti da fatture Aruba
     */
    public function sync_clients_from_invoices() {
        // Trova fatture Aruba
        $aruba_invoices = $this->api->find_invoices([
            'startDate' => date('Y-m-d', strtotime('-365 days')),
        ]);
        
        if (is_wp_error($aruba_invoices)) {
            return $aruba_invoices;
        }
        
        $clients_imported = 0;
        
        foreach ($aruba_invoices as $aruba_invoice) {
            $invoice_details = $this->api->get_invoice($aruba_invoice['id']);
            
            if (!is_wp_error($invoice_details) && !empty($invoice_details['file'])) {
                $xml_data = $this->xml_parser->parse($invoice_details['file']);
                
                if (!is_wp_error($xml_data) && !empty($xml_data['receiver'])) {
                    $this->client_service->import_from_aruba($xml_data['receiver']);
                    $clients_imported++;
                }
            }
        }
        
        return [
            'clients_imported' => $clients_imported,
        ];
    }
}
           $clients_imported++;
                }
            }
        }
        
        return [
            'clients_imported' => $clients_imported,
        ];
    }
}