<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\Size;
use App\Models\User;
use App\Services\StoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExtendedStoreFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private ProductVariant $variant;

    private StoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $product = Product::create(['name' => 'Giày thử mở rộng', 'category_id' => Category::create(['name' => 'Sneaker'])->id, 'brand_id' => Brand::create(['name' => 'SOLE'])->id]);
        $this->variant = $product->variants()->create(['sku' => 'EXT-40', 'size_id' => Size::create(['name' => '40'])->id, 'color_id' => Color::create(['name' => 'Trắng'])->id, 'price' => 750000, 'is_active' => true]);
        $this->variant->forceFill(['stock' => 10])->save();
        InventoryMovement::create(['product_variant_id' => $this->variant->id, 'user_id' => $this->admin->id, 'kind' => 'adjustment', 'quantity' => 10, 'balance_after' => 10, 'reference_type' => 'fixture', 'reference_id' => 1]);
        $this->service = app(StoreService::class);
    }

    private function account(string $email = 'customer@example.com'): CustomerAccount
    {
        $customer = Customer::create(['name' => 'Khách thử', 'phone' => '0901234567', 'email' => $email]);

        return CustomerAccount::create(['customer_id' => $customer->id, 'email' => $email, 'password' => 'Customer123']);
    }

    private function sale(string $channel = 'pos', array $extra = [], ?CustomerAccount $account = null): SalesOrder
    {
        return $this->service->createSale(array_merge(['customer_name' => 'Người nhận', 'customer_phone' => '0901234567', 'shipping_address' => '123 Đường thử', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]], $extra), $channel === 'pos' ? $this->staff : null, $channel, $account);
    }

    private function coupon(array $extra = []): Coupon
    {
        return Coupon::create(array_merge(['code' => 'TEST10', 'name' => 'Ưu đãi thử', 'type' => 'percent', 'value' => 10, 'minimum_order' => 500000, 'maximum_discount' => 50000, 'usage_limit' => 1], $extra));
    }

    private function fails(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected business validation to reject the operation.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_customer_registration_hashes_password_and_cannot_enter_staff_area(): void
    {
        $this->post('/tai-khoan/dang-ky', ['name' => 'Khách mới', 'phone' => '0901234567', 'email' => 'New@Example.com', 'password' => 'Customer123', 'password_confirmation' => 'Customer123'])->assertRedirect(route('account.index'));
        $account = CustomerAccount::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Customer123', $account->password));
        $this->assertAuthenticatedAs($account, 'customer');
        $this->get('/tai-khoan')->assertOk()->assertSee('Khách mới');
        $this->get('/admin')->assertRedirect(route('login'));
        $this->post('/tai-khoan/dang-xuat')->assertRedirect(route('shop.index'));
        $this->assertGuest('customer');
    }

    public function test_customer_login_is_separate_and_profile_and_password_require_authentication(): void
    {
        $account = $this->account();
        $this->put('/tai-khoan/ho-so', ['name' => 'Attack'])->assertRedirect(route('account.login'));
        $this->post('/tai-khoan/dang-nhap', ['email' => $account->email, 'password' => 'bad'])->assertSessionHasErrors('email');
        $this->post('/tai-khoan/dang-nhap', ['email' => strtoupper($account->email), 'password' => 'Customer123'])->assertRedirect(route('account.index'));
        $this->put('/tai-khoan/ho-so', ['name' => 'Tên nhận mới', 'phone' => '0907654321', 'address' => 'Địa chỉ mới'])->assertSessionHasNoErrors();
        $this->assertSame('Tên nhận mới', $account->customer->fresh()->name);
        $this->put('/tai-khoan/mat-khau', ['current_password' => 'wrong', 'password' => 'NewCustomer123', 'password_confirmation' => 'NewCustomer123'])->assertSessionHasErrors('current_password');
        $this->put('/tai-khoan/mat-khau', ['current_password' => 'Customer123', 'password' => 'NewCustomer123', 'password_confirmation' => 'NewCustomer123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewCustomer123', $account->fresh()->password));
    }

    public function test_wishlist_is_private_idempotent_and_can_be_removed(): void
    {
        $account = $this->account();
        $this->post(route('account.wishlist', $this->variant->product))->assertRedirect(route('account.login'));
        Auth::guard('customer')->login($account);
        $this->post(route('account.wishlist', $this->variant->product))->assertSessionHasNoErrors();
        $this->post(route('account.wishlist', $this->variant->product))->assertSessionHasNoErrors();
        $this->assertSame(1, $account->wishlist()->count());
        $this->get('/tai-khoan')->assertOk()->assertSee('Giày thử mở rộng');
        $this->delete(route('account.wishlist.remove', $this->variant->product))->assertSessionHasNoErrors();
        $this->assertSame(0, $account->wishlist()->count());
    }

    public function test_authenticated_checkout_uses_owned_customer_and_server_totals(): void
    {
        $account = $this->account();
        $other = $this->account('other@example.com');
        Auth::guard('customer')->login($account);
        $this->post('/gio-hang', ['variant_id' => $this->variant->id, 'quantity' => 1]);
        $token = session('checkout_token');
        $this->post('/dat-hang', ['checkout_token' => $token, 'customer_id' => $other->customer_id, 'customer_name' => 'Tên người nhận', 'customer_phone' => '0901234567', 'shipping_address' => 'Địa chỉ nhận', 'total' => 1, 'shipping_fee' => 0, 'discount' => 700000])->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->assertSame($account->customer_id, $order->customer_id);
        $this->assertSame(780000, $order->total);
        $this->assertSame('Tên người nhận', $order->customer_name);
        $this->get('/tai-khoan')->assertSee($order->code);
    }

    public function test_customer_cannot_read_or_cancel_another_customers_order(): void
    {
        $owner = $this->account();
        $other = $this->account('other@example.com');
        $order = $this->sale('online', [], $owner);
        Auth::guard('customer')->login($other);
        $this->get('/tai-khoan')->assertDontSee($order->code);
        $this->post(route('account.cancel', $order))->assertNotFound();
        Auth::guard('customer')->login($owner);
        $this->post(route('account.cancel', $order))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_customer_cannot_cancel_after_confirmation_and_reservation_is_preserved(): void
    {
        $owner = $this->account();
        $order = $this->sale('online', [], $owner);
        $this->service->advanceDelivery($order, $this->staff, 'confirmed');
        Auth::guard('customer')->login($owner);
        $this->post(route('account.cancel', $order))->assertSessionHasErrors('order');
        $this->assertSame(1, $this->variant->fresh()->reserved_stock);
    }

    public function test_coupon_caps_discount_counts_once_and_cancellation_releases_once(): void
    {
        $coupon = $this->coupon();
        $token = (string) Str::uuid();
        $order = $this->sale('online', ['coupon_code' => 'test10', 'checkout_token' => $token]);
        $this->assertSame(50000, $order->discount);
        $this->assertSame(730000, $order->total);
        $duplicate = $this->sale('online', ['coupon_code' => 'TEST10', 'checkout_token' => $token]);
        $this->assertSame($order->id, $duplicate->id);
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->fails(fn () => $this->sale('online', ['coupon_code' => 'TEST10']));
        $this->assertSame(1, SalesOrder::count());
        $this->service->cancelSale($order);
        $this->service->cancelSale($order);
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_expired_coupon_and_invalid_conditions_roll_back_the_entire_order(): void
    {
        $this->coupon(['ends_at' => today()->subDay()]);
        $this->fails(fn () => $this->sale('online', ['coupon_code' => 'TEST10']));
        $this->assertSame(0, SalesOrder::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, DB::table('order_events')->count());
    }

    public function test_shipping_threshold_uses_subtotal_before_discount(): void
    {
        $this->coupon(['type' => 'fixed', 'value' => 200000, 'maximum_discount' => null]);
        $order = $this->sale('online', ['coupon_code' => 'TEST10', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]]);
        $this->assertSame(0, $order->shipping_fee);
        $this->assertSame(1300000, $order->total);
    }

    public function test_online_reservation_blocks_pos_and_cancellation_releases_stock(): void
    {
        $order = $this->sale('online', ['items' => [['variant_id' => $this->variant->id, 'quantity' => 10]]]);
        $pos = $this->sale();
        $this->service->advanceDelivery($order, $this->staff, 'confirmed');
        $this->service->advanceDelivery($order, $this->staff, 'confirmed');
        $this->assertSame(10, $this->variant->fresh()->reserved_stock);
        $this->fails(fn () => $this->service->completeSale($pos, $this->staff, 'cash'));
        $this->assertSame(0, Payment::count());
        $this->service->cancelSale($order);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->service->completeSale($pos, $this->staff, 'cash');
        $this->assertSame(9, $this->variant->fresh()->stock);
    }

    public function test_online_delivery_requires_sequential_steps_and_collects_cod_only_after_delivery(): void
    {
        $order = $this->sale('online');
        $this->fails(fn () => $this->service->completeSale($order, $this->staff, 'cash'));
        $this->fails(fn () => $this->service->advanceDelivery($order, $this->staff, 'shipping'));
        foreach (['confirmed', 'packing', 'shipping', 'delivered'] as $step) {
            $this->service->advanceDelivery($order, $this->staff, $step, 'SHIP-123');
        }
        $this->fails(fn () => $this->service->cancelSale($order));
        $this->service->completeSale($order, $this->staff, 'cash');
        $this->service->completeSale($order, $this->staff, 'cash');
        $this->assertSame(9, $this->variant->fresh()->stock);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->assertSame(780000, Payment::firstOrFail()->amount);
        $this->assertSame(1, Payment::count());
        $this->assertSame('SHIP-123', $order->fresh()->tracking_number);
        $this->get(route('shop.order', $order->tracking_token))->assertOk()->assertSee('Đã giao hàng');
    }

    public function test_inventory_adjustment_is_idempotent_audited_and_cannot_consume_reserved_stock(): void
    {
        $data = ['variant_id' => $this->variant->id, 'quantity' => -2, 'reason' => 'Kiểm kê phát hiện hỏng', 'request_token' => (string) Str::uuid()];
        $this->service->adjustInventory($data, $this->admin);
        $this->service->adjustInventory($data, $this->admin);
        $this->assertSame(8, $this->variant->fresh()->stock);
        $this->assertSame(1, DB::table('inventory_adjustments')->count());
        $this->assertSame(8, (int) $this->variant->movements()->sum('quantity'));
        $online = $this->sale('online', ['items' => [['variant_id' => $this->variant->id, 'quantity' => 8]]]);
        $this->service->advanceDelivery($online, $this->staff, 'confirmed');
        $this->fails(fn () => $this->service->adjustInventory(array_merge($data, ['quantity' => -1, 'request_token' => (string) Str::uuid()]), $this->admin));
        $this->assertSame(8, $this->variant->fresh()->stock);
    }

    public function test_full_return_refunds_net_goods_only_and_restocks_once(): void
    {
        $this->coupon();
        $order = $this->sale('online', ['coupon_code' => 'TEST10']);
        foreach (['confirmed', 'packing', 'shipping', 'delivered'] as $step) {
            $this->service->advanceDelivery($order, $this->staff, $step);
        }
        $this->service->completeSale($order, $this->staff, 'cash');
        $return = $this->service->returnSale($order, $this->admin, 'Khách trả do không vừa size', true);
        $again = $this->service->returnSale($order, $this->admin, 'Gửi lặp chứng từ', true);
        $this->assertSame($return->id, $again->id);
        $this->assertSame(700000, $return->refund_amount);
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->assertSame(10, (int) $this->variant->movements()->sum('quantity'));
        $this->assertSame(1, SalesReturn::count());
        $this->assertSame(1, Payment::count());
        $this->actingAs($this->admin)->get('/admin/reports')->assertOk()->assertViewHas('net', fn ($v) => (int) $v === 30000)->assertViewHas('refunds', fn ($v) => (int) $v === 700000);
    }

    public function test_damaged_returns_do_not_restock_and_unpaid_orders_cannot_be_returned(): void
    {
        $order = $this->sale();
        $this->fails(fn () => $this->service->returnSale($order, $this->admin, 'Hàng chưa thanh toán', true));
        $this->service->completeSale($order, $this->staff, 'cash');
        $this->service->returnSale($order, $this->admin, 'Hàng hỏng không đủ điều kiện bán', false);
        $this->assertSame(9, $this->variant->fresh()->stock);
        $this->assertSame(0, InventoryMovement::where('kind', 'return')->count());
    }

    public function test_extension_endpoints_enforce_admin_role_and_export_escapes_formulas(): void
    {
        $order = $this->sale();
        $this->actingAs($this->staff);
        foreach (['/admin/coupons', '/admin/adjustments', '/admin/returns', '/admin/export/orders'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('orders.return', $order), ['reason' => 'Không được phép', 'restock' => 1])->assertForbidden();
        $this->post('/admin/coupons', ['code' => 'ATTACK'])->assertForbidden();
        $this->service->completeSale($order, $this->staff, 'cash');
        $order->update(['customer_name' => '=HYPERLINK("bad")']);
        $response = $this->actingAs($this->admin)->get('/admin/export/orders');
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
        $this->get('/admin/export/inventory')->assertOk()->assertDownload();
    }

    public function test_all_new_admin_pages_render_and_coupon_validation_rejects_over_100_percent(): void
    {
        $this->actingAs($this->admin);
        foreach (['/admin/coupons', '/admin/adjustments', '/admin/returns', '/admin/inventory', '/admin/reports'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->post('/admin/coupons', ['code' => 'BAD', 'name' => 'Ưu đãi lỗi', 'type' => 'percent', 'value' => 101, 'minimum_order' => 0, 'is_active' => 1])->assertSessionHasErrors('value');
        $this->assertSame(0, Coupon::count());
    }

    public function test_filters_apply_size_price_and_stock_to_the_same_variant(): void
    {
        $this->get('/?size_id='.$this->variant->size_id.'&min_price=700000&max_price=800000&in_stock=1')->assertOk()->assertViewHas('products', fn ($v) => $v->total() === 1);
        $this->variant->forceFill(['reserved_stock' => 10])->save();
        $this->get('/?in_stock=1')->assertOk()->assertViewHas('products', fn ($v) => $v->total() === 0);
        $this->get('/?min_price=900000&max_price=500000')->assertSessionHasErrors('max_price');
        $this->get('/huong-dan')->assertOk();
    }

    public function test_failed_delivery_keeps_reservations_until_goods_return_to_store(): void
    {
        $coupon = $this->coupon();
        $order = $this->sale('online', ['coupon_code' => 'TEST10']);
        foreach (['confirmed', 'packing', 'shipping', 'delivery_failed'] as $step) {
            $this->service->advanceDelivery($order, $this->staff, $step);
        }
        $this->fails(fn () => $this->service->cancelSale($order));
        $this->assertSame(1, $this->variant->fresh()->reserved_stock);
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->actingAs($this->admin)->get(route('orders.show', $order))->assertOk()->assertSee('Thử giao lại');
        $this->service->advanceDelivery($order, $this->staff, 'returned_to_store');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertSame(0, Payment::count());
    }

    public function test_optional_price_and_expiration_filters_work_without_lower_bounds(): void
    {
        $this->get('/?max_price=800000')->assertOk()->assertViewHas('products', fn ($v) => $v->total() === 1);
        $this->actingAs($this->admin)->post('/admin/coupons', ['code' => 'new50', 'name' => 'Giảm giá mới', 'type' => 'fixed', 'value' => 50000, 'minimum_order' => 0, 'ends_at' => today()->addDays(10)->toDateString(), 'is_active' => 1])->assertSessionHasNoErrors();
        $coupon = Coupon::where('code', 'NEW50')->firstOrFail();
        $this->put('/admin/coupons/'.$coupon->id, ['code' => 'NEW50', 'name' => 'Đã sửa ưu đãi', 'type' => 'fixed', 'value' => 60000, 'minimum_order' => 0, 'is_active' => 0, 'used_count' => 999])->assertSessionHasNoErrors();
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertFalse($coupon->fresh()->is_active);
        $this->get('/admin/coupons')->assertOk()->assertSee('Đã sửa ưu đãi');
    }

    public function test_coupon_changes_never_reprice_existing_orders_and_other_period_returns_are_separate(): void
    {
        $coupon = $this->coupon();
        $order = $this->sale('pos', ['coupon_code' => 'TEST10']);
        $coupon->update(['value' => 90, 'is_active' => false]);
        $this->service->completeSale($order, $this->staff, 'cash');
        $order->update(['completed_at' => today()->subMonth()]);
        $this->service->returnSale($order, $this->admin, 'Trả hàng từ đơn tháng trước', true);
        $this->actingAs($this->admin)->get('/admin/reports?from='.today()->toDateString().'&to='.today()->toDateString())->assertOk()->assertViewHas('revenue', fn ($v) => (int) $v === 0)->assertViewHas('net', fn ($v) => (int) $v === -700000);
        $this->assertSame(700000, Payment::firstOrFail()->amount);
    }
}
