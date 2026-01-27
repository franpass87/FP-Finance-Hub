<?php
namespace FP\FinanceHub\REST;
use FP\FinanceHub\Services\StatsService;
if (!defined('ABSPATH')) exit;

class StatsController extends Controller {
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
        register_rest_route(self::NAMESPACE, '/stats/trend', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_trend'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    public function get_trend($request) {
        $stats_service = StatsService::get_instance();
        $account_id = absint($request->get_param('account_id') ?: 0);
        $type = sanitize_text_field($request->get_param('type') ?: 'income');
        
        $trend = $stats_service->calculate_trend_12_months($account_id, $type);
        
        return new \WP_REST_Response($trend, 200);
    }
}
