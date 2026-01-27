<?php
/**
 * Clients REST Controller
 * 
 * API REST per gestione clienti
 */

namespace FP\FinanceHub\REST;

use FP\FinanceHub\Services\ClientService;

if (!defined('ABSPATH')) {
    exit;
}

class ClientsController extends Controller {
    
    private static $instance = null;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Registra routes REST
     */
    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/clients', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_clients'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_client'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/clients/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_client'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_client'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_client'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }
    
    /**
     * GET /clients
     */
    public function get_clients($request) {
        $client_service = ClientService::get_instance();
        
        $args = [
            'page' => absint($request->get_param('page') ?: 1),
            'per_page' => absint($request->get_param('per_page') ?: 20),
            'search' => sanitize_text_field($request->get_param('search') ?: ''),
        ];
        
        $clients = $client_service->get_all($args);
        $total = \FP\FinanceHub\Database\Models\Client::count($args);
        
        return new \WP_REST_Response([
            'clients' => $clients,
            'total' => $total,
        ], 200);
    }
    
    /**
     * GET /clients/{id}
     */
    public function get_client($request) {
        $client_service = ClientService::get_instance();
        $id = absint($request->get_param('id'));
        $client = $client_service->get($id);
        
        if (!$client) {
            return new \WP_Error('not_found', 'Cliente non trovato', ['status' => 404]);
        }
        
        return new \WP_REST_Response($client, 200);
    }
    
    /**
     * POST /clients
     */
    public function create_client($request) {
        $client_service = ClientService::get_instance();
        $data = $request->get_json_params();
        
        $result = $client_service->create($data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response([
            'id' => $result,
            'message' => 'Cliente creato con successo',
        ], 201);
    }
    
    /**
     * PUT /clients/{id}
     */
    public function update_client($request) {
        $client_service = ClientService::get_instance();
        $id = absint($request->get_param('id'));
        $data = $request->get_json_params();
        
        $result = $client_service->update($id, $data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response([
            'message' => 'Cliente aggiornato con successo',
        ], 200);
    }
    
    /**
     * DELETE /clients/{id}
     */
    public function delete_client($request) {
        $client_service = ClientService::get_instance();
        $id = absint($request->get_param('id'));
        
        $result = $client_service->delete($id);
        
        if (!$result) {
            return new \WP_Error('delete_failed', 'Errore durante eliminazione', ['status' => 500]);
        }
        
        return new \WP_REST_Response([
            'message' => 'Cliente eliminato con successo',
        ], 200);
    }
}
