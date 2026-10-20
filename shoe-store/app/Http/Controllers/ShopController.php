<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\Size;
use App\Services\BankTransfer;
use App\Services\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:200', 'category_id' => 'nullable|integer', 'brand_id' => 'nullable|integer', 'size_id' => 'nullable|integer', 'color_id' => 'nullable|integer', 'min_price' => 'nullable|integer|min:0|max:1000000000', 'max_price' => ['nullable', 'integer', 'min:0', 'max:1000000000', Rule::when($request->filled('min_price'), 'gte:min_price')], 'in_stock' => 'nullable|in:1', 'sort' => 'nullable|in:newest,price_asc,price_desc']);
        $variantFilter = function ($q) use ($request) {
            $q->where('is_active', true)->when($request->filled('size_id'), fn ($q) => $q->where('size_id', $request->input('size_id')))
                ->when($request->filled('color_id'), fn ($q) => $q->where('color_id', $request->input('color_id')))
                ->when($request->filled('min_price'), fn ($q) => $q->where('price', '>=', $request->integer('min_price')))
                ->when($request->filled('max_price'), fn ($q) => $q->where('price', '<=', $request->integer('max_price')))
                ->when($request->filled('in_stock'), fn ($q) => $q->whereColumn('stock', '>', 'reserved_stock'));
        };
        $query = Product::with(['images', 'brand', 'category'])->where('is_active', true)->whereHas('variants', $variantFilter)->withMin(['variants' => $variantFilter], 'price');
        foreach (['category_id', 'brand_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }
        if ($q = $request->query('q')) {
            $query->where('name', 'like', '%'.$q.'%');
        }
        match ($request->query('sort')) {
            'price_asc' => $query->orderBy('variants_min_price'), 'price_desc' => $query->orderByDesc('variants_min_price'), default => $query->latest('id')
        };

        return view('shop.index', ['products' => $query->paginate(12)->withQueryString(), 'categories' => Category::all(), 'brands' => $this->brandCatalog(), 'catalogPage' => $request->routeIs('shop.catalog'), 'sizes' => Size::orderBy('name')->get(), 'colors' => Color::orderBy('name')->get(), 'offers' => Coupon::where('is_active', true)->latest('id')->get()->filter(fn ($c) => $c->availableFor(max(1, $c->minimum_order)))->take(3), 'wishlistIds' => Auth::guard('customer')->user()?->wishlist()->pluck('products.id')->all() ?? []]);
    }

    public function brands()
    {
        return view('shop.brands', ['brands' => $this->brandCatalog()]);
    }

    private function brandCatalog()
    {
        $ranges = DB::table('products')->join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->where('products.is_active', true)->where('product_variants.is_active', true)
            ->selectRaw('products.brand_id, COUNT(DISTINCT products.id) AS product_count, MIN(product_variants.price) AS min_price, MAX(product_variants.price) AS max_price')
            ->groupBy('products.brand_id')->get()->keyBy('brand_id');

        return Brand::orderBy('name')->get()->map(function ($brand) use ($ranges) {
            $brand->catalog_count = (int) ($ranges[$brand->id]->product_count ?? 0);
            $brand->catalog_min = isset($ranges[$brand->id]) ? (int) $ranges[$brand->id]->min_price : null;
            $brand->catalog_max = isset($ranges[$brand->id]) ? (int) $ranges[$brand->id]->max_price : null;

            return $brand;
        });
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);
        $product->load(['brand', 'category', 'images', 'variants' => fn ($q) => $q->where('is_active', true)->with(['size', 'color'])]);
        $approved = ProductReview::where('product_id', $product->id)->where('status', 'approved');
        $reviewCount = (clone $approved)->count();
        $reviewAverage = (clone $approved)->avg('rating');
        $reviews = $approved->with('account.customer')->latest('id')->paginate(5, ['*'], 'reviews_page');
        $account = Auth::guard('customer')->user();
        $myReview = $account ? ProductReview::where('product_id', $product->id)->where('customer_account_id', $account->id)->first() : null;
        $canReview = $account && app(CustomerCareController::class)->purchasedOrders($product)->exists();

        $related = Product::with(['brand', 'images'])->where('is_active', true)->where('category_id', $product->category_id)->whereKeyNot($product->id)->withMin(['variants' => fn ($q) => $q->where('is_active', true)], 'price')->whereHas('variants', fn ($q) => $q->where('is_active', true))->limit(4)->get();

        return view('shop.product', compact('product', 'related', 'reviewCount', 'reviewAverage', 'reviews', 'myReview', 'canReview'));
    }

    public function cart(Request $request)
    {
        if (! $request->session()->has('checkout_token')) {
            $request->session()->put('checkout_token', (string) Str::uuid());
        }
        $cart = $request->session()->get('cart', []);
        $variants = ProductVariant::with(['product.images', 'size', 'color'])->whereIn('id', array_keys($cart))->get();

        $subtotal = (int) $variants->sum(fn ($v) => $v->price * $cart[$v->id]);
        $coupon = Coupon::where('code', $request->session()->get('coupon_code', ''))->first();
        $discount = $coupon && $coupon->availableFor($subtotal) ? $coupon->discountFor($subtotal) : 0;
        $shipping = $subtotal > 0 && $subtotal < config('store.free_shipping_from') ? config('store.shipping_fee') : 0;

        return view('shop.cart', ['cart' => $cart, 'variants' => $variants, 'subtotal' => $subtotal, 'discount' => $discount, 'shipping' => $shipping, 'total' => $subtotal - $discount + $shipping, 'customer' => Auth::guard('customer')->user()?->customer, 'coupon' => $coupon]);
    }

    public function add(Request $request)
    {
        $data = $request->validate(['variant_id' => 'required|integer|exists:product_variants,id', 'quantity' => 'required|integer|min:1|max:1000']);
        $variant = ProductVariant::with('product')->findOrFail($data['variant_id']);
        $cart = $request->session()->get('cart', []);
        $quantity = ($cart[$variant->id] ?? 0) + (int) $data['quantity'];
        if (! $variant->is_active || ! $variant->product->is_active || $quantity > $variant->available_stock || $quantity > 1000) {
            return back()->withErrors(['quantity' => 'Sản phẩm không đủ tồn hoặc đã ngừng bán.']);
        }
        if (! isset($cart[$variant->id]) && count($cart) >= 100) {
            return back()->withErrors(['cart' => 'Giỏ hàng có tối đa 100 biến thể.']);
        }
        $cart[$variant->id] = $quantity;
        $request->session()->put('cart', $cart);
        $request->session()->put('checkout_token', (string) Str::uuid());

        return redirect()->route('shop.cart')->with('success', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    public function update(Request $request, ProductVariant $variant)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:1000']);
        $cart = $request->session()->get('cart', []);
        if (! array_key_exists($variant->id, $cart)) {
            abort(404);
        }
        if ((int) $data['quantity'] === 0) {
            unset($cart[$variant->id]);
        } elseif ($data['quantity'] > $variant->available_stock || ! $variant->is_active || ! $variant->product->is_active) {
            return back()->withErrors(['quantity' => 'Số lượng vượt quá tồn kho.']);
        } else {
            $cart[$variant->id] = (int) $data['quantity'];
        }
        $request->session()->put('cart', $cart);
        $request->session()->put('checkout_token', (string) Str::uuid());

        return back()->with('success', 'Đã cập nhật giỏ hàng.');
    }

    public function checkout(Request $request, StoreService $service)
    {
        $request->validate(['checkout_token' => 'required|uuid']);
        abort_unless(hash_equals((string) $request->session()->get('checkout_token', ''), $request->string('checkout_token')->toString()), 419);
        $existing = SalesOrder::where('checkout_token', $request->input('checkout_token'))->first();
        if ($existing) {
            return redirect()->route('shop.payment', $existing->tracking_token);
        }
        $cart = $request->session()->get('cart', []);
        $data = $request->only(['customer_name', 'customer_phone', 'customer_email', 'shipping_address', 'note', 'checkout_token', 'payment_method']);
        $data['items'] = collect($cart)->map(fn ($quantity, $id) => ['variant_id' => $id, 'quantity' => $quantity])->values()->all();
        $data['coupon_code'] = $request->session()->get('coupon_code');
        $order = $service->createSale($data, null, 'online', Auth::guard('customer')->user());
        $request->session()->forget(['cart', 'coupon_code']);

        return redirect()->route('shop.payment', $order->tracking_token)->with('success', 'Đã tạo đơn. Kiểm tra thanh toán và lưu liên kết theo dõi bên dưới.');
    }

    public function order(string $token)
    {
        $order = SalesOrder::with(['items', 'events', 'saleReturn', 'payment'])->where('tracking_token', $token)->where('channel', 'online')->firstOrFail();
        if (app(StoreService::class)->expireUnpaidQr($order->id)) {
            $order->refresh()->load(['items', 'events', 'saleReturn', 'payment']);
        }

        return response()->view('shop.order', compact('order'))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function payment(string $token, BankTransfer $bank)
    {
        $order = SalesOrder::with(['items', 'payment'])->where('tracking_token', $token)->where('channel', 'online')->firstOrFail();
        if (app(StoreService::class)->expireUnpaidQr($order->id)) {
            $order->refresh()->load(['items', 'payment']);
        }
        $payload = $order->payment_method === 'bank_transfer' && $order->status === 'pending' && ! $order->payment ? $bank->payload($order) : null;

        return response()->view('shop.payment', ['order' => $order, 'payload' => $payload, 'reference' => $bank->reference($order)])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function reportTransfer(string $token, StoreService $service)
    {
        $order = SalesOrder::where('tracking_token', $token)->where('channel', 'online')->firstOrFail();
        $service->reportTransfer($order);

        return redirect()->route('shop.payment', $token)->with('success', 'Đã gửi thông báo. Cửa hàng sẽ kiểm tra tiền nhận thực tế; đơn chưa tự động được đánh dấu đã thanh toán.');
    }

    public function cancel(string $token, StoreService $service)
    {
        $order = SalesOrder::where('tracking_token', $token)->where('channel', 'online')->firstOrFail();
        $service->cancelSale($order, true);

        return redirect()->route('shop.order', $token)->with('success', 'Đã hủy đơn và giải phóng hàng giữ chỗ.');
    }

    public function media(string $filename)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9]+\.(jpg|jpeg|png|webp)$/', $filename), 404);
        $path = 'product-images/'.$filename;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), ['X-Content-Type-Options' => 'nosniff']);
    }

    public function coupon(Request $request)
    {
        $data = $request->validate(['coupon_code' => 'nullable|string|max:40|regex:/^[A-Za-z0-9_-]+$/']);
        if (empty($data['coupon_code'])) {
            $request->session()->forget('coupon_code');

            return back()->with('success', 'Đã bỏ mã giảm giá.');
        }
        $code = strtoupper($data['coupon_code']);
        $cart = $request->session()->get('cart', []);
        $subtotal = (int) ProductVariant::whereIn('id', array_keys($cart))->get()->sum(fn ($v) => $v->price * $cart[$v->id]);
        $coupon = Coupon::where('code', $code)->first();
        if (! $coupon || ! $coupon->availableFor($subtotal)) {
            return back()->withErrors(['coupon_code' => 'Mã ưu đãi không hợp lệ, hết hạn, hết lượt hoặc đơn chưa đủ giá trị tối thiểu.']);
        }
        $request->session()->put('coupon_code', $code);
        $request->session()->put('checkout_token', (string) Str::uuid());

        return back()->with('success', 'Đã áp dụng mã '.$code.'.');
    }

    public function guide()
    {
        return view('shop.guide');
    }
}
