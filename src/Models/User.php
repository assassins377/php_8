<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * User Model
 * 
 * Handles all database operations for users
 */
class User
{
    private PDO $db;
    
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }
    
    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, username, email, password_hash, role, status, two_fa_secret, created_at
            FROM users
            WHERE email = :email
            LIMIT 1
        ');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, username, email, role, status, avatar_path, bio, created_at
            FROM users
            WHERE username = :username
            LIMIT 1
        ');
        $stmt->execute(['username' => $username]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Find user by ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, username, email, role, status, avatar_path, bio, created_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Create new user
     */
    public function create(array $data): int|false
    {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO users (username, email, password_hash, role, status)
                VALUES (:username, :email, :password_hash, :role, :status)
            ');
            $stmt->execute([
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $data['password_hash'] ?? null,
                'role' => $data['role'] ?? 'guest',
                'status' => $data['status'] ?? 'pending',
            ]);
            
            return (int) $this->db->lastInsertId();
        } catch (\Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user
     */
    public function update(int $id, array $data): bool
    {
        try {
            $fields = [];
            $params = ['id' => $id];
            
            $allowedFields = ['username', 'email', 'role', 'status', 'avatar_path', 'bio'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return true;
            }
            
            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user password
     */
    public function updatePassword(int $id, string $passwordHash): bool
    {
        try {
            $stmt = $this->db->prepare('
                UPDATE users
                SET password_hash = :password_hash, updated_at = NOW()
                WHERE id = :id
            ');
            return $stmt->execute([
                'password_hash' => $passwordHash,
                'id' => $id,
            ]);
        } catch (\Exception $e) {
            error_log("Update password error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set 2FA secret
     */
    public function setTwoFASecret(int $id, string $secret): bool
    {
        try {
            $stmt = $this->db->prepare('
                UPDATE users
                SET two_fa_secret = :secret
                WHERE id = :id
            ');
            return $stmt->execute([
                'secret' => $secret,
                'id' => $id,
            ]);
        } catch (\Exception $e) {
            error_log("Set 2FA secret error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete user
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
            return $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all users with pagination
     */
    public function getAll(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->db->prepare('
            SELECT id, username, email, role, status, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get total user count
     */
    public function getCount(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM users');
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get top authors by post count
     */
    public function getTopAuthors(int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT u.id, u.username, u.avatar_path, COUNT(p.id) as post_count
            FROM users u
            LEFT JOIN posts p ON u.id = p.author_id AND p.is_published = 1
            WHERE u.role IN (\'author\', \'admin\', \'moderator\')
            GROUP BY u.id
            ORDER BY post_count DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Check if username exists
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return (int) $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check if email exists
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return (int) $stmt->fetchColumn() > 0;
    }
}
