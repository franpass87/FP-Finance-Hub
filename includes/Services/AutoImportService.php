<?php
/**
 * Auto Import Service
 * 
 * Monitora cartella e importa automaticamente CSV/OFX quando vengono aggiunti
 */

namespace FP\FinanceHub\Services;

use FP\FinanceHub\Import\Importer;

if (!defined('ABSPATH')) {
    exit;
}

class AutoImportService {
    
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
     * Ottieni cartella monitorata
     */
    private function get_watch_folder() {
        $upload_dir = wp_upload_dir();
        $folder = $upload_dir['basedir'] . '/fp-finance-hub/auto-import';
        
        // Crea cartella se non esiste
        if (!file_exists($folder)) {
            wp_mkdir_p($folder);
            // Crea file .htaccess per sicurezza
            file_put_contents($folder . '/.htaccess', 'deny from all');
        }
        
        return $folder;
    }
    
    /**
     * Ottieni URL cartella (per configurazione export automatico)
     */
    public function get_watch_folder_url() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/fp-finance-hub/auto-import';
    }
    
    /**
     * Ottieni path cartella (per configurazione export automatico)
     */
    public function get_watch_folder_path() {
        return $this->get_watch_folder();
    }
    
    /**
     * Verifica e importa nuovi file nella cartella
     */
    public function check_and_import() {
        $folder = $this->get_watch_folder();
        $files = glob($folder . '/*.{csv,ofx}', GLOB_BRACE);
        
        if (empty($files)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => []];
        }
        
        $importer = new Importer();
        $bank_service = BankService::get_instance();
        $accounts = $bank_service->get_active_accounts();
        
        if (empty($accounts)) {
            return new \WP_Error('no_accounts', 'Nessun conto bancario configurato. Crea almeno un conto prima di usare l\'import automatico.');
        }
        
        $total_imported = 0;
        $total_skipped = 0;
        $errors = [];
        $processed_files = [];
        
        foreach ($files as $file_path) {
            $file_name = basename($file_path);
            
            // Salta file già processati (hanno estensione .processed)
            if (file_exists($file_path . '.processed')) {
                continue;
            }
            
            // Prova a determinare il conto dal nome file o usa il primo disponibile
            $account_id = $this->detect_account_from_filename($file_name, $accounts);
            
            if (!$account_id) {
                // Se non riesce a determinare, usa il primo conto disponibile
                $account_id = $accounts[0]->id;
            }
            
            // Importa file
            $result = $importer->import_file($account_id, $file_path, 'auto');
            
            if (is_wp_error($result)) {
                $errors[] = [
                    'file' => $file_name,
                    'error' => $result->get_error_message(),
                ];
                continue;
            }
            
            $total_imported += $result['imported'];
            $total_skipped += $result['skipped'];
            
            // Marca file come processato
            file_put_contents($file_path . '.processed', date('Y-m-d H:i:s'));
            
            // Sposta file in cartella processed (opzionale, per tenere traccia)
            $processed_folder = $folder . '/processed';
            if (!file_exists($processed_folder)) {
                wp_mkdir_p($processed_folder);
            }
            
            $new_path = $processed_folder . '/' . date('Y-m-d_His') . '_' . $file_name;
            if (file_exists($file_path)) {
                rename($file_path, $new_path);
                // Rimuovi anche il file .processed
                if (file_exists($file_path . '.processed')) {
                    unlink($file_path . '.processed');
                }
            }
            
            $processed_files[] = $file_name;
        }
        
        return [
            'imported' => $total_imported,
            'skipped' => $total_skipped,
            'processed_files' => $processed_files,
            'errors' => $errors,
        ];
    }
    
    /**
     * Prova a determinare il conto dal nome file
     */
    private function detect_account_from_filename($filename, $accounts) {
        $filename_lower = strtolower($filename);
        
        foreach ($accounts as $account) {
            $account_name_lower = strtolower($account->name);
            $bank_name_lower = strtolower($account->bank_name ?? '');
            
            // Cerca match nel nome file
            if (strpos($filename_lower, $account_name_lower) !== false) {
                return $account->id;
            }
            
            if ($bank_name_lower && strpos($filename_lower, $bank_name_lower) !== false) {
                return $account->id;
            }
            
            // Match comuni
            if (strpos($filename_lower, 'ing') !== false && strpos($account_name_lower, 'ing') !== false) {
                return $account->id;
            }
            
            if (strpos($filename_lower, 'poste') !== false && (strpos($account_name_lower, 'poste') !== false || strpos($bank_name_lower, 'poste') !== false)) {
                return $account->id;
            }
        }
        
        return null;
    }
    
    /**
     * Pulisci file vecchi (più di 30 giorni)
     */
    public function cleanup_old_files() {
        $processed_folder = $this->get_watch_folder() . '/processed';
        
        if (!file_exists($processed_folder)) {
            return;
        }
        
        $files = glob($processed_folder . '/*');
        $cutoff_time = time() - (30 * DAY_IN_SECONDS);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}
