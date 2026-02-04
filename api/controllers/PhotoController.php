<?php
/**
 * PHOTO CONTROLLER
 * Save as: api/controllers/PhotoController.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/FileUpload.php';

class PhotoController {
    private $db;
    private $conn;
    private $fileUpload;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->fileUpload = new FileUpload();
    }
    
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? null;
        
        if ($action === 'upload') {
            if ($method === 'POST') {
                $this->upload();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($action === 'my-photos') {
            if ($method === 'GET') {
                $this->getMyPhotos();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Invalid photo endpoint', 404);
        }
    }
    
    private function upload() {
        $user_id = AuthMiddleware::verifyUser();
        
        if (!isset($_FILES['photo'])) {
            Response::error('No photo file uploaded', 422);
        }
        
        $upload_type = $_POST['upload_type'] ?? 'place_visit';
        $place_id = $_POST['place_id'] ?? null;
        $checkpoint_completion_id = $_POST['checkpoint_completion_id'] ?? null;
        
        if (!in_array($upload_type, ['place_visit', 'checkpoint_challenge'])) {
            Response::error('Invalid upload type', 422);
        }
        
        // Determine upload subfolder
        $subfolder = ($upload_type === 'place_visit') ? 'places' : 'checkpoints';
        
        // Upload file
        $uploadResult = $this->fileUpload->uploadPhoto($_FILES['photo'], $subfolder);
        
        if (!$uploadResult['success']) {
            Response::error($uploadResult['error'], 422);
        }
        
        // Save to database
        $query = "INSERT INTO photo_uploads 
                 (user_id, place_id, checkpoint_completion_id, file_path, file_name, 
                  file_size, upload_type, status)
                 VALUES (:user_id, :place_id, :checkpoint_id, :file_path, :file_name, 
                         :file_size, :upload_type, 'pending')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':user_id' => $user_id,
            ':place_id' => $place_id,
            ':checkpoint_id' => $checkpoint_completion_id,
            ':file_path' => $uploadResult['file_path'],
            ':file_name' => $uploadResult['file_name'],
            ':file_size' => $uploadResult['file_size'],
            ':upload_type' => $upload_type
        ]);
        
        $photo_id = $this->conn->lastInsertId();
        
        Response::success([
            'photo_id' => $photo_id,
            'file_path' => $uploadResult['file_path'],
            'message' => 'Photo uploaded and awaiting admin review'
        ], 'Photo uploaded successfully', 201);
    }
    
    private function getMyPhotos() {
        $user_id = AuthMiddleware::verifyUser();
        $status = $_GET['status'] ?? null;
        
        $where = "user_id = :user_id";
        $params = [':user_id' => $user_id];
        
        if ($status) {
            $where .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        $query = "SELECT * FROM photo_uploads 
                  WHERE $where 
                  ORDER BY uploaded_at DESC 
                  LIMIT 100";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $photos = $stmt->fetchAll();
        
        Response::success($photos, 'Photos retrieved');
    }
}
?>