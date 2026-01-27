<?php
/**
 * Gestione disattivazione plugin
 */

namespace FP\FinanceHub;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivation {
    
    /**
     * Hook disattivazione
     */
    public static function deactivate() {
        // Chiama metodo deactivate della classe Plugin
        Plugin::deactivate();
    }
}
