<?php
/**
 * Classe base Import
 * 
 * Orchestrazione import movimenti bancari
 */

namespace FP\FinanceHub\Import;

use FP\FinanceHub\Import\Bank\PostePayParser;
use FP\FinanceHub\Import\Bank\INGParser;
use FP\FinanceHub\Import\Bank\OFXParser;
use FP\FinanceHub\Services\BankService;
use FP\FinanceHub\Services\Intelligence\IntelligenceAnalysisService;

if (!defined('ABSPATH')) {
    exit;
}

class Importer {
    
    /**
     * Importa file CSV/OFX
     * 
     * @param int $account_id ID conto bancario
     * @param string $file_path Path file da importare
     * @param string $format Formato file (postepay, ing, ofx, auto)
     * @return array Risultato import
     */
    public function import_file($account_id, $file_path, $format = 'auto') {
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', 'File non trovato');
        }
        
        // Verifica path traversal (sicurezza)
        $real_file_path = realpath($file_path);
        $tmp_dir = sys_get_temp_dir();
        $real_tmp_dir = realpath($tmp_dir);
        $upload_dir = wp_upload_dir();
        $real_upload_dir = realpath($upload_dir['basedir']);
        
        // Verifica che il file sia dentro directory temporanea o upload
        if (!$real_file_path || 
            (strpos($real_file_path, $real_tmp_dir) !== 0 && 
             strpos($real_file_path, $real_upload_dir) !== 0)) {
            return new \WP_Error('invalid_path', 'Percorso file non valido');
        }
        
        // Verifica dimensione file (max 10MB)
        $file_size = filesize($file_path);
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file_size > $max_size) {
            return new \WP_Error('file_too_large', 'File troppo grande. Dimensione massima: 10MB.');
        }
        
        $content = file_get_contents($file_path);
        
        // Verifica che il contenuto sia stato letto correttamente
        if ($content === false) {
            return new \WP_Error('read_error', 'Errore durante lettura file');
        }
        
        if ($format === 'auto') {
            $format = $this->detect_format($file_path, $content);
        }
        
        // Parse file
        try {
            $parser = $this->get_parser($format);
            if (!$parser) {
                return new \WP_Error('invalid_format', 'Formato file non supportato: ' . $format);
            }
            
            $parsed = $parser->parse($content);
            
            if (is_wp_error($parsed)) {
                return $parsed;
            }
            
            if (empty($parsed) || !is_array($parsed)) {
                return new \WP_Error('parse_error', 'Errore durante il parsing del file');
            }
            
            if (empty($parsed['transactions'])) {
                return new \WP_Error('no_transactions', 'Nessun movimento trovato nel file');
            }
        } catch (\Exception $e) {
            error_log('[FP Finance Hub] Errore parsing file: ' . $e->getMessage());
            return new \WP_Error('parse_exception', 'Errore durante il parsing: ' . $e->getMessage());
        }
        
        // Importa movimenti
        $imported = 0;
        $skipped = 0;
        $bank_service = BankService::get_instance();
        
        try {
            foreach ($parsed['transactions'] as $transaction_data) {
                // Verifica struttura dati movimento
                if (!is_array($transaction_data) || empty($transaction_data['transaction_date'])) {
                    $skipped++;
                    continue;
                }
                
                // Verifica se già esistente (per evitare duplicati)
                if ($this->transaction_exists($account_id, $transaction_data)) {
                    $skipped++;
                    continue;
                }
                
                // Importa movimento
                $result = $bank_service->import_transaction($account_id, $transaction_data);
                
                if (!is_wp_error($result)) {
                    $imported++;
                } else {
                    error_log('[FP Finance Hub] Errore import movimento: ' . $result->get_error_message());
                    $skipped++;
                }
            }
        } catch (\Exception $e) {
            error_log('[FP Finance Hub] Errore durante import movimenti: ' . $e->getMessage());
            return new \WP_Error('import_exception', 'Errore durante l\'import: ' . $e->getMessage());
        }
        
        // Aggiorna saldo conto se presente
        if (!empty($parsed['final_balance'])) {
            $bank_service->update_account_balance(
                $account_id,
                $parsed['final_balance'],
                end($parsed['transactions'])['transaction_date'] ?? null
            );
        }
        
        // Invalida cache Intelligence se import riuscito
        if ($imported > 0) {
            IntelligenceAnalysisService::invalidate_cache();
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => count($parsed['transactions']),
            'final_balance' => $parsed['final_balance'] ?? null,
        ];
    }
    
    /**
     * Rileva formato file automaticamente
     */
    private function detect_format($file_path, $content) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // OFX ha estensione .ofx o contiene tag OFX
        if ($extension === 'ofx' || stripos($content, '<OFX>') !== false) {
            return 'ofx';
        }
        
        // CSV: prova a capire se è PostePay o ING
        if ($extension === 'csv') {
            $lines = explode("\n", $content);
            $first_line = strtolower($lines[0] ?? '');
            
            // Rileva separatore
            $delimiter = ';';
            if (strpos($first_line, ',') !== false && strpos($first_line, ';') === false) {
                $delimiter = ',';
            }
            
            $columns = str_getcsv($first_line, $delimiter);
            
            // ING: riconosci da header specifico
            if (stripos($first_line, 'data contabile') !== false || 
                stripos($first_line, 'uscite') !== false ||
                stripos($first_line, 'entrate') !== false ||
                (count($columns) >= 6 && (stripos($first_line, 'valuta') !== false || stripos($first_line, 'addebito') !== false))) {
                return 'ing';
            }
            
            // PostePay: Data,Descrizione,Importo,Saldo (4 colonne)
            if (count($columns) === 4) {
                return 'postepay';
            }
            
            // Default: prova ING se ha 6+ colonne
            if (count($columns) >= 6) {
                return 'ing';
            }
        }
        
        return 'csv';
    }
    
    /**
     * Ottieni parser appropriato
     */
    private function get_parser($format) {
        switch ($format) {
            case 'postepay':
                return new PostePayParser();
            case 'ing':
                return new INGParser();
            case 'ofx':
                return new OFXParser();
            default:
                return null;
        }
    }
    
    /**
     * Verifica se movimento già esistente
     */
    private function transaction_exists($account_id, $transaction_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
            WHERE account_id = %d
            AND transaction_date = %s
            AND ABS(amount - %f) < 0.01
            AND description = %s
            LIMIT 1",
            $account_id,
            $transaction_data['transaction_date'],
            $transaction_data['amount'],
            $transaction_data['description']
        ));
        
        return (int) $exists > 0;
    }
}