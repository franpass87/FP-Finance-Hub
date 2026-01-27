<?php
/**
 * Parser XML Fatture Aruba
 * 
 * Estrae dati da XML fattura elettronica PA
 */

namespace FP\FinanceHub\Integration\Aruba;

if (!defined('ABSPATH')) {
    exit;
}

class ArubaXMLParser {
    
    /**
     * Parse XML fattura (base64 encoded)
     * 
     * @param string $xml_base64 XML codificato in base64
     * @return array Dati estratti
     */
    public function parse($xml_base64) {
        $xml_string = base64_decode($xml_base64);
        
        if ($xml_string === false) {
            return new \WP_Error('invalid_xml', 'XML non valido o non codificato in base64');
        }
        
        // Disabilita errori XML durante parsing
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($xml_string);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            return new \WP_Error('xml_parse_error', 'Errore parsing XML: ' . implode(', ', array_map(function($e) {
                return $e->message;
            }, $errors)));
        }
        
        // Namespace FatturaPA
        $namespaces = $xml->getNamespaces(true);
        $root_ns = isset($namespaces['']) ? '' : 'p';
        
        // Estrai dati
        $data = [];
        
        // Numero fattura
        if (isset($xml->FatturaElettronicaBody->DatiGenerali->DatiGeneraliDocumento->Numero)) {
            $data['number'] = (string) $xml->FatturaElettronicaBody->DatiGenerali->DatiGeneraliDocumento->Numero;
        }
        
        // Data fattura
        if (isset($xml->FatturaElettronicaBody->DatiGenerali->DatiGeneraliDocumento->Data)) {
            $date_str = (string) $xml->FatturaElettronicaBody->DatiGenerali->DatiGeneraliDocumento->Data;
            // Formato: YYYY-MM-DD
            $data['date'] = $date_str;
        }
        
        // Dati Cedente (noi stessi)
        if (isset($xml->FatturaElettronicaHeader->CedentePrestatore)) {
            $cedente = $xml->FatturaElettronicaHeader->CedentePrestatore;
            $data['sender'] = [
                'name' => (string) ($cedente->DatiAnagrafici->Anagrafica->Denominazione ?? ''),
                'vat' => (string) ($cedente->DatiAnagrafici->IdFiscaleIVA->IdCodice ?? ''),
            ];
        }
        
        // Dati Cessionario (cliente)
        if (isset($xml->FatturaElettronicaHeader->CessionarioCommittente)) {
            $cessionario = $xml->FatturaElettronicaHeader->CessionarioCommittente;
            $data['receiver'] = [
                'description' => (string) ($cessionario->DatiAnagrafici->Anagrafica->Denominazione ?? 
                                         $cessionario->DatiAnagrafici->Anagrafica->Nome ?? ''),
                'vatCode' => (string) ($cessionario->DatiAnagrafici->IdFiscaleIVA->IdCodice ?? ''),
                'fiscalCode' => (string) ($cessionario->DatiAnagrafici->CodiceFiscale ?? ''),
                'countryCode' => (string) ($cessionario->DatiAnagrafici->IdFiscaleIVA->IdPaese ?? 'IT'),
            ];
        }
        
        // Dati riepilogativi (importi)
        if (isset($xml->FatturaElettronicaBody->DatiBeniServizi->DatiRiepilogo)) {
            $total = 0.0;
            $tax_total = 0.0;
            $subtotal = 0.0;
            
            foreach ($xml->FatturaElettronicaBody->DatiBeniServizi->DatiRiepilogo as $riepilogo) {
                $imponibile = floatval((string) ($riepilogo->ImponibileImporto ?? 0));
                $imposta = floatval((string) ($riepilogo->Imposta ?? 0));
                
                $subtotal += $imponibile;
                $tax_total += $imposta;
            }
            
            $total = $subtotal + $tax_total;
            
            $data['subtotal'] = $subtotal;
            $data['tax'] = $tax_total;
            $data['total'] = $total;
        }
        
        // Se non ci sono riepiloghi, prova a calcolare da righe dettaglio
        if (empty($data['total']) && isset($xml->FatturaElettronicaBody->DatiBeniServizi->DettaglioLinee)) {
            $total = 0.0;
            foreach ($xml->FatturaElettronicaBody->DatiBeniServizi->DettaglioLinee as $linea) {
                $importo = floatval((string) ($linea->PrezzoTotale ?? 0));
                $total += $importo;
            }
            $data['total'] = $total;
            $data['subtotal'] = $total;
            $data['tax'] = 0.0;
        }
        
        return $data;
    }
}
