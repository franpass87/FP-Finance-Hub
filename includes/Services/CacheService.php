<?php
/**
 * Cache Service
 * 
 * Gestisce cache query database e risultati per ottimizzazione performance
 */

namespace FP\FinanceHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

class CacheService {
    
    private static $instance = null;
    
    const CACHE_GROUP = 'fp_finance_hub';
    const DEFAULT_TTL = 3600; // 1 ora
    
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
     * Ottieni valore dalla cache
     * 
     * @param string $key Chiave cache
     * @param mixed $default Valore default se non trovato
     * @return mixed
     */
    public function get($key, $default = false) {
        $cache_key = $this->get_cache_key($key);
        
        // Prova prima con object cache (se disponibile)
        $value = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($value !== false) {
            return $value;
        }
        
        // Fallback a transient
        $value = get_transient($cache_key);
        if ($value !== false) {
            // Ripopola object cache
            wp_cache_set($cache_key, $value, self::CACHE_GROUP, self::DEFAULT_TTL);
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Salva valore in cache
     * 
     * @param string $key Chiave cache
     * @param mixed $value Valore da salvare
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = self::DEFAULT_TTL;
        }
        
        $cache_key = $this->get_cache_key($key);
        
        // Salva in object cache
        $result1 = wp_cache_set($cache_key, $value, self::CACHE_GROUP, $ttl);
        
        // Salva anche in transient come backup
        $result2 = set_transient($cache_key, $value, $ttl);
        
        return $result1 && $result2;
    }
    
    /**
     * Rimuovi valore dalla cache
     * 
     * @param string $key Chiave cache
     * @return bool
     */
    public function delete($key) {
        $cache_key = $this->get_cache_key($key);
        
        wp_cache_delete($cache_key, self::CACHE_GROUP);
        delete_transient($cache_key);
        
        return true;
    }
    
    /**
     * Pulisci tutta la cache del plugin
     * 
     * @return bool
     */
    public function flush() {
        global $wpdb;
        
        // Pulisci transients del plugin
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            OR option_name LIKE %s",
            '_transient_fp_finance_hub_%',
            '_transient_timeout_fp_finance_hub_%'
        ));
        
        // Pulisci object cache
        wp_cache_flush_group(self::CACHE_GROUP);
        
        return true;
    }
    
    /**
     * Cache query database con risultato
     * 
     * @param string $query_key Chiave univoca per la query
     * @param callable $callback Funzione che esegue la query
     * @param int $ttl Time to live
     * @return mixed Risultato query
     */
    public function remember($query_key, callable $callback, $ttl = null) {
        $cached = $this->get($query_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $result = $callback();
        $this->set($query_key, $result, $ttl);
        
        return $result;
    }
    
    /**
     * Genera chiave cache
     * 
     * @param string $key Chiave base
     * @return string Chiave completa
     */
    private function get_cache_key($key) {
        return 'fp_finance_hub_' . md5($key);
    }
    
    /**
     * Invalida cache per pattern
     * 
     * @param string $pattern Pattern da cercare (es. 'clients_*')
     * @return int Numero di cache invalidate
     */
    public function invalidate_pattern($pattern) {
        global $wpdb;
        
        $pattern_escaped = str_replace('*', '%', $wpdb->esc_like($pattern));
        $cache_key_pattern = $this->get_cache_key($pattern_escaped);
        
        // Pulisci transients
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            OR option_name LIKE %s",
            '_transient_' . $cache_key_pattern,
            '_transient_timeout_' . $cache_key_pattern
        ));
        
        return $deleted;
    }
}
