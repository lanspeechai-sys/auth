<?php
/**
 * Paystack Payment Handler
 * Handles payment initialization and verification with Paystack
 */

require_once __DIR__ . '/../config/paystack.php';

class PaystackPayment {
    private $secretKey;
    private $publicKey;
    private $apiBaseUrl;
    
    public function __construct() {
        $this->secretKey = PaystackConfig::getSecretKey();
        $this->publicKey = PaystackConfig::getPublicKey();
        $this->apiBaseUrl = PaystackConfig::getApiBaseUrl();
    }
    
    /**
     * Initialize a payment transaction
     * 
     * @param string $email Customer email
     * @param float $amount Amount in Naira (will be converted to kobo)
     * @param string $reference Unique transaction reference
     * @param array $metadata Additional transaction data
     * @return array Response from Paystack API
     */
    public function initializePayment($email, $amount, $reference, $metadata = []) {
        $url = $this->apiBaseUrl . "/transaction/initialize";
        
        // Convert amount to kobo (Paystack uses kobo, not naira)
        $amountInKobo = $amount * 100;
        
        $fields = [
            'email' => $email,
            'amount' => $amountInKobo,
            'reference' => $reference,
            'callback_url' => $this->getCallbackUrl(),
            'metadata' => $metadata
        ];
        
        $response = $this->makeRequest('POST', $url, $fields);
        
        return $response;
    }
    
    /**
     * Verify a payment transaction
     * 
     * @param string $reference Transaction reference
     * @return array Response from Paystack API
     */
    public function verifyPayment($reference) {
        $url = $this->apiBaseUrl . "/transaction/verify/" . $reference;
        
        $response = $this->makeRequest('GET', $url);
        
        return $response;
    }
    
    /**
     * Make HTTP request to Paystack API
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url API endpoint URL
     * @param array $data Request data (for POST requests)
     * @return array Decoded JSON response
     */
    private function makeRequest($method, $url, $data = []) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->secretKey,
            "Content-Type: application/json",
            "Cache-Control: no-cache"
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'status' => false,
                'message' => 'Connection error: ' . $error
            ];
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return $result;
    }
    
    /**
     * Generate a unique transaction reference
     * 
     * @param string $prefix Optional prefix for the reference
     * @return string Unique reference
     */
    public static function generateReference($prefix = 'SLA') {
        return $prefix . '_' . time() . '_' . uniqid();
    }
    
    /**
     * Get callback URL for payment verification
     * 
     * @return string Callback URL
     */
    private function getCallbackUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = str_replace('checkout.php', 'payment_callback.php', $_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . $scriptName;
    }
    
    /**
     * Get public key for frontend integration
     * 
     * @return string Public key
     */
    public function getPublicKey() {
        return $this->publicKey;
    }
    
    /**
     * Format amount for display (convert kobo to naira)
     * 
     * @param int $amountInKobo Amount in kobo
     * @return float Amount in naira
     */
    public static function formatAmount($amountInKobo) {
        return $amountInKobo / 100;
    }
}
?>