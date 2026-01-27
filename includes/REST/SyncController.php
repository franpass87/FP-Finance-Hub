<?php
/**
 * Sync REST Controller
 * 
 * API REST per sincronizzazioni
 */

namespace FP\FinanceHub\REST;

use FP\FinanceHub\Integration\Aruba\ArubaSync;
use FP\FinanceHub\Integration\OpenBanking\NordigenSyncService;
use FP\FinanceHub\Integration\PublisherSync;
use FP\FinanceHub\Integration\TaskAgendaSync;
use FP\FinanceHub\Integration\DMSSync;

if (!defined('ABSPATH')) {
    exit;
}

class SyncController extends Controller {
    
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
        register_rest_route(self::NAMESPACE, '/sync/aruba', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'sync_aruba'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/sync/nordigen', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'sync_nordigen'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/sync/publisher', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'sync_publisher'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/sync/task-agenda', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'sync_task_agenda'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/sync/dms', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'sync_dms'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    /**
     * POST /sync/aruba
     */
    public function sync_aruba($request) {
        $aruba_sync = new ArubaSync();
        $result = $aruba_sync->sync_invoices();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response($result, 200);
    }
    
    /**
     * POST /sync/nordigen
     */
    public function sync_nordigen($request) {
        $nordigen_sync = new NordigenSyncService();
        $nordigen_sync->sync_all_accounts();
        
        return new \WP_REST_Response(['message' => 'Sync avviata'], 200);
    }
    
    /**
     * POST /sync/publisher
     */
    public function sync_publisher($request) {
        $publisher_sync = new PublisherSync();
        $result = $publisher_sync->sync_to_publisher();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response($result, 200);
    }
    
    /**
     * POST /sync/task-agenda
     */
    public function sync_task_agenda($request) {
        $task_agenda_sync = new TaskAgendaSync();
        $result = $task_agenda_sync->sync_to_task_agenda();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response($result, 200);
    }
    
    /**
     * POST /sync/dms
     */
    public function sync_dms($request) {
        $dms_sync = new DMSSync();
        $result = $dms_sync->sync_to_dms();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response($result, 200);
    }
}
