<?php
/**
 * GoCardless Bank Account Data Service
 * 
 * Client API GoCardless Bank Account Data (ex Nordigen) per Open Banking
 */

namespace FP\FinanceHub\Integration\OpenBanking;

use FP\FinanceHub\Integration\OpenBanking\EncryptionService;

if (!defined('ABSPATH')) {
    exit;
}

class NordigenService {
    
    private $secret_id;
    private $secret_key;
    private $base_url = 'https://bankaccountdata.gocardless.com/api/v2';
    private $access_token = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->secret_id = get_option('fp_finance_hub_nordigen_secret_id', '');
        $this->secret_key = get_option('fp_finance_hub_nordigen_secret_key', '');
    }
    
    /**
     * Ottieni access token (necessario per tutte le chiamate)
     */
    private function get_access_token() {
        if ($this->access_token) {
            return $this->access_token;
        }
        
        // Verifica cache
        $cached_token = get_transient('fp_finance_hub_nordigen_access_token');
        if ($cached_token) {
            $this->access_token = $cached_token;
            return $this->access_token;
        }
        
        $response = wp_remote_post($this->base_url . '/token/new/', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'secret_id' => $this->secret_id,
                'secret_key' => $this->secret_key,
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['access'])) {
            $this->access_token = $body['access'];
            // Token valido 24 ore, salva in cache
            set_transient('fp_finance_hub_nordigen_access_token', $this->access_token, DAY_IN_SECONDS - 60); // -60 sec per sicurezza
            return $this->access_token;
        }
        
        return false;
    }
    
    /**
     * Ottieni lista banche disponibili (Italia)
     */
    public function get_institutions($country = 'IT') {
        $token = $this->get_access_token();
        if (!$token) {
            return new \WP_Error('auth_failed', 'Impossibile ottenere access token GoCardless');
        }
        
        $response = wp_remote_get(
            $this->base_url . '/institutions/?country=' . $country,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'timeout' => 30,
            ]
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200) {
            return $body;
        }
        
        return new \WP_Error('api_error', 'Errore API GoCardless: ' . ($body['message'] ?? 'Errore sconosciuto'));
    }
    
    /**
     * Genera URL OAuth per collegamento conto
     */
    public function get_oauth_url($institution_id, $redirect_uri, $reference = null) {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $reference = $reference ?: 'wp_fp_finance_hub_' . get_current_user_id() . '_' . time();
        
        // Crea requisition (connessione)
        $response = wp_remote_post($this->base_url . '/requisitions/', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'redirect' => $redirect_uri,
                'institution_id' => $institution_id,
                'reference' => $reference,
                'user_language' => 'IT',
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 201 && isset($body['link'])) {
            // Salva requisition_id per dopo
            set_transient('fp_finance_hub_nordigen_requisition_' . get_current_user_id(), $body['id'], HOUR_IN_SECONDS);
            return [
                'url' => $body['link'],
                'requisition_id' => $body['id'],
            ];
        }
        
        return new \WP_Error('api_error', 'Errore creazione requisition GoCardless: ' . ($body['message'] ?? 'Errore sconosciuto'));
    }
    
    /**
     * Ottieni conti collegati da requisition_id
     */
    public function get_accounts($requisition_id) {
        $token = $this->get_access_token();
        if (!$token) {
            return new \WP_Error('auth_failed', 'Impossibile ottenere access token GoCardless');
        }
        
        $response = wp_remote_get(
            $this->base_url . '/requisitions/' . $requisition_id . '/',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'timeout' => 30,
            ]
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['accounts'])) {
            return $body['accounts'];
        }
        
        return new \WP_Error('api_error', 'Errore API GoCardless: ' . ($body['message'] ?? 'Errore sconosciuto'));
    }
    
    /**
     * Ottieni dettagli conto
     */
    public function get_account_details($account_id) {
        $token = $this->get_access_token();
        if (!$token) {
            return new \WP_Error('auth_failed', 'Impossibile ottenere access token GoCardless');
        }
        
        $response = wp_remote_get(
            $this->base_url . '/accounts/' . $account_id . '/details/',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'timeout' => 30,
            ]
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['account'])) {
            return $body['account'];
        }
        
        return new \WP_Error('api_error', 'Errore API GoCardless: ' . ($body['message'] ?? 'Errore sconosciuto'));
    }
    
    /**
     * Ottieni saldo conto
     */
    public function get_balance($account_id) {
        $token = $this->get_access_token();
        if (!$token) {
            return new \WP_Error('auth_failed', 'Impossibile ottenere access token GoCardless');
        }
        
        $response = wp_remote_get(
            $this->base_url . '/accounts/' . $account_id . '/balances/',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'timeout' => 30,
            ]
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['balances']) && !empty($body['balances'])) {
            // Prendi il primo balance (solitamente "interimBooked" o "interimAvailable")
            return $body['balances'][0];
        }
        
        return new \WP_Error('api_error', 'Errore API GoCardless: ' . ($body['message'] ?? 'Errore sconosciuto'));
    }
    
    /**
     * Ottieni movimenti conto
     */
    public function get_transactions($account_id, $date_from = null, $date_to = null) {
        $token = $this->get_access_token();
        if (!$token) {
            return new \WP_Error('auth_failed', 'Impossibile ottenere access token GoCardless');
        }
        
        $params = [];
        if ($date_from) {
            $params['date_from'] = date('Y-m-d', strtotime($date_from));
        }
        if ($date_to) {
            $params['date_to'] = date('Y-m-d', strtotime($date_to));
        }
        
        $url = $this->base_url . '/accounts/' . $account_id . '/transactions/';
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['transactions'])) {
            // GoCardless separa transazioni in "booked" e "pending"
            $booked = $body['transactions']['booked'] ?? [];
            $pending = $body['transactions']['pending'] ?? [];
            
            return array_merge($booked, $pending);
        }
        
        return new \WP_Error('api_error', 'Errore API GoCardless: ' . ($body['message'] ?? 'Errore sconosciuto'));
    }
}
