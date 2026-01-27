<?php
/**
 * Nordigen Sync Service
 * 
 * Sincronizzazione automatica conti Nordigen (max 4/giorno)
 */

namespace FP\FinanceHub\Integration\OpenBanking;

use FP\FinanceHub\Integration\OpenBanking\NordigenService;
use FP\FinanceHub\Integration\OpenBanking\EncryptionService;
use FP\FinanceHub\Services\BankService;
use FP\FinanceHub\Database\Models\BankAccount as BankAccountModel;
use FP\FinanceHub\Database\Models\Transaction as TransactionModel;
use FP\FinanceHub\Services\Intelligence\IntelligenceAnalysisService;

if (!defined('ABSPATH')) {
    exit;
}

class NordigenSyncService {
    
    private $nordigen;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->nordigen = new NordigenService();
    }
    
    /**
     * Sincronizza tutti i conti attivi (max 4/giorno)
     */
    public function sync_all_accounts() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_connections';
        
        // Verifica quante sync già fatte oggi
        $syncs_today = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table}
            WHERE is_active = 1
            AND sync_enabled = 1
            AND DATE(last_sync_at) = CURDATE()"
        );
        
        // Max 4 sync/giorno (gratuito)
        if ($syncs_today >= 4) {
            error_log("[FP Finance Hub] Limite 4 sync/giorno raggiunto (Nordigen gratuito)");
            return;
        }
        
        // Trova conti da sincronizzare
        $accounts = $wpdb->get_results(
            "SELECT * FROM {$table}
            WHERE is_active = 1
            AND sync_enabled = 1
            AND provider = 'nordigen'
            AND next_sync_at <= NOW()
            ORDER BY next_sync_at ASC"
        );
        
        $synced_count = 0;
        foreach ($accounts as $account) {
            if ($this->sync_account($account)) {
                $synced_count++;
            }
            
            // Rate limiting: aspetta 1 secondo tra sync
            sleep(1);
        }
        
        // Invalida cache Intelligence se almeno un account sincronizzato
        if ($synced_count > 0) {
            IntelligenceAnalysisService::invalidate_cache();
        }
    }
    
    /**
     * Sincronizza singolo conto
     */
    public function sync_account($account) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_connections';
        
        // Nordigen usa requisition_id come connection_id
        $requisition_id = EncryptionService::decrypt($account->connection_id);
        
        if (is_wp_error($requisition_id)) {
            error_log("[FP Finance Hub] Errore decriptazione requisition_id per account {$account->id}");
            return false;
        }
        
        try {
            // Trova o crea BankAccount corrispondente (basandosi su IBAN)
            $bank_account = $this->get_or_create_bank_account($account);
            if (!$bank_account) {
                error_log("[FP Finance Hub] Impossibile creare/trovare BankAccount per connessione {$account->id}");
                return false;
            }
            
            // 1. Ottieni saldo
            $balance = $this->nordigen->get_balance($account->account_id);
            if (!is_wp_error($balance) && isset($balance['balanceAmount'])) {
                $amount = floatval($balance['balanceAmount']['amount']) / 100; // Centesimi → Euro
                
                // Aggiorna saldo nel conto bancario
                BankAccountModel::update_balance($bank_account->id, $amount);
            }
            
            // 2. Ottieni movimenti (ultimi 90 giorni o dall'ultima sync)
            $from_date = $account->last_sync_at 
                ? date('Y-m-d', strtotime($account->last_sync_at . ' -1 day'))
                : date('Y-m-d', strtotime('-90 days'));
            
            $transactions = $this->nordigen->get_transactions(
                $account->account_id,
                $from_date
            );
            
            $transactions_imported = 0;
            if (!is_wp_error($transactions) && !empty($transactions)) {
                $transactions_imported = $this->import_transactions($bank_account, $transactions);
            }
            
            // Aggiorna timestamp sync (minimo 6 ore = 4 volte/giorno max)
            $next_sync = date('Y-m-d H:i:s', strtotime("+6 hours"));
            $wpdb->update(
                $table,
                [
                    'last_sync_at' => current_time('mysql'),
                    'next_sync_at' => $next_sync,
                ],
                ['id' => $account->id],
                ['%s', '%s'],
                ['%d']
            );
            
            // Invalida cache Intelligence se transazioni importate
            if ($transactions_imported > 0) {
                IntelligenceAnalysisService::invalidate_cache();
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("[FP Finance Hub] Errore sync account {$account->id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Trova o crea BankAccount per connessione Nordigen
     */
    private function get_or_create_bank_account($connection) {
        global $wpdb;
        
        // Cerca per IBAN se presente
        if (!empty($connection->iban)) {
            $bank_accounts_table = $wpdb->prefix . 'fp_finance_hub_bank_accounts';
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$bank_accounts_table} WHERE iban = %s LIMIT 1",
                $connection->iban
            ));
            
            if ($existing) {
                return $existing;
            }
        }
        
        // Crea nuovo BankAccount
        $bank_account_data = [
            'name' => $connection->account_name ?: ($connection->bank_name . ' - ' . substr($connection->account_id, -4)),
            'type' => 'open_banking',
            'iban' => $connection->iban,
            'currency' => $connection->currency ?: 'EUR',
            'current_balance' => 0.00,
            'is_active' => true,
        ];
        
        $bank_account_id = BankAccountModel::create($bank_account_data);
        
        if (is_wp_error($bank_account_id)) {
            return null;
        }
        
        return BankAccountModel::get($bank_account_id);
    }
    
    /**
     * Importa movimenti nel database
     * 
     * @return int Numero di transazioni importate
     */
    private function import_transactions($bank_account, $transactions) {
        $bank_service = BankService::get_instance();
        $imported = 0;
        
        foreach ($transactions as $tx) {
            // Verifica se già esistente
            if ($this->transaction_exists($bank_account->id, $tx)) {
                continue;
            }
            
            // Converti formato Nordigen a formato nostro
            $transaction_data = [
                'transaction_date' => isset($tx['bookingDate']) ? date('Y-m-d', strtotime($tx['bookingDate'])) : 
                                     (isset($tx['valueDate']) ? date('Y-m-d', strtotime($tx['valueDate'])) : current_time('Y-m-d')),
                'value_date' => isset($tx['valueDate']) ? date('Y-m-d', strtotime($tx['valueDate'])) : null,
                'amount' => isset($tx['transactionAmount']['amount']) ? floatval($tx['transactionAmount']['amount']) / 100 : 0, // Centesimi → Euro
                'description' => $tx['remittanceInformationUnstructured'] ?? 
                                (isset($tx['remittanceInformationUnstructuredArray'][0]) ? $tx['remittanceInformationUnstructuredArray'][0] : 'N/A'),
                'reference' => $tx['transactionId'] ?? $tx['internalTransactionId'] ?? null,
            ];
            
            // Importa movimento (con categorizzazione automatica)
            $result = $bank_service->import_transaction($bank_account->id, $transaction_data);
            if (!is_wp_error($result)) {
                $imported++;
            }
        }
        
        return $imported;
    }
    
    /**
     * Verifica se movimento già esistente
     */
    private function transaction_exists($account_id, $tx) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        $external_id = $tx['transactionId'] ?? $tx['internalTransactionId'] ?? null;
        if (!$external_id) {
            return false;
        }
        
        $date = isset($tx['bookingDate']) ? date('Y-m-d', strtotime($tx['bookingDate'])) : null;
        $amount = isset($tx['transactionAmount']['amount']) ? floatval($tx['transactionAmount']['amount']) / 100 : 0;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
            WHERE account_id = %d
            AND reference = %s
            LIMIT 1",
            $account_id,
            $external_id
        ));
        
        return (int) $exists > 0;
    }
}
