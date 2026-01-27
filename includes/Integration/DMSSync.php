<?php
/**
 * Sincronizzazione Digital Marketing Suite
 * 
 * Export clienti verso Digital Marketing Suite
 */

namespace FP\FinanceHub\Integration;

use FP\FinanceHub\Database\Models\Client as ClientModel;

if (!defined('ABSPATH')) {
    exit;
}

class DMSSync {
    
    /**
     * Sincronizza clienti verso DMS
     * 
     * @param array $client_ids Array di ID clienti da sincronizzare (se null, tutti)
     * @return array Risultato sincronizzazione
     */
    public function sync_to_dms($client_ids = null) {
        global $wpdb;
        
        // Verifica che DMS sia attivo (verifica nome classe reale)
        // Assumendo esista una classe DMS principale
        if (!function_exists('fp_dms_init')) {
            return new \WP_Error('dms_not_active', 'Digital Marketing Suite non è attivo');
        }
        
        // Ottieni clienti da sincronizzare
        if ($client_ids === null) {
            $clients = ClientModel::get_all(['per_page' => -1]);
        } else {
            $clients = [];
            foreach ($client_ids as $client_id) {
                $client = ClientModel::get($client_id);
                if ($client) {
                    $clients[] = $client;
                }
            }
        }
        
        $synced = 0;
        $errors = 0;
        
        foreach ($clients as $client) {
            try {
                $result = $this->sync_single_client($client);
                if (!is_wp_error($result)) {
                    $synced++;
                    
                    // Aggiorna flag sincronizzazione
                    ClientModel::update($client->id, [
                        'synced_to_dms' => true,
                        'last_sync_dms' => current_time('mysql'),
                    ]);
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
                error_log("[FP Finance Hub] Errore sync cliente {$client->id} a DMS: " . $e->getMessage());
            }
        }
        
        return [
            'synced' => $synced,
            'errors' => $errors,
            'total' => count($clients),
        ];
    }
    
    /**
     * Sincronizza singolo cliente verso DMS
     */
    private function sync_single_client($client) {
        // Hook per sincronizzazione DMS
        // Il plugin DMS può implementare questo hook per ricevere i dati
        $result = apply_filters('fp_finance_hub_sync_client_to_dms', [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'vat_number' => $client->vat_number,
            'tags' => $client->tags ? json_decode($client->tags, true) : [],
            'category' => $client->category,
        ], $client);
        
        // Se nessun hook implementato, salva in opzione temporanea
        if (!has_filter('fp_finance_hub_sync_client_to_dms')) {
            // Salva in formato JSON per import futuro
            $dms_clients = get_option('fp_finance_hub_dms_clients', []);
            $dms_clients[$client->id] = [
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'vat_number' => $client->vat_number,
            ];
            update_option('fp_finance_hub_dms_clients', $dms_clients);
            
            return true;
        }
        
        return $result;
    }
}
