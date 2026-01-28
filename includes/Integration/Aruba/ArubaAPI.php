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
    
    private $username;
    private $password;
    private $environment; // 'demo' o 'production'
    private $auth_base_url;
    private $api_base_url;
    
    private $access_token = null;
    private $refresh_token = null;
    private $token_expires_at = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->username = get_option('fp_finance_hub_aruba_username', '');
        $this->password = get_option('fp_finance_hub_aruba_password', '');
        $this->environment = get_option('fp_finance_hub_aruba_environment', 'production');
        
        // URL base secondo documentazione ufficiale Aruba
        if ($this->environment === 'demo') {
            $this->auth_base_url = 'https://demoauth.fatturazioneelettronica.aruba.it';
            $this->api_base_url = 'https://demows.fatturazioneelettronica.aruba.it';
        } else {
            $this->auth_base_url = 'https://auth.fatturazioneelettronica.aruba.it';
            $this->api_base_url = 'https://ws.fatturazioneelettronica.aruba.it';
        }
    }
    
    /**
     * Autenticazione con username/password (metodo signin)
     * 
     * Secondo documentazione: POST /auth/signin
     * Parametri: grant_type=password, username, password (in body, non query string)
     */
    public function authenticate() {
        if (empty($this->username) || empty($this->password)) {
            return new \WP_Error('missing_credentials', 'Credenziali Aruba non configurate (username e password)');
        }
        
        // Verifica token esistente in cache (valido per 30 minuti)
        $cached_token = get_transient('fp_finance_hub_aruba_token');
        $cached_expires = get_transient('fp_finance_hub_aruba_token_expires');
        
        if ($cached_token && $cached_expires && time() < $cached_expires) {
            $this->access_token = $cached_token;
            $this->token_expires_at = $cached_expires;
            return true;
        }
        
        // Autenticazione via signin (POST /auth/signin)
        // IMPORTANTE: parametri nel body, NON nella query string
        $response = wp_remote_post($this->auth_base_url . '/auth/signin', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
            ],
            'body' => http_build_query([
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password,
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            $this->refresh_token = $body['refresh_token'] ?? null;
            
            // Token valido per expires_in secondi (default 1800 = 30 minuti)
            $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) : 1800;
            $this->token_expires_at = time() + $expires_in;
            
            // Salva token in cache (con margine di sicurezza: 25 minuti invece di 30)
            set_transient('fp_finance_hub_aruba_token', $this->access_token, 25 * MINUTE_IN_SECONDS);
            set_transient('fp_finance_hub_aruba_token_expires', $this->token_expires_at, 25 * MINUTE_IN_SECONDS);
            
            if ($this->refresh_token) {
                set_transient('fp_finance_hub_aruba_refresh_token', $this->refresh_token, 60 * MINUTE_IN_SECONDS);
            }
            
            return true;
        }
        
        $error_msg = $body['error_description'] ?? $body['error'] ?? 'Errore sconosciuto';
        return new \WP_Error('auth_failed', 'Autenticazione Aruba fallita: ' . $error_msg);
    }
    
    /**
     * Refresh token (metodo refresh)
     * 
     * Secondo documentazione: POST /auth/signin con grant_type=refresh_token
     */
    public function refresh_token() {
        $cached_refresh = get_transient('fp_finance_hub_aruba_refresh_token');
        
        if (!$cached_refresh) {
            // Se non c'è refresh token, fa nuova autenticazione
            return $this->authenticate();
        }
        
        $response = wp_remote_post($this->auth_base_url . '/auth/signin', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
            ],
            'body' => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $cached_refresh,
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            // Se refresh fallisce, prova nuova autenticazione
            return $this->authenticate();
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            $this->refresh_token = $body['refresh_token'] ?? $cached_refresh;
            
            $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) : 1800;
            $this->token_expires_at = time() + $expires_in;
            
            set_transient('fp_finance_hub_aruba_token', $this->access_token, 25 * MINUTE_IN_SECONDS);
            set_transient('fp_finance_hub_aruba_token_expires', $this->token_expires_at, 25 * MINUTE_IN_SECONDS);
            
            if ($this->refresh_token) {
                set_transient('fp_finance_hub_aruba_refresh_token', $this->refresh_token, 60 * MINUTE_IN_SECONDS);
            }
            
            return true;
        }
        
        // Se refresh fallisce, prova nuova autenticazione
        return $this->authenticate();
    }
    
    /**
     * Ottieni informazioni utente (GET /auth/userInfo)
     * 
     * Secondo documentazione: GET /auth/userInfo
     */
    public function get_user_info() {
        $auth_result = $this->authenticate();
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        
        $response = wp_remote_get($this->auth_base_url . '/auth/userInfo', [
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
        
        if ($code === 200) {
            return $body;
        }
        
        // Se token scaduto, prova refresh
        if ($code === 401) {
            $this->refresh_token();
            return $this->get_user_info();
        }
        
        return new \WP_Error('api_error', 'Errore API Aruba userInfo: ' . ($body['error'] ?? 'Errore sconosciuto'));
    }
    
    /**
     * Ricerca fatture emesse (GET /services/invoice/out/findByUsername)
     * 
     * Secondo documentazione: GET /services/invoice/out/findByUsername
     * 
     * @param array $filters Filtri ricerca (startDate, endDate, page, size, etc.)
     * @return array Lista fatture con paginazione
     */
    public function find_invoices($filters = []) {
        $auth_result = $this->authenticate();
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        
        $params = array_merge([
            'username' => $this->username,
            'page' => 1,
            'size' => 100,
        ], $filters);
        
        // Converti date in formato ISO 8601 se presenti
        if (isset($params['startDate']) && !empty($params['startDate'])) {
            $params['startDate'] = date('c', strtotime($params['startDate']));
        }
        if (isset($params['endDate']) && !empty($params['endDate'])) {
            $params['endDate'] = date('c', strtotime($params['endDate']));
        }
        
        $url = $this->api_base_url . '/services/invoice/out/findByUsername?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($body['content'])) {
            return $body['content']; // Array di fatture
        }
        
        // Se token scaduto, prova refresh
        if ($code === 401) {
            $this->refresh_token();
            return $this->find_invoices($filters);
        }
        
        $error_msg = $body['errorDescription'] ?? $body['errorCode'] ?? 'Errore sconosciuto';
        return new \WP_Error('api_error', 'Errore API Aruba findByUsername: ' . $error_msg);
    }
    
    /**
     * Ottieni dettagli fattura singola (GET /services/invoice/out/{invoiceId})
     * 
     * Secondo documentazione: GET /services/invoice/out/{invoiceId}
     * 
     * @param string $invoice_id ID fattura Aruba
     * @param bool $include_file Se true, include il file XML in base64
     * @return array Dettagli fattura con file XML
     */
    public function get_invoice($invoice_id, $include_file = true) {
        $auth_result = $this->authenticate();
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        
        $url = $this->api_base_url . '/services/invoice/out/' . urlencode($invoice_id);
        if (!$include_file) {
            $url .= '?includeFile=false';
        }
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200) {
            return $body;
        }
        
        // Se token scaduto, prova refresh
        if ($code === 401) {
            $this->refresh_token();
            return $this->get_invoice($invoice_id, $include_file);
        }
        
        $error_msg = $body['errorDescription'] ?? $body['errorCode'] ?? 'Errore sconosciuto';
        return new \WP_Error('api_error', 'Errore API Aruba getInvoice: ' . $error_msg);
    }
    
    /**
     * Ottieni fattura per filename (GET /services/invoice/out/getByFilename)
     * 
     * @param string $filename Nome file fattura (es. IT01879020517_abcde.xml.p7m)
     * @param bool $include_file Se true, include il file XML in base64
     * @return array Dettagli fattura
     */
    public function get_invoice_by_filename($filename, $include_file = true) {
        $auth_result = $this->authenticate();
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        
        $url = $this->api_base_url . '/services/invoice/out/getByFilename?' . http_build_query([
            'filename' => $filename,
            'includeFile' => $include_file ? 'true' : 'false',
        ]);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200) {
            return $body;
        }
        
        // Se token scaduto, prova refresh
        if ($code === 401) {
            $this->refresh_token();
            return $this->get_invoice_by_filename($filename, $include_file);
        }
        
        $error_msg = $body['errorDescription'] ?? $body['errorCode'] ?? 'Errore sconosciuto';
        return new \WP_Error('api_error', 'Errore API Aruba getByFilename: ' . $error_msg);
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
            'pec' => $user_info['pec'] ?? null,
            'userDescription' => $user_info['userDescription'] ?? null,
            'vatCode' => $user_info['vatCode'] ?? null,
            'accountStatus' => $user_info['accountStatus'] ?? null,
        ];
    }
}
