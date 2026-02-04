<?php
/**
 * Level System Utility
 * Handles user leveling, XP calculation, and badge rewards
 */

class LevelSystem {

    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Add experience points to user and check for level up
     * @param int $user_id
     * @param int $points
     * @return array
     */
    public function addExperience($user_id, $points) {

        $userQuery = "SELECT level, experience_points, total_points 
                      FROM users 
                      WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($userQuery);
        $stmt->execute([':user_id' => $user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // 🔒 Force numeric types (CRITICAL FIX)
        $currentLevel = (int) $user['level'];
        $currentXP    = (int) $user['experience_points'];
        $points       = (int) $points;

        $newXP = $currentXP + $points;

        // Check for level up
        $levelUpInfo = $this->checkLevelUp($currentLevel, $newXP);

        $newLevel    = (int) $levelUpInfo['new_level'];
        $leveledUp   = (bool) $levelUpInfo['leveled_up'];
        $badgesEarned = [];

        // Update user XP and level
        $updateQuery = "UPDATE users
                        SET experience_points = :xp,
                            level = :level
                        WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($updateQuery);
        $stmt->execute([
            ':xp'      => $newXP,
            ':level'   => $newLevel,
            ':user_id' => $user_id
        ]);

        // Award badges for new levels reached
        if ($leveledUp) {
            for ($level = $currentLevel + 1; $level <= $newLevel; $level++) {
                $badge = $this->awardLevelBadge($user_id, $level);
                if ($badge) {
                    $badgesEarned[] = $badge;
                }
            }
        }

        return [
            'success'         => true,
            'leveled_up'      => $leveledUp,
            'old_level'       => $currentLevel,
            'new_level'       => $newLevel,
            'current_xp'      => $newXP,
            'next_level_xp'   => $this->getNextLevelXP($newLevel),
            'badges_earned'   => $badgesEarned
        ];
    }

    /**
     * Determine if user levels up based on total XP
     */
    private function checkLevelUp($currentLevel, $totalXP) {

        $currentLevel = (int) $currentLevel;
        $totalXP      = (int) $totalXP;

        $query = "SELECT level_number, points_required
                  FROM user_levels
                  WHERE points_required <= :xp
                  ORDER BY level_number DESC
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':xp' => $totalXP]);
        $result = $stmt->fetch();

        if ($result) {
            $resultLevel = (int) $result['level_number'];

            if ($resultLevel > $currentLevel) {
                return [
                    'leveled_up' => true,
                    'new_level'  => $resultLevel
                ];
            }
        }

        return [
            'leveled_up' => false,
            'new_level'  => $currentLevel
        ];
    }

    /**
     * Award badge for reaching a specific level
     */
    private function awardLevelBadge($user_id, $level) {

        $level = (int) $level;

        $badgeQuery = "SELECT b.*
                       FROM badges b
                       INNER JOIN user_levels ul ON b.badge_id = ul.badge_id
                       WHERE ul.level_number = :level";

        $stmt = $this->conn->prepare($badgeQuery);
        $stmt->execute([':level' => $level]);
        $badge = $stmt->fetch();

        if (!$badge) {
            return null;
        }

        $awardQuery = "INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at)
                       VALUES (:user_id, :badge_id, NOW())";

        $stmt = $this->conn->prepare($awardQuery);
        $stmt->execute([
            ':user_id'  => $user_id,
            ':badge_id' => $badge['badge_id']
        ]);

        return $badge;
    }

    /**
     * Get XP required for next level
     */
    private function getNextLevelXP($currentLevel) {

        $currentLevel = (int) $currentLevel;

        $query = "SELECT points_required
                  FROM user_levels
                  WHERE level_number = :next_level";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':next_level' => $currentLevel + 1]);
        $result = $stmt->fetch();

        return $result ? (int) $result['points_required'] : null;
    }

    /**
     * Get user's level progress
     */
    public function getLevelProgress($user_id) {

        $userQuery = "SELECT u.level, u.experience_points, u.total_points,
                             ul.level_name, ul.points_required AS current_level_xp,
                             next_ul.points_required AS next_level_xp,
                             next_ul.level_name AS next_level_name
                      FROM users u
                      INNER JOIN user_levels ul ON u.level = ul.level_number
                      LEFT JOIN user_levels next_ul ON next_ul.level_number = u.level + 1
                      WHERE u.user_id = :user_id";

        $stmt = $this->conn->prepare($userQuery);
        $stmt->execute([':user_id' => $user_id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        // 🔒 Force numeric values
        $userXP          = (int) $data['experience_points'];
        $currentLevelXP = (int) $data['current_level_xp'];
        $nextLevelXP    = $data['next_level_xp'] !== null ? (int) $data['next_level_xp'] : null;

        $xpInCurrentLevel = $userXP - $currentLevelXP;
        $xpNeededForNext  = $nextLevelXP !== null ? ($nextLevelXP - $currentLevelXP) : 0;

        $progressPercentage = $xpNeededForNext > 0
            ? round(($xpInCurrentLevel / $xpNeededForNext) * 100)
            : 100;

        return [
            'level'                => (int) $data['level'],
            'level_name'           => $data['level_name'],
            'experience_points'    => $userXP,
            'total_points'         => (int) $data['total_points'],
            'current_level_xp'     => $currentLevelXP,
            'next_level_xp'        => $nextLevelXP,
            'next_level_name'      => $data['next_level_name'],
            'xp_to_next_level'     => $nextLevelXP !== null ? ($nextLevelXP - $userXP) : 0,
            'progress_percentage'  => $progressPercentage
        ];
    }
}
