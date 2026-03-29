<?php

declare(strict_types=1);

/**
 * Secure Blog - Main Entry Point
 * 
 * PHP 8.4+ required
 */

// Error handling for production
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/errors.log');

// Report all errors in development
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    if (($env['APP_DEBUG'] ?? 'false') === 'true') {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    }
}

// Autoload
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Config\Config;
use App\Config\Database;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AuthMiddleware;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Load configuration
Config::load(__DIR__ . '/..');

// Create app
$app = AppFactory::create();

// Add routing middleware
$app->addRoutingMiddleware();

// Add security headers middleware
$app->add(new SecurityHeadersMiddleware());

// Add session middleware
$app->add(new SessionMiddleware(null, 30));

// Add CSRF middleware
$app->add(new CsrfMiddleware());

// Add auth middleware
$app->add(new AuthMiddleware());

// Set up Twig view
$viewPath = __DIR__ . '/../templates';
$view = Twig::create($viewPath, [
    'cache' => __DIR__ . '/../storage/cache/twig',
    'auto_reload' => true,
    'debug' => Config::get('app.debug', false),
]);

// Add Twig to container via request attribute
$app->add(TwigMiddleware::create($app, $view));

// Custom Twig function for CSRF token
$globalFunction = new \Twig\TwigFunction('csrf_token', function(): string {
    return \App\Middleware\CsrfMiddleware::getToken();
});
$view->addFunction($globalFunction);

// Custom Twig function for asset URL
$assetFunction = new \Twig\TwigFunction('asset', function(string $path): string {
    return '/assets/' . ltrim($path, '/');
});
$view->addFunction($assetFunction);

// Error handling
$errorMiddleware = $app->addErrorMiddleware(
    Config::get('app.debug', false),
    true,
    true
);

// Custom 404 handler
$errorMiddleware->setErrorHandler(404, function ($request, $exception, $response) use ($view) {
    return $view->render($response->withStatus(404), 'errors/404.twig');
});

// Custom 500 handler
$errorMiddleware->setErrorHandler(500, function ($request, $exception, $response) use ($view) {
    error_log("500 Error: " . $exception->getMessage());
    return $view->render($response->withStatus(500), 'errors/500.twig');
});

// ============================================
// ROUTES
// ============================================

// Home routes
$app->get('/', App\Controllers\HomeController::class . ':index')->setName('home');
$app->get('/page/{page}', App\Controllers\HomeController::class . ':index')->setName('home_page');
$app->get('/post/{slug}', App\Controllers\HomeController::class . ':show')->setName('post_show');
$app->get('/tag/{slug}', App\Controllers\HomeController::class . ':byTag')->setName('tag');
$app->get('/tag/{slug}/page/{page}', App\Controllers\HomeController::class . ':byTag')->setName('tag_page');
$app->get('/search', App\Controllers\HomeController::class . ':search')->setName('search');

// Auth routes
$app->get('/login', App\Controllers\AuthController::class . ':showLogin')->setName('login');
$app->post('/login', App\Controllers\AuthController::class . ':login')->setName('login_post');
$app->get('/register', App\Controllers\AuthController::class . ':showRegister')->setName('register');
$app->post('/register', App\Controllers\AuthController::class . ':register')->setName('register_post');
$app->get('/logout', App\Controllers\AuthController::class . ':logout')->setName('logout');
$app->get('/2fa', App\Controllers\AuthController::class . ':show2FA')->setName('2fa');
$app->post('/2fa', App\Controllers\AuthController::class . ':verify2FA')->setName('2fa_verify');

// OAuth routes (placeholders)
$app->get('/auth/google', function ($request, $response) {
    // TODO: Implement Google OAuth
    return $response->write('Google OAuth not implemented');
})->setName('auth_google');
$app->get('/auth/google/callback', function ($request, $response) {
    // TODO: Implement Google OAuth callback
    return $response->write('Google OAuth callback not implemented');
})->setName('auth_google_callback');
$app->get('/auth/github', function ($request, $response) {
    // TODO: Implement GitHub OAuth
    return $response->write('GitHub OAuth not implemented');
})->setName('auth_github');
$app->get('/auth/github/callback', function ($request, $response) {
    // TODO: Implement GitHub OAuth callback
    return $response->write('GitHub OAuth callback not implemented');
})->setName('auth_github_callback');

// Admin routes (protected)
$app->group('/admin', function ($group) {
    $group->get('', App\Controllers\Admin\DashboardController::class . ':index')->setName('admin_dashboard');
    $group->get('/posts', App\Controllers\Admin\PostController::class . ':index')->setName('admin_posts');
    $group->get('/posts/create', App\Controllers\Admin\PostController::class . ':create')->setName('admin_posts_create');
    $group->post('/posts/create', App\Controllers\Admin\PostController::class . ':store')->setName('admin_posts_store');
    $group->get('/posts/{id}/edit', App\Controllers\Admin\PostController::class . ':edit')->setName('admin_posts_edit');
    $group->post('/posts/{id}/update', App\Controllers\Admin\PostController::class . ':update')->setName('admin_posts_update');
    $group->post('/posts/{id}/delete', App\Controllers\Admin\PostController::class . ':delete')->setName('admin_posts_delete');
    $group->get('/users', App\Controllers\Admin\UserController::class . ':index')->setName('admin_users');
    $group->get('/comments', App\Controllers\Admin\CommentController::class . ':index')->setName('admin_comments');
    $group->get('/tags', App\Controllers\Admin\TagController::class . ':index')->setName('admin_tags');
    $group->get('/logs', App\Controllers\Admin\LogController::class . ':index')->setName('admin_logs');
})->add(new App\Middleware\RoleMiddleware(['admin', 'moderator']));

// File upload proxy (files stored outside public)
$app->get('/uploads/{filename}', function ($request, $response, $args) {
    $filename = basename($args['filename']);
    $filepath = __DIR__ . '/../storage/uploads/' . $filename;
    
    if (!file_exists($filepath)) {
        return $response->withStatus(404);
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($filepath);
    
    $body = $response->getBody();
    $body->write(file_get_contents($filepath));
    
    return $response
        ->withHeader('Content-Type', $mimeType)
        ->withHeader('Cache-Control', 'public, max-age=31536000');
})->setName('uploads');

// Run app
$app->run();
