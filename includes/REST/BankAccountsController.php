<?php
/**
 * Bank Accounts REST Controller
 */

namespace FP\FinanceHub\REST;

use FP\FinanceHub\Services\BankService;

if (!defined('ABSPATH')) {
    exit;
}

class BankAccountsController extends Controller {
    
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
        register_rest_route(self::NAMESPACE, '/bank-accounts', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_accounts'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    public function get_accounts($request) {
        $bank_service = BankService::get_instance();
        $accounts = $bank_service->get_active_accounts();
        $total = $bank_service->get_total_balance();
        
        return new \WP_REST_Response([
            'accounts' => $accounts,
            'total_balance' => $total,
        ], 200);
    }
}
