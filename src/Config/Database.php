<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

/**
 * Database connection manager using PDO
 */
class Database
{
    private static ?PDO $instance = null;
    
    /**
     * Get database connection instance (Singleton pattern)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        
        return self::$instance;
    }
    
    /**
     * Create new PDO connection
     */
    private static function createConnection(): PDO
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $database = $_ENV['DB_DATABASE'] ?? 'secure_blog';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
        ];
        
        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            
            // Set timezone
            $pdo->exec("SET time_zone = '+00:00'");
            
            return $pdo;
        } catch (PDOException $e) {
            // Log error but don't expose details
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Database connection failed");
        }
    }
    
    /**
     * Reset connection (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup(): void
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
