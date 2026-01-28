<?php
/**
 * Parser CSV ING Direct
 * 
 * Estrae movimenti e saldo finale dal CSV ING
 */

namespace FP\FinanceHub\Import\Bank;

if (!defined('ABSPATH')) {
    exit;
}

class INGParser {
    
    /**
     * Parse CSV ING Direct
     * 
     * Formato reale: DATA CONTABILE;DATA VALUTA;USCITE;ENTRATE;CAUSALE;DESCRIZIONE OPERAZIONE
     * Oppure formato alternativo: Data,Valuta,Descrizione,Addebito,Accredito,Saldo
     * 
     * @param string $csv_content Contenuto CSV
     * @return array Array con 'transactions' e 'final_balance'
     */
    public function parse($csv_content) {
        $lines = explode("\n", $csv_content);
        $transactions = [];
        $final_balance = null;
        $running_balance = null;
        
        // Rileva separatore (; o ,)
        $delimiter = ';';
        if (strpos($lines[0] ?? '', ',') !== false && strpos($lines[0] ?? '', ';') === false) {
            $delimiter = ',';
        }
        
        // Skip header se presente
        $start_line = 0;
        $header_line = strtolower($lines[0] ?? '');
        if (stripos($header_line, 'data contabile') !== false || 
            stripos($header_line, 'data') !== false || 
            stripos($header_line, 'date') !== false) {
            $start_line = 1;
        }
        
        // Determina formato in base all'header
        $is_ing_format = stripos($header_line, 'data contabile') !== false || 
                         stripos($header_line, 'uscite') !== false ||
                         stripos($header_line, 'entrate') !== false;
        
        for ($i = $start_line; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }
            
            // Parse con il delimitatore corretto
            $data = str_getcsv($line, $delimiter);
            
            if ($is_ing_format) {
                // Formato ING reale: DATA CONTABILE;DATA VALUTA;USCITE;ENTRATE;CAUSALE;DESCRIZIONE OPERAZIONE
                if (count($data) >= 6) {
                    $data_contabile = trim($data[0]);
                    $data_valuta = trim($data[1]);
                    $uscite = trim($data[2]);
                    $entrate = trim($data[3]);
                    $causale = trim($data[4]);
                    $descrizione = trim($data[5]);
                    
                    // Verifica se è la riga "Saldo finale"
                    if (stripos($descrizione, 'saldo finale') !== false || 
                        stripos($descrizione, 'saldo iniziale') !== false) {
                        // Estrai saldo dalla colonna ENTRATE o USCITE
                        $balance_str = !empty($entrate) ? $entrate : $uscite;
                        if (!empty($balance_str)) {
                            $final_balance = $this->parse_amount($balance_str);
                            // Se è nella colonna USCITE ma ha il segno +, è positivo
                            if (!empty($entrate) && strpos($entrate, '+') === 0) {
                                $final_balance = abs($final_balance);
                            }
                        }
                        continue;
                    }
                    
                    // Parse date
                    $transaction_date = $this->parse_date($data_contabile);
                    $value_date = !empty($data_valuta) ? $this->parse_date($data_valuta) : $transaction_date;
                    
                    // Parse importi
                    $uscite_amount = !empty($uscite) ? $this->parse_amount($uscite) : 0;
                    $entrate_amount = !empty($entrate) ? $this->parse_amount($entrate) : 0;
                    
                    // Calcola amount (positivo=entrate, negativo=uscite)
                    $amount = $entrate_amount > 0 ? $entrate_amount : -$uscite_amount;
                    
                    // Combina causale e descrizione
                    $full_description = trim($causale . ' ' . $descrizione);
                    
                    $transactions[] = [
                        'transaction_date' => $transaction_date,
                        'value_date' => $value_date,
                        'amount' => $amount,
                        'balance' => null, // Non disponibile nel formato ING
                        'description' => $full_description,
                        'reference' => null,
                    ];
                }
            } else {
                // Formato alternativo: Data,Valuta,Descrizione,Addebito,Accredito,Saldo
                if (count($data) >= 6) {
                    $date = $this->parse_date($data[0]);
                    $currency = trim($data[1]);
                    $description = $data[2];
                    $addebito = $this->parse_amount($data[3] ?: '0');
                    $accredito = $this->parse_amount($data[4] ?: '0');
                    
                    // Calcola amount (positivo=accredito, negativo=addebito)
                    $amount = $accredito > 0 ? $accredito : -$addebito;
                    
                    $balance = !empty($data[5]) ? $this->parse_amount($data[5]) : null;
                    
                    // Salva ultimo saldo se disponibile
                    if ($balance !== null) {
                        $final_balance = $balance;
                    }
                    
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
        }
        
        return [
            'transactions' => $transactions,
            'final_balance' => $final_balance,
        ];
    }
    
    /**
     * Parse data in vari formati
     */
    private function parse_date($date_string) {
        // Prova formato DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_string, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        
        // Prova formato YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_string)) {
            return $date_string;
        }
        
        // Default: usa data corrente
        return current_time('Y-m-d');
    }
    
    /**
     * Parse importo (gestisce separatori decimali formato italiano)
     * Formato italiano: 1.234,56 (punto=migliaia, virgola=decimali)
     */
    private function parse_amount($amount_string) {
        // Rimuovi spazi e caratteri non numerici tranne punto/virgola/segno più/segno meno
        $amount_string = trim($amount_string);
        
        if (empty($amount_string)) {
            return 0.0;
        }
        
        // Rimuovi segno + se presente (gestito separatamente)
        $is_positive = (strpos($amount_string, '+') === 0);
        $amount_string = ltrim($amount_string, '+');
        
        // Gestisce formato italiano: 1.234,56 (punto=migliaia, virgola=decimali)
        // Se ha sia punto che virgola, assume formato italiano
        if (strpos($amount_string, '.') !== false && strpos($amount_string, ',') !== false) {
            // Formato italiano: rimuovi punti (migliaia) e sostituisci virgola con punto (decimali)
            $amount_string = str_replace('.', '', $amount_string);
            $amount_string = str_replace(',', '.', $amount_string);
        } 
        // Se ha solo virgola, assume sia decimale
        elseif (strpos($amount_string, ',') !== false && strpos($amount_string, '.') === false) {
            $amount_string = str_replace(',', '.', $amount_string);
        }
        // Se ha solo punti, potrebbe essere formato inglese o italiano
        elseif (strpos($amount_string, '.') !== false) {
            // Se ha più di un punto, probabilmente sono migliaia (formato italiano)
            if (substr_count($amount_string, '.') > 1) {
                $amount_string = str_replace('.', '', $amount_string);
            }
            // Altrimenti è formato inglese (punto=decimale), lascia così
        }
        
        $amount = floatval($amount_string);
        
        // Applica segno positivo se era presente
        if ($is_positive && $amount < 0) {
            $amount = abs($amount);
        }
        
        return $amount;
    }
}
