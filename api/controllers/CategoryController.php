<?php
/**
 * Category Controller
 * Handles category operations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class CategoryController {
    
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        $id = $segments[1] ?? null;
        
        if ($id && is_numeric($id)) {
            if ($method === 'GET') {
                $this->getById($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif (!$id) {
            if ($method === 'GET') {
                $this->getAll();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Invalid category endpoint', 404);
        }
    }
    
    /**
     * Get all categories
     * GET /api/categories
     */
    private function getAll() {
        $query = "SELECT c.*, 
                         COUNT(DISTINCT pc.place_id) as place_count
                  FROM categories c
                  LEFT JOIN place_category pc ON c.category_id = pc.category_id
                  LEFT JOIN places p ON pc.place_id = p.place_id AND p.is_verified = 1 AND p.status = 'approved'
                  GROUP BY c.category_id
                  ORDER BY c.name ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $categories = $stmt->fetchAll();
        
        Response::success($categories, 'Categories retrieved successfully');
    }
    
    /**
     * Get category by ID with places
     * GET /api/categories/{id}
     */
    private function getById($id) {
        // Get category info
        $query = "SELECT * FROM categories WHERE category_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Category not found', 404);
        }
        
        $category = $stmt->fetch();
        
        // Get places in this category
        $placesQuery = "SELECT p.* FROM places p
                       INNER JOIN place_category pc ON p.place_id = pc.place_id
                       WHERE pc.category_id = :id 
                       AND p.is_verified = 1 
                       AND p.status = 'approved'
                       ORDER BY p.created_at DESC
                       LIMIT 20";
        
        $stmt = $this->conn->prepare($placesQuery);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $category['places'] = $stmt->fetchAll();
        
        Response::success($category, 'Category retrieved successfully');
    }
}
?>