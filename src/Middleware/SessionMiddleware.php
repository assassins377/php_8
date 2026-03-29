<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Services\SessionService;

/**
 * Session Hardening Middleware
 * 
 * Implements secure session handling:
 * - Regenerates session ID after login
 * - Implements sliding timeout
 * - Validates session integrity
 */
class SessionMiddleware implements Middleware
{
    private SessionService $sessionService;
    private int $timeoutMinutes;
    
    public function __construct(?SessionService $sessionService = null, int $timeoutMinutes = 30)
    {
        $this->sessionService = $sessionService ?? new SessionService();
        $this->timeoutMinutes = $timeoutMinutes;
    }
    
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Initialize session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            $this->initSecureSession();
        }
        
        // Check session timeout and validity
        $this->validateSession();
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
        
        return $handler->handle($request);
    }
    
    /**
     * Initialize secure session settings
     */
    private function initSecureSession(): void
    {
        // Get HTTPS status from config
        $secure = filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        // Set session cookie parameters
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        
        // Start session with strict mode
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        
        session_start();
        
        // Set default session values if not exist
        if (!isset($_SESSION['created_at'])) {
            $_SESSION['created_at'] = time();
        }
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }
        if (!isset($_SESSION['ip_address'])) {
            $_SESSION['ip_address'] = $this->getClientIp();
        }
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $this->getUserAgentHash();
        }
    }
    
    /**
     * Validate session security
     */
    private function validateSession(): void
    {
        // Check timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > $this->timeoutMinutes * 60)) {
            $this->destroySession();
            return;
        }
        
        // Check IP address consistency (optional, can be disabled for mobile users)
        if (isset($_SESSION['ip_address']) && 
            $_SESSION['ip_address'] !== $this->getClientIp()) {
            // Log suspicious activity
            error_log("Session IP mismatch: {$_SESSION['ip_address']} vs {$this->getClientIp()}");
        }
        
        // Check User-Agent consistency
        if (isset($_SESSION['user_agent']) && 
            $_SESSION['user_agent'] !== $this->getUserAgentHash()) {
            // Log suspicious activity
            error_log("Session User-Agent mismatch");
        }
    }
    
    /**
     * Regenerate session ID securely
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
    
    /**
     * Destroy session completely
     */
    private function destroySession(): void
    {
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
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
    
    /**
     * Get hash of user agent for session validation
     */
    private function getUserAgentHash(): string
    {
        return hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }
}
