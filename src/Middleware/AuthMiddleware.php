<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Services\AuthService;

/**
 * Authentication Middleware
 * 
 * Checks if user is authenticated and adds user data to request attributes
 */
class AuthMiddleware implements Middleware
{
    private AuthService $authService;
    
    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }
    
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Get current user from session
        $user = $this->authService->getCurrentUser();
        
        // Add user to request attributes
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('isGuest', $user === null);
        $request = $request->withAttribute('isLoggedIn', $user !== null);
        
        return $handler->handle($request);
    }
}
