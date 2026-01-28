<?php
/**
 * Parser Excel Riepilogo Aruba
 * 
 * Estrae dati fatture da file Excel esportati da Aruba
 */

namespace FP\FinanceHub\Integration\Aruba;

if (!defined('ABSPATH')) {
    exit;
}

class ArubaExcelParser {
    
    /**
     * Parse file Excel
     * 
     * @param string $file_path Path file Excel
     * @return array|WP_Error Array di fatture o errore
     */
    public function parse($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // Prova prima con PhpSpreadsheet se disponibile
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            return $this->parse_with_phpspreadsheet($file_path);
        }
        
        // Fallback: prova a convertire in CSV e parsare
        return $this->parse_fallback($file_path, $extension);
    }
    
    /**
     * Parse con PhpSpreadsheet
     */
    private function parse_with_phpspreadsheet($file_path) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $invoices = [];
            $headers = [];
            $header_row = null;
            
            // Trova riga header (prima riga con "Numero" o "Data" o simile)
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $row_data = [];
                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    if ($value !== null) {
                        $row_data[] = trim((string) $value);
                    } else {
                        $row_data[] = '';
                    }
                }
                
                // Verifica se questa è la riga header
                $row_lower = array_map('strtolower', $row_data);
                if (in_array('numero', $row_lower) || 
                    in_array('data', $row_lower) || 
                    in_array('importo', $row_lower) ||
                    in_array('totale', $row_lower)) {
                    $headers = $row_data;
                    $header_row = $row->getRowIndex();
                    break;
                }
            }
            
            if (empty($headers)) {
                return new \WP_Error('no_header', 'Impossibile trovare riga header nel file Excel');
            }
            
            // Normalizza nomi colonne (case-insensitive)
            $header_map = $this->create_header_map($headers);
            
            // Leggi righe dati
            $row_index = $header_row + 1;
            $max_row = $worksheet->getHighestRow();
            
            for ($row = $row_index; $row <= $max_row; $row++) {
                $row_data = [];
                foreach ($headers as $col_index => $header) {
                    $cell = $worksheet->getCellByColumnAndRow($col_index + 1, $row);
                    $value = $cell->getValue();
                    $row_data[] = $value !== null ? trim((string) $value) : '';
                }
                
                // Se la riga è vuota, salta
                if (empty(array_filter($row_data))) {
                    continue;
                }
                
                // Converti riga in fattura
                $invoice = $this->row_to_invoice($row_data, $header_map);
                if ($invoice) {
                    $invoices[] = $invoice;
                }
            }
            
            return $invoices;
            
        } catch (\Exception $e) {
            return new \WP_Error('excel_parse_error', 'Errore parsing Excel: ' . $e->getMessage());
        }
    }
    
    /**
     * Parse fallback (senza PhpSpreadsheet)
     */
    private function parse_fallback($file_path, $extension) {
        // Per .xlsx, prova a estrarre come ZIP e leggere XML
        if ($extension === 'xlsx') {
            return $this->parse_xlsx_fallback($file_path);
        }
        
        // Per .xls, suggerisci installazione PhpSpreadsheet
        return new \WP_Error(
            'phpspreadsheet_required', 
            'Per importare file Excel (.xls/.xlsx) è necessario installare PhpSpreadsheet. ' .
            'Esegui: composer require phpoffice/phpspreadsheet nella cartella del plugin.'
        );
    }
    
    /**
     * Parse XLSX come ZIP (fallback semplice)
     */
    private function parse_xlsx_fallback($file_path) {
        if (!class_exists('ZipArchive')) {
            return new \WP_Error('zip_not_supported', 'Estensione ZIP non disponibile');
        }
        
        // XLSX è un file ZIP, ma leggere i dati è complesso senza PhpSpreadsheet
        // Per ora suggeriamo l'installazione di PhpSpreadsheet
        return new \WP_Error(
            'phpspreadsheet_required', 
            'Per importare file Excel (.xlsx) è necessario installare PhpSpreadsheet. ' .
            'Esegui: composer require phpoffice/phpspreadsheet nella cartella del plugin. ' .
            'In alternativa, esporta da Excel come CSV e importa il CSV.'
        );
    }
    
    /**
     * Crea mappa header (normalizza nomi colonne)
     */
    private function create_header_map($headers) {
        $map = [];
        $normalized = [];
        
        // Mappatura possibili nomi colonne Aruba
        $column_mappings = [
            'numero' => ['numero', 'num', 'n.', 'fattura', 'fattura n.', 'fattura n°'],
            'data' => ['data', 'data emissione', 'data fattura', 'emissione', 'emesso il'],
            'cliente' => ['cliente', 'destinatario', 'cessionario', 'committente', 'ragione sociale', 'denominazione'],
            'piva' => ['p.iva', 'partita iva', 'piva', 'vat', 'codice fiscale', 'cf'],
            'imponibile' => ['imponibile', 'imponibile importo', 'base imponibile', 'subtotale'],
            'iva' => ['iva', 'imposta', 'imposta iva', 'aliquota iva'],
            'totale' => ['totale', 'importo totale', 'totale fattura', 'totale documento'],
            'stato' => ['stato', 'status', 'esito', 'notifica'],
        ];
        
        foreach ($headers as $index => $header) {
            $header_lower = strtolower(trim($header));
            
            // Cerca corrispondenza nelle mappature
            foreach ($column_mappings as $key => $variants) {
                foreach ($variants as $variant) {
                    if (strpos($header_lower, $variant) !== false) {
                        $map[$key] = $index;
                        $normalized[$index] = $key;
                        break 2;
                    }
                }
            }
        }
        
        return [
            'map' => $map,
            'normalized' => $normalized,
            'headers' => $headers,
        ];
    }
    
    /**
     * Converte riga Excel in dati fattura
     */
    private function row_to_invoice($row_data, $header_map) {
        $map = $header_map['map'];
        $headers = $header_map['headers'];
        
        // Estrai valori usando mappa
        $numero = isset($map['numero']) ? trim($row_data[$map['numero']] ?? '') : '';
        $data = isset($map['data']) ? trim($row_data[$map['data']] ?? '') : '';
        $cliente = isset($map['cliente']) ? trim($row_data[$map['cliente']] ?? '') : '';
        $piva = isset($map['piva']) ? trim($row_data[$map['piva']] ?? '') : '';
        $imponibile = isset($map['imponibile']) ? $this->parse_amount($row_data[$map['imponibile']] ?? '') : 0;
        $iva = isset($map['iva']) ? $this->parse_amount($row_data[$map['iva']] ?? '') : 0;
        $totale = isset($map['totale']) ? $this->parse_amount($row_data[$map['totale']] ?? '') : 0;
        $stato = isset($map['stato']) ? trim($row_data[$map['stato']] ?? '') : '';
        
        // Se mancano dati essenziali, salta
        if (empty($numero) && empty($data)) {
            return null;
        }
        
        // Se totale non presente, calcola da imponibile + IVA
        if (empty($totale) && ($imponibile > 0 || $iva > 0)) {
            $totale = $imponibile + $iva;
        }
        
        // Se imponibile non presente ma totale sì, stima
        if (empty($imponibile) && $totale > 0) {
            $imponibile = $totale - $iva;
        }
        
        // Converti data in formato YYYY-MM-DD
        $date_formatted = $this->parse_date($data);
        
        return [
            'number' => $numero,
            'date' => $date_formatted ?: current_time('Y-m-d'),
            'receiver' => [
                'description' => $cliente,
                'vatCode' => $piva,
                'countryCode' => 'IT',
            ],
            'subtotal' => floatval($imponibile),
            'tax' => floatval($iva),
            'total' => floatval($totale),
            'aruba_status' => $stato ?: 'Inviata',
        ];
    }
    
    /**
     * Parse importo (rimuove simboli, converte virgola in punto)
     */
    private function parse_amount($value) {
        if (empty($value)) {
            return 0;
        }
        
        // Rimuovi simboli e spazi
        $value = str_replace(['€', '$', '£', ' ', "\t"], '', $value);
        
        // Converti virgola in punto
        $value = str_replace(',', '.', $value);
        
        // Rimuovi tutto tranne numeri e punto
        $value = preg_replace('/[^0-9.-]/', '', $value);
        
        return floatval($value);
    }
    
    /**
     * Parse data (supporta vari formati)
     */
    private function parse_date($value) {
        if (empty($value)) {
            return null;
        }
        
        // Se è già un timestamp Excel (numero)
        if (is_numeric($value) && $value > 25569) { // 25569 = 1970-01-01 in Excel
            // Converti da seriale Excel a timestamp Unix
            $timestamp = ($value - 25569) * 86400;
            return date('Y-m-d', $timestamp);
        }
        
        // Prova vari formati data
        $formats = [
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'Y/m/d',
            'd.m.Y',
        ];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        // Prova strtotime come fallback
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return null;
    }
}
