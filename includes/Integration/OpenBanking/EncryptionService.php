<?php
/**
 * Encryption Service
 * 
 * Criptazione token per Open Banking (AES-256)
 */

namespace FP\FinanceHub\Integration\OpenBanking;

if (!defined('ABSPATH')) {
    exit;
}

class EncryptionService {
    
    private static $key = null;
    
    /**
     * Ottieni chiave criptazione
     */
    private static function get_key() {
        if (!self::$key) {
            // Usa opzione WordPress o genera se non esiste
            self::$key = get_option('fp_finance_hub_encryption_key');
            if (!self::$key || strlen(self::$key) < 32) {
                self::$key = wp_generate_password(32, false);
                update_option('fp_finance_hub_encryption_key', self::$key);
            }
        }
        return self::$key;
    }
    
    /**
     * Cripta token
     */
    public static function encrypt($plaintext) {
        if (empty($plaintext)) {
            return '';
        }
        
        $key = self::get_key();
        $method = 'AES-256-CBC';
        
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt($plaintext, $method, $key, 0, $iv);
        
        if ($encrypted === false) {
            return new \WP_Error('encryption_failed', 'Errore durante criptazione');
        }
        
        // Combina IV + encrypted data
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decripta token
     */
    public static function decrypt($ciphertext) {
        if (empty($ciphertext)) {
            return '';
        }
        
        $key = self::get_key();
        $method = 'AES-256-CBC';
        
        $data = base64_decode($ciphertext);
        if ($data === false) {
            return new \WP_Error('decryption_failed', 'Errore durante decriptazione');
        }
        
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);
        
        if ($decrypted === false) {
            return new \WP_Error('decryption_failed', 'Errore durante decriptazione');
        }
        
        return $decrypted;
    }
}
