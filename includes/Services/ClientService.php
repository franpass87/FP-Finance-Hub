<?php
/**
 * Service Gestione Clienti
 * 
 * Logica business per gestione clienti, import da Aruba, matching
 */

namespace FP\FinanceHub\Services;

use FP\FinanceHub\Database\Models\Client as ClientModel;

if (!defined('ABSPATH')) {
    exit;
}

class ClientService {
    
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
     * Crea nuovo cliente
     */
    public function create($data) {
        return ClientModel::create($data);
    }
    
    /**
     * Ottieni cliente per ID
     */
    public function get($id) {
        return ClientModel::get($id);
    }
    
    /**
     * Ottieni tutti i clienti
     */
    public function get_all($args = []) {
        return ClientModel::get_all($args);
    }
    
    /**
     * Aggiorna cliente
     */
    public function update($id, $data) {
        return ClientModel::update($id, $data);
    }
    
    /**
     * Elimina cliente
     */
    public function delete($id) {
        return ClientModel::delete($id);
    }
    
    /**
     * Crea o aggiorna cliente da dati Aruba
     */
    public function import_from_aruba($aruba_client_data) {
        $client_data = [
            'name' => $aruba_client_data['description'] ?? '',
            'vat_number' => $aruba_client_data['vatCode'] ?? null,
            'fiscal_code' => $aruba_client_data['fiscalCode'] ?? null,
            'country' => $aruba_client_data['countryCode'] ?? 'IT',
            'source' => 'aruba',
            'source_id' => $aruba_client_data['id'] ?? null,
        ];
        
        return ClientModel::create_or_update($client_data);
    }
    
    /**
     * Trova cliente per P.IVA
     */
    public function find_by_vat($vat_number) {
        return ClientModel::find_by_vat($vat_number);
    }
}
