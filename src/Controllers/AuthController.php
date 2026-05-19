<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Helpers;

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check()) {
            Helpers::redirect('/');
        }
        Helpers::render('auth/login', ['title' => 'Login', 'noNav' => true]);
    }

    public static function doLogin(): void
    {
        $username = trim((string)Helpers::input('username', ''));
        $password = (string)Helpers::input('password', '');
        if ($username === '' || $password === '') {
            Helpers::flash('error', 'Please enter username and password.');
            Helpers::redirect('/login');
        }
        if (!Auth::attempt($username, $password)) {
            Helpers::flash('error', 'Invalid credentials.');
            Helpers::redirect('/login');
        }
        Helpers::redirect('/');
    }

    public static function logout(): void
    {
        Auth::logout();
        Helpers::redirect('/login');
    }
}
