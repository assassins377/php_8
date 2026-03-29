<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Application configuration manager
 */
class Config
{
    private static array $config = [];
    
    /**
     * Load configuration from environment
     */
    public static function load(string $basePath): void
    {
        // Load .env file
        $envFile = $basePath . '/.env';
        if (file_exists($envFile)) {
            $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
            $dotenv->load();
        }
        
        self::$config = [
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'Secure Blog',
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'url' => $_ENV['APP_URL'] ?? 'http://localhost',
                'key' => $_ENV['APP_KEY'] ?? '',
            ],
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                'database' => $_ENV['DB_DATABASE'] ?? 'secure_blog',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            ],
            'session' => [
                'driver' => $_ENV['SESSION_DRIVER'] ?? 'database',
                'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 30),
                'secure' => filter_var($_ENV['SESSION_SECURE'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'mail' => [
                'host' => $_ENV['MAIL_HOST'] ?? '',
                'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
                'username' => $_ENV['MAIL_USERNAME'] ?? '',
                'password' => $_ENV['MAIL_PASSWORD'] ?? '',
                'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
                'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? '',
                'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Secure Blog',
            ],
            'oauth' => [
                'google' => [
                    'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
                    'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
                    'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'] ?? '',
                ],
                'github' => [
                    'client_id' => $_ENV['GITHUB_CLIENT_ID'] ?? '',
                    'client_secret' => $_ENV['GITHUB_CLIENT_SECRET'] ?? '',
                    'redirect_uri' => $_ENV['GITHUB_REDIRECT_URI'] ?? '',
                ],
            ],
            'security' => [
                'bcrypt_cost' => (int) ($_ENV['BCRYPT_COST'] ?? 12),
                'max_login_attempts' => (int) ($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5),
                'login_lockout_minutes' => (int) ($_ENV['LOGIN_LOCKOUT_MINUTES'] ?? 15),
                'rate_limit_requests' => (int) ($_ENV['RATE_LIMIT_REQUESTS'] ?? 60),
                'rate_limit_decay' => (int) ($_ENV['RATE_LIMIT_DECAY'] ?? 1),
            ],
            'upload' => [
                'max_size' => (int) ($_ENV['MAX_FILE_SIZE'] ?? 5242880),
                'allowed_extensions' => explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,gif,webp'),
                'path' => $basePath . ($_ENV['UPLOAD_PATH'] ?? '/storage/uploads'),
            ],
            '2fa' => [
                'issuer_name' => $_ENV['2FA_ISSUER_NAME'] ?? 'SecureBlog',
            ],
        ];
    }
    
    /**
     * Get configuration value by key
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Check if configuration key exists
     */
    public static function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }
        
        return true;
    }
}
