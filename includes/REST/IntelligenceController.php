<?php
namespace FP\FinanceHub\REST;

use FP\FinanceHub\Services\Intelligence\IntelligenceAnalysisService;

if (!defined('ABSPATH')) exit;

class IntelligenceController extends Controller {
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
        // Insights completi
        register_rest_route(self::NAMESPACE, '/intelligence/insights', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_insights'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'end_date' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        // Anomalie
        register_rest_route(self::NAMESPACE, '/intelligence/anomalies', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_anomalies'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'end_date' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        // Pattern
        register_rest_route(self::NAMESPACE, '/intelligence/patterns', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_patterns'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'end_date' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        // Raccomandazioni
        register_rest_route(self::NAMESPACE, '/intelligence/recommendations', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_recommendations'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'start_date' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'end_date' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        // Predizioni
        register_rest_route(self::NAMESPACE, '/intelligence/predictions', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_predictions'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'days_ahead' => ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30],
            ],
        ]);
        
        // Report completo (usa cache)
        register_rest_route(self::NAMESPACE, '/intelligence/report', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_report'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'period_days' => ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30],
            ],
        ]);
        
        // Force refresh analisi
        register_rest_route(self::NAMESPACE, '/intelligence/analyze', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'force_analyze'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'period_days' => ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30],
            ],
        ]);
    }
    
    public function get_insights($request) {
        try {
            $intelligence_service = IntelligenceAnalysisService::get_instance();
            
            $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-30 days'));
            $end_date = $request->get_param('end_date') ?: date('Y-m-d');
            
            $insights = $intelligence_service->get_insights($start_date, $end_date);
            
            return new \WP_REST_Response($insights, 200);
        } catch (\Exception $e) {
            return new \WP_Error('intelligence_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    public function get_anomalies($request) {
        try {
            $intelligence_service = IntelligenceAnalysisService::get_instance();
            
            $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-30 days'));
            $end_date = $request->get_param('end_date') ?: date('Y-m-d');
            
            $anomalies = $intelligence_service->get_anomalies($start_date, $end_date);
            
            return new \WP_REST_Response($anomalies, 200);
        } catch (\Exception $e) {
            return new \WP_Error('intelligence_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    public function get_patterns($request) {
        try {
            $intelligence_service = IntelligenceAnalysisService::get_instance();
            
            $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-90 days'));
            $end_date = $request->get_param('end_date') ?: date('Y-m-d');
            
            $patterns = $intelligence_service->get_patterns($start_date, $end_date);
            
            return new \WP_REST_Response($patterns, 200);
        } catch (\Exception $e) {
            return new \WP_Error('intelligence_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    public function get_recommendations($request) {
        try {
            $intelligence_service = IntelligenceAnalysisService::get_instance();
            
            $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-30 days'));
            $end_date = $request->get_param('end_date') ?: date('Y-m-d');
            
            $recommendations = $intelligence_service->get_recommendations($start_date, $end_date);
            
            return new \WP_REST_Response($recommendations, 200);
        } catch (\Exception $e) {
            return new \WP_Error('intelligence_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    public function get_predictions($request) {
        try {
            $intelligence_service = IntelligenceAnalysisService::get_instance();
            
            $days_ahead = absint($request->get_param('days_ahead') ?: 30);
            
            $predictions = $intelligence_service->get_predictions($days_ahead);
            
            return new \WP_REST_Response($predictions, 200);
        } catch (\Exception $e) {
            return new \WP_Error('intelligence_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    public function get_report($request) {
        try {
            $intelligence_service = IntelligenceAnalysisService::get_instance();
            
            $period_days = absint($request->get_param('period_days') ?: 30);
            
            // Usa cache esistente (non force refresh)
            $report = $intelligence_service->generate_intelligence_report($period_days);
            
            return new \WP_REST_Response($report, 200);
        } catch (\Exception $e) {
            return new \WP_Error('intelligence_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    public function force_analyze($request) {
        try {
            $intelligence_service = IntelligenceAnalysisService::get_instance();
            
            $period_days = absint($request->get_param('period_days') ?: 30);
            
            $report = $intelligence_service->force_refresh($period_days);
            
            return new \WP_REST_Response($report, 200);
        } catch (\Exception $e) {
            return new \WP_Error('intelligence_error', $e->getMessage(), ['status' => 500]);
        }
    }
}
