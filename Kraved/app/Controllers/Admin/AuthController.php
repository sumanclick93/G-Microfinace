<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuthMiddleware;
use App\Core\Controller;
use App\Core\Helpers;
use App\Core\Session;
use App\Models\User;

final class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (AuthMiddleware::isAdmin()) {
            $this->redirect('/admin');
        }
        $this->view('Admin/auth/login', [
            'title' => 'Admin Login',
            'error' => Session::flash('error'),
        ]);
    }

    public function login(): void
    {
        Helpers::requireCsrf();
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = (new User())->findByEmail($email);
        if (!$user || $user['role'] !== 'admin' || !password_verify($password, $user['password_hash'])) {
            Session::flash('error', 'Invalid admin credentials.');
            $this->redirect('/admin/login');
        }

        Session::regenerate();
        Session::set('user', [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);
        $this->redirect('/admin');
    }

    public function logout(): void
    {
        Session::destroy();
        $this->redirect('/admin/login');
    }
}
