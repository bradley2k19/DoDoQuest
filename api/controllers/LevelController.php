<?php
/**
 * Level Controller
 * Handles user level and progression endpoints
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/LevelSystem.php';

class LevelController {
    
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? null;
        
        switch ($action) {
            case 'progress':
                if ($method === 'GET') {
                    $this->getProgress();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'leaderboard':
                if ($method === 'GET') {
                    $this->getLeaderboard();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'levels':
                if ($method === 'GET') {
                    $this->getAllLevels();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            default:
                Response::error('Invalid level endpoint', 404);
        }
    }
    
    /**
     * Get user's level progress
     * GET /api/levels/progress
     */
    private function getProgress() {
        $user_id = AuthMiddleware::verifyUser();
        
        $levelSystem = new LevelSystem($this->conn);
        $progress = $levelSystem->getLevelProgress($user_id);
        
        if (!$progress) {
            Response::error('User not found', 404);
        }
        
        Response::success($progress, 'Level progress retrieved');
    }
    
    /**
     * Get all level definitions
     * GET /api/levels/levels
     */
    private function getAllLevels() {
        $query = "SELECT ul.*, b.name as badge_name, b.description as badge_description
                 FROM user_levels ul
                 LEFT JOIN badges b ON ul.badge_id = b.badge_id
                 ORDER BY ul.level_number ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $levels = $stmt->fetchAll();
        
        Response::success($levels, 'Levels retrieved');
    }
    
    /**
     * Get leaderboard
     * GET /api/levels/leaderboard?limit=10
     */
    private function getLeaderboard() {
        $limit = $_GET['limit'] ?? 10;
        $limit = min((int)$limit, 100); // Max 100
        
        $query = "SELECT u.user_id, u.username, u.full_name, u.level, 
                        u.experience_points, u.total_points,
                        ul.level_name,
                        (SELECT COUNT(*) FROM user_hunt_progress WHERE user_id = u.user_id AND status = 'completed') as hunts_completed
                 FROM users u
                 INNER JOIN user_levels ul ON u.level = ul.level_number
                 ORDER BY u.level DESC, u.experience_points DESC
                 LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $leaderboard = $stmt->fetchAll();
        
        Response::success($leaderboard, 'Leaderboard retrieved');
    }
}