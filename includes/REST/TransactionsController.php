<?php
namespace FP\FinanceHub\REST;
use FP\FinanceHub\Services\BankService;
if (!defined('ABSPATH')) exit;

class TransactionsController extends Controller {
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
        register_rest_route(self::NAMESPACE, '/transactions', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_transactions'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    public function get_transactions($request) {
        $bank_service = BankService::get_instance();
        $account_id = absint($request->get_param('account_id'));
        
        if (!$account_id) {
            return new \WP_Error('missing_account_id', 'Account ID richiesto', ['status' => 400]);
        }
        
        $transactions = $bank_service->get_transactions($account_id, [
            'start_date' => sanitize_text_field($request->get_param('start_date') ?: ''),
            'end_date' => sanitize_text_field($request->get_param('end_date') ?: ''),
        ]);
        
        return new \WP_REST_Response($transactions, 200);
    }
}
