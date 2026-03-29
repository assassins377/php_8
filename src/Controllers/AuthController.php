<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Services\AuthService;
use App\Models\User;

/**
 * Auth Controller
 * 
 * Handles user authentication (login, logout, register)
 */
class AuthController
{
    private Twig $view;
    private AuthService $authService;
    private User $userModel;
    
    public function __construct(
        ?Twig $view = null,
        ?AuthService $authService = null,
        ?User $userModel = null
    ) {
        $this->view = $view ?? Twig::fromRaw('');
        $this->authService = $authService ?? new AuthService();
        $this->userModel = $userModel ?? new User();
    }
    
    /**
     * Show login page
     */
    public function showLogin(Request $request, Response $response): Response
    {
        // Redirect if already logged in
        if ($this->authService->getCurrentUser()) {
            return $this->redirectToRoute($response, 'home');
        }
        
        return $this->view->render($response, 'pages/auth/login.twig', [
            'csrf_token' => \App\Middleware\CsrfMiddleware::getToken(),
            'error' => $_SESSION['login_error'] ?? null,
        ]);
    }
    
    /**
     * Process login form
     */
    public function login(Request $request, Response $response): Response
    {
        // Clear previous error
        unset($_SESSION['login_error']);
        
        $data = $request->getParsedBody();
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';
        
        // Validate input
        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Введите email и пароль';
            return $this->redirectToRoute($response, 'login');
        }
        
        // Authenticate
        $user = $this->authService->authenticate($email, $password);
        
        if (!$user) {
            $_SESSION['login_error'] = 'Неверный email или пароль';
            return $this->redirectToRoute($response, 'login');
        }
        
        // Check if 2FA is required (for admin/moderator)
        if (in_array($user['role'], ['admin', 'moderator'], true) && !empty($user['two_fa_secret'])) {
            $_SESSION['2fa_pending_user_id'] = $user['id'];
            return $this->redirectToRoute($response, '2fa');
        }
        
        // Login successful
        $this->authService->login($user);
        
        return $this->redirectToRoute($response, 'home');
    }
    
    /**
     * Show 2FA verification page
     */
    public function show2FA(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['2fa_pending_user_id'])) {
            return $this->redirectToRoute($response, 'login');
        }
        
        return $this->view->render($response, 'pages/auth/2fa.twig', [
            'csrf_token' => \App\Middleware\CsrfMiddleware::getToken(),
            'error' => $_SESSION['2fa_error'] ?? null,
        ]);
    }
    
    /**
     * Verify 2FA code
     */
    public function verify2FA(Request $request, Response $response): Response
    {
        unset($_SESSION['2fa_error']);
        
        if (!isset($_SESSION['2fa_pending_user_id'])) {
            return $this->redirectToRoute($response, 'login');
        }
        
        $data = $request->getParsedBody();
        $code = $data['code'] ?? '';
        
        // TODO: Implement TOTP verification using pragmarx/google2fa
        // For now, placeholder implementation
        
        $userId = $_SESSION['2fa_pending_user_id'];
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            unset($_SESSION['2fa_pending_user_id']);
            return $this->redirectToRoute($response, 'login');
        }
        
        // Verify code (placeholder - implement with Google2FA library)
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $valid = $google2fa->verifyKey($user['two_fa_secret'], $code);
        
        if (!$valid) {
            $_SESSION['2fa_error'] = 'Неверный код подтверждения';
            return $this->redirectToRoute($response, '2fa');
        }
        
        // Clear 2FA pending and login
        unset($_SESSION['2fa_pending_user_id']);
        $this->authService->login($user);
        
        return $this->redirectToRoute($response, 'home');
    }
    
    /**
     * Show registration page
     */
    public function showRegister(Request $request, Response $response): Response
    {
        if ($this->authService->getCurrentUser()) {
            return $this->redirectToRoute($response, 'home');
        }
        
        return $this->view->render($response, 'pages/auth/register.twig', [
            'csrf_token' => \App\Middleware\CsrfMiddleware::getToken(),
            'errors' => $_SESSION['register_errors'] ?? [],
            'old' => $_SESSION['register_old'] ?? [],
        ]);
    }
    
    /**
     * Process registration form
     */
    public function register(Request $request, Response $response): Response
    {
        unset($_SESSION['register_errors'], $_SESSION['register_old']);
        
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';
        
        $errors = [];
        
        // Validate input
        if (strlen($username) < 3) {
            $errors['username'] = 'Имя пользователя должно быть не менее 3 символов';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }
        
        if (strlen($password) < 8) {
            $errors['password'] = 'Пароль должен быть не менее 8 символов';
        }
        
        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Пароли не совпадают';
        }
        
        // Check uniqueness
        if ($this->userModel->usernameExists($username)) {
            $errors['username'] = 'Это имя пользователя уже занято';
        }
        
        if ($this->userModel->emailExists($email)) {
            $errors['email'] = 'Этот email уже зарегистрирован';
        }
        
        if (!empty($errors)) {
            $_SESSION['register_errors'] = $errors;
            $_SESSION['register_old'] = [
                'username' => $username,
                'email' => $email,
            ];
            return $this->redirectToRoute($response, 'register');
        }
        
        // Create user
        $bcryptCost = (int) ($_ENV['BCRYPT_COST'] ?? 12);
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $bcryptCost]);
        
        $userId = $this->userModel->create([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => 'guest',
            'status' => 'active',
        ]);
        
        if (!$userId) {
            $_SESSION['register_errors'] = ['general' => 'Ошибка при регистрации'];
            return $this->redirectToRoute($response, 'register');
        }
        
        // Auto-login after registration
        $user = $this->userModel->findById($userId);
        $this->authService->login($user);
        
        return $this->redirectToRoute($response, 'home');
    }
    
    /**
     * Logout user
     */
    public function logout(Request $request, Response $response): Response
    {
        $this->authService->logout();
        return $this->redirectToRoute($response, 'home');
    }
    
    /**
     * Helper method to redirect
     */
    private function redirectTo(Response $response, string $url): Response
    {
        return $response->withHeader('Location', $url)->withStatus(302);
    }
    
    /**
     * Helper method to redirect using route name
     */
    private function redirectToRoute(Response $response, string $routeName, array $params = []): Response
    {
        // This would use Slim's route parser in real implementation
        $url = '/' . $routeName;
        return $this->redirectTo($response, $url);
    }
}
