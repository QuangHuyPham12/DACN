<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\Size;
use App\Models\User;
use App\Services\BankTransfer;
use App\Services\StoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QrPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private ProductVariant $variant;

    private StoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Test-only recipient. Never seeded or used as a real receiving account.
        config(['payment.bank_bin' => '970436', 'payment.bank_name' => 'Test Bank', 'payment.account_number' => '0000000000000', 'payment.account_name' => 'TAI KHOAN KIEM THU']);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $product = Product::create(['name' => 'Giày QR', 'brand_id' => Brand::create(['name' => 'Test'])->id, 'category_id' => Category::create(['name' => 'Sneaker'])->id]);
        $this->variant = $product->variants()->create(['sku' => 'QR-40', 'size_id' => Size::create(['name' => '40'])->id, 'color_id' => Color::create(['name' => 'Trắng'])->id, 'price' => 750000, 'stock' => 4, 'is_active' => true]);
        $this->variant->forceFill(['stock' => 4])->save();
        InventoryMovement::create(['product_variant_id' => $this->variant->id, 'user_id' => $this->admin->id, 'kind' => 'adjustment', 'quantity' => 4, 'balance_after' => 4, 'reference_type' => 'fixture', 'reference_id' => 1]);
        $this->service = app(StoreService::class);
    }

    private function sale(array $extra = []): SalesOrder
    {
        return $this->service->createSale(array_merge(['customer_name' => 'Khách kiểm thử', 'customer_phone' => '0901234567', 'shipping_address' => 'Địa chỉ kiểm thử', 'payment_method' => 'bank_transfer', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]], $extra), null, 'online');
    }

    private function fails(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected business validation rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_checkout_enters_payment_step_and_qr_uses_only_server_amount_and_bank(): void
    {
        $this->post('/gio-hang', ['variant_id' => $this->variant->id, 'quantity' => 1]);
        $data = ['checkout_token' => session('checkout_token'), 'customer_name' => 'Khách thử', 'customer_phone' => '0901234567', 'shipping_address' => 'Địa chỉ thử', 'payment_method' => 'bank_transfer', 'total' => 1, 'bank_snapshot' => ['number' => 'ATTACK'], 'payment' => ['amount' => 1]];
        $response = $this->post('/dat-hang', $data)->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $response->assertRedirect(route('shop.payment', $order->tracking_token));
        $this->post('/dat-hang', $data)->assertRedirect(route('shop.payment', $order->tracking_token));
        $this->assertSame(1, SalesOrder::count());
        $this->assertSame(780000, $order->total);
        $this->assertSame('0000000000000', $order->bank_snapshot['number']);
        $this->assertSame(1, $this->variant->fresh()->reserved_stock);
        $this->assertDatabaseCount('payments', 0);
        $this->get(route('shop.payment', $order->tracking_token))->assertOk()->assertSee('780.000')->assertSee('data-qr-payload', false)->assertSee('SOLE'.$order->code);
        $this->assertEmpty(session('cart'));
    }

    public function test_missing_or_invalid_bank_config_disables_qr_and_rejects_forged_selection(): void
    {
        config(['payment.bank_bin' => 'invalid']);
        $this->post('/gio-hang', ['variant_id' => $this->variant->id, 'quantity' => 1]);
        $this->get('/gio-hang')->assertOk()->assertSee('đang chờ cửa hàng cấu hình');
        $this->post('/dat-hang', ['checkout_token' => session('checkout_token'), 'customer_name' => 'Khách thử', 'customer_phone' => '0901234567', 'shipping_address' => 'Địa chỉ thử', 'payment_method' => 'bank_transfer'])->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('sales_orders', 0);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->assertNotEmpty(session('cart'));
        $cod = $this->sale(['payment_method' => 'cod']);
        $this->get(route('shop.payment', $cod->tracking_token))->assertOk()->assertSee('Nhận giày, rồi thanh toán')->assertDontSee('data-qr-payload', false);
    }

    public function test_vietqr_tlv_crc_and_recipient_snapshot_are_stable(): void
    {
        $order = $this->sale();
        $bank = app(BankTransfer::class);
        $this->assertSame('29B1', $bank->crc('123456789'));
        $payload = $bank->payload($order);
        $this->assertStringStartsWith('000201010212', $payload);
        $this->assertStringContainsString('0010A000000727', $payload);
        $this->assertStringContainsString('000697043601130000000000000', $payload);
        $this->assertStringContainsString('0208QRIBFTTA', $payload);
        $this->assertStringContainsString('530370454067800005802VN', $payload);
        $this->assertStringContainsString('62160812SOLE'.$order->code.'6304', $payload);
        $this->assertSame($bank->crc(substr($payload, 0, -4)), substr($payload, -4));
        config(['payment.account_number' => '9999999999999']);
        $this->assertSame($payload, $bank->payload($order->fresh()));
        $this->assertNull($bank->payload($this->sale(['payment_method' => 'cod'])));
    }

    public function test_customer_transfer_notification_is_idempotent_and_never_marks_paid(): void
    {
        $order = $this->sale();
        $url = route('shop.transfer.report', $order->tracking_token);
        $this->post($url, ['amount' => 780000, 'status' => 'paid'])->assertSessionHasNoErrors();
        $this->post($url)->assertSessionHasNoErrors();
        $this->assertNotNull($order->fresh()->transfer_reported_at);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(1, $order->events()->where('status', 'transfer_reported')->count());
        $this->get(route('shop.payment', $order->tracking_token))->assertSee('Chờ đối soát');
        $this->post(route('shop.cancel', $order->tracking_token))->assertSessionHasErrors('order');
        $this->post(route('shop.transfer.report', str_repeat('x', 48)))->assertNotFound();
    }

    public function test_private_payment_page_hides_qr_after_cancel_and_guest_can_release_unpaid_hold(): void
    {
        $order = $this->sale();
        $this->get(route('shop.payment', str_repeat('x', 48)))->assertNotFound();
        $page = $this->get(route('shop.payment', $order->tracking_token));
        $page->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $this->post(route('shop.cancel', $order->tracking_token))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->get(route('shop.payment', $order->tracking_token))->assertSee('Đơn hàng đã hủy')->assertDontSee('data-qr-payload', false);
    }

    public function test_verification_requires_staff_exact_amount_and_a_unique_bank_reference(): void
    {
        $order = $this->sale();
        $url = route('orders.transfer.verify', $order);
        $this->post($url, ['amount' => $order->total, 'reference' => 'BANK-001'])->assertRedirect(route('login'));
        $this->actingAs($this->staff)->post($url, ['amount' => 1, 'reference' => 'BANK-001'])->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payments', 0);
        $this->post($url, ['amount' => $order->total, 'reference' => 'BANK-001'])->assertSessionHasNoErrors();
        $this->post($url, ['amount' => $order->total, 'reference' => 'BANK-001'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($this->staff->id, Payment::firstOrFail()->verified_by);
        $this->assertSame('pending', $order->fresh()->status);
        $other = $this->sale();
        $this->post(route('orders.transfer.verify', $other), ['amount' => $other->total, 'reference' => ' bank-001 '])->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_qr_order_needs_payment_before_shipping_and_completes_without_double_receipt(): void
    {
        $order = $this->sale();
        foreach (['confirmed', 'packing'] as $step) {
            $this->service->advanceDelivery($order, $this->staff, $step);
        }
        $this->assertSame(1, $this->variant->fresh()->reserved_stock);
        $this->fails(fn () => $this->service->advanceDelivery($order, $this->staff, 'shipping'));
        $this->service->verifyTransfer($order, $this->staff, 780000, 'PAID-001');
        $this->fails(fn () => $this->service->completeSale($order, $this->staff, 'bank_transfer'));
        $this->service->advanceDelivery($order, $this->staff, 'shipping', 'SHIP-QR');
        $this->service->advanceDelivery($order, $this->staff, 'delivered');
        $this->service->completeSale($order, $this->staff, 'cash');
        $this->service->completeSale($order, $this->staff, 'cash');
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('bank_transfer', Payment::firstOrFail()->method);
        $this->assertSame(3, $this->variant->fresh()->stock);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->assertSame('completed', $order->fresh()->status);
        $this->get(route('shop.payment', $order->tracking_token))->assertSee('đã nhận đủ tiền')->assertDontSee('data-qr-payload', false);
    }

    public function test_paid_cancellation_retains_receipt_and_requires_admin_to_record_real_refund(): void
    {
        $order = $this->sale();
        $this->service->verifyTransfer($order, $this->staff, $order->total, 'CANCEL-PAID');
        $this->fails(fn () => $this->service->cancelSale($order, true));
        $this->service->cancelSale($order);
        $this->service->cancelSale($order);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->assertSame(4, $this->variant->fresh()->stock);
        $this->get(route('shop.order', $order->tracking_token))->assertSee('Chờ hoàn tiền');
        $this->actingAs($this->staff)->post(route('orders.refund.cancelled', $order), ['reference' => 'REFUND-001'])->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/reports')->assertViewHas('revenue', fn ($v) => (int) $v === 0)->assertViewHas('pendingRefunds', fn ($v) => (int) $v === 780000)->assertViewHas('cashNet', fn ($v) => (int) $v === 780000);
        $this->post(route('orders.refund.cancelled', $order), ['reference' => 'REFUND-001'])->assertSessionHasNoErrors();
        $this->post(route('orders.refund.cancelled', $order), ['reference' => 'REFUND-001'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('refunded', $order->payment()->firstOrFail()->status);
        $this->assertSame(780000, $order->payment()->firstOrFail()->amount);
        $this->get('/admin/reports')->assertViewHas('cashNet', fn ($v) => (int) $v === 0)->assertViewHas('pendingRefunds', fn ($v) => (int) $v === 0);
        $this->get(route('shop.order', $order->tracking_token))->assertSee('Đã hoàn tiền');
    }

    public function test_late_transfer_after_cancellation_can_be_reconciled_for_refund(): void
    {
        $order = $this->sale();
        $this->service->cancelSale($order);
        $this->service->verifyTransfer($order, $this->staff, $order->total, 'LATE-PAID');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('Chờ hoàn tiền', $order->fresh()->payment_label);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->service->refundCancelled($order, $this->admin, 'LATE-REFUND');
        $this->assertSame('Đã hoàn tiền', $order->fresh()->payment_label);
    }

    public function test_failed_prepaid_delivery_refunds_only_after_goods_return_and_never_decrements_stock(): void
    {
        $order = $this->sale();
        $this->service->verifyTransfer($order, $this->staff, $order->total, 'FAILED-PAID');
        foreach (['confirmed', 'packing', 'shipping', 'delivery_failed'] as $step) {
            $this->service->advanceDelivery($order, $this->staff, $step);
        }
        $this->fails(fn () => $this->service->cancelSale($order));
        $this->fails(fn () => $this->service->refundCancelled($order, $this->admin, 'TOO-EARLY'));
        $this->service->advanceDelivery($order, $this->staff, 'returned_to_store');
        $this->assertSame('Chờ hoàn tiền', $order->fresh()->payment_label);
        $this->assertSame(4, $this->variant->fresh()->stock);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->service->refundCancelled($order, $this->admin, 'FAILED-REFUND');
        $this->assertSame(780000, $order->payment()->firstOrFail()->amount);
    }

    public function test_shipment_location_is_audited_and_shown_without_advancing_fulfillment(): void
    {
        $order = $this->sale(['payment_method' => 'cod']);
        $data = ['shipping_carrier' => 'Vận chuyển thử', 'tracking_number' => 'TEST-TRACK', 'delivery_location' => 'Bưu cục thử Quận 1', 'delivery_note' => '<script>alert(1)</script>'];
        $this->post(route('orders.shipment', $order), $data)->assertRedirect(route('login'));
        $this->actingAs($this->staff)->post(route('orders.shipment', $order), $data)->assertSessionHasNoErrors();
        $this->assertSame('pending', $order->fresh()->fulfillment_status);
        $this->assertSame(1, $order->events()->where('status', 'shipment_update')->count());
        $this->get(route('shop.order', $order->tracking_token))->assertOk()->assertSee('Bưu cục thử Quận 1')->assertSee('TEST-TRACK')->assertSee('Vận chuyển thử')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->service->cancelSale($order);
        $this->post(route('orders.shipment', $order), $data)->assertSessionHasErrors('order');
    }

    public function test_qr_reservation_prevents_overselling_and_duplicate_confirmation_does_not_hold_twice(): void
    {
        $order = $this->sale(['items' => [['variant_id' => $this->variant->id, 'quantity' => 4]]]);
        $this->fails(fn () => $this->sale());
        $this->assertDatabaseCount('sales_orders', 1);
        $this->service->advanceDelivery($order, $this->staff, 'confirmed');
        $this->service->advanceDelivery($order, $this->staff, 'confirmed');
        $this->assertSame(4, $this->variant->fresh()->reserved_stock);
        $this->service->cancelSale($order);
        $this->assertSame(0, $this->variant->fresh()->reserved_stock);
        $this->assertSame(4, $this->variant->fresh()->stock);
    }
}
