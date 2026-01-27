<?php
/**
 * Yapily Service
 * 
 * Client API Yapily per Open Banking
 */

namespace FP\FinanceHub\Integration\OpenBanking;

use FP\FinanceHub\Integration\OpenBanking\EncryptionService;

if (!defined('ABSPATH')) {
    exit;
}

class YapilyService {
    
    private $app_id;
    private $app_secret;
    private $base_url = 'https://api.yapily.com';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->app_id = get_option('fp_finance_hub_yapily_app_id', '');
        $this->app_secret = get_option('fp_finance_hub_yapily_app_secret', '');
    }
    
    /**
     * Ottieni header autenticazione HTTP Basic Auth
     */
    private function get_auth_headers() {
        $credentials = base64_encode($this->app_id . ':' . $this->app_secret);
        return [
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/json',
        ];
    }
    
    /**
     * Esegui richiesta API
     */
    private function api_request($method, $endpoint, $body = null) {
        $url = $this->base_url . $endpoint;
        $headers = $this->get_auth_headers();
        
        $args = [
            'headers' => $headers,
            'timeout' => 30,
            'method' => $method,
        ];
        
        if ($body !== null) {
            $args['body'] = json_encode($body);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code >= 200 && $code < 300) {
            return $response_body;
        }
        
        // Estrai messaggio di errore più dettagliato
        $error_message = 'Errore sconosciuto';
        if (isset($response_body['message'])) {
            $error_message = $response_body['message'];
        } elseif (isset($response_body['error'])) {
            $error_message = is_array($response_body['error']) ? json_encode($response_body['error']) : $response_body['error'];
        } elseif (isset($response_body['errors']) && is_array($response_body['errors'])) {
            $errors = array_map(function($err) {
                return isset($err['message']) ? $err['message'] : json_encode($err);
            }, $response_body['errors']);
            $error_message = implode(', ', $errors);
        } elseif (isset($response_body['data']['message'])) {
            $error_message = $response_body['data']['message'];
        }
        
        // Aggiungi codice HTTP al messaggio
        $full_message = sprintf('Errore API Yapily (HTTP %d): %s', $code, $error_message);
        
        return new \WP_Error('api_error', $full_message, ['code' => $code, 'response' => $response_body]);
    }
    
    /**
     * Verifica se le credenziali sono configurate
     */
    public function is_configured() {
        return !empty($this->app_id) && !empty($this->app_secret);
    }
    
    /**
     * Test connessione API Yapily
     * Restituisce true se le credenziali sono valide
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return new \WP_Error('not_configured', 'Credenziali Yapily non configurate.');
        }
        
        // Prova a ottenere le istituzioni per testare la connessione
        $result = $this->api_request('GET', '/institutions?country=IT&limit=1');
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return true;
    }
    
    /**
     * Ottieni lista banche disponibili (Italia)
     */
    public function get_institutions($country = 'IT') {
        // Verifica che le credenziali siano configurate
        if (!$this->is_configured()) {
            return new \WP_Error('not_configured', 'Credenziali Yapily non configurate. Inserisci Application ID e Application Secret nelle Impostazioni.');
        }
        
        // Prova prima senza filtro per vedere se ci sono banche
        // Yapily potrebbe richiedere parametri aggiuntivi o avere un formato diverso
        // In sandbox, potrebbe essere necessario non filtrare per paese
        $endpoint = '/institutions?country=' . urlencode($country);
        $result = $this->api_request('GET', $endpoint);
        
        // Se non ci sono risultati con filtro paese, prova senza filtro (per sandbox)
        if (!is_wp_error($result) && isset($result['data']) && empty($result['data'])) {
            $endpoint_no_country = '/institutions';
            $result_no_country = $this->api_request('GET', $endpoint_no_country);
            
            // Se senza filtro ci sono risultati, usa quelli
            if (!is_wp_error($result_no_country) && isset($result_no_country['data']) && !empty($result_no_country['data'])) {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[FP Finance Hub] Yapily: Nessuna banca trovata con filtro paese, ma trovate ' . count($result_no_country['data']) . ' banche senza filtro');
                }
                $result = $result_no_country;
            }
        }
        
        // Debug: log la risposta se WP_DEBUG è attivo
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[FP Finance Hub] Yapily get_institutions endpoint: ' . $endpoint);
            error_log('[FP Finance Hub] Yapily get_institutions response: ' . print_r($result, true));
        }
        
        if (is_wp_error($result)) {
            // Migliora il messaggio di errore con più dettagli
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            $error_data = $result->get_error_data();
            
            // Se è un errore 401, probabilmente le credenziali sono errate
            if (isset($error_data['code']) && $error_data['code'] == 401) {
                return new \WP_Error('auth_error', 'Credenziali Yapily non valide. Verifica Application ID e Application Secret nelle Impostazioni. Errore: ' . $error_message, $error_data);
            }
            
            // Se è un errore 403, potrebbe essere un problema di permessi
            if (isset($error_data['code']) && $error_data['code'] == 403) {
                return new \WP_Error('permission_error', 'Accesso negato all\'API Yapily. Verifica che l\'account Yapily sia attivo e abbia i permessi necessari. Errore: ' . $error_message, $error_data);
            }
            
            return $result;
        }
        
        // Gestisci diversi formati di risposta Yapily
        $institutions_data = null;
        
        // Formato 1: { data: [...] }
        if (isset($result['data']) && is_array($result['data'])) {
            $institutions_data = $result['data'];
        }
        // Formato 2: array diretto
        elseif (is_array($result) && isset($result[0]) && is_array($result[0])) {
            $institutions_data = $result;
        }
        // Formato 3: { institutions: [...] }
        elseif (isset($result['institutions']) && is_array($result['institutions'])) {
            $institutions_data = $result['institutions'];
        }
        
        if ($institutions_data && is_array($institutions_data) && !empty($institutions_data)) {
            $total_count = count($institutions_data);
            
            // Filtra solo banche che supportano Account Information
            $filtered = array_filter($institutions_data, function($institution) {
                // Verifica se ha features
                $features = $institution['features'] ?? [];
                if (is_array($features)) {
                    return in_array('ACCOUNTS', $features) || in_array('ACCOUNT_TRANSACTIONS', $features);
                }
                // Se non ha features, includila comunque (potrebbe essere che Yapily non le specifichi sempre)
                return true;
            });
            $filtered_count = count($filtered);
            
            // Se dopo il filtro non ci sono banche, restituisci tutte le banche (il problema potrebbe essere nel filtro)
            if ($filtered_count === 0 && $total_count > 0) {
                // Log per debug (solo se WP_DEBUG è attivo)
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[FP Finance Hub] Yapily: ' . $total_count . ' banche trovate, ma nessuna passa il filtro ACCOUNTS/ACCOUNT_TRANSACTIONS');
                    error_log('[FP Finance Hub] Yapily: Prima banca esempio: ' . print_r($institutions_data[0], true));
                }
                // Restituisci tutte le banche invece di filtrarle
                return array_values($institutions_data);
            }
            
            return array_values($filtered);
        }
        
        // Se non ci sono dati, potrebbe essere un problema con la risposta
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[FP Finance Hub] Yapily API Response (no data): ' . print_r($result, true));
        }
        
        return new \WP_Error('no_institutions', 'Nessuna banca disponibile dalla risposta API Yapily. Verifica che l\'account Yapily sia attivo su console.yapily.com e che abbia accesso alle banche italiane. Risposta ricevuta: ' . json_encode($result));
    }
    
    /**
     * Crea consent request per collegamento conto
     */
    public function create_consent($institution_id, $redirect_uri, $callback_uri = null) {
        $user_uuid = 'wp_fp_finance_hub_' . get_current_user_id() . '_' . time();
        
        $body = [
            'institutionId' => $institution_id,
            'callback' => $callback_uri ?: $redirect_uri,
            'redirect' => [
                'url' => $redirect_uri,
            ],
            'userUuid' => $user_uuid,
            'applicationUserId' => 'user_' . get_current_user_id(),
        ];
        
        $result = $this->api_request('POST', '/account-auth-requests', $body);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        if (isset($result['data'])) {
            $data = $result['data'];
            
            // Salva consent_id per dopo
            if (isset($data['id'])) {
                set_transient('fp_finance_hub_yapily_consent_' . get_current_user_id(), $data['id'], HOUR_IN_SECONDS);
            }
            
            if (isset($data['authorisationUrl'])) {
                return [
                    'url' => $data['authorisationUrl'],
                    'consent_id' => $data['id'] ?? null,
                ];
            }
        }
        
        return new \WP_Error('api_error', 'Errore creazione consent Yapily: URL autorizzazione non trovato');
    }
    
    /**
     * Ottieni stato consent
     */
    public function get_consent($consent_id) {
        $result = $this->api_request('GET', '/consents/' . $consent_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return isset($result['data']) ? $result['data'] : $result;
    }
    
    /**
     * Ottieni conti collegati da consent_id
     */
    public function get_accounts($consent_id) {
        $result = $this->api_request('GET', '/accounts?consent=' . $consent_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        if (isset($result['data']) && isset($result['data']['accounts'])) {
            return $result['data']['accounts'];
        }
        
        return [];
    }
    
    /**
     * Ottieni dettagli conto
     */
    public function get_account_details($account_id, $consent_id) {
        $result = $this->api_request('GET', '/accounts/' . $account_id . '?consent=' . $consent_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return isset($result['data']) ? $result['data'] : $result;
    }
    
    /**
     * Ottieni saldo conto
     */
    public function get_balance($account_id, $consent_id) {
        $result = $this->api_request('GET', '/accounts/' . $account_id . '/balances?consent=' . $consent_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        if (isset($result['data']) && isset($result['data']['balances']) && !empty($result['data']['balances'])) {
            // Prendi il primo balance disponibile
            return $result['data']['balances'][0];
        }
        
        return new \WP_Error('api_error', 'Nessun saldo disponibile');
    }
    
    /**
     * Ottieni movimenti conto
     */
    public function get_transactions($account_id, $consent_id, $date_from = null, $date_to = null) {
        $params = ['consent' => $consent_id];
        
        if ($date_from) {
            $params['from'] = date('Y-m-d', strtotime($date_from));
        }
        if ($date_to) {
            $params['to'] = date('Y-m-d', strtotime($date_to));
        }
        
        $endpoint = '/accounts/' . $account_id . '/transactions?' . http_build_query($params);
        
        $result = $this->api_request('GET', $endpoint);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        if (isset($result['data']) && isset($result['data']['transactions'])) {
            return $result['data']['transactions'];
        }
        
        return [];
    }
}
