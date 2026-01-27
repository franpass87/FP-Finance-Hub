<?php
/**
 * Parser OFX (Open Financial Exchange)
 * 
 * Estrae movimenti e saldo finale da file OFX
 */

namespace FP\FinanceHub\Import\Bank;

if (!defined('ABSPATH')) {
    exit;
}

class OFXParser {
    
    /**
     * Parse file OFX
     * 
     * @param string $ofx_content Contenuto file OFX
     * @return array Array con 'transactions' e 'final_balance'
     */
    public function parse($ofx_content) {
        // Normalizza line endings
        $ofx_content = str_replace(["\r\n", "\r"], "\n", $ofx_content);
        
        $transactions = [];
        $balance = null;
        
        // Estrai saldo finale
        if (preg_match('/<LEDGERBAL>[\s\S]*?<BALAMT>([\d.-]+)<\/BALAMT>/i', $ofx_content, $matches)) {
            $balance = floatval($matches[1]);
        }
        
        // Estrai transazioni
        if (preg_match_all('/<STMTTRN>([\s\S]*?)<\/STMTTRN>/i', $ofx_content, $transaction_matches)) {
            foreach ($transaction_matches[1] as $transaction_xml) {
                $transaction = $this->parse_transaction($transaction_xml);
                if ($transaction) {
                    $transactions[] = $transaction;
                }
            }
        }
        
        // Ordina per data (più recente prima)
        usort($transactions, function($a, $b) {
            return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
        });
        
        // Se abbiamo il saldo finale, usalo per l'ultimo movimento
        if ($balance !== null && !empty($transactions)) {
            $transactions[0]['balance'] = $balance;
        }
        
        return [
            'transactions' => $transactions,
            'final_balance' => $balance,
        ];
    }
    
    /**
     * Parse singola transazione OFX
     */
    private function parse_transaction($transaction_xml) {
        // Estrai data
        $date = null;
        if (preg_match('/<DTPOSTED>(\d{8})/i', $transaction_xml, $matches)) {
            $date_str = $matches[1];
            // Formato OFX: YYYYMMDD
            $date = substr($date_str, 0, 4) . '-' . substr($date_str, 4, 2) . '-' . substr($date_str, 6, 2);
        }
        
        if (!$date) {
            return null;
        }
        
        // Estrai importo
        $amount = 0.0;
        if (preg_match('/<TRNAMT>([\d.-]+)<\/TRNAMT>/i', $transaction_xml, $matches)) {
            $amount = floatval($matches[1]);
        }
        
        // Estrai descrizione
        $description = '';
        if (preg_match('/<MEMO>(.*?)<\/MEMO>/i', $transaction_xml, $matches)) {
            $description = trim($matches[1]);
        } elseif (preg_match('/<NAME>(.*?)<\/NAME>/i', $transaction_xml, $matches)) {
            $description = trim($matches[1]);
        }
        
        // Estrai riferimento
        $reference = null;
        if (preg_match('/<FITID>(.*?)<\/FITID>/i', $transaction_xml, $matches)) {
            $reference = trim($matches[1]);
        }
        
        return [
            'transaction_date' => $date,
            'value_date' => $date,
            'amount' => $amount,
            'balance' => null, // Verrà calcolato se necessario
            'description' => $description,
            'reference' => $reference,
        ];
    }
}
