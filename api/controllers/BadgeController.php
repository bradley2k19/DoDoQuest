<?php
/**
 * BADGE CONTROLLER
 * Save as: api/controllers/BadgeController.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';

class BadgeController {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? null;
        
        if ($action === 'my-badges') {
            if ($method === 'GET') {
                $this->getMyBadges();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif (!$action) {
            if ($method === 'GET') {
                $this->getAll();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Invalid badge endpoint', 404);
        }
    }
    
    private function getAll() {
        $query = "SELECT * FROM badges ORDER BY rarity DESC, name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $badges = $stmt->fetchAll();
        Response::success($badges, 'Badges retrieved');
    }
    
    private function getMyBadges() {
        $user_id = AuthMiddleware::verifyUser();
        
        $query = "SELECT b.*, ub.earned_at, th.title as hunt_title
                  FROM user_badges ub
                  INNER JOIN badges b ON ub.badge_id = b.badge_id
                  LEFT JOIN treasure_hunts th ON ub.related_hunt_id = th.hunt_id
                  WHERE ub.user_id = :user_id
                  ORDER BY ub.earned_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $badges = $stmt->fetchAll();
        
        Response::success($badges, 'User badges retrieved');
    }
}
?>