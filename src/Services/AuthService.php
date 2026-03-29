<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;

/**
 * Authentication Service
 * 
 * Handles user authentication, login, logout, and session management
 */
class AuthService
{
    private PDO $db;
    
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }
    
    /**
     * Authenticate user with email and password
     */
    public function authenticate(string $email, string $password): array|false
    {
        // Check for brute force attempts
        if ($this->isIpLocked()) {
            return false;
        }
        
        // Find user by email
        $stmt = $this->db->prepare('
            SELECT id, username, email, password_hash, role, status, two_fa_secret
            FROM users
            WHERE email = :email
            LIMIT 1
        ');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['password_hash']) {
            $this->recordFailedAttempt($email);
            return false;
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($email);
            return false;
        }
        
        // Check if user is banned
        if ($user['status'] === 'banned') {
            return false;
        }
        
        // Check if account is pending activation
        if ($user['status'] === 'pending') {
            return false;
        }
        
        // Clear failed attempts on successful login
        $this->clearFailedAttempts();
        
        return $user;
    }
    
    /**
     * Login user and create session
     */
    public function login(array $user): bool
    {
        // Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        
        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in_at'] = time();
        
        // Log the login action
        $this->logAction($user['id'], 'login', 'User logged in');
        
        return true;
    }
    
    /**
     * Logout current user
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $userId = $_SESSION['user_id'] ?? null;
            
            if ($userId) {
                $this->logAction($userId, 'logout', 'User logged out');
            }
            
            $_SESSION = [];
            
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'] ?? false,
                    $params['httponly']
                );
            }
            
            session_destroy();
        }
    }
    
    /**
     * Get current logged-in user
     */
    public function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        $stmt = $this->db->prepare('
            SELECT id, username, email, role, status, avatar_path, bio, created_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }
    
    /**
     * Check if current user has specific role
     */
    public function hasRole(string|array $roles): bool
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }
        
        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($user['role'], $roles, true);
    }
    
    /**
     * Check if current user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
    
    /**
     * Check if current user is moderator or admin
     */
    public function isModerator(): bool
    {
        return $this->hasRole(['admin', 'moderator']);
    }
    
    /**
     * Check if IP is locked due to too many failed attempts
     */
    private function isIpLocked(): bool
    {
        $ip = $this->getClientIp();
        $maxAttempts = (int) ($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5);
        $lockoutMinutes = (int) ($_ENV['LOGIN_LOCKOUT_MINUTES'] ?? 15);
        
        $stmt = $this->db->prepare('
            SELECT attempts, locked_until
            FROM login_attempts
            WHERE ip_address = :ip
            ORDER BY last_attempt DESC
            LIMIT 1
        ');
        $stmt->execute(['ip' => $ip]);
        $attempt = $stmt->fetch();
        
        if (!$attempt) {
            return false;
        }
        
        // Check if locked until time has passed
        if ($attempt['locked_until'] && strtotime($attempt['locked_until']) > time()) {
            return true;
        }
        
        // Reset if lockout period has expired
        if ($attempt['locked_until'] && strtotime($attempt['locked_until']) <= time()) {
            $this->clearFailedAttempts();
            return false;
        }
        
        return $attempt['attempts'] >= $maxAttempts;
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt(?string $email): void
    {
        $ip = $this->getClientIp();
        $maxAttempts = (int) ($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5);
        $lockoutMinutes = (int) ($_ENV['LOGIN_LOCKOUT_MINUTES'] ?? 15);
        
        // Get existing attempts
        $stmt = $this->db->prepare('
            SELECT id, attempts
            FROM login_attempts
            WHERE ip_address = :ip
            ORDER BY last_attempt DESC
            LIMIT 1
        ');
        $stmt->execute(['ip' => $ip]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $newAttempts = $existing['attempts'] + 1;
            $lockedUntil = null;
            
            if ($newAttempts >= $maxAttempts) {
                $lockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));
            }
            
            $updateStmt = $this->db->prepare('
                UPDATE login_attempts
                SET attempts = :attempts,
                    email = :email,
                    locked_until = :locked_until,
                    last_attempt = NOW()
                WHERE id = :id
            ');
            $updateStmt->execute([
                'attempts' => $newAttempts,
                'email' => $email,
                'locked_until' => $lockedUntil,
                'id' => $existing['id'],
            ]);
        } else {
            $insertStmt = $this->db->prepare('
                INSERT INTO login_attempts (ip_address, email, attempts, last_attempt)
                VALUES (:ip, :email, 1, NOW())
            ');
            $insertStmt->execute([
                'ip' => $ip,
                'email' => $email,
            ]);
        }
    }
    
    /**
     * Clear failed login attempts for current IP
     */
    private function clearFailedAttempts(): void
    {
        $ip = $this->getClientIp();
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE ip_address = :ip');
        $stmt->execute(['ip' => $ip]);
    }
    
    /**
     * Log user action
     */
    private function logAction(int $userId, string $action, ?string $details = null): void
    {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO logs (user_id, action, details, ip_address, user_agent)
                VALUES (:user_id, :action, :details, :ip, :ua)
            ');
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'details' => $details,
                'ip' => $this->getClientIp(),
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log action: " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return trim($ip);
                }
            }
        }
        
        return '0.0.0.0';
    }
}
