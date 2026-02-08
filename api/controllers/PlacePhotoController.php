<?php
/**
 * Place Photo Controller
 * Handles photo uploads and admin approval for places
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';

class PlacePhotoController {
    
    private $db;
    private $conn;
    private $uploadDir;
    private $tempDir;
    private $approvedDir;
    private $maxFileSize = 5242880; // 5MB
    private $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        
        // Set upload directories
        $baseDir = dirname(__DIR__, 2) . '/uploads/places/';
        $this->uploadDir = $baseDir;
        $this->tempDir = $baseDir . 'temp/';
        $this->approvedDir = $baseDir . 'approved/';
        
        // Create directories if they don't exist
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        if (!file_exists($this->approvedDir)) {
            mkdir($this->approvedDir, 0755, true);
        }
    }
    
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? null;
        
        switch ($action) {
            case 'upload':
                if ($method === 'POST') {
                    $this->uploadPhoto();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'pending':
                if ($method === 'GET') {
                    $this->getPendingPhotos();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'approve':
                if ($method === 'POST') {
                    $this->approvePhoto();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'reject':
                if ($method === 'POST') {
                    $this->rejectPhoto();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'place':
                $place_id = $segments[2] ?? null;
                if ($method === 'GET' && $place_id) {
                    $this->getPlacePhotos($place_id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            default:
                Response::error('Invalid photo endpoint', 404);
        }
    }
    
    /**
     * Upload photo for a place
     * POST /api/place-photos/upload
     */
    private function uploadPhoto() {
        $user_id = AuthMiddleware::verifyUser();
        
        // Validate file upload
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            Response::error('No file uploaded or upload error', 422);
            return;
        }
        
        $place_id = $_POST['place_id'] ?? null;
        if (!$place_id) {
            Response::error('place_id is required', 422);
            return;
        }
        
        // Verify place exists
        $placeQuery = "SELECT place_id, name FROM places WHERE place_id = :place_id";
        $stmt = $this->conn->prepare($placeQuery);
        $stmt->execute([':place_id' => $place_id]);
        
        if ($stmt->rowCount() === 0) {
            Response::error('Place not found', 404);
            return;
        }
        
        $file = $_FILES['photo'];
        
        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            Response::error('File size exceeds 5MB limit', 422);
            return;
        }
        
        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            Response::error('Invalid file type. Only JPEG, PNG, and WebP are allowed', 422);
            return;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'place_' . $place_id . '_' . time() . '_' . uniqid() . '.' . $extension;
        $filePath = $this->tempDir . $fileName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            Response::error('Failed to save file', 500);
            return;
        }
        
        // Optimize image (resize if too large)
        $this->optimizeImage($filePath, $mimeType);
        
        // Store in database
        $relativeUrl = '/treasure_hunt/uploads/places/temp/' . $fileName;
        
        $insertQuery = "INSERT INTO place_photos 
                       (place_id, user_id, photo_url, file_name, file_size, mime_type, status)
                       VALUES 
                       (:place_id, :user_id, :photo_url, :file_name, :file_size, :mime_type, 'pending')";
        
        $stmt = $this->conn->prepare($insertQuery);
        $stmt->execute([
            ':place_id' => $place_id,
            ':user_id' => $user_id,
            ':photo_url' => $relativeUrl,
            ':file_name' => $fileName,
            ':file_size' => filesize($filePath),
            ':mime_type' => $mimeType
        ]);
        
        $photo_id = $this->conn->lastInsertId();
        
        Response::success([
            'photo_id' => $photo_id,
            'photo_url' => $relativeUrl,
            'status' => 'pending'
        ], 'Photo uploaded successfully. Awaiting admin approval.', 201);
    }
    
    /**
     * Get pending photos for admin review
     * GET /api/place-photos/pending
     */
    private function getPendingPhotos() {
        $user_id = AuthMiddleware::verifyAdmin();
        
        // Verify user is admin
        // $userQuery = "SELECT user_type FROM users WHERE user_id = :user_id";
        // $stmt = $this->conn->prepare($userQuery);
        // $stmt->execute([':user_id' => $user_id]);
        // $user = $stmt->fetch();
        
        // if ($user['user_type'] !== 'admin') {
        //     Response::error('Admin access required', 403);
        //     return;
        // }
        
        $query = "SELECT pp.*, 
                        p.name as place_name, 
                        p.city,
                        u.username as uploader_username,
                        u.full_name as uploader_name
                 FROM place_photos pp
                 INNER JOIN places p ON pp.place_id = p.place_id
                 INNER JOIN users u ON pp.user_id = u.user_id
                 WHERE pp.status = 'pending'
                 ORDER BY pp.uploaded_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $photos = $stmt->fetchAll();
        
        Response::success($photos, 'Pending photos retrieved');
    }
    
   /**
     * Approve a photo
     * POST /api/place-photos/approve
     */
    private function approvePhoto() {
        $user_id = AuthMiddleware::verifyAdmin();

        // Get JSON input
        $data = json_decode(file_get_contents("php://input"), true);
        $photo_id = $data['photo_id'] ?? null;
        $set_as_primary = $data['set_as_primary'] ?? true;

        if (!$photo_id) {
            Response::error('photo_id is required', 422);
            return;
        }

        // Get photo details
        $photoQuery = "SELECT * FROM place_photos WHERE photo_id = :photo_id AND status = 'pending'";
        $stmt = $this->conn->prepare($photoQuery);
        $stmt->execute([':photo_id' => $photo_id]);

        if ($stmt->rowCount() === 0) {
            Response::error('Photo not found or already processed', 404);
            return;
        }

        $photo = $stmt->fetch();

        // Move file from temp to approved directory
        $oldPath = $this->tempDir . $photo['file_name'];
        $newFileName = 'approved_' . $photo['file_name'];
        $newPath = $this->approvedDir . $newFileName;
        $newUrl = '/treasure_hunt/uploads/places/approved/' . $newFileName;

        if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
        }

        // Update photo status
        $updateQuery = "UPDATE place_photos 
                        SET status = 'approved',
                            photo_url = :new_url,
                            file_name = :new_file_name,
                            reviewed_at = NOW(),
                            reviewed_by = :reviewer_id,
                            is_primary = :is_primary
                        WHERE photo_id = :photo_id";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->execute([
            ':new_url' => $newUrl,
            ':new_file_name' => $newFileName,
            ':reviewer_id' => $user_id,
            ':is_primary' => $set_as_primary ? 1 : 0,
            ':photo_id' => $photo_id
        ]);

        // If setting as primary, update place and unset other primary photos
        if ($set_as_primary) {
            $unsetQuery = "UPDATE place_photos 
                        SET is_primary = 0 
                        WHERE place_id = :place_id AND photo_id != :photo_id";
            $stmt = $this->conn->prepare($unsetQuery);
            $stmt->execute([
                ':place_id' => $photo['place_id'],
                ':photo_id' => $photo_id
            ]);

            $placeUpdate = "UPDATE places 
                            SET approved_photo_url = :photo_url 
                            WHERE place_id = :place_id";
            $stmt = $this->conn->prepare($placeUpdate);
            $stmt->execute([
                ':photo_url' => $newUrl,
                ':place_id' => $photo['place_id']
            ]);
        }

        Response::success([
            'photo_id' => $photo_id,
            'photo_url' => $newUrl,
            'is_primary' => $set_as_primary
        ], 'Photo approved successfully');
    }

    /**
     * Reject a photo
     * POST /api/place-photos/reject
     */
    private function rejectPhoto() {
        $user_id = AuthMiddleware::verifyAdmin();

        // Get JSON input
        $data = json_decode(file_get_contents("php://input"), true);
        $photo_id = $data['photo_id'] ?? null;
        $reason = $data['reason'] ?? 'Photo did not meet quality standards';

        if (!$photo_id) {
            Response::error('photo_id is required', 422);
            return;
        }

        // Get photo details
        $photoQuery = "SELECT * FROM place_photos WHERE photo_id = :photo_id AND status = 'pending'";
        $stmt = $this->conn->prepare($photoQuery);
        $stmt->execute([':photo_id' => $photo_id]);

        if ($stmt->rowCount() === 0) {
            Response::error('Photo not found or already processed', 404);
            return;
        }

        $photo = $stmt->fetch();

        // Delete file
        $filePath = $this->tempDir . $photo['file_name'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Update photo status
        $updateQuery = "UPDATE place_photos 
                        SET status = 'rejected',
                            rejection_reason = :reason,
                            reviewed_at = NOW(),
                            reviewed_by = :reviewer_id
                        WHERE photo_id = :photo_id";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->execute([
            ':reason' => $reason,
            ':reviewer_id' => $user_id,
            ':photo_id' => $photo_id
        ]);

        Response::success(['photo_id' => $photo_id], 'Photo rejected');
    }

    
    /**
     * Get photos for a specific place
     * GET /api/place-photos/place/{place_id}
     */
    private function getPlacePhotos($place_id) {
        $query = "SELECT pp.*, u.username as uploader_username
                 FROM place_photos pp
                 INNER JOIN users u ON pp.user_id = u.user_id
                 WHERE pp.place_id = :place_id AND pp.status = 'approved'
                 ORDER BY pp.is_primary DESC, pp.uploaded_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':place_id' => $place_id]);
        
        $photos = $stmt->fetchAll();
        
        Response::success($photos, 'Place photos retrieved');
    }
    
    /**
     * Verify user is admin
     */
    private function verifyAdmin($user_id) {
        $userQuery = "SELECT user_type FROM users WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($userQuery);
        $stmt->execute([':user_id' => $user_id]);
        $user = $stmt->fetch();
        
        if ($user['user_type'] !== 'admin') {
            Response::error('Admin access required', 403);
            exit;
        }
    }
    
    /**
     * Optimize image (resize if too large)
     */
    private function optimizeImage($filePath, $mimeType) {
        $maxWidth = 1200;
        $maxHeight = 800;
        
        // Get current dimensions
        list($width, $height) = getimagesize($filePath);
        
        // Check if resize is needed
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return; // No resize needed
        }
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
        
        // Create image resource
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                $source = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($filePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($filePath);
                break;
            default:
                return;
        }
        
        // Create new image
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        if ($mimeType === 'image/png') {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
        }
        
        // Resize
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save optimized image
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                imagejpeg($destination, $filePath, 85);
                break;
            case 'image/png':
                imagepng($destination, $filePath, 8);
                break;
            case 'image/webp':
                imagewebp($destination, $filePath, 85);
                break;
        }
        
        // Free memory
        imagedestroy($source);
        imagedestroy($destination);
    }
}