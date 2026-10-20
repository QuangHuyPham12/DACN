<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerCredentialVersion
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('customer')->check() && (int) $request->session()->get('customer_auth_version', 1) !== (int) Auth::guard('customer')->user()->auth_version) {
            Auth::guard('customer')->logout();
            $request->session()->forget(['customer_auth_version', 'advisor']);
            $request->session()->regenerate(true);
            $request->session()->regenerateToken();
        }

        return $next($request);
    }
}
