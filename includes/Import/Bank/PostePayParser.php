<?php
/**
 * Parser CSV PostePay Evolution
 * 
 * Estrae movimenti e saldo finale dal CSV PostePay
 */

namespace FP\FinanceHub\Import\Bank;

if (!defined('ABSPATH')) {
    exit;
}

class PostePayParser {
    
    /**
     * Parse CSV PostePay
     * 
     * Formato atteso: Data,Descrizione,Importo,Saldo
     * 
     * @param string $csv_content Contenuto CSV
     * @return array Array con 'transactions' e 'final_balance'
     */
    public function parse($csv_content) {
        $lines = explode("\n", $csv_content);
        $transactions = [];
        $last_balance = null;
        
        // Skip header se presente
        $start_line = 0;
        if (!empty($lines[0]) && stripos($lines[0], 'data') !== false) {
            $start_line = 1;
        }
        
        for ($i = $start_line; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }
            
            $data = str_getcsv($line);
            
            // Formato: Data,Descrizione,Importo,Saldo
            if (count($data) >= 4) {
                $date = $this->parse_date($data[0]);
                $description = $data[1];
                $amount = $this->parse_amount($data[2]);
                $balance = floatval($data[3]);
                
                // Salva ultimo saldo
                $last_balance = $balance;
                
                $transactions[] = [
                    'transaction_date' => $date,
                    'value_date' => $date,
                    'amount' => $amount,
                    'balance' => $balance,
                    'description' => $description,
                    'reference' => null,
                ];
            }
        }
        
        return [
            'transactions' => $transactions,
            'final_balance' => $last_balance,
        ];
    }
    
    /**
     * Parse data in vari formati
     */
    private function parse_date($date_string) {
        // Prova formato YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_string)) {
            return $date_string;
        }
        
        // Prova formato DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_string, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        
        // Prova formato DD-MM-YYYY
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date_string, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        
        // Default: usa data corrente
        return current_time('Y-m-d');
    }
    
    /**
     * Parse importo (gestisce separatori decimali)
     */
    private function parse_amount($amount_string) {
        // Rimuovi spazi e caratteri non numerici tranne punto/virgola/segno meno
        $amount_string = trim($amount_string);
        
        // Sostituisci virgola con punto se presente
        $amount_string = str_replace(',', '.', $amount_string);
        
        // Rimuovi eventuali punti come separatori migliaia
        if (substr_count($amount_string, '.') > 1) {
            // Ha separatori migliaia: rimuovi tutti tranne l'ultimo
            $parts = explode('.', $amount_string);
            $last = array_pop($parts);
            $amount_string = implode('', $parts) . '.' . $last;
        }
        
        return floatval($amount_string);
    }
}
