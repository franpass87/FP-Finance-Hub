<?php
/**
 * Gestione attivazione plugin
 */

namespace FP\FinanceHub;

if (!defined('ABSPATH')) {
    exit;
}

class Activation {
    
    /**
     * Hook attivazione
     */
    public static function activate() {
        // Chiama metodo activate della classe Plugin
        Plugin::activate();
    }
}
