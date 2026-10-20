<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerSession
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('customer')->check()) {
            return redirect()->route('account.login')->with('info', 'Đăng nhập để lưu sản phẩm và theo dõi đơn hàng của bạn.');
        }

        return $next($request);
    }
}
