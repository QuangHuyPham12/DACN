<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_product_images_are_served_and_svg_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aM2kAAAAASUVORK5CYII=');
        $image = UploadedFile::fake()->createWithContent('shoe.png', $png);
        $data = ['name' => 'Giày có ảnh', 'category_id' => $this->variant->product->category_id, 'brand_id' => $this->variant->product->brand_id, 'is_active' => 1, 'image' => $image];
        $this->actingAs($this->admin)->post('/admin/products', $data)->assertSessionHasNoErrors();
        $product = Product::where('name', 'Giày có ảnh')->firstOrFail();
        $this->assertCount(1, $product->images);
        $this->get($product->image_url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $data['image'] = UploadedFile::fake()->createWithContent('danger.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post('/admin/products', $data)->assertSessionHasErrors('image');
    }

    public function test_admin_creates_staff_but_staff_cannot_promote_themselves(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', ['name' => 'Nhân viên mới', 'email' => 'newstaff@example.com', 'password' => 'Newstaff123', 'role' => 'staff', 'is_active' => 1])->assertSessionHasNoErrors();
        $user = User::where('email', 'newstaff@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Newstaff123', $user->password));
        $this->actingAs($user)->put('/admin/users/'.$user->id, ['role' => 'admin'])->assertForbidden();
        $this->assertSame('staff', $user->fresh()->role);
    }

    public function test_discontinued_products_cannot_be_ordered_or_paid(): void
    {
        $this->stock();
        $order = $this->sale();
        $this->variant->product->update(['is_active' => false]);
        $this->get('/san-pham/'.$this->variant->product_id)->assertNotFound();
        $this->post('/gio-hang', ['variant_id' => $this->variant->id, 'quantity' => 1])->assertSessionHasErrors();
        $this->actingAs($this->staff)->post('/admin/orders/'.$order->id.'/complete', ['method' => 'cash'])->assertSessionHasErrors();
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->assertSame(0, Payment::count());
    }

    private User $admin;

    private User $staff;

    private ProductVariant $variant;

    private Supplier $supplier;

    private StoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $product = Product::create(['name' => 'Giày thử nghiệm', 'category_id' => Category::create(['name' => 'Sneaker'])->id, 'brand_id' => Brand::create(['name' => 'SOLE'])->id]);
        $this->variant = $product->variants()->create(['sku' => 'TEST-40', 'size_id' => Size::create(['name' => '40'])->id, 'color_id' => Color::create(['name' => 'Trắng'])->id, 'price' => 750000, 'low_stock_threshold' => 5]);
        $this->supplier = Supplier::create(['name' => 'Nhà cung cấp thử']);
        $this->service = app(StoreService::class);
    }

    private function stock(int $quantity = 10): void
    {
        $p = $this->service->createPurchase(['supplier_id' => $this->supplier->id, 'items' => [['variant_id' => $this->variant->id, 'quantity' => $quantity, 'unit_cost' => 400000]]], $this->admin);
        $this->service->confirmPurchase($p, $this->admin);
    }

    private function sale(int $quantity = 1): SalesOrder
    {
        return $this->service->createSale(['items' => [['variant_id' => $this->variant->id, 'quantity' => $quantity]]], $this->staff);
    }

    public function test_public_and_all_management_pages_render(): void
    {
        $this->stock();
        $order = $this->sale();
        $customer = Customer::create(['name' => 'Khách thử']);
        foreach (['/', '/san-pham/'.$this->variant->product_id, '/gio-hang', '/dang-nhap'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->actingAs($this->admin);
        foreach (['/admin', '/admin/products', '/admin/products/create', '/admin/products/'.$this->variant->product_id.'/edit', '/admin/inventory', '/admin/orders', '/admin/orders/create', '/admin/orders/'.$order->id, '/admin/purchases', '/admin/purchases/create', '/admin/purchases/'.PurchaseOrder::firstOrFail()->id, '/admin/reports', '/admin/users', '/admin/doi-mat-khau', '/admin/customers/'.$customer->id] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['categories', 'brands', 'colors', 'sizes', 'customers', 'suppliers'] as $resource) {
            $this->get('/admin/data/'.$resource)->assertOk();
        }
    }

    public function test_guests_cannot_access_management(): void
    {
        $this->get('/admin')->assertRedirect('/dang-nhap');
        $this->post('/admin/orders', [])->assertRedirect('/dang-nhap');
    }

    public function test_staff_cannot_read_or_mutate_admin_modules(): void
    {
        $this->actingAs($this->staff);
        foreach (['/admin/reports', '/admin/users', '/admin/purchases', '/admin/products/create', '/admin/data/suppliers', '/admin/data/brands'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/products', [])->assertForbidden();
        $this->post('/admin/data/brands', ['name' => 'Forbidden'])->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
        $this->get('/admin')->assertOk()->assertDontSee('Doanh thu hôm nay');
    }

    public function test_disabled_user_is_denied_even_with_existing_session(): void
    {
        $this->admin->update(['is_active' => false]);
        $this->actingAs($this->admin)->get('/admin')->assertRedirect('/dang-nhap');
        $this->assertGuest();
    }

    public function test_login_logout_and_password_change(): void
    {
        $this->admin->update(['email' => 'admin@test.vn', 'password' => 'Testpass123']);
        $this->post('/dang-nhap', ['email' => 'admin@test.vn', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/dang-nhap', ['email' => 'admin@test.vn', 'password' => 'Testpass123'])->assertRedirect('/admin');
        $this->put('/admin/doi-mat-khau', ['current_password' => 'Testpass123', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Newpass123', $this->admin->fresh()->password));
        $this->post('/dang-xuat')->assertRedirect('/dang-nhap');
        $this->assertGuest();
    }

    public function test_purchase_confirmation_is_idempotent_and_draft_does_not_increase_stock(): void
    {
        $purchase = $this->service->createPurchase(['supplier_id' => $this->supplier->id, 'items' => [['variant_id' => $this->variant->id, 'quantity' => 8, 'unit_cost' => 400000]]], $this->admin);
        $this->assertSame(0, $this->variant->fresh()->stock);
        $this->service->confirmPurchase($purchase, $this->admin);
        $this->service->confirmPurchase($purchase, $this->admin);
        $this->assertSame(8, $this->variant->fresh()->stock);
        $this->assertSame(1, InventoryMovement::count());
        $this->assertSame(3200000, $purchase->fresh()->total);
    }

    public function test_sale_uses_server_price_and_only_decrements_stock_on_completion(): void
    {
        $this->stock();
        $order = $this->service->createSale(['total' => 1, 'items' => [['variant_id' => $this->variant->id, 'quantity' => 2, 'unit_price' => 1]]], $this->staff);
        $this->assertSame(1500000, $order->total);
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->assertSame(0, Payment::count());
        $this->service->completeSale($order, $this->staff, 'cash');
        $this->service->completeSale($order, $this->staff, 'cash');
        $this->assertSame(8, $this->variant->fresh()->stock);
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, InventoryMovement::where('kind', 'sale')->count());
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_competing_drafts_cannot_oversell_the_last_pair(): void
    {
        $this->stock(1);
        $first = $this->sale();
        $second = $this->sale();
        $this->service->completeSale($first, $this->staff, 'cash');
        try {
            $this->service->completeSale($second, $this->staff, 'cash');
            $this->fail('Must reject overselling.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }
        $this->assertSame(0, $this->variant->fresh()->stock);
        $this->assertSame('draft', $second->fresh()->status);
        $this->assertSame(1, Payment::count());
    }

    public function test_a_failure_on_second_line_rolls_back_all_stock_and_payments(): void
    {
        $this->stock(3);
        $other = $this->variant->product->variants()->create(['sku' => 'TEST-41', 'size_id' => Size::create(['name' => '41'])->id, 'color_id' => $this->variant->color_id, 'price' => 650000]);
        $other->forceFill(['stock' => 1])->save();
        $order = $this->service->createSale(['items' => [['variant_id' => $this->variant->id, 'quantity' => 2], ['variant_id' => $other->id, 'quantity' => 1]]], $this->staff);
        $other->forceFill(['stock' => 0])->save();
        try {
            $this->service->completeSale($order, $this->staff, 'cash');
            $this->fail('Must fail.');
        } catch (ValidationException) {
        }
        $this->assertSame(3, $this->variant->fresh()->stock);
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, InventoryMovement::where('kind', 'sale')->count());
    }

    public function test_completed_sales_cannot_be_cancelled_and_cancelled_sales_cannot_be_paid(): void
    {
        $this->stock();
        $order = $this->sale();
        $this->actingAs($this->staff)->post('/admin/orders/'.$order->id.'/cancel')->assertSessionHasNoErrors();
        $this->post('/admin/orders/'.$order->id.'/complete', ['method' => 'cash'])->assertSessionHasErrors();
        $other = $this->sale();
        $this->service->completeSale($other, $this->staff, 'cash');
        $this->post('/admin/orders/'.$other->id.'/cancel')->assertSessionHasErrors();
        $this->assertSame('completed', $other->fresh()->status);
    }

    public function test_price_and_name_changes_do_not_rewrite_order_history(): void
    {
        $this->stock();
        $order = $this->sale();
        $this->variant->update(['price' => 999000]);
        $this->variant->product->update(['name' => 'Tên mới']);
        $this->service->completeSale($order, $this->staff, 'cash');
        $this->assertSame(750000, $order->fresh()->total);
        $this->assertSame('Giày thử nghiệm', $order->items->first()->product_name);
    }

    public function test_invalid_quantities_and_duplicate_lines_are_rejected(): void
    {
        $this->stock();
        $this->actingAs($this->staff)->post('/admin/orders', ['items' => [['variant_id' => $this->variant->id, 'quantity' => 0]]])->assertSessionHasErrors('items.0.quantity');
        $line = ['variant_id' => $this->variant->id, 'quantity' => 1];
        $this->post('/admin/orders', ['items' => [$line, $line]])->assertSessionHasErrors('items.0.variant_id');
        $this->assertSame(0, SalesOrder::count());
    }

    public function test_product_variant_validation_and_direct_stock_tampering(): void
    {
        $this->stock();
        $url = '/admin/products/'.$this->variant->product_id.'/variants/'.$this->variant->id;
        $data = ['sku' => $this->variant->sku, 'size_id' => $this->variant->size_id, 'color_id' => $this->variant->color_id, 'price' => -1, 'low_stock_threshold' => 5, 'is_active' => 1];
        $this->actingAs($this->admin)->put($url, $data)->assertSessionHasErrors('price');
        $data['price'] = 800000;
        $data['stock'] = 9999;
        $this->put($url, $data)->assertSessionHasNoErrors();
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->post('/admin/products/'.$this->variant->product_id.'/variants', $data)->assertSessionHasErrors(['sku', 'size_id']);
    }

    public function test_online_checkout_is_idempotent_and_does_not_reserve_stock(): void
    {
        $this->stock();
        $this->post('/gio-hang', ['variant_id' => $this->variant->id, 'quantity' => 2])->assertRedirect('/gio-hang');
        $token = session('checkout_token');
        $data = ['checkout_token' => $token, 'customer_name' => 'Khách online', 'customer_phone' => '0901234567', 'shipping_address' => 'Hà Nội', 'total' => 1];
        $response = $this->post('/dat-hang', $data);
        $order = SalesOrder::firstOrFail();
        $response->assertRedirect(route('shop.payment', $order->tracking_token));
        $this->post('/dat-hang', $data)->assertRedirect(route('shop.payment', $order->tracking_token));
        $this->assertSame(1, SalesOrder::count());
        $this->assertSame(1500000, $order->total);
        $this->assertSame('pending', $order->status);
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->get(route('shop.order', $order->tracking_token))->assertOk();
        $this->get('/don-hang/not-a-valid-token')->assertNotFound();
    }

    public function test_checkout_requires_session_token_and_valid_stock(): void
    {
        $this->stock(1);
        $this->post('/gio-hang', ['variant_id' => $this->variant->id, 'quantity' => 2])->assertSessionHasErrors();
        $this->post('/dat-hang', ['checkout_token' => (string) Str::uuid()])->assertStatus(419);
        $this->assertSame(0, SalesOrder::count());
    }

    public function test_customer_crud_and_historical_data_delete_protection(): void
    {
        $this->actingAs($this->staff)->post('/admin/data/customers', ['name' => 'Khách mới', 'phone' => '0901234567'])->assertSessionHasNoErrors();
        $customer = Customer::firstOrFail();
        $this->put('/admin/data/customers/'.$customer->id, ['name' => 'Khách đã sửa'])->assertSessionHasNoErrors();
        $this->stock();
        $this->service->createSale(['customer_id' => $customer->id, 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]], $this->staff);
        $this->delete('/admin/data/customers/'.$customer->id)->assertSessionHasErrors();
        $this->assertModelExists($customer);
    }

    public function test_reports_only_count_completed_orders_in_selected_dates(): void
    {
        $this->stock();
        $done = $this->sale(2);
        $this->service->completeSale($done, $this->staff, 'cash');
        $this->sale(3);
        $cancelled = $this->sale(4);
        $this->service->cancelSale($cancelled);
        $response = $this->actingAs($this->admin)->get('/admin/reports?from='.today()->toDateString().'&to='.today()->toDateString());
        $response->assertOk()->assertViewHas('revenue', fn ($value) => (int) $value === 1500000)->assertViewHas('count', 1);
        $this->get('/admin/reports?from=2026-10-22&to=2026-10-21')->assertSessionHasErrors('to');
    }

    public function test_admin_cannot_lock_their_own_account(): void
    {
        $this->actingAs($this->admin)->put('/admin/users/'.$this->admin->id, ['name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'staff', 'is_active' => 0])->assertSessionHasErrors();
        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertSame('admin', $this->admin->fresh()->role);
    }
}
