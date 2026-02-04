<?php
/**
 * Progress Controller
 * Handles user hunt progress and checkpoint completions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Response.php';

class ProgressController {
    
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function handleRequest($method, $segments) {
        $action = $segments[1] ?? null;
        
        switch ($action) {
            case 'start':
                if ($method === 'POST') {
                    $this->startHunt();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'complete-checkpoint':
                if ($method === 'POST') {
                    $this->completeCheckpoint();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'my-hunts':  // Make sure this case exists
                if ($method === 'GET') {
                    $this->getMyHunts();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'hunt':
                $hunt_id = $segments[2] ?? null;
                if ($method === 'GET' && $hunt_id) {
                    $this->getHuntProgress($hunt_id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'abandon':
                if ($method === 'POST') {
                    $this->abandonHunt();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            default:
                // Check if it's a numeric ID for getting progress by ID
                if (is_numeric($action)) {
                    if ($method === 'GET') {
                        $this->getProgressById($action);
                    } else {
                        Response::error('Method not allowed', 405);
                    }
                } else {
                    Response::error('Invalid progress endpoint', 404);
                }
                break;
        }
    }
    
    /**
     * Start a new hunt
     * POST /api/progress/start
     * Body: { "hunt_id": 1 }
     */
    private function startHunt() {
        $user_id = AuthMiddleware::verifyUser();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $hunt_id = $data['hunt_id'] ?? null;
        if (!$hunt_id) {
            Response::error('hunt_id is required', 422);
        }
        
        // Check if hunt exists and is active
        $huntQuery = "SELECT * FROM treasure_hunts WHERE hunt_id = :hunt_id AND is_active = 1";
        $stmt = $this->conn->prepare($huntQuery);
        $stmt->bindParam(':hunt_id', $hunt_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Hunt not found or inactive', 404);
        }
        
        // Check if user already has progress for this hunt
        $checkQuery = "SELECT * FROM user_hunt_progress 
                      WHERE user_id = :user_id AND hunt_id = :hunt_id";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->execute([':user_id' => $user_id, ':hunt_id' => $hunt_id]);
        
        if ($stmt->rowCount() > 0) {
            $existing = $stmt->fetch();
            if ($existing['status'] === 'completed') {
                Response::error('Hunt already completed', 400);
            } elseif ($existing['status'] === 'in_progress') {
                Response::success([
                    'progress_id' => $existing['progress_id'],
                    'message' => 'Hunt already in progress'
                ], 'Hunt resumed');
            }
        }
        
        // Check active hunts limit (max 10)
        $activeCountQuery = "SELECT COUNT(*) as count FROM user_hunt_progress 
                            WHERE user_id = :user_id AND status = 'in_progress'";
        $stmt = $this->conn->prepare($activeCountQuery);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $activeCount = $stmt->fetch()['count'];
        
        if ($activeCount >= 10) {
            Response::error('Maximum 10 active hunts allowed. Please complete or abandon some hunts.', 400);
        }
        
        // Get first checkpoint
        $cpQuery = "SELECT checkpoint_id FROM checkpoints 
                   WHERE hunt_id = :hunt_id ORDER BY sequence_order ASC LIMIT 1";
        $stmt = $this->conn->prepare($cpQuery);
        $stmt->bindParam(':hunt_id', $hunt_id);
        $stmt->execute();
        $firstCheckpoint = $stmt->fetch();
        
        // Create progress record
        $insertQuery = "INSERT INTO user_hunt_progress 
                       (user_id, hunt_id, status, current_checkpoint_id, started_at, last_activity_at)
                       VALUES (:user_id, :hunt_id, 'in_progress', :checkpoint_id, NOW(), NOW())";
        
        $stmt = $this->conn->prepare($insertQuery);
        $stmt->execute([
            ':user_id' => $user_id,
            ':hunt_id' => $hunt_id,
            ':checkpoint_id' => $firstCheckpoint['checkpoint_id']
        ]);
        
        $progress_id = $this->conn->lastInsertId();
        
        // Update participant count
        $updateHunt = "UPDATE treasure_hunts SET participant_count = participant_count + 1 
                      WHERE hunt_id = :hunt_id";
        $stmt = $this->conn->prepare($updateHunt);
        $stmt->bindParam(':hunt_id', $hunt_id);
        $stmt->execute();
        
        Response::success([
            'progress_id' => $progress_id,
            'current_checkpoint_id' => $firstCheckpoint['checkpoint_id']
        ], 'Hunt started successfully', 201);
    }
    
    /**
     * Complete a checkpoint
     * POST /api/progress/complete-checkpoint
     */
    private function completeCheckpoint() {
        $user_id = AuthMiddleware::verifyUser();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $progress_id = $data['progress_id'] ?? null;
        $checkpoint_id = $data['checkpoint_id'] ?? null;
        $verification_method = $data['verification_method'] ?? null;
        $verification_data = $data['verification_data'] ?? null;
        
        if (!$progress_id || !$checkpoint_id || !$verification_method) {
            Response::error('Missing required fields', 422);
            return;
        }
        
        // Get progress
        $progressQuery = "SELECT * FROM user_hunt_progress 
                        WHERE progress_id = :progress_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($progressQuery);
        $stmt->execute([':progress_id' => $progress_id, ':user_id' => $user_id]);
        
        if ($stmt->rowCount() === 0) {
            Response::error('Progress not found', 404);
            return;
        }
        
        $progress = $stmt->fetch();
        
        if ($progress['status'] !== 'in_progress') {
            Response::error('Hunt is not in progress', 400);
            return;
        }
        
        // Get checkpoint details
        $cpQuery = "SELECT * FROM checkpoints WHERE checkpoint_id = :checkpoint_id";
        $stmt = $this->conn->prepare($cpQuery);
        $stmt->bindParam(':checkpoint_id', $checkpoint_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            Response::error('Checkpoint not found', 404);
            return;
        }
        
        $checkpoint = $stmt->fetch();
        
        // Check if checkpoint already completed
        $completedQuery = "SELECT * FROM checkpoint_completions 
                        WHERE progress_id = :progress_id AND checkpoint_id = :checkpoint_id";
        $stmt = $this->conn->prepare($completedQuery);
        $stmt->execute([':progress_id' => $progress_id, ':checkpoint_id' => $checkpoint_id]);
        
        if ($stmt->rowCount() > 0) {
            Response::error('Checkpoint already completed', 400);
            return;
        }
        
        // Determine if verification is needed
        $is_verified = ($verification_method === 'photo') ? 0 : 1;
        
        // Calculate points (with hint penalty if used)
        $hints_used = $data['hints_used'] ?? 0;
        $points_earned = $checkpoint['points_awarded'];
        if ($hints_used > 0) {
            $points_earned = (int)($points_earned * 0.9); // 10% penalty per hint
        }
        
        // Record completion
        $completeQuery = "INSERT INTO checkpoint_completions 
                        (progress_id, checkpoint_id, verification_method, verification_data,
                        points_earned, hints_used, is_verified)
                        VALUES (:progress_id, :checkpoint_id, :method, :data, :points, :hints, :verified)";
        
        $stmt = $this->conn->prepare($completeQuery);
        $stmt->execute([
            ':progress_id' => $progress_id,
            ':checkpoint_id' => $checkpoint_id,
            ':method' => $verification_method,
            ':data' => json_encode($verification_data),
            ':points' => $points_earned,
            ':hints' => $hints_used,
            ':verified' => $is_verified
        ]);
        
        // Initialize level result
        $levelResult = null;

        // Update progress
        if ($is_verified) {
            // Add points to user
            $updateUser = "UPDATE users SET total_points = total_points + :points WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($updateUser);
            $stmt->execute([':points' => $points_earned, ':user_id' => $user_id]);
            
            // Add experience and check for level up
            require_once __DIR__ . '/../utils/LevelSystem.php';
            $levelSystem = new LevelSystem($this->conn);
            $levelResult = $levelSystem->addExperience($user_id, $points_earned);
            
            // IMPORTANT: Only include level_up in response if user actually leveled up
            if ($levelResult && isset($levelResult['leveled_up']) && $levelResult['leveled_up'] === false) {
                $levelResult = null; // Clear it if no level up occurred
            }
            
            // Update hunt progress points
            $updateProgress = "UPDATE user_hunt_progress 
                            SET points_earned = points_earned + :points,
                                hints_used_count = hints_used_count + :hints,
                                last_activity_at = NOW()
                            WHERE progress_id = :progress_id";
            $stmt = $this->conn->prepare($updateProgress);
            $stmt->execute([
                ':points' => $points_earned,
                ':hints' => $hints_used,
                ':progress_id' => $progress_id
            ]);
        }

        // Get next checkpoint
        $nextCpQuery = "SELECT checkpoint_id FROM checkpoints 
                    WHERE hunt_id = :hunt_id AND sequence_order > :current_order
                    ORDER BY sequence_order ASC LIMIT 1";
        $stmt = $this->conn->prepare($nextCpQuery);
        $stmt->execute([
            ':hunt_id' => $progress['hunt_id'],
            ':current_order' => $checkpoint['sequence_order']
        ]);

        $nextCheckpoint = $stmt->fetch();

        if ($nextCheckpoint) {
            // Update current checkpoint
            $updateCurrent = "UPDATE user_hunt_progress SET current_checkpoint_id = :next_cp 
                            WHERE progress_id = :progress_id";
            $stmt = $this->conn->prepare($updateCurrent);
            $stmt->execute([':next_cp' => $nextCheckpoint['checkpoint_id'], ':progress_id' => $progress_id]);
            
            // Build response - only include level_up if it actually happened
            $responseData = [
                'next_checkpoint_id' => $nextCheckpoint['checkpoint_id'],
                'points_earned' => $points_earned,
                'requires_verification' => !$is_verified
            ];
            
            // Only add level_up to response if user actually leveled up
            if ($levelResult !== null) {
                $responseData['level_up'] = $levelResult;
            }
            
            Response::success($responseData, 'Checkpoint completed');
        } else {
            // Hunt completed!
            $this->completeHunt($progress_id, $user_id, $progress['hunt_id'], $levelResult);
        }
    }

    /**
     * Get user's hunts
     * GET /api/progress/my-hunts?status=in_progress
     */
    private function getMyHunts() {
        $user_id = AuthMiddleware::verifyUser();
        $status = $_GET['status'] ?? null;
        
        $where = "uhp.user_id = :user_id";
        $params = [':user_id' => $user_id];
        
        if ($status) {
            $where .= " AND uhp.status = :status";
            $params[':status'] = $status;
        }
        
        $query = "SELECT uhp.*, th.title, th.difficulty_level, th.reward_points,
                        c.clue_text as current_clue,
                        (SELECT COUNT(*) FROM checkpoints WHERE hunt_id = uhp.hunt_id) as total_checkpoints,
                        (SELECT COUNT(*) FROM checkpoint_completions WHERE progress_id = uhp.progress_id) as completed_checkpoints
                FROM user_hunt_progress uhp
                INNER JOIN treasure_hunts th ON uhp.hunt_id = th.hunt_id
                LEFT JOIN checkpoints c ON uhp.current_checkpoint_id = c.checkpoint_id
                WHERE $where
                ORDER BY uhp.last_activity_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        $hunts = $stmt->fetchAll();
        
        // Calculate completion percentage for each hunt
        foreach ($hunts as &$hunt) {
            $total = (int)$hunt['total_checkpoints'];
            $completed = (int)$hunt['completed_checkpoints'];
            $hunt['completion_percentage'] = $total > 0 ? round(($completed / $total) * 100) : 0;
        }
        
        Response::success($hunts, 'User hunts retrieved');
    }
    
    /**
     * Complete entire hunt
     */
    private function completeHunt($progress_id, $user_id, $hunt_id, $levelResult = null) {
        // Get hunt details
        $huntQuery = "SELECT * FROM treasure_hunts WHERE hunt_id = :hunt_id";
        $stmt = $this->conn->prepare($huntQuery);
        $stmt->bindParam(':hunt_id', $hunt_id);
        $stmt->execute();
        $hunt = $stmt->fetch();
        
        // Award hunt completion points
        $updateUser = "UPDATE users SET total_points = total_points + :points WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($updateUser);
        $stmt->execute([':points' => $hunt['reward_points'], ':user_id' => $user_id]);
        
        // Add experience for hunt completion
        require_once __DIR__ . '/../utils/LevelSystem.php';
        $levelSystem = new LevelSystem($this->conn);
        $huntLevelResult = $levelSystem->addExperience($user_id, $hunt['reward_points']);
        
        // Merge level results if checkpoint also caused level up
        if ($levelResult && isset($levelResult['leveled_up']) && $levelResult['leveled_up'] === true) {
            // Checkpoint caused level up, merge badges
            $allBadges = array_merge(
                $levelResult['badges_earned'] ?? [], 
                $huntLevelResult['badges_earned'] ?? []
            );
            $huntLevelResult['badges_earned'] = $allBadges;
            $huntLevelResult['old_level'] = $levelResult['old_level'];
        } else if ($huntLevelResult && isset($huntLevelResult['leveled_up']) && $huntLevelResult['leveled_up'] === false) {
            // Hunt completion didn't cause level up either
            $huntLevelResult = null;
        }
        
        // Update progress to completed
        $updateProgress = "UPDATE user_hunt_progress 
                        SET status = 'completed',
                            completed_at = NOW(),
                            completion_percentage = 100,
                            points_earned = points_earned + :reward
                        WHERE progress_id = :progress_id";
        $stmt = $this->conn->prepare($updateProgress);
        $stmt->execute([':reward' => $hunt['reward_points'], ':progress_id' => $progress_id]);
        
        // Award badge if configured
        $badge_awarded = false;
        if ($hunt['badge_id']) {
            $badgeQuery = "INSERT IGNORE INTO user_badges (user_id, badge_id, related_hunt_id) 
                        VALUES (:user_id, :badge_id, :hunt_id)";
            $stmt = $this->conn->prepare($badgeQuery);
            $stmt->execute([
                ':user_id' => $user_id,
                ':badge_id' => $hunt['badge_id'],
                ':hunt_id' => $hunt_id
            ]);
            $badge_awarded = $stmt->rowCount() > 0;
        }
        
        // Build response
        $responseData = [
            'hunt_completed' => true,
            'hunt_title' => $hunt['title'],
            'reward_points' => $hunt['reward_points'],
            'total_points_earned' => $hunt['reward_points'],
            'badge_awarded' => $badge_awarded,
            'completion_message' => 'Congratulations! Hunt completed!'
        ];
        
        // Only include level_up if user actually leveled up
        if ($huntLevelResult !== null) {
            $responseData['level_up'] = $huntLevelResult;
        }
        
        Response::success($responseData, 'Congratulations! Hunt completed!');
    }
    
    /**
     * Abandon hunt
     * POST /api/progress/abandon
     */
    private function abandonHunt() {
        $user_id = AuthMiddleware::verifyUser();
        $data = json_decode(file_get_contents("php://input"), true);
        
        $progress_id = $data['progress_id'] ?? null;
        if (!$progress_id) {
            Response::error('progress_id required', 422);
        }
        
        $query = "UPDATE user_hunt_progress SET status = 'abandoned' 
                 WHERE progress_id = :progress_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':progress_id' => $progress_id, ':user_id' => $user_id]);
        
        if ($stmt->rowCount() > 0) {
            Response::success(null, 'Hunt abandoned');
        } else {
            Response::error('Progress not found', 404);
        }
    }
    /**
     * Get detailed progress by progress_id
     * GET /api/progress/{progress_id}
     */
    private function getProgressById($progress_id) {
        $user_id = AuthMiddleware::verifyUser();
        
        // Get progress details
        $query = "SELECT uhp.*, 
                        th.title as hunt_title,
                        th.reward_points as total_points,
                        th.difficulty_level,
                        (SELECT COUNT(*) FROM checkpoints WHERE hunt_id = uhp.hunt_id) as total_checkpoints
                FROM user_hunt_progress uhp
                INNER JOIN treasure_hunts th ON uhp.hunt_id = th.hunt_id
                WHERE uhp.progress_id = :progress_id AND uhp.user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':progress_id' => $progress_id,
            ':user_id' => $user_id
        ]);
        
        if ($stmt->rowCount() === 0) {
            Response::error('Progress not found', 404);
        }
        
        $progress = $stmt->fetch();
        
        // Get all checkpoints with completion status
        $cpQuery = "SELECT c.*,
                        p.name as place_name,
                        p.latitude,
                        p.longitude,
                        p.address,
                        cc.completed_at,
                        cc.points_earned,
                        cc.verification_method,
                        cc.hints_used,
                        CASE 
                            WHEN cc.completion_id IS NOT NULL THEN 'completed'
                            WHEN c.checkpoint_id = :current_checkpoint THEN 'in_progress'
                            ELSE 'pending'
                        END as status
                    FROM checkpoints c
                    INNER JOIN places p ON c.place_id = p.place_id
                    LEFT JOIN checkpoint_completions cc ON c.checkpoint_id = cc.checkpoint_id 
                        AND cc.progress_id = :progress_id
                    WHERE c.hunt_id = :hunt_id
                    ORDER BY c.sequence_order ASC";
        
        $stmt = $this->conn->prepare($cpQuery);
        $stmt->execute([
            ':progress_id' => $progress_id,
            ':hunt_id' => $progress['hunt_id'],
            ':current_checkpoint' => $progress['current_checkpoint_id']
        ]);
        
        $progress['checkpoints'] = $stmt->fetchAll();
        
        // Calculate completion percentage
        $completedCount = count(array_filter($progress['checkpoints'], function($cp) {
            return $cp['status'] === 'completed';
        }));
        
        $progress['completion_percentage'] = $progress['total_checkpoints'] > 0 
            ? round(($completedCount / $progress['total_checkpoints']) * 100) 
            : 0;
        
        Response::success($progress, 'Progress retrieved');
    }
}
?>