<?php

namespace App\Http\Controllers;

use App\Models\CustomerAccount;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class CustomerPasswordController extends Controller
{
    public function form()
    {
        return response()->view('shop.password-help', ['reset' => false])->header('Cache-Control', 'private, no-store');
    }

    public function send(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => 'required|email|max:255']);
        try {
            Password::broker('customers')->sendResetLink($data);
        } catch (\Throwable $exception) {
            // Do not log the token, recipient, SMTP credentials or raw transport error.
            Log::warning('customer_password_mail_failed', ['type' => get_class($exception)]);
        }

        return back()->with('success', 'Nếu email có tài khoản, hướng dẫn khôi phục sẽ được gửi. Kiểm tra cả thư rác; liên hệ cửa hàng nếu chưa nhận được.');
    }

    public function resetForm(Request $request, string $token)
    {
        return response()->view('shop.password-help', ['reset' => true, 'token' => $token, 'email' => $request->query('email', '')])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function reset(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => 'required|email|max:255', 'token' => 'required|string|max:255', 'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()]]);
        $status = DB::transaction(function () use ($data) {
            if (DB::connection()->getDriverName() === 'sqlite') {
                DB::table('customer_password_reset_tokens')->whereRaw('1 = 0')->update(['email' => DB::raw('email')]);
            }
            DB::table('customer_password_reset_tokens')->where('email', $data['email'])->lockForUpdate()->first();

            return Password::broker('customers')->reset($data, function (CustomerAccount $account, string $password) {
                $account->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'auth_version' => $account->auth_version + 1])->save();
                event(new PasswordReset($account));
            });
        }, 3);
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Liên kết không hợp lệ hoặc đã hết hạn. Hãy yêu cầu liên kết mới.'])->onlyInput('email');
        }
        Auth::guard('customer')->logout();
        $request->session()->forget(['customer_auth_version', 'advisor']);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('account.login')->with('success', 'Đã đặt lại mật khẩu. Vui lòng đăng nhập bằng mật khẩu mới.');
    }
}
