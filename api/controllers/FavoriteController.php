<?php
/**
 * FAVORITE CONTROLLER
 * Save as: api/controllers/FavoriteController.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';

class FavoriteController {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? null;
        
        if ($action === 'add') {
            if ($method === 'POST') {
                $this->add();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($action === 'remove') {
            if ($method === 'DELETE') {
                $this->remove();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif (!$action) {
            if ($method === 'GET') {
                $this->getMyFavorites();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Invalid favorite endpoint', 404);
        }
    }
    
    private function getMyFavorites() {
        $user_id = AuthMiddleware::verifyUser();
        $type = $_GET['type'] ?? null;
        
        $where = "f.user_id = :user_id";
        $params = [':user_id' => $user_id];
        
        if ($type) {
            $where .= " AND f.favoritable_type = :type";
            $params[':type'] = $type;
        }
        
        $query = "SELECT f.*, 
                         CASE 
                             WHEN f.favoritable_type = 'place' THEN p.name
                             WHEN f.favoritable_type = 'hunt' THEN th.title
                         END as item_name
                  FROM favorites f
                  LEFT JOIN places p ON f.favoritable_type = 'place' AND f.favoritable_id = p.place_id
                  LEFT JOIN treasure_hunts th ON f.favoritable_type = 'hunt' AND f.favoritable_id = th.hunt_id
                  WHERE $where
                  ORDER BY f.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $favorites = $stmt->fetchAll();
        
        Response::success($favorites, 'Favorites retrieved');
    }
    
    private function add() {
        $user_id = AuthMiddleware::verifyUser();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $type = $data['favoritable_type'] ?? null;
        $id = $data['favoritable_id'] ?? null;
        $notes = $data['notes'] ?? null;
        
        if (!$type || !$id || !in_array($type, ['place', 'hunt'])) {
            Response::error('Invalid favorite data', 422);
        }
        
        $query = "INSERT INTO favorites (user_id, favoritable_type, favoritable_id, notes) 
                  VALUES (:user_id, :type, :id, :notes)
                  ON DUPLICATE KEY UPDATE notes = :notes";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':user_id' => $user_id,
            ':type' => $type,
            ':id' => $id,
            ':notes' => $notes
        ]);
        
        Response::success(null, 'Added to favorites', 201);
    }
    
    private function remove() {
        $user_id = AuthMiddleware::verifyUser();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $type = $data['favoritable_type'] ?? null;
        $id = $data['favoritable_id'] ?? null;
        
        if (!$type || !$id) {
            Response::error('Invalid data', 422);
        }
        
        $query = "DELETE FROM favorites 
                  WHERE user_id = :user_id 
                  AND favoritable_type = :type 
                  AND favoritable_id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $user_id, ':type' => $type, ':id' => $id]);
        
        Response::success(null, 'Removed from favorites');
    }
}
?>