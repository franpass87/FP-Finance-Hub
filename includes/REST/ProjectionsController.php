<?php
namespace FP\FinanceHub\REST;
use FP\FinanceHub\Services\ProjectionService;
if (!defined('ABSPATH')) exit;

class ProjectionsController extends Controller {
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
        register_rest_route(self::NAMESPACE, '/projections', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_projections'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    public function get_projections($request) {
        $projection_service = ProjectionService::get_instance();
        $month = absint($request->get_param('month') ?: date('n'));
        $year = absint($request->get_param('year') ?: date('Y'));
        
        $projections = $projection_service->calculate_income_projections($month, $year);
        
        return new \WP_REST_Response($projections, 200);
    }
}
