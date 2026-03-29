<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;

/**
 * Role-based Access Control Middleware
 * 
 * Restricts access to routes based on user roles
 */
class RoleMiddleware implements Middleware
{
    private array $allowedRoles;
    
    /**
     * @param array|string $allowedRoles Single role or array of roles
     */
    public function __construct(array|string $allowedRoles)
    {
        $this->allowedRoles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    }
    
    public function process(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user');
        
        // Check if user is logged in
        if ($user === null) {
            return $this->redirectToLogin($request);
        }
        
        // Check if user has required role
        if (!in_array($user['role'], $this->allowedRoles, true)) {
            return $this->handleForbidden($request);
        }
        
        return $handler->handle($request);
    }
    
    /**
     * Redirect to login page
     */
    private function redirectToLogin(Request $request): Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $urlGenerator = $routeContext->getRouteCollector()->getRouteParser();
        
        $uri = $urlGenerator->urlFor('login');
        
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Location', $uri)
            ->withStatus(302);
    }
    
    /**
     * Handle forbidden access
     */
    private function handleForbidden(Request $request): Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $urlGenerator = $routeContext->getRouteCollector()->getRouteParser();
        
        $uri = $urlGenerator->urlFor('error', ['code' => 403]);
        
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Location', $uri)
            ->withStatus(302);
    }
}
