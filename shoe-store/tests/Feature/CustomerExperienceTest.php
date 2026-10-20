<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\OrderMessage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\Size;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\CustomerResetPassword;
use App\Services\OrderMessaging;
use App\Services\StoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerExperienceTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    private CustomerAccount $account;

    private User $admin;

    private StoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.bank_bin' => '970436', 'payment.bank_name' => 'Test Bank', 'payment.account_number' => '0000000000000', 'payment.account_name' => 'TAI KHOAN KIEM THU']);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Khách kiểm thử', 'phone' => '0901234567']);
        $this->account = CustomerAccount::create(['customer_id' => $customer->id, 'email' => 'customer@example.test', 'password' => 'OldPass123']);
        $this->account->refresh();
        $product = Product::create(['name' => 'Giày thử', 'brand_id' => Brand::create(['name' => 'Test'])->id, 'category_id' => Category::create(['name' => 'Sneaker'])->id, 'is_active' => true]);
        $this->variant = $product->variants()->create(['sku' => 'TEST-40', 'size_id' => Size::create(['name' => '40'])->id, 'color_id' => Color::create(['name' => 'Trắng'])->id, 'price' => 750000, 'is_active' => true]);
        $this->variant->forceFill(['stock' => 10])->save();
        $this->service = app(StoreService::class);
    }

    private function order(array $extra = [], bool $completed = false): SalesOrder
    {
        $order = $this->service->createSale($extra + ['customer_name' => 'Khách thử', 'customer_phone' => '0901234567', 'shipping_address' => 'Địa chỉ thử', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]], null, 'online', $this->account);
        if ($completed) {
            foreach (['confirmed', 'packing', 'shipping', 'delivered'] as $step) {
                $this->service->advanceDelivery($order, $this->admin, $step);
            }
            $order = $this->service->completeSale($order, $this->admin, 'cash');
        }

        return $order->fresh();
    }

    public function test_qr_expiry_releases_only_once_and_restores_coupon_without_payment(): void
    {
        $this->freezeTime();
        $coupon = Coupon::create(['code' => 'EXPIRY', 'name' => 'Test', 'type' => 'fixed', 'value' => 10000, 'minimum_order' => 0, 'usage_limit' => 10, 'is_active' => true]);
        $order = $this->order(['payment_method' => 'bank_transfer', 'coupon_code' => 'EXPIRY']);
        $this->assertSame(1, $this->variant->fresh()->reserved_stock);
        $this->assertTrue($order->payment_expires_at->equalTo(now()->addMinutes(30)->startOfSecond()));
        $this->travel(31)->minutes();
        $this->artisan('store:expire-qr')->assertSuccessful();
        $this->artisan('store:expire-qr')->assertSuccessful();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->expired_at);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(1, $order->events()->where('status', 'expired')->count());
    }

    public function test_expiry_preserves_reported_confirmed_paid_and_legacy_qr_orders(): void
    {
        $reported = $this->order(['payment_method' => 'bank_transfer']);
        $this->service->reportTransfer($reported);
        $confirmed = $this->order(['payment_method' => 'bank_transfer']);
        $this->service->advanceDelivery($confirmed, $this->admin, 'confirmed');
        $paid = $this->order(['payment_method' => 'bank_transfer']);
        $this->service->verifyTransfer($paid, $this->admin, $paid->total, 'BANK-EXPIRE-TEST');
        $legacy = $this->order(['payment_method' => 'bank_transfer']);
        $legacy->update(['payment_expires_at' => null]);
        $cod = $this->order();
        $this->travel(31)->minutes();
        $this->artisan('store:expire-qr')->assertSuccessful();
        foreach ([$reported, $confirmed, $paid, $legacy, $cod] as $order) {
            $this->assertSame('pending', $order->fresh()->status);
        }
        $this->assertSame(4, $this->variant->fresh()->reserved_stock);
    }

    public function test_expired_page_hides_qr_and_late_payment_requires_refund(): void
    {
        $order = $this->order(['payment_method' => 'bank_transfer']);
        $this->travel(31)->minutes();
        $this->get(route('shop.payment', $order->tracking_token))->assertOk()->assertSee('Đơn đã hết hạn thanh toán.')->assertDontSee('data-qr-payload=', false);
        $this->post(route('shop.transfer.report', $order->tracking_token))->assertSessionHasErrors('payment');
        $this->service->verifyTransfer($order, $this->admin, $order->total, 'LATE-TRANSFER');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('Chờ hoàn tiền', $order->fresh()->payment_label);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
    }

    public function test_confirmation_outbox_is_idempotent_and_uses_account_email(): void
    {
        Mail::fake();
        $token = (string) Str::uuid();
        $order = $this->order(['checkout_token' => $token, 'customer_email' => 'another@example.test']);
        $this->order(['checkout_token' => $token]);
        $message = OrderMessage::firstOrFail();
        $this->assertSame($this->account->email, $message->recipient);
        $this->assertDatabaseCount('order_messages', 1);
        app(OrderMessaging::class)->deliver($message->id);
        app(OrderMessaging::class)->deliver($message->id);
        Mail::assertSent(OrderConfirmation::class, 1);
        $this->assertSame('preview', $message->fresh()->status);
        $this->assertStringContainsString($order->tracking_token, (new OrderConfirmation($order->load('items')))->render());
    }

    public function test_mail_failure_retains_order_and_can_retry_without_duplicate_order(): void
    {
        $order = $this->order();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Simulated failure'));
        $message = OrderMessage::firstOrFail();
        $this->assertFalse(app(OrderMessaging::class)->deliver($message->id));
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertSame(1, $message->fresh()->attempts);
        $this->assertSame('pending', $order->fresh()->status);
        $this->actingAs($this->admin, 'web')->post(route('care.messages.retry', $message))->assertRedirect();
        $this->assertSame('pending', $message->fresh()->status);
    }

    public function test_password_reset_uses_private_single_use_token_and_revokes_old_sessions(): void
    {
        Notification::fake();
        $this->post(route('account.password.email'), ['email' => strtoupper($this->account->email)])->assertSessionHas('success');
        $token = null;
        Notification::assertSentTo($this->account, CustomerResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->assertNotSame($token, DB::table('customer_password_reset_tokens')->value('token'));
        $data = ['email' => $this->account->email, 'token' => $token, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'];
        $this->post(route('account.password.reset'), $data)->assertRedirect(route('account.login'));
        $this->assertTrue(Hash::check('NewPass123', $this->account->fresh()->password));
        $this->post(route('account.password.reset'), $data)->assertSessionHasErrors('email');
        $this->actingAs($this->account->fresh(), 'customer')->withSession(['customer_auth_version' => 1])->get(route('account.index'))->assertRedirect(route('account.login'));
    }

    public function test_password_reset_is_expiring_and_does_not_reveal_account_existence_or_touch_staff(): void
    {
        Notification::fake();
        $response = $this->post(route('account.password.email'), ['email' => 'unknown@example.test']);
        $unknown = session('success');
        $this->post(route('account.password.email'), ['email' => $this->account->email])->assertSessionHas('success', $unknown);
        $this->post(route('account.password.email'), ['email' => $this->admin->email])->assertSessionHas('success', $unknown);
        $this->assertDatabaseMissing('customer_password_reset_tokens', ['email' => $this->admin->email]);
        $token = Password::broker('customers')->createToken($this->account);
        $this->travel(61)->minutes();
        $this->post(route('account.password.reset'), ['email' => $this->account->email, 'token' => $token, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldPass123', $this->account->fresh()->password));
        $mail = (new CustomerResetPassword('test-token'))->toMail($this->account);
        $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/', $mail->actionUrl);
    }

    public function test_reviews_require_completed_purchase_and_publish_only_after_admin_moderation(): void
    {
        $product = $this->variant->product;
        $this->actingAs($this->account, 'customer')->post(route('account.review', $product), ['rating' => 5, 'body' => 'Giày vừa chân, đúng màu.'])->assertSessionHasErrors('review');
        $this->order([], true);
        $this->post(route('account.review', $product), ['rating' => 4, 'body' => '<script>alert(1)</script> Giày vừa chân.'])->assertSessionHasNoErrors();
        $review = ProductReview::firstOrFail();
        $this->get(route('shop.product', $product))->assertOk()->assertSee('Chờ duyệt')->assertDontSee('<script>alert(1)', false)->assertSee('0 đánh giá');
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff, 'web')->put(route('care.reviews.moderate', $review), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($this->admin, 'web')->put(route('care.reviews.moderate', $review), ['status' => 'approved', 'content_hash' => $review->contentHash()])->assertSessionHasNoErrors();
        $this->get(route('shop.product', $product))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)', false)->assertSee('1 đánh giá');
        $this->actingAs($this->account, 'customer')->post(route('account.review', $product), ['rating' => 3, 'body' => 'Cập nhật cảm nhận sau khi sử dụng.'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_reviews', 1);
        $this->assertSame('pending', $review->fresh()->status);
        $this->actingAs($this->admin, 'web')->put(route('care.reviews.moderate', $review), ['status' => 'approved', 'content_hash' => $review->contentHash()])->assertSessionHasErrors('review');
        $this->assertSame('pending', $review->fresh()->status);
        $this->put(route('care.reviews.moderate', $review), ['status' => 'approved', 'content_hash' => $review->fresh()->contentHash()])->assertSessionHasNoErrors();
        $this->assertSame('approved', $review->fresh()->status);
    }

    public function test_support_request_is_private_idempotent_and_does_not_mutate_finances(): void
    {
        $order = $this->order([], true);
        $before = $this->variant->fresh()->stock;
        $otherCustomer = Customer::create(['name' => 'Khách khác']);
        $other = CustomerAccount::create(['customer_id' => $otherCustomer->id, 'email' => 'other@example.test', 'password' => 'Pass12345']);
        $this->actingAs($other->fresh(), 'customer')->get(route('account.support', $order))->assertNotFound();
        $this->post(route('account.support.store', $order), ['kind' => 'return', 'reason' => 'Muốn trả do không phù hợp nhu cầu.'])->assertNotFound();
        $this->actingAs($this->account, 'customer')->post(route('account.support.store', $order), ['kind' => 'exchange', 'reason' => 'Giày hơi chật, muốn đổi sang size lớn hơn.'])->assertSessionHasErrors('desired_size');
        $this->post(route('account.support.store', $order), ['kind' => 'exchange', 'desired_size' => '41', 'reason' => 'Giày hơi chật, muốn đổi sang size lớn hơn.'])->assertSessionHasNoErrors();
        $this->post(route('account.support.store', $order), ['kind' => 'return', 'reason' => 'Muốn gửi lại yêu cầu lần nữa.'])->assertSessionHasErrors('order');
        $this->assertDatabaseCount('support_requests', 1);
        $this->assertDatabaseCount('sales_returns', 0);
        $this->assertSame($before, $this->variant->fresh()->stock);
        $this->get(route('account.support', $order))->assertOk()->assertSee('Chờ tiếp nhận');
    }

    public function test_staff_can_respond_but_cannot_resolve_before_actual_return_record(): void
    {
        $order = $this->order([], true);
        $ticket = SupportRequest::create(['sales_order_id' => $order->id, 'customer_account_id' => $this->account->id, 'kind' => 'return', 'reason' => 'Muốn trả toàn bộ đơn do không phù hợp.']);
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff, 'web')->put(route('care.requests.respond', $ticket), ['status' => 'approved', 'staff_reply' => 'Cửa hàng đồng ý kiểm tra, vui lòng liên hệ gửi hàng.'])->assertSessionHasNoErrors();
        $this->put(route('care.requests.respond', $ticket), ['status' => 'resolved', 'staff_reply' => 'Đã hoàn thành xử lý yêu cầu của bạn.'])->assertSessionHasErrors('status');
        $this->service->returnSale($order, $this->admin, 'Đã kiểm tra hàng và hoàn tiền thực tế.', true);
        $this->put(route('care.requests.respond', $ticket), ['status' => 'resolved', 'staff_reply' => 'Đã nhận hàng và ghi nhận hoàn tiền hàng.'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->get(route('care.requests'))->assertOk();
        $this->get(route('care.reviews'))->assertForbidden();
        $this->get(route('care.messages'))->assertForbidden();
    }

    public function test_admin_gallery_keeps_existing_images_and_protects_cross_product_actions(): void
    {
        Storage::fake('public');
        $product = $this->variant->product;
        $data = ['name' => $product->name, 'brand_id' => $product->brand_id, 'category_id' => $product->category_id, 'is_active' => 1, 'material' => 'Vải theo thông số đã kiểm tra', 'fit_note' => 'Đo chân trước khi chọn', 'images' => [UploadedFile::fake()->createWithContent('front.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6ioAAAAASUVORK5CYII=')), UploadedFile::fake()->createWithContent('side.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6ioAAAAASUVORK5CYII='))]];
        $this->actingAs($this->admin, 'web')->put(route('products.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $product->images()->count());
        $this->put(route('products.update', $product), array_diff_key($data, ['images' => 1]))->assertSessionHasNoErrors();
        $this->assertSame(2, $product->images()->count());
        $image = $product->images()->latest('id')->first();
        $this->post(route('products.image', [$product, $image]), ['action' => 'cover'])->assertSessionHasNoErrors();
        $this->assertSame($image->id, $product->images()->first()->id);
        $other = Product::create(['name' => 'Other', 'brand_id' => $product->brand_id, 'category_id' => $product->category_id]);
        $this->post(route('products.image', [$other, $image]), ['action' => 'delete'])->assertNotFound();
        $this->get(route('products.edit', $product))->assertOk()->assertSee('Thư viện ảnh');
        $this->get(route('shop.product', $product))->assertOk()->assertSee('Vải theo thông số đã kiểm tra');
    }

    public function test_gallery_rejects_excess_images_and_removes_files_on_database_failure(): void
    {
        Storage::fake('public');
        $product = $this->variant->product;
        $upload = fn () => UploadedFile::fake()->createWithContent('front.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6ioAAAAASUVORK5CYII='));
        $data = ['name' => $product->name, 'brand_id' => $product->brand_id, 'category_id' => $product->category_id, 'is_active' => 1];
        for ($i = 0; $i < 8; $i++) {
            $product->images()->create(['path' => 'existing-'.$i.'.png', 'sort_order' => $i]);
        }
        $this->actingAs($this->admin, 'web')->put(route('products.update', $product), $data + ['images' => [$upload()]])->assertSessionHasErrors('images');
        $this->assertSame(8, $product->images()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $product->images()->delete();
        ProductImage::creating(function () {
            throw new \RuntimeException('Simulated database failure');
        });
        try {
            $this->put(route('products.update', $product), $data + ['images' => [$upload()]])->assertStatus(500);
            $this->assertDatabaseCount('product_images', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        } finally {
            ProductImage::flushEventListeners();
        }
    }
}
