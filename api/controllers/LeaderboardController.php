<?php
/**
 * LEADERBOARD CONTROLLER
 * Save as: api/controllers/LeaderboardController.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';

class LeaderboardController {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        if ($method === 'GET') {
            $this->getLeaderboard();
        } else {
            Response::error('Method not allowed', 405);
        }
    }
    
    private function getLeaderboard() {
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
        $user_type = $_GET['user_type'] ?? null;
        
        $where = "account_status = 'active'";
        $params = [];
        
        if ($user_type) {
            $where .= " AND user_type = :user_type";
            $params[':user_type'] = $user_type;
        }
        
        $query = "SELECT user_id, username, user_type, total_points, level,
                         hunts_completed, badges_earned
                  FROM leaderboard
                  WHERE $where
                  ORDER BY total_points DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $leaderboard = $stmt->fetchAll();
        
        // Add rank
        foreach ($leaderboard as $index => &$user) {
            $user['rank'] = $index + 1;
        }
        
        Response::success($leaderboard, 'Leaderboard retrieved');
    }
}
?>