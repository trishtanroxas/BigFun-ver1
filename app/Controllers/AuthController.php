<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class AuthController extends Controller {
    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function showLogin(): void {
        $this->view('auth/login');
    }

    public function signup(): void {
        $this->view('auth/signup');
    }

    public function forgotPassword(): void {
        $this->view('auth/forgot-password');
    }

    public function resetPassword(): void {
        $this->view('auth/reset-password');
    }

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $maxAttempts = 3;

        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            $this->json(['status' => 'error', 'message' => 'Invalid email or password']);
            return;
        }

        if ($user['is_locked']) {
            $this->json(['status' => 'error', 'message' => 'Your account is locked. Please reset your password.']);
            return;
        }

        if (password_verify($password, $user['password'])) {
            if ($user['is_verified'] != 1) {
                $this->json(['status' => 'error', 'message' => 'Please verify your email before logging in.']);
                return;
            }

            // Reset attempts on success
            $this->userModel->updateLoginAttempts((int)$user['id'], 0, 0);

            // Start session and log history
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['logged_in'] = true;

            $this->userModel->logHistory((int)$user['id'], $_SERVER['REMOTE_ADDR']);

            $this->json(['status' => 'success', 'redirect' => 'index.php?route=profile']);
        } else {
            $newAttempts = (int)$user['login_attempts'] + 1;
            $locked = ($newAttempts >= $maxAttempts) ? 1 : 0;
            
            $this->userModel->updateLoginAttempts((int)$user['id'], $newAttempts, $locked);

            if ($locked) {
                $this->json(['status' => 'error', 'message' => 'Account locked after 3 failed attempts.']);
                return;
            }

            $remaining = $maxAttempts - $newAttempts;
            $this->json(['status' => 'error', 'message' => "Invalid email or password. $remaining attempt(s) remaining."]);
        }
    }
}
