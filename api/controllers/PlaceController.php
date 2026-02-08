<?php
/**
 * Place Controller
 * Handles place operations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class PlaceController {
    
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        $id = $segments[1] ?? null;
        
        if ($id === 'create') {
            if ($method === 'POST') {
                $this->create();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id === 'search') {
            if ($method === 'GET') {
                $this->search();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id && is_numeric($id)) {
            switch ($method) {
                case 'GET':
                    $this->getById($id);
                    break;
                case 'PUT':
                    $this->update($id);
                    break;
                case 'DELETE':
                    $this->delete($id);
                    break;
                default:
                    Response::error('Method not allowed', 405);
            }
        } elseif (!$id) {
            if ($method === 'GET') {
                $this->getAll();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Invalid place endpoint', 404);
        }
    }
    
    /**
     * Get all places (with pagination and filters)
     * GET /api/places?page=1&limit=10&city=Port%20Louis&type=museum&verified=true
     */
    private function getAll() {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        $where = ["p.is_verified = 1", "p.status = 'approved'"];
        $params = [];
        
        // Filters
        if (isset($_GET['city']) && !empty($_GET['city'])) {
            $where[] = "p.city = :city";
            $params[':city'] = $_GET['city'];
        }
        
        if (isset($_GET['type']) && !empty($_GET['type'])) {
            $where[] = "p.place_type = :type";
            $params[':type'] = $_GET['type'];
        }
        
        if (isset($_GET['category']) && !empty($_GET['category'])) {
            $where[] = "EXISTS (SELECT 1 FROM place_category pc WHERE pc.place_id = p.place_id AND pc.category_id = :category)";
            $params[':category'] = $_GET['category'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM places p WHERE $whereClause";
        $stmt = $this->conn->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get places
        $query = "SELECT p.*, u.username as created_by_username,
                         GROUP_CONCAT(c.name) as categories
                  FROM places p
                  LEFT JOIN users u ON p.created_by_user_id = u.user_id
                  LEFT JOIN place_category pc ON p.place_id = pc.place_id
                  LEFT JOIN categories c ON pc.category_id = c.category_id
                  WHERE $whereClause
                  GROUP BY p.place_id
                  ORDER BY p.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $places = $stmt->fetchAll();
        
        Response::paginated($places, $total, $page, $limit, 'Places retrieved successfully');
    }
    
    /**
     * Get place by ID
     * GET /api/places/{id}
     */
    private function getById($id) {
        $query = "SELECT p.*, 
                        p.approved_photo_url as image_url,
                        u.username as created_by_username,
                        GROUP_CONCAT(DISTINCT c.category_id) as category_ids,
                        GROUP_CONCAT(DISTINCT c.name) as categories,
                        (SELECT COUNT(*) FROM checkpoints WHERE place_id = p.place_id) as used_in_hunts,
                        (SELECT COUNT(*) FROM checkpoint_completions cc 
                        INNER JOIN checkpoints c ON cc.checkpoint_id = c.checkpoint_id 
                        WHERE c.place_id = p.place_id) as visit_count
                FROM places p
                LEFT JOIN users u ON p.created_by_user_id = u.user_id
                LEFT JOIN place_category pc ON p.place_id = pc.place_id
                LEFT JOIN categories c ON pc.category_id = c.category_id
                WHERE p.place_id = :id AND p.is_verified = 1 AND p.status = 'approved'
                GROUP BY p.place_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Place not found', 404);
        }
        
        $place = $stmt->fetch();
        
        // Convert category_ids to array
        if ($place['category_ids']) {
            $place['category_ids'] = array_map('intval', explode(',', $place['category_ids']));
        }
        
        Response::success($place, 'Place retrieved successfully');
    }

    
    /**
     * Create new place
     * POST /api/places/create
     */
    private function create() {
        $user_id = AuthMiddleware::verifyUser();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $validator = new Validator();
        $validator->required('name', $data['name'] ?? '');
        $validator->required('latitude', $data['latitude'] ?? '');
        $validator->latitude('latitude', $data['latitude'] ?? '');
        $validator->required('longitude', $data['longitude'] ?? '');
        $validator->longitude('longitude', $data['longitude'] ?? '');
        $validator->required('place_type', $data['place_type'] ?? '');
        $validator->required('category_ids', $data['category_ids'] ?? '');
        
        if (!$validator->isValid()) {
            Response::error('Validation failed', 422, $validator->getErrors());
        }
        
        // Validate category_ids is array
        if (!is_array($data['category_ids']) || empty($data['category_ids'])) {
            Response::error('At least one category is required', 422);
        }
        
        // Insert place
        $query = "INSERT INTO places (name, description, latitude, longitude, address, 
                                     city, region, country, place_type, opening_hours, 
                                     created_by_user_id, status)
                  VALUES (:name, :description, :latitude, :longitude, :address, 
                          :city, :region, :country, :place_type, :opening_hours, 
                          :user_id, 'pending')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':name' => Validator::sanitize($data['name']),
            ':description' => $data['description'] ?? null,
            ':latitude' => $data['latitude'],
            ':longitude' => $data['longitude'],
            ':address' => $data['address'] ?? null,
            ':city' => $data['city'] ?? null,
            ':region' => $data['region'] ?? null,
            ':country' => $data['country'] ?? 'Mauritius',
            ':place_type' => $data['place_type'],
            ':opening_hours' => $data['opening_hours'] ?? null,
            ':user_id' => $user_id
        ]);
        
        $place_id = $this->conn->lastInsertId();
        
        // Insert place categories
        $catQuery = "INSERT INTO place_category (place_id, category_id) VALUES (:place_id, :category_id)";
        $catStmt = $this->conn->prepare($catQuery);
        
        foreach ($data['category_ids'] as $cat_id) {
            $catStmt->execute([':place_id' => $place_id, ':category_id' => $cat_id]);
        }
        
        Response::success([
            'place_id' => $place_id,
            'message' => 'Place created and awaiting admin approval'
        ], 'Place submitted successfully', 201);
    }
    
    /**
     * Search places
     * GET /api/places/search?q=beach&lat=-20.1609&lon=57.5012&radius=10
     */
    private function search() {
        $query = $_GET['q'] ?? '';
        $lat = $_GET['lat'] ?? null;
        $lon = $_GET['lon'] ?? null;
        $radius = $_GET['radius'] ?? 10; // km
        
        $where = ["p.is_verified = 1", "p.status = 'approved'"];
        $params = [];
        
        if (!empty($query)) {
            $where[] = "(p.name LIKE :query OR p.description LIKE :query OR p.city LIKE :query)";
            $params[':query'] = "%$query%";
        }
        
        $sql = "SELECT p.*, 
                    p.approved_photo_url as image_url,
                    GROUP_CONCAT(c.name) as categories";
        
        // Add distance calculation if coordinates provided
        if ($lat && $lon) {
            $sql .= ", (6371 * acos(cos(radians(:lat)) * cos(radians(p.latitude)) * 
                        cos(radians(p.longitude) - radians(:lon)) + 
                        sin(radians(:lat)) * sin(radians(p.latitude)))) AS distance";
            $params[':lat'] = $lat;
            $params[':lon'] = $lon;
        }
        
        $sql .= " FROM places p
                LEFT JOIN place_category pc ON p.place_id = pc.place_id
                LEFT JOIN categories c ON pc.category_id = c.category_id
                WHERE " . implode(' AND ', $where);
        
        $sql .= " GROUP BY p.place_id";
        
        // Filter by radius if coordinates provided
        if ($lat && $lon) {
            $sql .= " HAVING distance <= :radius";
            $params[':radius'] = $radius;
            $sql .= " ORDER BY distance ASC";
        } else {
            $sql .= " ORDER BY p.created_at DESC";
        }
        
        $sql .= " LIMIT 50";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $places = $stmt->fetchAll();
        
        Response::success($places, 'Search results');
    }
    
    /**
     * Update place
     * PUT /api/places/{id}
     */
    private function update($id) {
        $user_id = AuthMiddleware::verifyUser();
        
        // Check if user owns this place
        $checkQuery = "SELECT created_by_user_id FROM places WHERE place_id = :id";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Place not found', 404);
        }
        
        $place = $stmt->fetch();
        if ($place['created_by_user_id'] != $user_id) {
            Response::error('Unauthorized to update this place', 403);
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        $updates = [];
        $params = [':id' => $id];
        
        $allowedFields = ['name', 'description', 'address', 'city', 'opening_hours'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = Validator::sanitize($data[$field]);
            }
        }
        
        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }
        
        // Set status back to pending after update
        $updates[] = "status = 'pending'";
        
        $query = "UPDATE places SET " . implode(', ', $updates) . " WHERE place_id = :id";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($params)) {
            Response::success(null, 'Place updated and awaiting re-approval');
        } else {
            Response::error('Update failed', 500);
        }
    }
    
    /**
     * Delete place
     * DELETE /api/places/{id}
     */
    private function delete($id) {
        $user_id = AuthMiddleware::verifyUser();
        
        // Check ownership
        $checkQuery = "SELECT created_by_user_id FROM places WHERE place_id = :id";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Place not found', 404);
        }
        
        $place = $stmt->fetch();
        if ($place['created_by_user_id'] != $user_id) {
            Response::error('Unauthorized to delete this place', 403);
        }
        
        $query = "DELETE FROM places WHERE place_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            Response::success(null, 'Place deleted successfully');
        } else {
            Response::error('Delete failed', 500);
        }
    }
}
?>