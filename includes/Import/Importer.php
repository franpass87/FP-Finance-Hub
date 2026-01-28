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
        
        // Log per debug
        error_log('[FP Finance Hub] File letto - Dimensione: ' . strlen($content) . ' bytes | Prime 200 caratteri: ' . substr($content, 0, 200));
        
        if ($format === 'auto') {
            $format = $this->detect_format($file_path, $content);
            error_log('[FP Finance Hub] Formato rilevato: ' . ($format ?? 'NULL'));
        }
        
        // Parse file
        try {
            // Se il formato è null, significa che il rilevamento automatico non è riuscito
            if ($format === null) {
                return new \WP_Error('invalid_format', 'Impossibile rilevare il formato del file. Assicurati che sia un CSV PostePay, CSV ING o file OFX valido.');
            }
            
            $parser = $this->get_parser($format);
            if (!$parser) {
                return new \WP_Error('invalid_format', 'Formato file non supportato: ' . $format . '. Formati supportati: postepay, ing, ofx');
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
        // Rimuovi BOM UTF-8 se presente
        $content = ltrim($content, "\xEF\xBB\xBF");
        
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // OFX ha estensione .ofx o contiene tag OFX
        if ($extension === 'ofx' || stripos($content, '<OFX>') !== false) {
            return 'ofx';
        }
        
        // CSV: prova a capire se è PostePay o ING
        if ($extension === 'csv') {
            // Normalizza line breaks
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            $lines = explode("\n", $content);
            
            // Trova la prima riga non vuota
            $first_line = '';
            $first_line_index = 0;
            for ($i = 0; $i < min(5, count($lines)); $i++) {
                $line = trim($lines[$i]);
                if (!empty($line)) {
                    $first_line = $line;
                    $first_line_index = $i;
                    break;
                }
            }
            
            if (empty($first_line)) {
                error_log('[FP Finance Hub] Prima riga CSV vuota o non trovata');
                return null;
            }
            
            // Rileva separatore (conta occorrenze)
            $semicolon_count = substr_count($first_line, ';');
            $comma_count = substr_count($first_line, ',');
            $delimiter = ($semicolon_count > $comma_count) ? ';' : ',';
            
            // Log per debug
            error_log('[FP Finance Hub] Rilevamento formato CSV - Prima riga: ' . substr($first_line, 0, 100) . ' | Separatore: ' . $delimiter . ' | Punto e virgola: ' . $semicolon_count . ' | Virgole: ' . $comma_count);
            
            $columns = str_getcsv($first_line, $delimiter);
            $column_count = count($columns);
            
            // Log colonne rilevate
            error_log('[FP Finance Hub] Colonne rilevate: ' . $column_count . ' | Prime 3 colonne: ' . implode(' | ', array_slice($columns, 0, 3)));
            
            // Normalizza prima riga per confronto (rimuovi spazi extra)
            $first_line_normalized = preg_replace('/\s+/', ' ', strtolower($first_line));
            
            // ING: riconosci da header specifico (case-insensitive, gestisce spazi multipli)
            $ing_keywords = [
                'data contabile',
                'uscite',
                'entrate',
                'causale',
                'descrizione operazione',
                'data valuta'
            ];
            
            foreach ($ing_keywords as $keyword) {
                if (stripos($first_line_normalized, $keyword) !== false) {
                    error_log('[FP Finance Hub] Rilevato formato ING per keyword: ' . $keyword);
                    return 'ing';
                }
            }
            
            // ING: se ha 6 colonne e separatore `;`, è molto probabile che sia ING
            if ($column_count === 6 && $delimiter === ';') {
                error_log('[FP Finance Hub] Rilevato formato ING per 6 colonne con separatore ;');
                return 'ing';
            }
            
            // ING: formato alternativo con header "Data,Valuta,Descrizione,Addebito,Accredito,Saldo"
            if ($column_count >= 6) {
                $alt_keywords = ['valuta', 'addebito', 'accredito', 'saldo'];
                foreach ($alt_keywords as $keyword) {
                    if (stripos($first_line_normalized, $keyword) !== false) {
                        error_log('[FP Finance Hub] Rilevato formato ING alternativo per keyword: ' . $keyword);
                        return 'ing';
                    }
                }
            }
            
            // PostePay: Data,Descrizione,Importo,Saldo (4 colonne con virgola)
            if ($column_count === 4 && $delimiter === ',') {
                error_log('[FP Finance Hub] Rilevato formato PostePay per 4 colonne con separatore ,');
                return 'postepay';
            }
            
            // Fallback: se ha 6+ colonne, prova ING (formato più comune)
            if ($column_count >= 6) {
                error_log('[FP Finance Hub] Fallback: rilevato formato ING per 6+ colonne');
                return 'ing';
            }
            
            // Fallback: se ha separatore `;` e almeno 5 colonne, è probabilmente ING
            if ($delimiter === ';' && $column_count >= 5) {
                error_log('[FP Finance Hub] Fallback: rilevato formato ING per separatore ; e ' . $column_count . ' colonne');
                return 'ing';
            }
            
            // Fallback finale: se ha separatore `;`, prova comunque ING (molto comune per banche italiane)
            if ($delimiter === ';') {
                error_log('[FP Finance Hub] Fallback finale: rilevato formato ING per separatore ; (formato comune banche italiane)');
                return 'ing';
            }
            
            error_log('[FP Finance Hub] Impossibile rilevare formato CSV - Colonne: ' . $column_count . ' | Separatore: ' . $delimiter . ' | Prima riga: ' . substr($first_line, 0, 150));
        }
        
        // Se non riesce a rilevare, restituisce null per generare errore più chiaro
        return null;
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
     * 
     * Controlla se un movimento con stessa data, importo e descrizione esiste già.
     * Questo permette di importare più file CSV senza creare duplicati.
     * 
     * Nota: ING limita l'export a 3 mesi, quindi puoi importare più file CSV
     * per periodi diversi e i duplicati verranno automaticamente saltati.
     */
    private function transaction_exists($account_id, $transaction_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        // Normalizza descrizione per confronto (rimuovi spazi multipli, trim)
        $description = trim(preg_replace('/\s+/', ' ', $transaction_data['description'] ?? ''));
        
        // Controlla se esiste un movimento con stessa data, importo (con tolleranza) e descrizione simile
        // Usa confronto diretto per compatibilità MySQL
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
            $description
        ));
        
        // Se non trovato con descrizione esatta, prova con descrizione normalizzata (per gestire variazioni minori)
        if ((int) $exists === 0 && !empty($description)) {
            // Cerca movimenti con stessa data e importo (descrizione può variare leggermente)
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE account_id = %d
                AND transaction_date = %s
                AND ABS(amount - %f) < 0.01
                LIMIT 1",
                $account_id,
                $transaction_data['transaction_date'],
                $transaction_data['amount']
            ));
            
            // Se trovato, verifica che la descrizione sia simile (almeno 80% di match)
            if ((int) $exists > 0) {
                $similar = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table}
                    WHERE account_id = %d
                    AND transaction_date = %s
                    AND ABS(amount - %f) < 0.01
                    AND (
                        description = %s
                        OR description LIKE %s
                        OR %s LIKE CONCAT('%%', description, '%%')
                    )
                    LIMIT 1",
                    $account_id,
                    $transaction_data['transaction_date'],
                    $transaction_data['amount'],
                    $description,
                    '%' . $wpdb->esc_like(substr($description, 0, 50)) . '%',
                    $description
                ));
                $exists = $similar;
            }
        }
        
        return (int) $exists > 0;
    }
}