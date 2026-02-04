<?php
/**
 * SYNC CONTROLLER
 * Save as: api/controllers/SyncController.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/FileUpload.php';

class SyncController {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? null;
        
        if ($action === 'queue') {
            if ($method === 'POST') {
                $this->addToQueue();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($action === 'process') {
            if ($method === 'POST') {
                $this->processQueue();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($action === 'pending') {
            if ($method === 'GET') {
                $this->getPending();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Invalid sync endpoint', 404);
        }
    }
    
    /**
     * Add action to sync queue (for offline operations)
     * POST /api/sync/queue
     */
    private function addToQueue() {
        $user_id = AuthMiddleware::verifyUser();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $action_type = $data['action_type'] ?? null;
        $entity_type = $data['entity_type'] ?? null;
        $entity_data = $data['entity_data'] ?? null;
        
        if (!$action_type || !$entity_type || !$entity_data) {
            Response::error('Missing required fields', 422);
        }
        
        $query = "INSERT INTO sync_queue (user_id, action_type, entity_type, entity_data, status)
                  VALUES (:user_id, :action_type, :entity_type, :entity_data, 'pending')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':user_id' => $user_id,
            ':action_type' => $action_type,
            ':entity_type' => $entity_type,
            ':entity_data' => json_encode($entity_data)
        ]);
        
        $sync_id = $this->conn->lastInsertId();
        
        Response::success([
            'sync_id' => $sync_id
        ], 'Added to sync queue', 201);
    }
    
    /**
     * Process sync queue
     * POST /api/sync/process
     */
    private function processQueue() {
        $user_id = AuthMiddleware::verifyUser();
        
        // Get pending items for this user
        $query = "SELECT * FROM sync_queue 
                  WHERE user_id = :user_id AND status = 'pending'
                  ORDER BY created_at ASC
                  LIMIT 50";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $items = $stmt->fetchAll();
        
        $processed = 0;
        $failed = 0;
        $results = [];
        
        foreach ($items as $item) {
            try {
                // Update status to processing
                $updateQuery = "UPDATE sync_queue SET status = 'processing', attempts = attempts + 1 
                               WHERE sync_id = :sync_id";
                $stmt = $this->conn->prepare($updateQuery);
                $stmt->execute([':sync_id' => $item['sync_id']]);
                
                // Process based on action type
                $entity_data = json_decode($item['entity_data'], true);
                $success = $this->processAction($item['action_type'], $item['entity_type'], $entity_data, $user_id);
                
                if ($success) {
                    // Mark as completed
                    $completeQuery = "UPDATE sync_queue 
                                     SET status = 'completed', processed_at = NOW() 
                                     WHERE sync_id = :sync_id";
                    $stmt = $this->conn->prepare($completeQuery);
                    $stmt->execute([':sync_id' => $item['sync_id']]);
                    $processed++;
                    
                    $results[] = [
                        'sync_id' => $item['sync_id'],
                        'status' => 'success'
                    ];
                } else {
                    throw new Exception('Processing failed');
                }
                
            } catch (Exception $e) {
                // Mark as failed
                $failQuery = "UPDATE sync_queue 
                             SET status = 'failed', error_message = :error 
                             WHERE sync_id = :sync_id";
                $stmt = $this->conn->prepare($failQuery);
                $stmt->execute([
                    ':sync_id' => $item['sync_id'],
                    ':error' => $e->getMessage()
                ]);
                $failed++;
                
                $results[] = [
                    'sync_id' => $item['sync_id'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        Response::success([
            'processed' => $processed,
            'failed' => $failed,
            'results' => $results
        ], 'Sync queue processed');
    }
    
    /**
     * Process individual action
     */
    private function processAction($action_type, $entity_type, $data, $user_id) {
        // This is a simplified version - expand based on your needs
        switch ($action_type) {
            case 'checkpoint_complete':
                // Process checkpoint completion
                // Implementation would call ProgressController logic
                return true;
                
            case 'photo_upload':
                // Process photo upload
                return true;
                
            case 'favorite_add':
                // Add favorite
                $query = "INSERT INTO favorites (user_id, favoritable_type, favoritable_id) 
                         VALUES (:user_id, :type, :id)";
                $stmt = $this->conn->prepare($query);
                return $stmt->execute([
                    ':user_id' => $user_id,
                    ':type' => $data['type'],
                    ':id' => $data['id']
                ]);
                
            default:
                return false;
        }
    }
    
    /**
     * Get pending sync items
     * GET /api/sync/pending
     */
    private function getPending() {
        $user_id = AuthMiddleware::verifyUser();
        
        $query = "SELECT * FROM sync_queue 
                  WHERE user_id = :user_id AND status = 'pending'
                  ORDER BY created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $items = $stmt->fetchAll();
        
        Response::success($items, 'Pending sync items retrieved');
    }
}
?>