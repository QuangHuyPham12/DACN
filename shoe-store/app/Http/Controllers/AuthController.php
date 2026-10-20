<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function form()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = mb_strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Thử đăng nhập quá nhiều lần. Vui lòng thử lại sau '.RateLimiter::availableIn($key).' giây.'])->onlyInput('email');
        }
        if (Auth::attempt($data + ['is_active' => true])) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }
        RateLimiter::hit($key, 60);

        return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function passwordForm()
    {
        return view('auth.password', ['pageTitle' => 'Đổi mật khẩu']);
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $request->user()->update(['password' => Hash::make($data['password'])]);
        $request->session()->regenerate();

        return back()->with('success', 'Đã cập nhật mật khẩu.');
    }
}
