<?php
/**
 * Sincronizzazione FP Publisher
 * 
 * Export clienti verso FP Publisher come Remote Sites
 */

namespace FP\FinanceHub\Integration;

use FP\FinanceHub\Database\Models\Client as ClientModel;

if (!defined('ABSPATH')) {
    exit;
}

class PublisherSync {
    
    /**
     * Sincronizza clienti verso FP Publisher
     * 
     * @param array $client_ids Array di ID clienti da sincronizzare (se null, tutti)
     * @return array Risultato sincronizzazione
     */
    public function sync_to_publisher($client_ids = null) {
        global $wpdb;
        
        // Verifica che FP Publisher sia attivo
        if (!class_exists('FP\\Publisher\\Plugin')) {
            return new \WP_Error('publisher_not_active', 'FP Publisher non è attivo');
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
                        'synced_to_publisher' => true,
                        'last_sync_publisher' => current_time('mysql'),
                    ]);
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
                error_log("[FP Finance Hub] Errore sync cliente {$client->id} a Publisher: " . $e->getMessage());
            }
        }
        
        return [
            'synced' => $synced,
            'errors' => $errors,
            'total' => count($clients),
        ];
    }
    
    /**
     * Sincronizza singolo cliente verso Publisher
     */
    private function sync_single_client($client) {
        global $wpdb;
        
        // Tabella FP Publisher Remote Sites
        $table = $wpdb->prefix . 'fp_pub_remote_sites';
        
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            return new \WP_Error('table_not_found', 'Tabella FP Publisher non trovata');
        }
        
        // Verifica se Remote Site già esistente
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE source_id = %s AND source = %s",
            'fp_finance_hub_' . $client->id,
            'fp_finance_hub'
        ));
        
        $data = [
            'name' => $client->name,
            'url' => $client->website ?: '',
            'notes' => $this->format_client_notes($client),
            'source' => 'fp_finance_hub',
            'source_id' => 'fp_finance_hub_' . $client->id,
            'updated_at' => current_time('mysql'),
        ];
        
        if ($existing) {
            // Aggiorna esistente
            return $wpdb->update(
                $table,
                $data,
                ['id' => $existing->id],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Crea nuovo
            $data['created_at'] = current_time('mysql');
            return $wpdb->insert($table, $data);
        }
    }
    
    /**
     * Formatta note cliente per Publisher
     */
    private function format_client_notes($client) {
        $notes = [];
        
        if (!empty($client->email)) {
            $notes[] = "Email: {$client->email}";
        }
        if (!empty($client->phone)) {
            $notes[] = "Tel: {$client->phone}";
        }
        if (!empty($client->vat_number)) {
            $notes[] = "P.IVA: {$client->vat_number}";
        }
        if (!empty($client->notes)) {
            $notes[] = $client->notes;
        }
        
        return implode("\n", $notes);
    }
}
