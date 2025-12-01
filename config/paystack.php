<?php
/**
 * Paystack Payment Gateway Configuration
 * 
 * Get your API keys from: https://dashboard.paystack.com/#/settings/developer
 */

class PaystackConfig {
    // Test mode keys - Replace with live keys in production
    const TEST_PUBLIC_KEY = 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    const TEST_SECRET_KEY = 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    
    // Live mode keys - Use in production
    const LIVE_PUBLIC_KEY = 'pk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    const LIVE_SECRET_KEY = 'sk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    
    // Set to false in production
    const TEST_MODE = true;
    
    // API Base URL
    const API_BASE_URL = 'https://api.paystack.co';
    
    /**
     * Get the appropriate public key based on mode
     */
    public static function getPublicKey() {
        return self::TEST_MODE ? self::TEST_PUBLIC_KEY : self::LIVE_PUBLIC_KEY;
    }
    
    /**
     * Get the appropriate secret key based on mode
     */
    public static function getSecretKey() {
        return self::TEST_MODE ? self::TEST_SECRET_KEY : self::LIVE_SECRET_KEY;
    }
    
    /**
     * Get API base URL
     */
    public static function getApiBaseUrl() {
        return self::API_BASE_URL;
    }
    
    /**
     * Check if in test mode
     */
    public static function isTestMode() {
        return self::TEST_MODE;
    }
}
?>