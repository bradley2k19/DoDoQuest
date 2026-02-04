<?php
/**
 * Database Configuration
 * Handles database connection using PDO
 */

class Database {

    private string $host = "localhost";
    private string $db_name = "treasure_hunt_db";
    private string $username = "root";
    private string $password = "";
    private ?PDO $conn = null;

    public function getConnection(): PDO
    {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};port=3307;dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database connection failed',
                'details' => $e->getMessage()
            ]);
            exit;
        }

        return $this->conn;
    }

    public function closeConnection(): void
    {
        $this->conn = null;
    }
}
