<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! $request->user()?->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Tài khoản đã bị khóa.']);
        }
        abort_unless(in_array($request->user()->role, $roles, true), 403, 'Bạn không có quyền thực hiện thao tác này.');

        return $next($request);
    }
}
