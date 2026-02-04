<?php
/**
 * Authentication Controller
 * Handles user registration, login, and password reset
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class AuthController {
    
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Handle authentication requests
     */
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? '';
        
        switch ($action) {
            case 'register':
                if ($method === 'POST') {
                    $this->register();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'login':
                if ($method === 'POST') {
                    $this->login();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'forgot-password':
                if ($method === 'POST') {
                    $this->forgotPassword();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'reset-password':
                if ($method === 'POST') {
                    $this->resetPassword();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            default:
                Response::error('Invalid authentication endpoint', 404);
        }
    }
    
    /**
     * Register new user
     * POST /api/auth/register
     */
    private function register() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $validator = new Validator();
        
        // Validate inputs
        $validator->required('username', $data['username'] ?? '');
        $validator->minLength('username', $data['username'] ?? '', 3, 'Username');
        $validator->maxLength('username', $data['username'] ?? '', 50, 'Username');
        
        $validator->required('email', $data['email'] ?? '');
        $validator->email('email', $data['email'] ?? '');
        
        $validator->required('password', $data['password'] ?? '');
        $validator->minLength('password', $data['password'] ?? '', 6, 'Password');
        
        $validator->required('full_name', $data['full_name'] ?? '');
        
        $validator->required('user_type', $data['user_type'] ?? '');
        $validator->enum('user_type', $data['user_type'] ?? '', ['tourist', 'student', 'local']);
        
        if (!$validator->isValid()) {
            Response::error('Validation failed', 422, $validator->getErrors());
        }
        
        // Sanitize inputs
        $username = Validator::sanitize($data['username']);
        $email = strtolower(trim($data['email']));
        $password = $data['password'];
        $full_name = Validator::sanitize($data['full_name']);
        $user_type = $data['user_type'];
        
        // Check if email already exists
        $query = "SELECT user_id FROM users WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            Response::error('Email already registered', 409);
        }
        
        // Check if username already exists
        $query = "SELECT user_id FROM users WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            Response::error('Username already taken', 409);
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert user
        $query = "INSERT INTO users (username, email, password, full_name, user_type) 
                  VALUES (:username, :email, :password, :full_name, :user_type)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':user_type', $user_type);
        
        if ($stmt->execute()) {
            $user_id = $this->conn->lastInsertId();
            
            // Generate JWT token
            $token = JWT::encode([
                'user_id' => $user_id,
                'email' => $email,
                'username' => $username,
                'user_type' => $user_type
            ]);
            
            Response::success([
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email,
                'full_name' => $full_name,
                'user_type' => $user_type,
                'token' => $token
            ], 'Registration successful', 201);
        } else {
            Response::error('Registration failed', 500);
        }
    }
    
    /**
     * User login
     * POST /api/auth/login
     */
    private function login() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $validator = new Validator();
        $validator->required('email', $data['email'] ?? '');
        $validator->required('password', $data['password'] ?? '');
        
        if (!$validator->isValid()) {
            Response::error('Validation failed', 422, $validator->getErrors());
        }
        
        $email = strtolower(trim($data['email']));
        $password = $data['password'];
        
        // Get user
        $query = "SELECT * FROM users WHERE email = :email AND account_status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Invalid credentials', 401);
        }
        
        $user = $stmt->fetch();
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            Response::error('Invalid credentials', 401);
        }
        
        // Update last active
        $updateQuery = "UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE user_id = :user_id";
        $updateStmt = $this->conn->prepare($updateQuery);
        $updateStmt->bindParam(':user_id', $user['user_id']);
        $updateStmt->execute();
        
        // Generate JWT token
        $token = JWT::encode([
            'user_id' => $user['user_id'],
            'email' => $user['email'],
            'username' => $user['username'],
            'user_type' => $user['user_type']
        ]);
        
        Response::success([
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'user_type' => $user['user_type'],
            'total_points' => $user['total_points'],
            'level' => $user['level'],
            'token' => $token
        ], 'Login successful');
    }
    
    /**
     * Request password reset
     * POST /api/auth/forgot-password
     */
    private function forgotPassword() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $validator = new Validator();
        $validator->required('email', $data['email'] ?? '');
        $validator->email('email', $data['email'] ?? '');
        
        if (!$validator->isValid()) {
            Response::error('Validation failed', 422, $validator->getErrors());
        }
        
        $email = strtolower(trim($data['email']));
        
        // Check if user exists
        $query = "SELECT user_id FROM users WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            // Don't reveal if email exists or not for security
            Response::success(null, 'If the email exists, a reset token has been generated');
        }
        
        // Generate reset token (valid for 1 hour)
        $reset_token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $updateQuery = "UPDATE users 
                       SET password_reset_token = :token, 
                           password_reset_expires = :expires 
                       WHERE email = :email";
        
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bindParam(':token', $reset_token);
        $stmt->bindParam(':expires', $expires);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        // In production, send email with reset link
        // For now, return the token (REMOVE IN PRODUCTION!)
        Response::success([
            'reset_token' => $reset_token
        ], 'Password reset token generated. Check your email.');
    }
    
    /**
     * Reset password with token
     * POST /api/auth/reset-password
     */
    private function resetPassword() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $validator = new Validator();
        $validator->required('token', $data['token'] ?? '');
        $validator->required('new_password', $data['new_password'] ?? '');
        $validator->minLength('new_password', $data['new_password'] ?? '', 6, 'New password');
        
        if (!$validator->isValid()) {
            Response::error('Validation failed', 422, $validator->getErrors());
        }
        
        $token = $data['token'];
        $new_password = $data['new_password'];
        
        // Find user with valid token
        $query = "SELECT user_id FROM users 
                  WHERE password_reset_token = :token 
                  AND password_reset_expires > NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Invalid or expired reset token', 400);
        }
        
        $user = $stmt->fetch();
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        // Update password and clear reset token
        $updateQuery = "UPDATE users 
                       SET password = :password, 
                           password_reset_token = NULL, 
                           password_reset_expires = NULL 
                       WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':user_id', $user['user_id']);
        
        if ($stmt->execute()) {
            Response::success(null, 'Password reset successful');
        } else {
            Response::error('Password reset failed', 500);
        }
    }
}
?>