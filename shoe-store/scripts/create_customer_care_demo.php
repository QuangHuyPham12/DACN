<?php

use App\Models\CustomerAccount;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\StoreService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || ! in_array(config('mail.default'), ['log', 'array']) || ! in_array('--demo', $argv, true)) {
    throw new RuntimeException('Chỉ tạo dữ liệu DEMO ở local, mail log/array và có --demo.');
}
$marker = 'DEMO-CARE-2026 · Đơn minh họa đánh giá và hỗ trợ; không giao hàng hoặc thu tiền thật.';
$order = SalesOrder::where('note', $marker)->first();
if (! $order) {
    $account = CustomerAccount::where('email', 'khach@sole.vn')->firstOrFail();
    $admin = User::where('email', 'admin@sole.vn')->where('role', 'admin')->firstOrFail();
    $variant = ProductVariant::where('is_active', true)->whereRaw('stock - reserved_stock > 2')->whereHas('product', fn ($q) => $q->where('is_active', true))->firstOrFail();
    $service = app(StoreService::class);
    $order = $service->createSale(['customer_name' => 'Khách DEMO trải nghiệm', 'customer_phone' => '0900000000', 'shipping_address' => 'Địa chỉ minh họa đồ án, không giao hàng thật', 'note' => $marker, 'items' => [['variant_id' => $variant->id, 'quantity' => 1]]], null, 'online', $account);
    foreach (['confirmed', 'packing', 'shipping', 'delivered'] as $step) {
        $service->advanceDelivery($order, $admin, $step);
    }
    $service->completeSale($order, $admin, 'cash');
}
echo json_encode(['demo_order' => $order->id, 'product' => $order->items()->first()->variant->product_id], JSON_UNESCAPED_UNICODE).PHP_EOL;
