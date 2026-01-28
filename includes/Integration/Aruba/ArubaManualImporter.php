<?php
/**
 * Import Manuale File XML Aruba
 * 
 * Permette di importare fatture Aruba caricando file XML manualmente
 * Utile per account base che non possono usare le API
 */

namespace FP\FinanceHub\Integration\Aruba;

use FP\FinanceHub\Services\InvoiceService;
use FP\FinanceHub\Services\ClientService;
use FP\FinanceHub\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class ArubaManualImporter {
    
    private $xml_parser;
    private $invoice_service;
    private $client_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->xml_parser = new ArubaXMLParser();
        $this->invoice_service = InvoiceService::get_instance();
        $this->client_service = ClientService::get_instance();
    }
    
    /**
     * Importa file XML caricati
     * 
     * @param array $files Array di file da $_FILES
     * @return array|WP_Error Risultato import
     */
    public function import_files($files) {
        if (empty($files) || empty($files['name'])) {
            return new \WP_Error('no_files', 'Nessun file selezionato');
        }
        
        $imported = 0;
        $updated = 0;
        $errors = 0;
        $error_details = [];
        
        // Gestisci file multipli
        $file_count = is_array($files['name']) ? count($files['name']) : 1;
        
        for ($i = 0; $i < $file_count; $i++) {
            $file_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $file_tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $file_error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            $file_size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
            
            // Verifica errori upload
            if ($file_error !== UPLOAD_ERR_OK) {
                $errors++;
                $error_details[] = "File '{$file_name}': " . $this->get_upload_error_message($file_error);
                continue;
            }
            
            // Verifica dimensione (max 50MB)
            $max_size = 50 * 1024 * 1024; // 50MB
            if ($file_size > $max_size) {
                $errors++;
                $error_details[] = "File '{$file_name}': Dimensione troppo grande (max 50MB)";
                continue;
            }
            
            // Processa file
            $result = $this->process_file($file_tmp, $file_name);
            
            if (is_wp_error($result)) {
                $errors++;
                $error_details[] = "File '{$file_name}': " . $result->get_error_message();
            } else {
                $imported += $result['imported'];
                $updated += $result['updated'];
                if (!empty($result['errors'])) {
                    $errors += $result['errors'];
                    $error_details = array_merge($error_details, $result['error_details'] ?? []);
                }
            }
        }
        
        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'error_details' => $error_details,
        ];
    }
    
    /**
     * Processa un singolo file (XML, ZIP o Excel)
     */
    private function process_file($file_path, $file_name) {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Se è un ZIP, estrai e processa i file XML dentro
        if ($extension === 'zip') {
            return $this->process_zip($file_path);
        }
        
        // Se è Excel, processa come Excel
        if (in_array($extension, ['xls', 'xlsx'])) {
            return $this->process_excel_file($file_path, $file_name);
        }
        
        // Altrimenti processa come XML
        return $this->process_xml_file($file_path, $file_name);
    }
    
    /**
     * Processa file Excel
     */
    private function process_excel_file($file_path, $file_name) {
        require_once __DIR__ . '/ArubaExcelParser.php';
        $excel_parser = new ArubaExcelParser();
        
        $invoices = $excel_parser->parse($file_path);
        
        if (is_wp_error($invoices)) {
            return $invoices;
        }
        
        if (empty($invoices)) {
            return new \WP_Error('no_invoices', 'Nessuna fattura trovata nel file Excel');
        }
        
        $imported = 0;
        $updated = 0;
        $errors = 0;
        $error_details = [];
        
        foreach ($invoices as $invoice_data) {
            // Verifica se fattura già esistente
            $existing = null;
            if (!empty($invoice_data['number'])) {
                $existing = \FP\FinanceHub\Database\Models\Invoice::find_by_number($invoice_data['number']);
                // Verifica anche che la data corrisponda
                if ($existing && !empty($invoice_data['date']) && $existing->issue_date !== $invoice_data['date']) {
                    $existing = null;
                }
            }
            
            // Crea struttura dati simile a quella di Aruba API
            $aruba_invoice_data = [
                'id' => md5($file_name . ($invoice_data['number'] ?? '') . ($invoice_data['date'] ?? '')),
                'idSdi' => null,
                'status' => $invoice_data['aruba_status'] ?? 'Inviata',
                'filename' => $file_name,
            ];
            
            // Importa/aggiorna fattura
            $wp_invoice_id = $this->invoice_service->import_from_aruba($aruba_invoice_data, $invoice_data);
            
            if (is_wp_error($wp_invoice_id)) {
                $errors++;
                $error_details[] = "Fattura '{$invoice_data['number']}': " . $wp_invoice_id->get_error_message();
            } else {
                if ($existing) {
                    $updated++;
                } else {
                    $imported++;
                }
                
                // Importa cliente se presente
                if (!empty($invoice_data['receiver'])) {
                    $this->client_service->import_from_aruba($invoice_data['receiver']);
                }
                
                Logger::log('aruba_excel_import_success', 'Fattura importata da Excel', [
                    'file' => $file_name,
                    'invoice_number' => $invoice_data['number'] ?? null,
                    'invoice_id' => $wp_invoice_id,
                ]);
            }
        }
        
        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'error_details' => $error_details,
        ];
    }
    
    /**
     * Processa file ZIP estraendo XML
     */
    private function process_zip($zip_path) {
        if (!class_exists('ZipArchive')) {
            return new \WP_Error('zip_not_supported', 'Estensione ZIP non disponibile sul server');
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new \WP_Error('zip_open_error', 'Impossibile aprire il file ZIP');
        }
        
        $imported = 0;
        $updated = 0;
        $errors = 0;
        $error_details = [];
        
        // Estrai tutti i file XML
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry_name = $zip->getNameIndex($i);
            
            // Salta directory e file non XML
            if (substr($entry_name, -1) === '/' || 
                (strtolower(pathinfo($entry_name, PATHINFO_EXTENSION)) !== 'xml' && 
                 strpos(strtolower($entry_name), '.xml') === false)) {
                continue;
            }
            
            // Estrai contenuto
            $xml_content = $zip->getFromIndex($i);
            if ($xml_content === false) {
                $errors++;
                $error_details[] = "File ZIP - Impossibile estrarre '{$entry_name}'";
                continue;
            }
            
            // Processa XML
            $result = $this->process_xml_content($xml_content, $entry_name);
            
            if (is_wp_error($result)) {
                $errors++;
                $error_details[] = "File ZIP '{$entry_name}': " . $result->get_error_message();
            } else {
                if ($result['imported']) {
                    $imported++;
                } else {
                    $updated++;
                }
            }
        }
        
        $zip->close();
        
        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'error_details' => $error_details,
        ];
    }
    
    /**
     * Processa file XML singolo
     */
    private function process_xml_file($file_path, $file_name) {
        // Leggi contenuto file
        $xml_content = file_get_contents($file_path);
        if ($xml_content === false) {
            return new \WP_Error('read_error', 'Impossibile leggere il file');
        }
        
        return $this->process_xml_content($xml_content, $file_name);
    }
    
    /**
     * Processa contenuto XML
     */
    private function process_xml_content($xml_content, $file_name) {
        // Se il file è .p7m (firmato), prova a estrarre l'XML
        if (strpos(strtolower($file_name), '.p7m') !== false) {
            // Per file .p7m, prova a estrarre l'XML interno
            // Nota: questo è un approccio semplificato, potrebbe non funzionare per tutti i file firmati
            $xml_content = $this->extract_xml_from_p7m($xml_content);
            if ($xml_content === false) {
                return new \WP_Error('p7m_extract_error', 'Impossibile estrarre XML da file firmato .p7m');
            }
        }
        
        // Codifica in base64 per il parser (che si aspetta base64)
        $xml_base64 = base64_encode($xml_content);
        
        // Parse XML
        $xml_data = $this->xml_parser->parse($xml_base64);
        
        if (is_wp_error($xml_data)) {
            return $xml_data;
        }
        
        // Verifica che ci siano dati essenziali
        if (empty($xml_data['number']) && empty($xml_data['date'])) {
            return new \WP_Error('invalid_xml', 'File XML non contiene dati fattura validi');
        }
        
        // Verifica se fattura già esistente (per numero e data)
        $existing = null;
        if (!empty($xml_data['number'])) {
            $existing = \FP\FinanceHub\Database\Models\Invoice::find_by_number($xml_data['number']);
            // Verifica anche che la data corrisponda (se disponibile)
            if ($existing && !empty($xml_data['date']) && $existing->issue_date !== $xml_data['date']) {
                $existing = null; // Numero uguale ma data diversa = fattura diversa
            }
        }
        
        // Crea struttura dati simile a quella di Aruba API
        $aruba_invoice_data = [
            'id' => md5($file_name . ($xml_data['number'] ?? '') . ($xml_data['date'] ?? '')), // ID fittizio basato su file
            'idSdi' => null, // Non disponibile da file manuale
            'status' => 'Inviata', // Default
            'filename' => $file_name,
        ];
        
        // Importa/aggiorna fattura
        $wp_invoice_id = $this->invoice_service->import_from_aruba($aruba_invoice_data, $xml_data);
        
        if (is_wp_error($wp_invoice_id)) {
            return $wp_invoice_id;
        }
        
        // Importa cliente se presente
        if (!empty($xml_data['receiver'])) {
            $this->client_service->import_from_aruba($xml_data['receiver']);
        }
        
        // Log successo
        Logger::log('aruba_manual_import_success', 'Fattura importata manualmente', [
            'file' => $file_name,
            'invoice_number' => $xml_data['number'] ?? null,
            'invoice_id' => $wp_invoice_id,
        ]);
        
        return [
            'imported' => $existing ? 0 : 1,
            'updated' => $existing ? 1 : 0,
        ];
    }
    
    /**
     * Estrae XML da file .p7m (firmato)
     * 
     * Approccio semplificato: cerca tag XML nel contenuto
     */
    private function extract_xml_from_p7m($p7m_content) {
        // Se il contenuto inizia già con <?xml, è già XML estratto
        if (strpos($p7m_content, '<?xml') === 0) {
            return $p7m_content;
        }
        
        // Prova a trovare inizio XML nel contenuto
        $xml_start = strpos($p7m_content, '<?xml');
        if ($xml_start !== false) {
            // Estrai da qui fino alla fine
            $xml_content = substr($p7m_content, $xml_start);
            
            // Trova fine XML (ultimo </)
            $xml_end = strrpos($xml_content, '</');
            if ($xml_end !== false) {
                // Trova chiusura tag root
                $last_tag_end = strpos($xml_content, '>', $xml_end);
                if ($last_tag_end !== false) {
                    $xml_content = substr($xml_content, 0, $last_tag_end + 1);
                }
            }
            
            return $xml_content;
        }
        
        // Se non trova XML, restituisce false
        return false;
    }
    
    /**
     * Messaggio errore upload
     */
    private function get_upload_error_message($error_code) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File troppo grande (supera upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File troppo grande (supera MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File caricato parzialmente',
            UPLOAD_ERR_NO_FILE => 'Nessun file caricato',
            UPLOAD_ERR_NO_TMP_DIR => 'Directory temporanea mancante',
            UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere file su disco',
            UPLOAD_ERR_EXTENSION => 'Upload bloccato da estensione PHP',
        ];
        
        return $messages[$error_code] ?? 'Errore sconosciuto durante upload';
    }
}
