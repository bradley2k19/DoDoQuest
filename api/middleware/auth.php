<?php
/**
 * Authentication Middleware
 * Validates JWT tokens for protected routes
 */

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthMiddleware {
    
    /**
     * Verify user authentication
     * @return int|false - User ID if authenticated, false otherwise
     */
    public static function verifyUser() {
        $token = self::getBearerToken();
        
        if (!$token) {
            Response::error('Access denied. No token provided.', 401);
            exit();
        }
        
        $decoded = JWT::decode($token);
        
        if (!$decoded) {
            Response::error('Invalid or expired token.', 401);
            exit();
        }
        
        if (!isset($decoded->data->user_id) && !isset($decoded->data->admin_id)) {
            Response::error('Invalid token payload.', 401);
            exit();
        }

        return $decoded->data->user_id ?? $decoded->data->admin_id;

    }
    
    /**
     * Verify admin authentication
     * @return int|false - Admin ID if authenticated, false otherwise
     */
    public static function verifyAdmin() {
        $token = self::getBearerToken();
        
        if (!$token) {
            Response::error('Access denied. No token provided.', 401);
            exit();
        }
        
        $decoded = JWT::decode($token);
        
        if (!$decoded) {
            Response::error('Invalid or expired token.', 401);
            exit();
        }
        
        if (!isset($decoded->data->admin_id)) {
            Response::error('Admin access required.', 403);
            exit();
        }
        
        return $decoded->data->admin_id;
    }
    
    /**
     * Get bearer token from header
     * @return string|false
     */
    private static function getBearerToken() {
        $headers = self::getAuthorizationHeader();
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }
    
    /**
     * Get authorization header
     * @return string|null
     */
    private static function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)), 
                array_values($requestHeaders)
            );
            
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }
}
?>