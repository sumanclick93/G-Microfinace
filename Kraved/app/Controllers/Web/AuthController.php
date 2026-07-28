<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\AuthMiddleware;
use App\Core\Controller;
use App\Core\Helpers;
use App\Core\Session;
use App\Models\Cart;
use App\Models\User;

final class AuthController extends Controller
{
    public function loginForm(): void
    {
        $this->view('Storefront/auth/login', [
            'title'     => 'Login',
            'error'     => Session::flash('error'),
            'cartCount' => Cart::count(),
            'fulfillment' => Cart::fulfillment(),
        ], 'Storefront/layouts/main');
    }

    public function login(): void
    {
        Helpers::requireCsrf();
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = (new User())->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Session::flash('error', 'Invalid email or password.');
            $this->redirect('/login');
        }

        Session::regenerate();
        Session::set('user', [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);

        if ($user['role'] === 'admin') {
            $this->redirect('/admin');
        }
        $this->redirect('/');
    }

    public function registerForm(): void
    {
        $this->view('Storefront/auth/register', [
            'title'     => 'Create Account',
            'error'     => Session::flash('error'),
            'cartCount' => Cart::count(),
            'fulfillment' => Cart::fulfillment(),
        ], 'Storefront/layouts/main');
    }

    public function register(): void
    {
        Helpers::requireCsrf();
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || strlen($password) < 6) {
            Session::flash('error', 'Please fill all fields (password min 6 chars).');
            $this->redirect('/register');
        }

        $userModel = new User();
        if ($userModel->findByEmail($email)) {
            Session::flash('error', 'Email already registered.');
            $this->redirect('/register');
        }

        $id = $userModel->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'customer',
        ]);

        Session::regenerate();
        Session::set('user', [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'role' => 'customer',
        ]);
        $this->redirect('/');
    }

    public function logout(): void
    {
        Session::destroy();
        $this->redirect('/');
    }
}
