<?php
/**
 * Client API Aruba Fatturazione Elettronica
 * 
 * Gestisce autenticazione e chiamate API Aruba secondo documentazione ufficiale:
 * https://fatturazioneelettronica.aruba.it/apidoc/docs.html
 * 
 * Supporta:
 * - Autenticazione con username/password (signin + refresh token)
 * - Ricerca fatture emesse e ricevute
 * - Gestione multicedenti (utenze Premium)
 * - Download fatture con file XML e PDF
 * 
 * NOTA: Per utenze Premium, alcuni parametri sono obbligatori:
 * - findByUsername (fatture emesse): countrySender, vatcodeSender
 * - findByUsername (fatture ricevute): countryReceiver, vatcodeReceiver
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
        
        $error_msg = $body['error'] ?? $body['errorDescription'] ?? 'Errore sconosciuto';
        return new \WP_Error('api_error', 'Errore API Aruba userInfo: ' . $error_msg, ['http_code' => $code]);
    }
    
    /**
     * Ricerca fatture emesse (GET /services/invoice/out/findByUsername)
     * 
     * Secondo documentazione: GET /services/invoice/out/findByUsername
     * 
     * NOTA: Per utenze Premium, i parametri countrySender e vatcodeSender sono OBBLIGATORI
     * 
     * @param array $filters Filtri ricerca (startDate, endDate, page, size, countrySender, vatcodeSender, etc.)
     * @return array Lista fatture con paginazione (restituisce direttamente $body['content'])
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
        
        // Verifica se l'utente è Premium (solo per utenti Premium servono countrySender/vatcodeSender)
        // IMPORTANTE: Per account base, NON aggiungere questi parametri
        $is_premium = $this->is_premium_user();
        
        // Solo se è CERTAMENTE Premium (bool true), gestisci i parametri
        if ($is_premium === true && (empty($params['countrySender']) || empty($params['vatcodeSender']))) {
            $premium_params = $this->get_premium_params();
            if (!is_wp_error($premium_params)) {
                if (empty($params['countrySender']) && !empty($premium_params['countrySender'])) {
                    $params['countrySender'] = $premium_params['countrySender'];
                }
                if (empty($params['vatcodeSender']) && !empty($premium_params['vatcodeSender'])) {
                    $params['vatcodeSender'] = $premium_params['vatcodeSender'];
                }
            } else {
                // Se il recupero automatico fallisce e mancano i parametri, blocca la chiamata
                if (empty($params['countrySender']) || empty($params['vatcodeSender'])) {
                    $error = $premium_params->get_error_message();
                    return new \WP_Error(
                        'premium_params_required',
                        'Account Premium rilevato ma parametri countrySender/vatcodeSender non disponibili. ' .
                        'SOLUZIONE: Vai in Impostazioni → FP Finance Hub → Impostazioni → Sezione "Integrazione Aruba" → ' .
                        '"Parametri Premium (Opzionali)" e inserisci manualmente Codice Paese (es: IT) e Partita IVA. ' .
                        'Dettaglio: ' . $error,
                        ['http_code' => 400]
                    );
                }
            }
        }
        
        // Verifica finale: se è CERTAMENTE Premium, i parametri DEVONO essere presenti
        if ($is_premium === true) {
            if (empty($params['countrySender']) || empty($params['vatcodeSender'])) {
                return new \WP_Error(
                    'premium_params_required',
                    'Account Premium rilevato ma parametri countrySender/vatcodeSender mancanti nella richiesta. ' .
                    'SOLUZIONE: Vai in Impostazioni → FP Finance Hub → Impostazioni → Sezione "Integrazione Aruba" → ' .
                    '"Parametri Premium (Opzionali)" e inserisci manualmente Codice Paese (es: IT) e Partita IVA.',
                    ['http_code' => 400]
                );
            }
        }
        
        // Per account base (is_premium === false), NON aggiungere parametri Premium
        // Se l'API restituisce comunque errore "deleghe", potrebbe essere un problema di permessi/configurazione account
        
        // Converti date in formato ISO 8601 se presenti
        if (isset($params['startDate']) && !empty($params['startDate'])) {
            $params['startDate'] = date('c', strtotime($params['startDate']));
        }
        if (isset($params['endDate']) && !empty($params['endDate'])) {
            $params['endDate'] = date('c', strtotime($params['endDate']));
        }
        
        // Rimuovi parametri null/vuoti per evitare query string sporca
        $params = array_filter($params, function($value) {
            return $value !== null && $value !== '';
        });
        
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
        
        // Gestione errori specifici
        $error_msg = $body['errorDescription'] ?? $body['errorCode'] ?? $body['message'] ?? 'Errore sconosciuto';
        
        // Se errore 400 o "Errore deleghe utente", gestisci in base al tipo di account
        if ($code === 400 || (is_string($error_msg) && stripos($error_msg, 'deleghe') !== false)) {
            // Verifica se è Premium per dare un messaggio più preciso
            $is_premium = $this->is_premium_user();
            
            if ($is_premium === true) {
                // Account Premium: richiede parametri
                $error_msg = 'Errore deleghe utente: Account Premium rilevato ma parametri countrySender/vatcodeSender mancanti o non validi. ' . 
                            'SOLUZIONE: Vai in Impostazioni → FP Finance Hub → Impostazioni → Sezione "Integrazione Aruba" → ' .
                            '"Parametri Premium (Opzionali)" e inserisci manualmente Codice Paese (es: IT) e Partita IVA completa. ' .
                            'Dettaglio API: ' . $error_msg;
            } elseif ($is_premium === false) {
                // Account base: errore "deleghe" indica che l'account base non può usare le API direttamente
                // Secondo documentazione Aruba: "I Web Services sono a disposizione delle utenze Premium o utenze base a loro collegate mediante delega"
                $error_msg = 'Errore deleghe utente: Gli account base Aruba non possono accedere direttamente alle API Web Service. ' .
                            'Secondo la documentazione Aruba, i Web Services sono disponibili solo per: ' .
                            '1) Utenti Premium, oppure 2) Account base collegati a un account Premium tramite delega. ' .
                            'SOLUZIONE: Per utilizzare le API con un account base, è necessario: ' .
                            'a) Passare a un account Premium Aruba, oppure b) Collegare l\'account base a un account Premium tramite delega ' .
                            '(sezione "Deleghe" nel pannello Aruba). ' .
                            'Dettaglio API: ' . $error_msg;
            } else {
                // Impossibile determinare (errore nel controllo)
                $error_msg = 'Errore deleghe utente: ' . $error_msg . 
                            ' Se hai un account Premium, inserisci manualmente Codice Paese e Partita IVA nelle Impostazioni Aruba (Parametri Premium). ' .
                            'Se hai un account base, ricorda che gli account base non possono usare le API direttamente: ' .
                            'è necessario un account Premium o una delega a un account Premium.';
            }
        }
        
        return new \WP_Error('api_error', 'Errore API Aruba findByUsername: ' . $error_msg, ['http_code' => $code]);
    }
    
    /**
     * Ricerca fatture ricevute (GET /services/invoice/in/findByUsername)
     * 
     * Secondo documentazione: GET /services/invoice/in/findByUsername
     * 
     * NOTA: Per utenze Premium, i parametri countryReceiver e vatcodeReceiver sono OBBLIGATORI
     * 
     * @param array $filters Filtri ricerca (startDate, endDate, page, size, countryReceiver, vatcodeReceiver, etc.)
     * @return array Lista fatture ricevute con paginazione
     */
    public function find_received_invoices($filters = []) {
        $auth_result = $this->authenticate();
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        
        $params = array_merge([
            'username' => $this->username,
            'page' => 1,
            'size' => 100,
        ], $filters);
        
        // Verifica se l'utente è Premium (solo per utenti Premium servono countryReceiver/vatcodeReceiver)
        // IMPORTANTE: Per account base, NON aggiungere questi parametri
        $is_premium = $this->is_premium_user();
        
        // Solo se è CERTAMENTE Premium (bool true), gestisci i parametri
        if ($is_premium === true && (empty($params['countryReceiver']) || empty($params['vatcodeReceiver']))) {
            $premium_params = $this->get_premium_params();
            if (!is_wp_error($premium_params)) {
                if (empty($params['countryReceiver']) && !empty($premium_params['countrySender'])) {
                    $params['countryReceiver'] = $premium_params['countrySender'];
                }
                if (empty($params['vatcodeReceiver']) && !empty($premium_params['vatcodeSender'])) {
                    $params['vatcodeReceiver'] = $premium_params['vatcodeSender'];
                }
            }
        }
        
        // Per account base (is_premium === false), NON aggiungere parametri Premium
        
        // Converti date in formato ISO 8601 se presenti
        if (isset($params['startDate']) && !empty($params['startDate'])) {
            $params['startDate'] = date('c', strtotime($params['startDate']));
        }
        if (isset($params['endDate']) && !empty($params['endDate'])) {
            $params['endDate'] = date('c', strtotime($params['endDate']));
        }
        
        // Rimuovi parametri null/vuoti
        $params = array_filter($params, function($value) {
            return $value !== null && $value !== '';
        });
        
        $url = $this->api_base_url . '/services/invoice/in/findByUsername?' . http_build_query($params);
        
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
            return $body['content'];
        }
        
        // Se token scaduto, prova refresh
        if ($code === 401) {
            $this->refresh_token();
            return $this->find_received_invoices($filters);
        }
        
        $error_msg = $body['errorDescription'] ?? $body['errorCode'] ?? $body['message'] ?? 'Errore sconosciuto';
        
        // Se errore 400 o "Errore deleghe utente", gestisci in base al tipo di account
        if ($code === 400 || (is_string($error_msg) && stripos($error_msg, 'deleghe') !== false)) {
            // Verifica se è Premium per dare un messaggio più preciso
            $is_premium = $this->is_premium_user();
            
            if ($is_premium === true) {
                // Account Premium: richiede parametri
                $error_msg = 'Errore deleghe utente: Account Premium rilevato ma parametri countryReceiver/vatcodeReceiver mancanti o non validi. ' . 
                            'SOLUZIONE: Vai in Impostazioni → FP Finance Hub → Impostazioni → Sezione "Integrazione Aruba" → ' .
                            '"Parametri Premium (Opzionali)" e inserisci manualmente Codice Paese (es: IT) e Partita IVA completa. ' .
                            'Dettaglio API: ' . $error_msg;
            } elseif ($is_premium === false) {
                // Account base: errore "deleghe" indica che l'account base non può usare le API direttamente
                // Secondo documentazione Aruba: "I Web Services sono a disposizione delle utenze Premium o utenze base a loro collegate mediante delega"
                $error_msg = 'Errore deleghe utente: Gli account base Aruba non possono accedere direttamente alle API Web Service. ' .
                            'Secondo la documentazione Aruba, i Web Services sono disponibili solo per: ' .
                            '1) Utenti Premium, oppure 2) Account base collegati a un account Premium tramite delega. ' .
                            'SOLUZIONE: Per utilizzare le API con un account base, è necessario: ' .
                            'a) Passare a un account Premium Aruba, oppure b) Collegare l\'account base a un account Premium tramite delega ' .
                            '(sezione "Deleghe" nel pannello Aruba). ' .
                            'Dettaglio API: ' . $error_msg;
            } else {
                // Impossibile determinare
                $error_msg = 'Errore deleghe utente: ' . $error_msg . 
                            ' Se hai un account Premium, inserisci manualmente Codice Paese e Partita IVA nelle Impostazioni Aruba (Parametri Premium). ' .
                            'Se hai un account base, contatta il supporto Aruba (assistenza@aruba.it) per verificare i permessi API.';
            }
        }
        
        return new \WP_Error('api_error', 'Errore API Aruba findReceivedInvoices: ' . $error_msg, ['http_code' => $code]);
    }
    
    /**
     * Ottieni lista multicedenti (GET /auth/multicedenti)
     * 
     * Disponibile solo per utenze Premium
     * 
     * @param array $filters Filtri (countryCode, vatCode, status, page, size)
     * @return array Lista multicedenti
     */
    public function get_multicedenti($filters = []) {
        $auth_result = $this->authenticate();
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        
        $params = array_merge([
            'page' => 1,
            'size' => 10,
        ], $filters);
        
        $params = array_filter($params, function($value) {
            return $value !== null && $value !== '';
        });
        
        $url = $this->auth_base_url . '/auth/multicedenti?' . http_build_query($params);
        
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
            return $body;
        }
        
        if ($code === 401) {
            $this->refresh_token();
            return $this->get_multicedenti($filters);
        }
        
        $error_msg = $body['errorDescription'] ?? $body['errorCode'] ?? 'Errore sconosciuto';
        return new \WP_Error('api_error', 'Errore API Aruba multicedenti: ' . $error_msg, ['http_code' => $code]);
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
        return new \WP_Error('api_error', 'Errore API Aruba getInvoice: ' . $error_msg, ['http_code' => $code]);
    }
    
    /**
     * Ottieni fattura per filename (GET /services/invoice/out/getByFilename)
     * 
     * @param string $filename Nome file fattura (es. IT01879020517_abcde.xml.p7m)
     * @param bool $include_file Se true, include il file XML in base64
     * @param bool $include_pdf Se true, include anche il PDF
     * @return array Dettagli fattura
     */
    public function get_invoice_by_filename($filename, $include_file = true, $include_pdf = false) {
        $auth_result = $this->authenticate();
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        
        $query_params = [
            'filename' => $filename,
            'includeFile' => $include_file ? 'true' : 'false',
        ];
        
        if ($include_pdf) {
            $query_params['includePdf'] = 'true';
        }
        
        $url = $this->api_base_url . '/services/invoice/out/getByFilename?' . http_build_query($query_params);
        
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
            return $this->get_invoice_by_filename($filename, $include_file, $include_pdf);
        }
        
        $error_msg = $body['errorDescription'] ?? $body['errorCode'] ?? 'Errore sconosciuto';
        return new \WP_Error('api_error', 'Errore API Aruba getByFilename: ' . $error_msg, ['http_code' => $code]);
    }
    
    /**
     * Ottieni fattura ricevuta per filename (GET /services/invoice/in/getByFilename)
     * 
     * @param string $filename Nome file fattura
     * @param bool $include_file Se true, include il file XML in base64
     * @param bool $include_pdf Se true, include anche il PDF
     * @return array Dettagli fattura ricevuta
     */
    public function get_received_invoice_by_filename($filename, $include_file = true, $include_pdf = false) {
        $auth_result = $this->authenticate();
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        
        $query_params = [
            'filename' => $filename,
            'includeFile' => $include_file ? 'true' : 'false',
        ];
        
        if ($include_pdf) {
            $query_params['includePdf'] = 'true';
        }
        
        $url = $this->api_base_url . '/services/invoice/in/getByFilename?' . http_build_query($query_params);
        
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
        
        if ($code === 401) {
            $this->refresh_token();
            return $this->get_received_invoice_by_filename($filename, $include_file, $include_pdf);
        }
        
        $error_msg = $body['errorDescription'] ?? $body['errorCode'] ?? 'Errore sconosciuto';
        return new \WP_Error('api_error', 'Errore API Aruba getReceivedInvoiceByFilename: ' . $error_msg, ['http_code' => $code]);
    }
    
    /**
     * Verifica se l'utente è Premium (ha multicedenti)
     * 
     * @return bool|WP_Error true se Premium, false se base, WP_Error in caso di errore
     */
    public function is_premium_user() {
        // Prova a recuperare multicedenti (solo disponibile per Premium)
        $multicedenti = $this->get_multicedenti(['size' => 1]);
        
        if (is_wp_error($multicedenti)) {
            // Se errore 403/404, sicuramente NON è Premium (endpoint non disponibile)
            $error_data = $multicedenti->get_error_data();
            if (isset($error_data['http_code']) && in_array($error_data['http_code'], [403, 404])) {
                return false; // Account base
            }
            // Altri errori (es: 401, 500) potrebbero essere temporanei, restituisci errore
            return $multicedenti;
        }
        
        // Se ha multicedenti, è Premium
        // Se l'array è vuoto o non ha content, è account base
        if (isset($multicedenti['content']) && is_array($multicedenti['content']) && count($multicedenti['content']) > 0) {
            return true; // Account Premium
        }
        
        return false; // Account base (nessun multicedente)
    }
    
    /**
     * Recupera parametri Premium (countrySender, vatcodeSender) 
     * 
     * Ordine di priorità:
     * 1. Impostazioni manuali (se configurate)
     * 2. userInfo (vatCode)
     * 3. multicedenti (primo elemento)
     * 
     * @return array|WP_Error Array con countrySender e vatcodeSender, o WP_Error
     */
    private function get_premium_params() {
        // 1. Prova prima dalle impostazioni manuali (priorità massima)
        $manual_country = get_option('fp_finance_hub_aruba_country_sender', '');
        $manual_vatcode = get_option('fp_finance_hub_aruba_vatcode_sender', '');
        
        if (!empty($manual_country) && !empty($manual_vatcode)) {
            return [
                'countrySender' => strtoupper($manual_country),
                'vatcodeSender' => $manual_vatcode,
            ];
        }
        
        // 2. Prova a recuperare da userInfo
        $user_info = $this->get_user_info();
        if (!is_wp_error($user_info)) {
            $vat_code = $user_info['vatCode'] ?? null;
            if ($vat_code) {
                // Per il paese, prova a dedurlo dal VAT code o usa default IT
                // I VAT code italiani iniziano con IT
                $country = 'IT'; // Default Italia
                if (preg_match('/^([A-Z]{2})/', $vat_code, $matches)) {
                    $country = $matches[1];
                }
                
                // Se manca il paese manuale, usa quello dedotto
                if (empty($manual_country)) {
                    $manual_country = $country;
                }
                
                // Se manca il VAT code manuale, usa quello da userInfo
                if (empty($manual_vatcode)) {
                    $manual_vatcode = $vat_code;
                }
                
                if (!empty($manual_country) && !empty($manual_vatcode)) {
                    return [
                        'countrySender' => strtoupper($manual_country),
                        'vatcodeSender' => $manual_vatcode,
                    ];
                }
            }
        }
        
        // 3. Se non disponibile da userInfo, prova multicedenti (primo elemento)
        $multicedenti = $this->get_multicedenti(['size' => 1]);
        if (!is_wp_error($multicedenti) && isset($multicedenti['content']) && count($multicedenti['content']) > 0) {
            $first = $multicedenti['content'][0];
            if (isset($first['countryCode']) && isset($first['vatCode'])) {
                $country = !empty($manual_country) ? $manual_country : $first['countryCode'];
                $vatcode = !empty($manual_vatcode) ? $manual_vatcode : $first['vatCode'];
                
                return [
                    'countrySender' => strtoupper($country),
                    'vatcodeSender' => $vatcode,
                ];
            }
        }
        
        // Se non disponibile, restituisci errore con suggerimento
        return new \WP_Error(
            'premium_params_missing', 
            'Impossibile recuperare countrySender e vatcodeSender automaticamente. ' .
            'Per utenti Premium, inserisci manualmente questi valori nelle Impostazioni Aruba (Parametri Premium).'
        );
    }
    
    /**
     * Test connessione
     */
    public function test_connection() {
        $user_info = $this->get_user_info();
        
        if (is_wp_error($user_info)) {
            // Se userInfo fallisce, prova comunque a vedere se l'autenticazione funziona
            $auth_result = $this->authenticate();
            if (is_wp_error($auth_result)) {
                return $user_info; // Restituisci l'errore originale
            }
            
            // Autenticazione OK ma userInfo non disponibile
            return [
                'success' => true,
                'username' => null,
                'pec' => null,
                'userDescription' => null,
                'vatCode' => null,
                'fiscalCode' => null,
                'accountStatus' => null,
                'usageStatus' => null,
                'isPremium' => null,
                'warning' => 'Autenticazione riuscita ma impossibile recuperare informazioni utente. ' .
                            'Verifica i permessi dell\'account Aruba o contatta il supporto.',
                'raw_response' => $user_info->get_error_message(),
            ];
        }
        
        // Verifica se è Premium (opzionale, non blocca il test)
        $is_premium = $this->is_premium_user();
        $premium_status = null;
        if (is_bool($is_premium)) {
            $premium_status = $is_premium;
        }
        
        // Se userInfo è vuoto o non contiene dati, potrebbe essere un problema di permessi
        $has_data = !empty($user_info) && (isset($user_info['username']) || isset($user_info['vatCode']));
        
        $result = [
            'success' => true,
            'username' => $user_info['username'] ?? null,
            'pec' => $user_info['pec'] ?? null,
            'userDescription' => $user_info['userDescription'] ?? null,
            'vatCode' => $user_info['vatCode'] ?? null,
            'fiscalCode' => $user_info['fiscalCode'] ?? null,
            'accountStatus' => $user_info['accountStatus'] ?? null,
            'usageStatus' => $user_info['usageStatus'] ?? null,
            'isPremium' => $premium_status,
        ];
        
        // Se non ci sono dati e l'utente è Premium, suggerisci di inserire manualmente i parametri
        if (!$has_data && $premium_status === true) {
            $result['warning'] = 'Account Premium rilevato ma informazioni utente non disponibili. ' .
                                'Inserisci manualmente Codice Paese e Partita IVA nelle Impostazioni Aruba (Parametri Premium).';
        } elseif (!$has_data) {
            $result['warning'] = 'Informazioni utente non disponibili. ' .
                                'Se hai un account Premium e ricevi errori, inserisci manualmente Codice Paese e Partita IVA nelle Impostazioni.';
        }
        
        return $result;
    }
}
