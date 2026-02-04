<?php
/**
 * User Controller
 * Handles user profile operations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class UserController {
    
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? '';
        
        switch ($action) {
            case 'profile':
                if ($method === 'GET') {
                    $this->getProfile();
                } elseif ($method === 'PUT') {
                    $this->updateProfile();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'stats':
                if ($method === 'GET') {
                    $this->getUserStats();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'change-password':
                if ($method === 'POST') {
                    $this->changePassword();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            default:
                Response::error('Invalid user endpoint', 404);
        }
    }
    
    /**
     * Get user profile
     * GET /api/users/profile
     */
    private function getProfile() {
        $user_id = AuthMiddleware::verifyUser();
        
        $query = "SELECT user_id, username, email, full_name, user_type, 
                         total_points, level, is_verified, account_status, 
                         created_at, last_active 
                  FROM users WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('User not found', 404);
        }
        
        $user = $stmt->fetch();
        Response::success($user, 'Profile retrieved successfully');
    }
    
    /**
     * Update user profile
     * PUT /api/users/profile
     */
    private function updateProfile() {
        $user_id = AuthMiddleware::verifyUser();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $validator = new Validator();
        
        // Only validate fields that are being updated
        $updates = [];
        $params = [':user_id' => $user_id];
        
        if (isset($data['username'])) {
            $validator->minLength('username', $data['username'], 3, 'Username');
            $validator->maxLength('username', $data['username'], 50, 'Username');
            
            // Check username uniqueness
            $checkQuery = "SELECT user_id FROM users WHERE username = :username AND user_id != :user_id";
            $stmt = $this->conn->prepare($checkQuery);
            $stmt->execute([':username' => $data['username'], ':user_id' => $user_id]);
            
            if ($stmt->rowCount() > 0) {
                Response::error('Username already taken', 409);
            }
            
            $updates[] = "username = :username";
            $params[':username'] = Validator::sanitize($data['username']);
        }
        
        if (isset($data['full_name'])) {
            $updates[] = "full_name = :full_name";
            $params[':full_name'] = Validator::sanitize($data['full_name']);
        }
        
        if (!$validator->isValid()) {
            Response::error('Validation failed', 422, $validator->getErrors());
        }
        
        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }
        
        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($params)) {
            $this->getProfile();
        } else {
            Response::error('Profile update failed', 500);
        }
    }
    
    /**
     * Get user statistics
     * GET /api/users/stats
     */
    private function getUserStats() {
        $user_id = AuthMiddleware::verifyUser();
        
        // Get basic user info
        $userQuery = "SELECT username, total_points, level FROM users WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($userQuery);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        // Get hunts completed
        $huntsQuery = "SELECT COUNT(*) as completed FROM user_hunt_progress 
                      WHERE user_id = :user_id AND status = 'completed'";
        $stmt = $this->conn->prepare($huntsQuery);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $hunts = $stmt->fetch();
        
        // Get badges earned
        $badgesQuery = "SELECT COUNT(*) as earned FROM user_badges WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($badgesQuery);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $badges = $stmt->fetch();
        
        // Get places visited
        $placesQuery = "SELECT COUNT(DISTINCT place_id) as visited FROM photo_uploads 
                       WHERE user_id = :user_id AND status = 'approved' AND upload_type = 'place_visit'";
        $stmt = $this->conn->prepare($placesQuery);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $places = $stmt->fetch();
        
        // Get active hunts
        $activeQuery = "SELECT COUNT(*) as active FROM user_hunt_progress 
                       WHERE user_id = :user_id AND status = 'in_progress'";
        $stmt = $this->conn->prepare($activeQuery);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $active = $stmt->fetch();
        
        Response::success([
            'username' => $user['username'],
            'total_points' => (int)$user['total_points'],
            'level' => $user['level'],
            'hunts_completed' => (int)$hunts['completed'],
            'badges_earned' => (int)$badges['earned'],
            'places_visited' => (int)$places['visited'],
            'active_hunts' => (int)$active['active']
        ], 'User statistics retrieved');
    }
    
    /**
     * Change password
     * POST /api/users/change-password
     */
    private function changePassword() {
        $user_id = AuthMiddleware::verifyUser();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $validator = new Validator();
        $validator->required('current_password', $data['current_password'] ?? '');
        $validator->required('new_password', $data['new_password'] ?? '');
        $validator->minLength('new_password', $data['new_password'] ?? '', 6, 'New password');
        
        if (!$validator->isValid()) {
            Response::error('Validation failed', 422, $validator->getErrors());
        }
        
        // Get current password
        $query = "SELECT password FROM users WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        // Verify current password
        if (!password_verify($data['current_password'], $user['password'])) {
            Response::error('Current password is incorrect', 401);
        }
        
        // Hash new password
        $hashed = password_hash($data['new_password'], PASSWORD_BCRYPT);
        
        // Update password
        $updateQuery = "UPDATE users SET password = :password WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bindParam(':password', $hashed);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            Response::success(null, 'Password changed successfully');
        } else {
            Response::error('Password change failed', 500);
        }
    }
}
?>