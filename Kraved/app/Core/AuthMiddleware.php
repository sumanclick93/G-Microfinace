<?php

declare(strict_types=1);

namespace App\Core;

final class AuthMiddleware
{
    public static function requireAdmin(): void
    {
        $user = Session::get('user');
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            Session::flash('error', 'Please log in as admin.');
            Helpers::redirect('/admin/login');
        }
    }

    public static function requireCustomer(): void
    {
        $user = Session::get('user');
        if (!$user) {
            Session::flash('error', 'Please log in to continue.');
            Helpers::redirect('/login');
        }
    }

    public static function user(): ?array
    {
        return Session::get('user');
    }

    public static function check(): bool
    {
        return Session::has('user');
    }

    public static function isAdmin(): bool
    {
        $user = Session::get('user');
        return $user && ($user['role'] ?? '') === 'admin';
    }
}
