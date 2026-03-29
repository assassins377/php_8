<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * CSRF Protection Middleware
 * 
 * Implements CSRF token validation for state-changing requests
 */
class CsrfMiddleware implements Middleware
{
    private string $tokenKey = 'csrf_token';
    private int $tokenLength = 32;
    
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate token if not exists
        if (empty($_SESSION[$this->tokenKey])) {
            $_SESSION[$this->tokenKey] = $this->generateToken();
        }
        
        // Validate CSRF token for unsafe methods
        $method = $request->getMethod();
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $this->validateToken($request);
        }
        
        return $handler->handle($request);
    }
    
    /**
     * Generate CSRF token
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes($this->tokenLength));
    }
    
    /**
     * Get current CSRF token
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token from request
     */
    private function validateToken(Request $request): void
    {
        $parsedBody = $request->getParsedBody();
        $headers = $request->getHeaders();
        
        // Check form body first
        $token = $parsedBody[$this->tokenKey] ?? '';
        
        // Check headers (for AJAX requests)
        if (empty($token) && isset($headers['X-Csrf-Token'][0])) {
            $token = $headers['X-Csrf-Token'][0];
        }
        
        // Validate token
        if (empty($token) || !hash_equals($_SESSION[$this->tokenKey], $token)) {
            throw new \RuntimeException('CSRF token validation failed', 403);
        }
    }
    
    /**
     * Regenerate CSRF token
     */
    public static function regenerateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}
