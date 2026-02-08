<?php
/**
 * Admin Controller
 * Handles admin operations - login, place approval, photo review, hunt approval
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class AdminController {
    
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? null;
        
        switch ($action) {
            case 'login':
                if ($method === 'POST') {
                    $this->login();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'dashboard-stats':
                if ($method === 'GET') {
                    $this->getDashboardStats();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'pending-places':
                if ($method === 'GET') {
                    $this->getPendingPlaces();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'approve-place':
                if ($method === 'POST') {
                    $this->approvePlace();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'pending-photos':
                if ($method === 'GET') {
                    $this->getPendingPhotos();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'review-photo':
                if ($method === 'POST') {
                    $this->reviewPhoto();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'pending-hunts':
                if ($method === 'GET') {
                    $this->getPendingHunts();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'approve-hunt':
                if ($method === 'POST') {
                    $this->approveHunt();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'users':
                if ($method === 'GET') {
                    $this->getUsers();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'user':
                $user_id = $segments[2] ?? null;
                if ($method === 'DELETE' && $user_id) {
                    $this->deleteUser($user_id);
                } elseif ($method === 'PUT' && $user_id) {
                    $this->updateUser($user_id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'create-admin':
                if ($method === 'POST') {
                    $this->createAdminUser();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'place':
                $place_id = $segments[2] ?? null;
                if ($method === 'DELETE' && $place_id) {
                    $this->deletePlace($place_id);
                } elseif ($method === 'PUT' && $place_id) {
                    $this->updatePlace($place_id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'create-place':
                if ($method === 'POST') {
                    $this->createPlace();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'category':
                $category_id = $segments[2] ?? null;
                if ($method === 'DELETE' && $category_id) {
                    $this->deleteCategory($category_id);
                } elseif ($method === 'PUT' && $category_id) {
                    $this->updateCategory($category_id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'create-category':
                if ($method === 'POST') {
                    $this->createCategory();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'hunt':
                $hunt_id = $segments[2] ?? null;
                if ($method === 'DELETE' && $hunt_id) {
                    $this->deleteHunt($hunt_id);
                } elseif ($method === 'PUT' && $hunt_id) {
                    $this->updateHunt($hunt_id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            default:
                Response::error('Invalid admin endpoint', 404);
        }
    }
    
    /**
     * Admin login
     * POST /api/admin/login
     */
    private function login() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            Response::error('Email and password required', 422);
        }
        
        $query = "SELECT * FROM admin_users WHERE email = :email AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Invalid credentials', 401);
        }
        
        $admin = $stmt->fetch();
        
        if (!password_verify($password, $admin['password'])) {
            Response::error('Invalid credentials', 401);
        }
        
        // Update last login
        $updateQuery = "UPDATE admin_users SET last_login = NOW() WHERE admin_id = :admin_id";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->execute([':admin_id' => $admin['admin_id']]);
        
        // Generate JWT
        $token = JWT::encode([
            'admin_id' => $admin['admin_id'],
            'email' => $admin['email'],
            'role' => $admin['role']
        ]);
        
        Response::success([
            'admin_id' => $admin['admin_id'],
            'username' => $admin['username'],
            'email' => $admin['email'],
            'role' => $admin['role'],
            'token' => $token
        ], 'Admin login successful');
    }
    
    /**
     * Get dashboard statistics
     * GET /api/admin/dashboard-stats
     */
    private function getDashboardStats() {
        AuthMiddleware::verifyAdmin();
        
        // Total users
        $usersQuery = "SELECT COUNT(*) as total FROM users WHERE account_status = 'active'";
        $stmt = $this->conn->prepare($usersQuery);
        $stmt->execute();
        $totalUsers = $stmt->fetch()['total'];
        
        // Total places
        $placesQuery = "SELECT COUNT(*) as total FROM places WHERE is_verified = 1";
        $stmt = $this->conn->prepare($placesQuery);
        $stmt->execute();
        $totalPlaces = $stmt->fetch()['total'];
        
        // Total hunts
        $huntsQuery = "SELECT COUNT(*) as total FROM treasure_hunts WHERE is_active = 1";
        $stmt = $this->conn->prepare($huntsQuery);
        $stmt->execute();
        $totalHunts = $stmt->fetch()['total'];
        
        // Pending places
        $pendingPlacesQuery = "SELECT COUNT(*) as total FROM places WHERE status = 'pending'";
        $stmt = $this->conn->prepare($pendingPlacesQuery);
        $stmt->execute();
        $pendingPlaces = $stmt->fetch()['total'];
        
        // Pending photos - UPDATED to use place_photos table
        $pendingPhotosQuery = "SELECT COUNT(*) as total FROM place_photos WHERE status = 'pending'";
        $stmt = $this->conn->prepare($pendingPhotosQuery);
        $stmt->execute();
        $pendingPhotos = $stmt->fetch()['total'];
        
        // Active hunts in progress
        $activeProgressQuery = "SELECT COUNT(*) as total FROM user_hunt_progress WHERE status = 'in_progress'";
        $stmt = $this->conn->prepare($activeProgressQuery);
        $stmt->execute();
        $activeProgress = $stmt->fetch()['total'];

        // Pending hunts
        $pendingHuntsQuery = "SELECT COUNT(*) as total FROM treasure_hunts WHERE is_active = 0";
        $stmt = $this->conn->prepare($pendingHuntsQuery);
        $stmt->execute();
        $pendingHunts = $stmt->fetch()['total'];
        
        Response::success([
            'total_users' => (int)$totalUsers,
            'total_places' => (int)$totalPlaces,
            'total_hunts' => (int)$totalHunts,
            'pending_places' => (int)$pendingPlaces,
            'pending_photos' => (int)$pendingPhotos,
            'pending_hunts' => (int)$pendingHunts,
            'active_hunt_progress' => (int)$activeProgress
        ], 'Dashboard stats retrieved');
    }
    
    /**
     * Get pending places for approval
     * GET /api/admin/pending-places
     */
    private function getPendingPlaces() {
        AuthMiddleware::verifyAdmin();
        
        $query = "SELECT p.*, u.username as created_by_username,
                         GROUP_CONCAT(c.name) as categories
                  FROM places p
                  INNER JOIN users u ON p.created_by_user_id = u.user_id
                  LEFT JOIN place_category pc ON p.place_id = pc.place_id
                  LEFT JOIN categories c ON pc.category_id = c.category_id
                  WHERE p.status = 'pending'
                  GROUP BY p.place_id
                  ORDER BY p.created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $places = $stmt->fetchAll();
        
        Response::success($places, 'Pending places retrieved');
    }
    
    /**
     * Approve or reject place
     * POST /api/admin/approve-place
     */
    private function approvePlace() {
        $admin_id = AuthMiddleware::verifyAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $place_id = $data['place_id'] ?? null;
        $action = $data['action'] ?? null; // 'approve' or 'reject'
        
        if (!$place_id || !$action || !in_array($action, ['approve', 'reject'])) {
            Response::error('Invalid data', 422);
        }
        
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $is_verified = ($action === 'approve') ? 1 : 0;
        
        $query = "UPDATE places 
                  SET status = :status, is_verified = :verified 
                  WHERE place_id = :place_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':status' => $status,
            ':verified' => $is_verified,
            ':place_id' => $place_id
        ]);
        
        if ($stmt->rowCount() > 0) {
            Response::success(null, "Place $action" . "d successfully");
        } else {
            Response::error('Place not found', 404);
        }
    }
    
    /**
     * Get pending photos for review
     * GET /api/admin/pending-photos
     */
    private function getPendingPhotos() {
        AuthMiddleware::verifyAdmin();
        
        $query = "SELECT pp.*, 
                        p.name as place_name,
                        p.city,
                        u.username as uploader_username,
                        u.full_name as uploader_name
                FROM place_photos pp
                INNER JOIN places p ON pp.place_id = p.place_id
                INNER JOIN users u ON pp.user_id = u.user_id
                WHERE pp.status = 'pending'
                ORDER BY pp.uploaded_at ASC
                LIMIT 50";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $photos = $stmt->fetchAll();
        
        Response::success($photos, 'Pending photos retrieved');
    }

    /**
     * Review photo (approve/reject)
     * POST /api/admin/review-photo
     * Body: { "photo_id": 1, "action": "approve" } or { "photo_id": 1, "action": "reject", "reason": "..." }
     */
    private function reviewPhoto() {
        $admin_id = AuthMiddleware::verifyAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $photo_id = $data['photo_id'] ?? null;
        $action = $data['action'] ?? null;
        $reason = $data['reason'] ?? null;
        
        if (!$photo_id || !$action || !in_array($action, ['approve', 'reject'])) {
            Response::error('Invalid data. Required: photo_id and action (approve/reject)', 422);
        }
        
        // Get photo details
        $photoQuery = "SELECT * FROM place_photos WHERE photo_id = :photo_id AND status = 'pending'";
        $stmt = $this->conn->prepare($photoQuery);
        $stmt->execute([':photo_id' => $photo_id]);
        
        if ($stmt->rowCount() === 0) {
            Response::error('Photo not found or already processed', 404);
        }
        
        $photo = $stmt->fetch();
        
        if ($action === 'approve') {
            // Move file from temp to approved directory
            $baseDir = dirname(__DIR__, 2) . '/uploads/places/';
            $tempDir = $baseDir . 'temp/';
            $approvedDir = $baseDir . 'approved/';
            
            $oldPath = $tempDir . $photo['file_name'];
            $newFileName = 'approved_' . $photo['file_name'];
            $newPath = $approvedDir . $newFileName;
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
                            is_primary = 1
                        WHERE photo_id = :photo_id";
            
            $stmt = $this->conn->prepare($updateQuery);
            $stmt->execute([
                ':new_url' => $newUrl,
                ':new_file_name' => $newFileName,
                ':reviewer_id' => $admin_id,
                ':photo_id' => $photo_id
            ]);
            
            // Unset other primary photos for this place
            $unsetQuery = "UPDATE place_photos 
                        SET is_primary = 0 
                        WHERE place_id = :place_id AND photo_id != :photo_id";
            $stmt = $this->conn->prepare($unsetQuery);
            $stmt->execute([
                ':place_id' => $photo['place_id'],
                ':photo_id' => $photo_id
            ]);
            
            // Update place with approved photo
            $placeUpdate = "UPDATE places 
                        SET approved_photo_url = :photo_url 
                        WHERE place_id = :place_id";
            $stmt = $this->conn->prepare($placeUpdate);
            $stmt->execute([
                ':photo_url' => $newUrl,
                ':place_id' => $photo['place_id']
            ]);
            
            Response::success([
                'photo_id' => $photo_id,
                'photo_url' => $newUrl
            ], 'Photo approved successfully');
            
        } else {
            // Reject photo
            if (!$reason) {
                Response::error('Rejection reason is required', 422);
            }
            
            // Delete file
            $baseDir = dirname(__DIR__, 2) . 'uploads/places/';
            $filePath = $baseDir . 'temp/' . $photo['file_name'];
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
                ':reviewer_id' => $admin_id,
                ':photo_id' => $photo_id
            ]);
            
            Response::success(['photo_id' => $photo_id], 'Photo rejected');
        }
    }
    
    /**
     * Get pending hunts
     * GET /api/admin/pending-hunts
     */
    private function getPendingHunts() {
        AuthMiddleware::verifyAdmin();
        
        $query = "SELECT th.*, u.username as created_by,
                         (SELECT COUNT(*) FROM checkpoints WHERE hunt_id = th.hunt_id) as checkpoint_count
                  FROM treasure_hunts th
                  INNER JOIN users u ON th.created_by_user_id = u.user_id
                  WHERE th.is_active = 0
                  ORDER BY th.created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $hunts = $stmt->fetchAll();
        
        Response::success($hunts, 'Pending hunts retrieved');
    }
    
    /**
     * Approve hunt for public listing
     * POST /api/admin/approve-hunt
     */
    private function approveHunt() {
        AuthMiddleware::verifyAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $hunt_id = $data['hunt_id'] ?? null;
        $is_featured = $data['is_featured'] ?? 0;
        
        if (!$hunt_id) {
            Response::error('hunt_id required', 422);
        }
        
        $query = "UPDATE treasure_hunts 
                  SET is_active = 1, is_featured = :featured 
                  WHERE hunt_id = :hunt_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':featured' => $is_featured, ':hunt_id' => $hunt_id]);
        
        Response::success(null, 'Hunt approved and activated');
    }
    
    /**
     * Get all users
     * GET /api/admin/users?page=1&limit=20
     */
    private function getUsers() {
        AuthMiddleware::verifyAdmin();
        
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;
        
        // Count total
        $countQuery = "SELECT COUNT(*) as total FROM users";
        $stmt = $this->conn->prepare($countQuery);
        $stmt->execute();
        $total = $stmt->fetch()['total'];
        
        // Get users
        $query = "SELECT user_id, username, email, full_name, user_type, 
                         total_points, level, account_status, created_at, last_active
                  FROM users
                  ORDER BY created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $users = $stmt->fetchAll();
        
        Response::paginated($users, $total, $page, $limit, 'Users retrieved');
    }
    
    /**
     * Delete user
     * DELETE /api/admin/user/{user_id}
     */
    private function deleteUser($user_id) {
        AuthMiddleware::verifyAdmin();
        
        $query = "UPDATE users SET account_status = 'deleted' WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        
        if ($stmt->rowCount() > 0) {
            Response::success(null, 'User deleted successfully');
        } else {
            Response::error('User not found', 404);
        }
    }
    
    /**
     * Update user
     * PUT /api/admin/user/{user_id}
     */
    private function updateUser($user_id) {
        AuthMiddleware::verifyAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $updates = [];
        $params = [':user_id' => $user_id];
        
        $allowedFields = ['username', 'email', 'full_name', 'user_type', 'account_status', 'total_points'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }
        
        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($params)) {
            Response::success(null, 'User updated successfully');
        } else {
            Response::error('Update failed', 500);
        }
    }
    
    /**
     * Create admin user
     * POST /api/admin/create-admin
     */
    private function createAdminUser() {
        AuthMiddleware::verifyAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $full_name = $data['full_name'] ?? null;
        
        if (!$username || !$email || !$password || !$full_name) {
            Response::error('Missing required fields', 422);
        }
        
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        $query = "INSERT INTO admin_users (username, email, password, full_name, role) 
                  VALUES (:username, :email, :password, :full_name, 'moderator')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hashed_password,
            ':full_name' => $full_name
        ]);
        
        Response::success([
            'admin_id' => $this->conn->lastInsertId()
        ], 'Admin user created successfully', 201);
    }
    
    /**
     * Delete place
     * DELETE /api/admin/place/{place_id}
     */
    private function deletePlace($place_id) {
        AuthMiddleware::verifyAdmin();
        
        $query = "DELETE FROM places WHERE place_id = :place_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':place_id' => $place_id]);
        
        if ($stmt->rowCount() > 0) {
            Response::success(null, 'Place deleted successfully');
        } else {
            Response::error('Place not found', 404);
        }
    }
    
    /**
     * Create place (admin)
     * POST /api/admin/create-place
     */
    private function createPlace() {
        AuthMiddleware::verifyAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $name = $data['name'] ?? null;
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;
        $place_type = $data['place_type'] ?? null;
        $created_by_user_id = $data['created_by_user_id'] ?? 1;
        $category_ids = $data['category_ids'] ?? [2];
        
        if (!$name || !$latitude || !$longitude || !$place_type) {
            Response::error('Missing required fields', 422);
        }
        
        // Insert place
        $query = "INSERT INTO places 
                  (name, description, latitude, longitude, city, place_type, 
                   is_verified, status, created_by_user_id)
                  VALUES (:name, :desc, :lat, :lon, :city, :type, 1, :status, :user_id)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':name' => $data['name'],
            ':desc' => $data['description'] ?? null,
            ':lat' => $latitude,
            ':lon' => $longitude,
            ':city' => $data['city'] ?? null,
            ':type' => $place_type,
            ':status' => $data['status'] ?? 'approved',
            ':user_id' => $created_by_user_id
        ]);
        
        $place_id = $this->conn->lastInsertId();
        
        // Link to categories
        $catQuery = "INSERT INTO place_category (place_id, category_id) VALUES (:place_id, :cat_id)";
        $catStmt = $this->conn->prepare($catQuery);
        
        foreach ($category_ids as $cat_id) {
            $catStmt->execute([':place_id' => $place_id, ':cat_id' => $cat_id]);
        }
        
        Response::success([
            'place_id' => $place_id
        ], 'Place created successfully', 201);
    }
    
    /**
     * Update place
     * PUT /api/admin/place/{place_id}
     */
    private function updatePlace($place_id) {
        AuthMiddleware::verifyAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $updates = [];
        $params = [':place_id' => $place_id];
        
        $allowedFields = ['name', 'description', 'latitude', 'longitude', 'city', 'place_type', 'status', 'is_verified'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        // Auto-verify if status is approved
        if (isset($data['status']) && $data['status'] === 'approved') {
            $updates[] = "is_verified = 1";
        }
        
        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }
        
        $query = "UPDATE places SET " . implode(', ', $updates) . " WHERE place_id = :place_id";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($params)) {
            Response::success(null, 'Place updated successfully');
        } else {
            Response::error('Update failed', 500);
        }
    }
    
    /**
     * Delete hunt
     * DELETE /api/admin/hunt/{hunt_id}
     */
    private function deleteHunt($hunt_id) {
        AuthMiddleware::verifyAdmin();
        
        // First delete associated checkpoints
        $deleteCheckpoints = "DELETE FROM checkpoints WHERE hunt_id = :hunt_id";
        $stmt = $this->conn->prepare($deleteCheckpoints);
        $stmt->execute([':hunt_id' => $hunt_id]);
        
        // Then delete the hunt
        $query = "DELETE FROM treasure_hunts WHERE hunt_id = :hunt_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':hunt_id' => $hunt_id]);
        
        if ($stmt->rowCount() > 0) {
            Response::success(null, 'Hunt and associated checkpoints deleted successfully');
        } else {
            Response::error('Hunt not found', 404);
        }
    }
    
    /**
     * Update hunt
     * PUT /api/admin/hunt/{hunt_id}
     */
    private function updateHunt($hunt_id) {
        AuthMiddleware::verifyAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $updates = [];
        $params = [':hunt_id' => $hunt_id];
        
        $allowedFields = ['title', 'description', 'difficulty_level', 'reward_points', 'is_active', 'is_featured'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }
        
        $query = "UPDATE treasure_hunts SET " . implode(', ', $updates) . " WHERE hunt_id = :hunt_id";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($params)) {
            Response::success(null, 'Hunt updated successfully');
        } else {
            Response::error('Update failed', 500);
        }
    }
    
    /**
     * Create category
     * POST /api/admin/create-category
     */
    private function createCategory() {
        AuthMiddleware::verifyAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $name = $data['name'] ?? null;
        $description = $data['description'] ?? null;
        $icon_url = $data['icon_url'] ?? null;
        
        if (!$name) {
            Response::error('Category name is required', 422);
        }
        
        // Check if category already exists
        $checkQuery = "SELECT category_id FROM categories WHERE name = :name";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->execute([':name' => $name]);
        
        if ($stmt->rowCount() > 0) {
            Response::error('Category with this name already exists', 409);
        }
        
        $query = "INSERT INTO categories (name, description, icon_url) 
                  VALUES (:name, :description, :icon_url)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':icon_url' => $icon_url
        ]);
        
        Response::success([
            'category_id' => $this->conn->lastInsertId()
        ], 'Category created successfully', 201);
    }
    
    /**
     * Update category
     * PUT /api/admin/category/{category_id}
     */
    private function updateCategory($category_id) {
        AuthMiddleware::verifyAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $updates = [];
        $params = [':category_id' => $category_id];
        
        $allowedFields = ['name', 'description', 'icon_url'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }
        
        $query = "UPDATE categories SET " . implode(', ', $updates) . " WHERE category_id = :category_id";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($params)) {
            Response::success(null, 'Category updated successfully');
        } else {
            Response::error('Update failed', 500);
        }
    }
    
    /**
     * Delete category
     * DELETE /api/admin/category/{category_id}
     */
    private function deleteCategory($category_id) {
        AuthMiddleware::verifyAdmin();
        
        // First delete place_category relationships
        $deletePlaceCat = "DELETE FROM place_category WHERE category_id = :category_id";
        $stmt = $this->conn->prepare($deletePlaceCat);
        $stmt->execute([':category_id' => $category_id]);
        
        // Then delete the category
        $query = "DELETE FROM categories WHERE category_id = :category_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':category_id' => $category_id]);
        
        if ($stmt->rowCount() > 0) {
            Response::success(null, 'Category deleted successfully');
        } else {
            Response::error('Category not found', 404);
        }
    }
}