<?php
/**
 * JWT Configuration and Helper Functions
 * Handles JWT token generation and validation
 */

class JWT {
    // Secret key for signing tokens - CHANGE THIS IN PRODUCTION!
    private static $secret_key = "TreasureHunt2024SecretKey!@#";
    private static $issuer = "treasure_hunt_api";
    private static $audience = "treasure_hunt_app";
    
    /**
     * Generate JWT token
     * @param array $payload - Data to encode in token
     * @param int $expiry - Token expiry in seconds (default 24 hours)
     * @return string
     */
    public static function encode($payload, $expiry = 86400) {
        $issuedAt = time();
        $expire = $issuedAt + $expiry;
        
        $token_payload = [
            'iss' => self::$issuer,
            'aud' => self::$audience,
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => $payload
        ];
        
        // Create token header
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        // Create token payload
        $payload_encoded = json_encode($token_payload);
        
        // Encode Header
        $base64UrlHeader = self::base64UrlEncode($header);
        
        // Encode Payload
        $base64UrlPayload = self::base64UrlEncode($payload_encoded);
        
        // Create Signature
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret_key, true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        // Create JWT
        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
        
        return $jwt;
    }
    
    /**
     * Decode and validate JWT token
     * @param string $jwt - Token to decode
     * @return object|false - Decoded payload or false if invalid
     */
    public static function decode($jwt) {
        if (!$jwt) {
            return false;
        }
        
        // Split the JWT
        $tokenParts = explode('.', $jwt);
        
        if (count($tokenParts) != 3) {
            return false;
        }
        
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signature_provided = $tokenParts[2];
        
        // Verify the signature
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret_key, true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        if ($base64UrlSignature !== $signature_provided) {
            return false;
        }
        
        // Decode payload
        $payload_data = json_decode($payload);
        
        if (!$payload_data) {
            return false;
        }
        
        // Check if token is expired
        if (isset($payload_data->exp) && $payload_data->exp < time()) {
            return false;
        }
        
        return $payload_data;
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode($text) {
        return str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode($text)
        );
    }
    
    /**
     * Get user ID from token
     * @param string $jwt
     * @return int|false
     */
    public static function getUserIdFromToken($jwt) {
        $decoded = self::decode($jwt);
        
        if ($decoded && isset($decoded->data->user_id)) {
            return $decoded->data->user_id;
        }
        
        return false;
    }
    
    /**
     * Get admin ID from token
     * @param string $jwt
     * @return int|false
     */
    public static function getAdminIdFromToken($jwt) {
        $decoded = self::decode($jwt);
        
        if ($decoded && isset($decoded->data->admin_id)) {
            return $decoded->data->admin_id;
        }
        
        return false;
    }
}
?>