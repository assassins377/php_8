<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;

/**
 * Session Service
 * 
 * Handles database-backed session storage
 */
class SessionService
{
    private PDO $db;
    
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }
    
    /**
     * Read session from database
     */
    public function read(string $sessionId): string|false
    {
        try {
            $stmt = $this->db->prepare('
                SELECT payload
                FROM sessions
                WHERE id = :id
                LIMIT 1
            ');
            $stmt->execute(['id' => $sessionId]);
            $result = $stmt->fetch();
            
            if ($result) {
                // Update last activity
                $updateStmt = $this->db->prepare('
                    UPDATE sessions
                    SET last_activity = :time
                    WHERE id = :id
                ');
                $updateStmt->execute([
                    'time' => time(),
                    'id' => $sessionId,
                ]);
                
                return $result['payload'];
            }
            
            return '';
        } catch (\Exception $e) {
            error_log("Session read error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Write session to database
     */
    public function write(string $sessionId, string $data): bool
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            
            // Check if session exists
            $checkStmt = $this->db->prepare('SELECT id FROM sessions WHERE id = :id');
            $checkStmt->execute(['id' => $sessionId]);
            
            if ($checkStmt->fetch()) {
                // Update existing session
                $stmt = $this->db->prepare('
                    UPDATE sessions
                    SET payload = :payload,
                        user_id = :user_id,
                        ip_address = :ip,
                        last_activity = :time
                    WHERE id = :id
                ');
            } else {
                // Insert new session
                $stmt = $this->db->prepare('
                    INSERT INTO sessions (id, user_id, ip_address, payload, last_activity)
                    VALUES (:id, :user_id, :ip, :payload, :time)
                ');
            }
            
            return $stmt->execute([
                'id' => $sessionId,
                'user_id' => $userId,
                'ip' => $ipAddress,
                'payload' => $data,
                'time' => time(),
            ]);
        } catch (\Exception $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Destroy session
     */
    public function destroy(string $sessionId): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM sessions WHERE id = :id');
            return $stmt->execute(['id' => $sessionId]);
        } catch (\Exception $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Garbage collection - remove old sessions
     */
    public function gc(int $maxLifetime): int|false
    {
        try {
            $stmt = $this->db->prepare('
                DELETE FROM sessions
                WHERE last_activity < :time
            ');
            $stmt->execute(['time' => time() - $maxLifetime]);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("Session GC error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all sessions for a user (for admin panel)
     */
    public function getUserSessions(int $userId): array
    {
        try {
            $stmt = $this->db->prepare('
                SELECT id, ip_address, last_activity, created_at
                FROM sessions
                WHERE user_id = :user_id
                ORDER BY last_activity DESC
            ');
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            error_log("Get user sessions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete all sessions for a user (force logout everywhere)
     */
    public function deleteUserSessions(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM sessions WHERE user_id = :user_id');
            return $stmt->execute(['user_id' => $userId]);
        } catch (\Exception $e) {
            error_log("Delete user sessions error: " . $e->getMessage());
            return false;
        }
    }
}
