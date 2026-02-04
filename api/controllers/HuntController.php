<?php
/**
 * Hunt Controller
 * Handles treasure hunt operations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class HuntController {
    
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? null;
        
        if ($action === 'create') {
            if ($method === 'POST') {
                $this->create();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($action === 'featured') {
            if ($method === 'GET') {
                $this->getFeatured();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($action && is_numeric($action)) {
            $id = $action;
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
        } elseif (!$action) {
            if ($method === 'GET') {
                $this->getAll();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Invalid hunt endpoint', 404);
        }
    }
    
    /**
     * Get all hunts
     * GET /api/hunts?difficulty=easy&page=1&limit=10
     */
    private function getAll() {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        $where = ["th.is_active = 1"];
        $params = [];
        
        if (isset($_GET['difficulty']) && !empty($_GET['difficulty'])) {
            $where[] = "th.difficulty_level = :difficulty";
            $params[':difficulty'] = $_GET['difficulty'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Count total
        $countQuery = "SELECT COUNT(*) as total FROM treasure_hunts th WHERE $whereClause";
        $stmt = $this->conn->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get hunts
        $query = "SELECT th.*, 
                         u.username as created_by_username,
                         p1.name as starting_location_name,
                         p2.name as ending_location_name,
                         b.name as badge_name,
                         (SELECT COUNT(*) FROM checkpoints WHERE hunt_id = th.hunt_id) as checkpoint_count
                  FROM treasure_hunts th
                  LEFT JOIN users u ON th.created_by_user_id = u.user_id
                  LEFT JOIN places p1 ON th.starting_location_id = p1.place_id
                  LEFT JOIN places p2 ON th.ending_location_id = p2.place_id
                  LEFT JOIN badges b ON th.badge_id = b.badge_id
                  WHERE $whereClause
                  ORDER BY th.is_featured DESC, th.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $hunts = $stmt->fetchAll();
        
        Response::paginated($hunts, $total, $page, $limit, 'Hunts retrieved successfully');
    }
    
    /**
     * Get hunt by ID with checkpoints
     * GET /api/hunts/{id}
     */
    private function getById($id) {
        $query = "SELECT th.*, 
                         u.username as created_by_username,
                         p1.name as starting_location_name,
                         p1.latitude as start_lat,
                         p1.longitude as start_lon,
                         p2.name as ending_location_name,
                         b.name as badge_name,
                         b.icon_url as badge_icon
                  FROM treasure_hunts th
                  LEFT JOIN users u ON th.created_by_user_id = u.user_id
                  LEFT JOIN places p1 ON th.starting_location_id = p1.place_id
                  LEFT JOIN places p2 ON th.ending_location_id = p2.place_id
                  LEFT JOIN badges b ON th.badge_id = b.badge_id
                  WHERE th.hunt_id = :id AND th.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Hunt not found', 404);
        }
        
        $hunt = $stmt->fetch();
        
        // Get checkpoints
        $cpQuery = "SELECT c.*, p.name as place_name, p.latitude, p.longitude, p.address
                   FROM checkpoints c
                   INNER JOIN places p ON c.place_id = p.place_id
                   WHERE c.hunt_id = :id
                   ORDER BY c.sequence_order ASC";
        
        $stmt = $this->conn->prepare($cpQuery);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $hunt['checkpoints'] = $stmt->fetchAll();
        
        Response::success($hunt, 'Hunt retrieved successfully');
    }
    
    /**
     * Get featured hunts
     * GET /api/hunts/featured
     */
    private function getFeatured() {
        $query = "SELECT th.*, 
                         u.username as created_by_username,
                         p1.name as starting_location_name,
                         (SELECT COUNT(*) FROM checkpoints WHERE hunt_id = th.hunt_id) as checkpoint_count
                  FROM treasure_hunts th
                  LEFT JOIN users u ON th.created_by_user_id = u.user_id
                  LEFT JOIN places p1 ON th.starting_location_id = p1.place_id
                  WHERE th.is_active = 1 AND th.is_featured = 1
                  ORDER BY th.created_at DESC
                  LIMIT 10";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $hunts = $stmt->fetchAll();
        
        Response::success($hunts, 'Featured hunts retrieved');
    }
    
    /**
     * Create new hunt
     * POST /api/hunts/create
     */
    private function create() {
        try {
            $user_id = AuthMiddleware::verifyUser();
            $rawInput = file_get_contents("php://input");
            
            error_log("=== HUNT CREATE DEBUG ===");
            error_log("Raw Input: " . $rawInput);
            
            $data = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON Error: " . json_last_error_msg());
                Response::error('Invalid JSON: ' . json_last_error_msg(), 400);
                return;
            }
            
            error_log("Parsed Data: " . print_r($data, true));
            
            $validator = new Validator();
            $validator->required('title', $data['title'] ?? '');
            $validator->required('difficulty_level', $data['difficulty_level'] ?? '');
            $validator->enum('difficulty_level', $data['difficulty_level'] ?? '', ['easy', 'medium', 'hard']);
            $validator->required('starting_location_id', $data['starting_location_id'] ?? '');
            $validator->required('checkpoints', $data['checkpoints'] ?? '');
            
            if (!$validator->isValid()) {
                error_log("Validation Errors: " . print_r($validator->getErrors(), true));
                Response::error('Validation failed', 422, $validator->getErrors());
                return;
            }
            
            // Validate checkpoints array (minimum 3)
            if (!is_array($data['checkpoints']) || count($data['checkpoints']) < 3) {
                error_log("Checkpoint count: " . count($data['checkpoints'] ?? []));
                Response::error('Hunt must have at least 3 checkpoints', 422);
                return;
            }
            
            // Validate that starting_location_id exists
            $locCheckQuery = "SELECT place_id FROM places WHERE place_id = :place_id";
            $locStmt = $this->conn->prepare($locCheckQuery);
            $locStmt->execute([':place_id' => $data['starting_location_id']]);
            
            if ($locStmt->rowCount() === 0) {
                error_log("Starting location not found: " . $data['starting_location_id']);
                Response::error('Starting location not found', 422);
                return;
            }
            
            // Begin transaction
            $this->conn->beginTransaction();
            
            // Insert hunt
            $query = "INSERT INTO treasure_hunts 
                    (title, description, difficulty_level, estimated_duration, distance_km,
                    reward_points, created_by_user_id, starting_location_id, ending_location_id,
                    badge_id, is_active)
                    VALUES (:title, :desc, :difficulty, :duration, :distance, :points,
                            :user_id, :start_loc, :end_loc, :badge_id, 0)";
            
            $stmt = $this->conn->prepare($query);
            
            $params = [
                ':title' => Validator::sanitize($data['title']),
                ':desc' => $data['description'] ?? null,
                ':difficulty' => $data['difficulty_level'],
                ':duration' => isset($data['estimated_duration']) && $data['estimated_duration'] !== '' ? (int)$data['estimated_duration'] : null,
                ':distance' => isset($data['distance_km']) && $data['distance_km'] !== '' ? (float)$data['distance_km'] : null,
                ':points' => $data['reward_points'] ?? 100,
                ':user_id' => $user_id,
                ':start_loc' => (int)$data['starting_location_id'],
                ':end_loc' => isset($data['ending_location_id']) && $data['ending_location_id'] ? (int)$data['ending_location_id'] : null,
                ':badge_id' => isset($data['badge_id']) && $data['badge_id'] ? (int)$data['badge_id'] : null
            ];
            
            error_log("Hunt Insert Params: " . print_r($params, true));
            
            if (!$stmt->execute($params)) {
                $error = $stmt->errorInfo();
                error_log("Hunt Insert Error: " . print_r($error, true));
                throw new Exception("Failed to insert hunt: " . $error[2]);
            }
            
            $hunt_id = $this->conn->lastInsertId();
            error_log("Hunt ID: " . $hunt_id);
            
            // Insert checkpoints
            $cpQuery = "INSERT INTO checkpoints 
                    (hunt_id, place_id, sequence_order, clue_text, hint_text, 
                        challenge_type, challenge_data, points_awarded, time_limit_minutes, is_mandatory)
                    VALUES (:hunt_id, :place_id, :sequence, :clue, :hint, 
                            :challenge_type, :challenge_data, :points, :time_limit, :mandatory)";
            
            $cpStmt = $this->conn->prepare($cpQuery);
            
            foreach ($data['checkpoints'] as $index => $cp) {
                // Validate place exists
                $placeCheckStmt = $this->conn->prepare($locCheckQuery);
                $placeCheckStmt->execute([':place_id' => $cp['place_id']]);
                
                if ($placeCheckStmt->rowCount() === 0) {
                    throw new Exception("Place ID {$cp['place_id']} not found for checkpoint");
                }
                
                // Handle challenge_data - convert to JSON string if it's an array or object
                $challengeData = null;
                if (isset($cp['challenge_data'])) {
                    if (is_array($cp['challenge_data']) || is_object($cp['challenge_data'])) {
                        $challengeData = json_encode($cp['challenge_data']);
                    } else if (is_string($cp['challenge_data'])) {
                        // Already a string, keep as is
                        $challengeData = $cp['challenge_data'];
                    }
                }
                
                error_log("Checkpoint " . ($index + 1) . " - Original challenge_data: " . print_r($cp['challenge_data'], true));
                error_log("Checkpoint " . ($index + 1) . " - Encoded challenge_data: " . $challengeData);
                
                $cpParams = [
                    ':hunt_id' => $hunt_id,
                    ':place_id' => (int)$cp['place_id'],
                    ':sequence' => $index + 1,
                    ':clue' => $cp['clue_text'],
                    ':hint' => $cp['hint_text'] ?? null,
                    ':challenge_type' => $cp['challenge_type'] ?? 'geofence',
                    ':challenge_data' => $challengeData,
                    ':points' => $cp['points_awarded'] ?? 10,
                    ':time_limit' => isset($cp['time_limit_minutes']) && $cp['time_limit_minutes'] !== '' ? (int)$cp['time_limit_minutes'] : null,
                    ':mandatory' => $cp['is_mandatory'] ?? 1
                ];
                
                error_log("Checkpoint Insert Params: " . print_r($cpParams, true));
                
                if (!$cpStmt->execute($cpParams)) {
                    $error = $cpStmt->errorInfo();
                    error_log("Checkpoint Insert Error: " . print_r($error, true));
                    throw new Exception("Failed to insert checkpoint: " . $error[2]);
                }
            }
            
            $this->conn->commit();
            error_log("Hunt created successfully! Hunt ID: " . $hunt_id);
            
            Response::success([
                'hunt_id' => $hunt_id,
                'message' => 'Hunt created successfully. Awaiting admin approval for public listing.'
            ], 'Hunt created', 201);
            
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Hunt Create Exception: " . $e->getMessage());
            error_log("Stack Trace: " . $e->getTraceAsString());
            Response::error('Failed to create hunt: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update hunt
     * PUT /api/hunts/{id}
     */
    private function update($id) {
        $user_id = AuthMiddleware::verifyUser();
        
        // Check ownership
        $checkQuery = "SELECT created_by_user_id FROM treasure_hunts WHERE hunt_id = :id";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Hunt not found', 404);
        }
        
        $hunt = $stmt->fetch();
        if ($hunt['created_by_user_id'] != $user_id) {
            Response::error('Unauthorized', 403);
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        $updates = [];
        $params = [':id' => $id];
        
        $allowedFields = ['title', 'description', 'estimated_duration', 'distance_km'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }
        
        $query = "UPDATE treasure_hunts SET " . implode(', ', $updates) . " WHERE hunt_id = :id";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($params)) {
            Response::success(null, 'Hunt updated successfully');
        } else {
            Response::error('Update failed', 500);
        }
    }
    
    /**
     * Delete hunt
     * DELETE /api/hunts/{id}
     */
    private function delete($id) {
        $user_id = AuthMiddleware::verifyUser();
        
        // Check ownership 
        $checkQuery = "SELECT created_by_user_id FROM treasure_hunts WHERE hunt_id = :id";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Hunt not found', 404);
        }
        
        $hunt = $stmt->fetch();
        if ($hunt['created_by_user_id'] != $user_id) {
            Response::error('Unauthorized', 403);
        }
        
        // Soft delete by setting is_active to false
        $query = "UPDATE treasure_hunts SET is_active = 0 WHERE hunt_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            Response::success(null, 'Hunt deleted successfully');
        } else {
            Response::error('Delete failed', 500);
        }
    }
}
?>