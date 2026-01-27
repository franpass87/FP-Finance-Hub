<?php
/**
 * Client API Aruba Fatturazione Elettronica
 * 
 * Gestisce autenticazione e chiamate API Aruba (solo lettura)
 */

namespace FP\FinanceHub\Integration\Aruba;

if (!defined('ABSPATH')) {
    exit;
}

class ArubaAPI {
    
    private $api_key;
    private $username;
    private $environment; // 'test' o 'production'
    private $base_url;
    
    private $access_token = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('fp_finance_hub_aruba_api_key', '');
        $this->username = get_option('fp_finance_hub_aruba_username', '');
        $this->environment = get_option('fp_finance_hub_aruba_environment', 'production');
        
        // URL base API Aruba
        if ($this->environment === 'test') {
            $this->base_url = 'https://fatturazioneelettronica.aruba.it/test/api';
        } else {
            $this->base_url = 'https://fatturazioneelettronica.aruba.it/api';
        }
    }
    
    /**
     * Verifica credenziali e ottieni access token
     */
    public function authenticate() {
        if (empty($this->api_key) || empty($this->username)) {
            return new \WP_Error('missing_credentials', 'Credenziali Aruba non configurate');
        }
        
        // Verifica token esistente in cache
        $cached_token = get_transient('fp_finance_hub_aruba_token');
        if ($cached_token) {
            $this->access_token = $cached_token;
            return true;
        }
        
        // Autenticazione via API Key
        $response = wp_remote_post($this->base_url . '/userInfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'username' => $this->username,
            ]),
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['username'])) {
            // Salva token in cache (1 ora)
            set_transient('fp_finance_hub_aruba_token', $this->api_key, HOUR_IN_SECONDS);
            $this->access_token = $this->api_key;
            return true;
        }
        
        return new \WP_Error('auth_failed', 'Autenticazione Aruba fallita: ' . ($body['message'] ?? 'Errore sconosciuto'));
    }
    
    /**
     * Ottieni informazioni utente
     */
    public function get_user_info() {
        $this->authenticate();
        
        $response = wp_remote_get($this->base_url . '/userInfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return $body;
    }
    
    /**
     * Ricerca fatture emesse (findByUsername)
     * 
     * @param array $filters Filtri ricerca (startDate, endDate, etc.)
     * @return array Lista fatture
     */
    public function find_invoices($filters = []) {
        $this->authenticate();
        
        $params = array_merge([
            'username' => $this->username,
        ], $filters);
        
        $url = $this->base_url . '/invoice/out/findByUsername?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['invoices'])) {
            return $body['invoices'];
        }
        
        return new \WP_Error('api_error', 'Errore API Aruba: ' . ($body['message'] ?? 'Errore sconosciuto'));
    }
    
    /**
     * Ottieni dettagli fattura singola (getByInvoiceId)
     * 
     * @param string $invoice_id ID fattura Aruba
     * @return array Dettagli fattura con file XML
     */
    public function get_invoice($invoice_id) {
        $this->authenticate();
        
        $url = $this->base_url . '/invoice/out/' . urlencode($invoice_id);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['invoice'])) {
            return $body['invoice'];
        }
        
        return new \WP_Error('api_error', 'Errore API Aruba: ' . ($body['message'] ?? 'Errore sconosciuto'));
    }
    
    /**
     * Test connessione
     */
    public function test_connection() {
        $user_info = $this->get_user_info();
        
        if (is_wp_error($user_info)) {
            return $user_info;
        }
        
        return [
            'success' => true,
            'username' => $user_info['username'] ?? null,
            'email' => $user_info['email'] ?? null,
        ];
    }
}
