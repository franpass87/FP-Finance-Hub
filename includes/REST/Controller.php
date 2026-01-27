<?php
/**
 * REST Controller Base
 * 
 * Classe base per REST API controllers
 */

namespace FP\FinanceHub\REST;

if (!defined('ABSPATH')) {
    exit;
}

class Controller {
    
    const NAMESPACE = 'fp-finance-hub/v1';
    
    /**
     * Verifica permessi utente
     */
    protected function check_permission($capability = 'manage_options') {
        return current_user_can($capability);
    }
}
