<?php
/**
 * Sincronizzazione FP Task Agenda
 * 
 * Export clienti verso FP Task Agenda
 */

namespace FP\FinanceHub\Integration;

use FP\FinanceHub\Database\Models\Client as ClientModel;

if (!defined('ABSPATH')) {
    exit;
}

class TaskAgendaSync {
    
    /**
     * Sincronizza clienti verso FP Task Agenda
     * 
     * @param array $client_ids Array di ID clienti da sincronizzare (se null, tutti)
     * @return array Risultato sincronizzazione
     */
    public function sync_to_task_agenda($client_ids = null) {
        global $wpdb;
        
        // Verifica che FP Task Agenda sia attivo
        if (!class_exists('FP\\TaskAgenda\\Plugin')) {
            return new \WP_Error('task_agenda_not_active', 'FP Task Agenda non è attivo');
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
                        'synced_to_task_agenda' => true,
                        'last_sync_task_agenda' => current_time('mysql'),
                    ]);
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
                error_log("[FP Finance Hub] Errore sync cliente {$client->id} a Task Agenda: " . $e->getMessage());
            }
        }
        
        return [
            'synced' => $synced,
            'errors' => $errors,
            'total' => count($clients),
        ];
    }
    
    /**
     * Sincronizza singolo cliente verso Task Agenda
     */
    private function sync_single_client($client) {
        global $wpdb;
        
        // Tabella FP Task Agenda Clients
        $table = $wpdb->prefix . 'fp_task_agenda_clients';
        
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            return new \WP_Error('table_not_found', 'Tabella FP Task Agenda non trovata');
        }
        
        // Verifica se cliente già esistente
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE source_id = %s AND source = %s",
            'fp_finance_hub_' . $client->id,
            'fp_finance_hub'
        ));
        
        $data = [
            'name' => $client->name,
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
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Crea nuovo
            $data['created_at'] = current_time('mysql');
            return $wpdb->insert($table, $data);
        }
    }
}
