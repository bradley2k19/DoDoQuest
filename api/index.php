<?php
/**
 * Treasure Hunt API Router
 * Main entry point for all API requests
 */

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jwt.php';
require_once __DIR__ . '/utils/Response.php';

// Get request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Remove query string and get path
$uri = strtok($requestUri, '?');

// Remove /api/ prefix if present
$uri = str_replace('/treasure_hunt/api/', '', $uri);
$uri = str_replace('/api/', '', $uri);

// Split URI into segments
$segments = array_filter(explode('/', $uri));
$segments = array_values($segments); // Re-index array

// Get endpoint (first segment)
$endpoint = $segments[0] ?? '';

// Route the request
try {
    switch ($endpoint) {
        case 'auth':
            require_once __DIR__ . '/controllers/AuthController.php';
            $controller = new AuthController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'users':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'places':
            require_once __DIR__ . '/controllers/PlaceController.php';
            $controller = new PlaceController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'categories':
            require_once __DIR__ . '/controllers/CategoryController.php';
            $controller = new CategoryController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'hunts':
            require_once __DIR__ . '/controllers/HuntController.php';
            $controller = new HuntController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'checkpoints':
            require_once __DIR__ . '/controllers/CheckpointController.php';
            $controller = new CheckpointController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'progress':
            require_once __DIR__ . '/controllers/ProgressController.php';
            $controller = new ProgressController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'badges':
            require_once __DIR__ . '/controllers/BadgeController.php';
            $controller = new BadgeController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'favorites':
            require_once __DIR__ . '/controllers/FavoriteController.php';
            $controller = new FavoriteController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'photos':
            require_once __DIR__ . '/controllers/PhotoController.php';
            $controller = new PhotoController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'sync':
            require_once __DIR__ . '/controllers/SyncController.php';
            $controller = new SyncController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'admin':
            require_once __DIR__ . '/controllers/AdminController.php';
            $controller = new AdminController();
            $controller->handleRequest($method, $segments);
            break;
            
        case 'leaderboard':
            require_once __DIR__ . '/controllers/LeaderboardController.php';
            $controller = new LeaderboardController();
            $controller->handleRequest($method, $segments);
            break;
        
        case 'levels':
            require_once __DIR__ . '/controllers/LevelController.php';
            $controller = new LevelController();
            $controller->handleRequest($method, $segments);
            break;
            
        case '':
            // API root - show available endpoints
            Response::success([
                'version' => '1.0.0',
                'endpoints' => [
                    'auth' => '/api/auth',
                    'users' => '/api/users',
                    'places' => '/api/places',
                    'categories' => '/api/categories',
                    'hunts' => '/api/hunts',
                    'checkpoints' => '/api/checkpoints',
                    'progress' => '/api/progress',
                    'badges' => '/api/badges',
                    'favorites' => '/api/favorites',
                    'photos' => '/api/photos',
                    'sync' => '/api/sync',
                    'leaderboard' => '/api/leaderboard',
                    'admin' => '/api/admin'
                ]
            ], 'Treasure Hunt API v1.0.0');
            break;
            
        default:
            Response::error('Endpoint not found', 404);
            break;
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
?>