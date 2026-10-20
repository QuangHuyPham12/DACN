<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class CustomerAccountController extends Controller
{
    public function form(Request $request)
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('account.index');
        }

        return view('shop.account-auth', ['register' => $request->routeIs('account.register'), 'next' => in_array($request->query('next'), ['cart', 'wishlist', 'shop']) ? $request->query('next') : 'account']);
    }

    public function register(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => 'required|string|max:150', 'email' => 'required|email|max:255|unique:customer_accounts,email', 'phone' => 'required|string|regex:/^[0-9+() .-]{8,30}$/', 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $account = DB::transaction(function () use ($data) {
            $customer = Customer::create(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone']]);

            return CustomerAccount::create(['customer_id' => $customer->id, 'email' => $data['email'], 'password' => $data['password']]);
        });
        Auth::guard('customer')->login($account);
        $request->session()->regenerate();
        $request->session()->put('customer_auth_version', $account->auth_version ?? 1);

        return $this->afterLogin($request)->with('success', 'Chào mừng bạn đến với SOLE! Tài khoản đã được tạo.');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $data['email'] = mb_strtolower(trim($data['email']));
        $key = 'customer|'.$data['email'].'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Bạn thử quá nhiều lần. Vui lòng thử lại sau một phút.'])->onlyInput('email');
        }
        if (! Auth::guard('customer')->attempt($data)) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'Email hoặc mật khẩu chưa đúng.'])->onlyInput('email');
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('customer_auth_version', Auth::guard('customer')->user()->auth_version);

        return $this->afterLogin($request)->with('success', 'Đăng nhập thành công.');
    }

    private function afterLogin(Request $request)
    {
        return match ($request->input('next')) {
            'cart' => redirect()->route('shop.cart'),
            'shop' => redirect()->route('shop.catalog'),
            'wishlist' => redirect()->to(route('account.index').'#yeu-thich'),
            default => redirect()->route('account.index'),
        };
    }

    public function index()
    {
        $account = Auth::guard('customer')->user();

        return response()->view('shop.account', ['account' => $account->load('customer'), 'orders' => $account->customer->orders()->with(['saleReturn', 'payment'])->latest('id')->paginate(8), 'wishlist' => $account->wishlist()->with(['brand', 'images'])->withMin(['variants' => fn ($q) => $q->where('is_active', true)], 'price')->get()])->header('Cache-Control', 'private, no-store');
    }

    public function profile(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'phone' => 'required|string|regex:/^[0-9+() .-]{8,30}$/', 'address' => 'nullable|string|max:255']);
        Auth::guard('customer')->user()->customer->update($data);

        return back()->with('success', 'Đã lưu thông tin nhận hàng.');
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|current_password:customer', 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $account = Auth::guard('customer')->user();
        $account->forceFill(['password' => $data['password'], 'auth_version' => $account->auth_version + 1])->save();
        $request->session()->put('customer_auth_version', $account->auth_version);
        $request->session()->regenerate();

        return back()->with('success', 'Đã đổi mật khẩu.');
    }

    public function wishlist(Product $product)
    {
        abort_unless($product->is_active, 404);
        $account = Auth::guard('customer')->user();
        // syncWithoutDetaching is idempotent; removal has a separate endpoint.
        DB::table('wishlists')->insertOrIgnore(['customer_account_id' => $account->id, 'product_id' => $product->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Đã lưu vào danh sách yêu thích.');
    }

    public function removeWishlist(Product $product)
    {
        Auth::guard('customer')->user()->wishlist()->detach($product->id);

        return back()->with('success', 'Đã bỏ lưu sản phẩm.');
    }

    public function cancel(SalesOrder $order, StoreService $service)
    {
        abort_unless($order->customer_id === Auth::guard('customer')->user()->customer_id && $order->channel === 'online', 404);
        if ($order->status !== 'pending' || $order->fulfillment_status !== 'pending') {
            return back()->withErrors(['order' => 'Chỉ có thể tự hủy đơn trước khi cửa hàng xác nhận.']);
        }
        $service->cancelSale($order, true);

        return back()->with('success', 'Đã hủy đơn hàng.');
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->forget('advisor');
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('shop.index');
    }
}
