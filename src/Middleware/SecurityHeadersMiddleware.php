<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;

/**
 * Security Headers Middleware
 * 
 * Adds security headers to all HTTP responses
 */
class SecurityHeadersMiddleware implements Middleware
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);
        
        // Strict Transport Security (HSTS)
        $response = $response->withHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains'
        );
        
        // Prevent MIME type sniffing
        $response = $response->withHeader('X-Content-Type-Options', 'nosniff');
        
        // Prevent clickjacking
        $response = $response->withHeader('X-Frame-Options', 'DENY');
        
        // XSS Protection
        $response = $response->withHeader('X-XSS-Protection', '1; mode=block');
        
        // Referrer Policy
        $response = $response->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Content Security Policy
        $response = $response->withHeader(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: https:; connect-src 'self';"
        );
        
        // Permissions Policy
        $response = $response->withHeader(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=()'
        );
        
        return $response;
    }
}
