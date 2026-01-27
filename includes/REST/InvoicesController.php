<?php
/**
 * Invoices REST Controller
 */

namespace FP\FinanceHub\REST;

use FP\FinanceHub\Services\InvoiceService;

if (!defined('ABSPATH')) {
    exit;
}

class InvoicesController extends Controller {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/invoices', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_invoices'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    public function get_invoices($request) {
        $invoice_service = InvoiceService::get_instance();
        $invoices = $invoice_service->get_unpaid();
        
        return new \WP_REST_Response($invoices, 200);
    }
}
