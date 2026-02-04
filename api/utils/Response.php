<?php
/**
 * Response Utility Class
 * Standardized JSON responses for API
 */

class Response {
    
    /**
     * Send success response
     * @param mixed $data - Data to send
     * @param string $message - Success message
     * @param int $code - HTTP status code
     */
    public static function success($data = null, $message = 'Success', $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Send error response
     * @param string $message - Error message
     * @param int $code - HTTP status code
     * @param array $errors - Detailed errors
     */
    public static function error($message = 'Error', $code = 400, $errors = null) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Send paginated response
     * @param array $data - Data items
     * @param int $total - Total items
     * @param int $page - Current page
     * @param int $limit - Items per page
     * @param string $message - Success message
     */
    public static function paginated($data, $total, $page, $limit, $message = 'Success') {
        http_response_code(200);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'total' => (int)$total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => ceil($total / $limit)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
}
?>