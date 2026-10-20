<?php

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\Supplier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$errors = [];
foreach (ProductVariant::with('movements')->get() as $variant) {
    if ($variant->stock !== (int) $variant->movements->sum('quantity')) {
        $errors[] = 'Stock mismatch: '.$variant->sku;
    }
    if ($variant->stock < 0) {
        $errors[] = 'Negative stock: '.$variant->sku;
    }
    $reserved = (int) DB::table('sales_order_items')->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')->where('product_variant_id', $variant->id)->where('stock_reserved', true)->where('status', 'pending')->sum('quantity');
    if ($variant->reserved_stock !== $reserved || $reserved > $variant->stock) {
        $errors[] = 'Reservation mismatch: '.$variant->sku;
    }
}
foreach (SalesOrder::with(['items', 'payment'])->get() as $order) {
    $subtotal = $order->items->sum(fn ($item) => $item->quantity * $item->unit_price);
    $total = $subtotal - $order->discount + $order->shipping_fee;
    if ($order->subtotal !== $subtotal || $order->discount < 0 || $order->discount > $subtotal) {
        $errors[] = 'Invalid subtotal/discount: '.$order->code;
    }
    if ($order->total !== $total) {
        $errors[] = 'Order total mismatch: '.$order->code;
    }
    if ($order->status === 'completed' && (! $order->payment || $order->payment->amount !== $total)) {
        $errors[] = 'Payment mismatch: '.$order->code;
    }
    if ($order->payment && $order->payment->amount !== $total) {
        $errors[] = 'Incorrect received amount: '.$order->code;
    }
    if ($order->status !== 'completed' && $order->payment && ! ($order->channel === 'online' && $order->payment_method === 'bank_transfer' && in_array($order->status, ['pending', 'cancelled'], true))) {
        $errors[] = 'Unexpected prepayment: '.$order->code;
    }
    if ($order->payment?->refunded_at && ($order->status !== 'cancelled' || $order->payment->status !== 'refunded')) {
        $errors[] = 'Invalid cancellation refund: '.$order->code;
    }
    if ($order->payment_method === 'bank_transfer' && $order->status === 'pending' && ! $order->stock_reserved) {
        $errors[] = 'Unreserved QR order: '.$order->code;
    }
}
foreach (Coupon::all() as $coupon) {
    $used = SalesOrder::where('coupon_id', $coupon->id)->where('status', '!=', 'cancelled')->count();
    if ($coupon->used_count !== $used) {
        $errors[] = 'Coupon counter mismatch: '.$coupon->code;
    }
}
foreach (SalesReturn::with('order')->get() as $return) {
    if ($return->order->status !== 'completed' || $return->refund_amount !== $return->order->subtotal - $return->order->discount) {
        $errors[] = 'Invalid refund: '.$return->id;
    }
}
echo json_encode(['products' => Product::count(), 'variants' => ProductVariant::count(), 'suppliers' => Supplier::count(), 'purchases' => PurchaseOrder::count(), 'sales_orders' => SalesOrder::count(), 'payments' => Payment::count(), 'errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($errors ? 1 : 0);
