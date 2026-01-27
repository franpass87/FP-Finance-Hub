<?php
/**
 * Yapily Sync Service
 * 
 * Sincronizzazione automatica conti Yapily
 */

namespace FP\FinanceHub\Integration\OpenBanking;

use FP\FinanceHub\Integration\OpenBanking\YapilyService;
use FP\FinanceHub\Integration\OpenBanking\EncryptionService;
use FP\FinanceHub\Services\BankService;
use FP\FinanceHub\Database\Models\BankAccount as BankAccountModel;
use FP\FinanceHub\Database\Models\Transaction as TransactionModel;
use FP\FinanceHub\Services\Intelligence\IntelligenceAnalysisService;

if (!defined('ABSPATH')) {
    exit;
}

class YapilySyncService {
    
    private $yapily;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->yapily = new YapilyService();
    }
    
    /**
     * Sincronizza tutti i conti attivi
     */
    public function sync_all_accounts() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_finance_hub_bank_connections';
        
        // Trova conti da sincronizzare
        $accounts = $wpdb->get_results(
            "SELECT * FROM {$table}
            WHERE is_active = 1
            AND sync_enabled = 1
            AND provider = 'yapily'
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
        
        // Yapily usa consent_id come connection_id
        $consent_id = EncryptionService::decrypt($account->connection_id);
        
        if (is_wp_error($consent_id)) {
            error_log("[FP Finance Hub] Errore decriptazione consent_id per account {$account->id}");
            return false;
        }
        
        // Verifica che il consent sia ancora valido
        $consent = $this->yapily->get_consent($consent_id);
        if (is_wp_error($consent) || !isset($consent['status']) || $consent['status'] !== 'AUTHORIZED') {
            error_log("[FP Finance Hub] Consent non valido o scaduto per account {$account->id}");
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
            $balance = $this->yapily->get_balance($account->account_id, $consent_id);
            if (!is_wp_error($balance) && isset($balance['amount'])) {
                $amount = floatval($balance['amount']);
                
                // Aggiorna saldo nel conto bancario
                BankAccountModel::update_balance($bank_account->id, $amount);
            }
            
            // 2. Ottieni movimenti (ultimi 90 giorni o dall'ultima sync)
            $from_date = $account->last_sync_at 
                ? date('Y-m-d', strtotime($account->last_sync_at . ' -1 day'))
                : date('Y-m-d', strtotime('-90 days'));
            
            $transactions = $this->yapily->get_transactions(
                $account->account_id,
                $consent_id,
                $from_date
            );
            
            $transactions_imported = 0;
            if (!is_wp_error($transactions) && !empty($transactions)) {
                $transactions_imported = $this->import_transactions($bank_account, $transactions);
            }
            
            // Aggiorna timestamp sync (ogni 6 ore)
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
     * Trova o crea BankAccount per connessione Yapily
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
            
            // Converti formato Yapily a formato nostro
            $transaction_data = [
                'transaction_date' => isset($tx['date']) ? date('Y-m-d', strtotime($tx['date'])) : 
                                     (isset($tx['bookingDateTime']) ? date('Y-m-d', strtotime($tx['bookingDateTime'])) : current_time('Y-m-d')),
                'value_date' => isset($tx['valueDateTime']) ? date('Y-m-d', strtotime($tx['valueDateTime'])) : null,
                'amount' => isset($tx['amount']) ? floatval($tx['amount']) : 0,
                'description' => $tx['description'] ?? 
                                (isset($tx['remittanceInformationUnstructured']) ? $tx['remittanceInformationUnstructured'] : 'N/A'),
                'reference' => $tx['id'] ?? $tx['transactionId'] ?? null,
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
        
        $external_id = $tx['id'] ?? $tx['transactionId'] ?? null;
        if (!$external_id) {
            return false;
        }
        
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
