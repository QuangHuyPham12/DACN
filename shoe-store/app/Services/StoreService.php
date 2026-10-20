<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\InventoryMovement;
use App\Models\OrderMessage;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreService
{
    private function transaction(\Closure $callback, int $attempts = 3): mixed
    {
        return DB::transaction(function () use ($callback) {
            if (DB::connection()->getDriverName() === 'sqlite') {
                // PHP 8.2/8.3 starts deferred SQLite transactions. Acquire the writer
                // lock before any reads to avoid a read-to-write upgrade deadlock.
                // This statement changes no rows; SQLite still reserves the writer.
                DB::table('sales_orders')->whereRaw('1 = 0')->update(['id' => DB::raw('id')]);
            }

            return $callback();
        }, $attempts);
    }

    private function paymentTransaction(\Closure $callback): void
    {
        try {
            $this->transaction($callback);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages(['reference' => 'Mã giao dịch đã được ghi nhận. Kiểm tra lại đơn và mã giao dịch ngân hàng.']);
            }
            throw $exception;
        }
    }

    public function createPurchase(array $data, User $user): PurchaseOrder
    {
        $data = Validator::make($data, [
            'supplier_id' => 'required|exists:suppliers,id', 'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:100',
            'items.*.variant_id' => 'required|integer|distinct|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:10000',
            'items.*.unit_cost' => 'required|integer|min:0|max:1000000000',
        ])->validate();

        return $this->transaction(function () use ($data, $user) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $order = PurchaseOrder::create(['supplier_id' => $supplier->id, 'supplier_name' => $supplier->name,
                'user_id' => $user->id, 'note' => $data['note'] ?? null, 'status' => 'draft']);
            $total = 0;
            foreach ($data['items'] as $line) {
                $variant = ProductVariant::with('product')->findOrFail($line['variant_id']);
                $order->items()->create(['product_variant_id' => $variant->id, 'product_name' => $variant->product->name,
                    'sku' => $variant->sku, 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost']]);
                $total += (int) $line['quantity'] * (int) $line['unit_cost'];
            }
            $order->update(['total' => $total]);

            return $order;
        });
    }

    public function confirmPurchase(PurchaseOrder $purchase, User $user): PurchaseOrder
    {
        return $this->transaction(function () use ($purchase, $user) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($purchase->id);
            if ($order->status === 'confirmed') {
                return $order;
            }
            foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
                $variant = ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);
                if ($variant->stock + $item->quantity > 2000000000) {
                    throw ValidationException::withMessages(['stock' => 'Số lượng tồn vượt giới hạn cho phép.']);
                }
                $variant->increment('stock', $item->quantity);
                InventoryMovement::create(['product_variant_id' => $variant->id, 'user_id' => $user->id,
                    'kind' => 'purchase', 'quantity' => $item->quantity, 'balance_after' => $variant->stock,
                    'reference_type' => 'purchase', 'reference_id' => $order->id]);
            }
            $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);

            return $order;
        }, 3);
    }

    public function createSale(array $data, ?User $user, string $channel = 'pos', ?CustomerAccount $account = null): SalesOrder
    {
        $data = Validator::make($data, [
            'customer_id' => 'nullable|exists:customers,id', 'note' => 'nullable|string|max:2000',
            'customer_name' => ($channel === 'online' ? 'required' : 'nullable').'|string|max:150',
            'customer_phone' => ($channel === 'online' ? 'required' : 'nullable').'|string|max:30|regex:/^[0-9+() .-]{8,30}$/',
            'shipping_address' => ($channel === 'online' ? 'required' : 'nullable').'|string|max:255',
            'checkout_token' => 'nullable|uuid',
            'coupon_code' => 'nullable|string|max:40|regex:/^[A-Za-z0-9_-]+$/',
            'payment_method' => 'nullable|in:cod,bank_transfer',
            'customer_email' => 'nullable|email|max:255',
            'items' => 'required|array|min:1|max:100',
            'items.*.variant_id' => 'required|integer|distinct|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
        ])->validate();

        try {
            return $this->transaction(function () use ($data, $user, $channel, $account) {
                if ($channel === 'online' && ! empty($data['checkout_token'])) {
                    $existing = SalesOrder::where('checkout_token', $data['checkout_token'])->first();
                    if ($existing) {
                        return $existing;
                    }
                }
                $customer = ! empty($data['customer_id']) ? Customer::findOrFail($data['customer_id']) : null;
                $method = $channel === 'online' ? ($data['payment_method'] ?? 'cod') : 'cod';
                $bank = $method === 'bank_transfer' ? app(BankTransfer::class)->snapshot() : null;
                if ($method === 'bank_transfer' && ! $bank) {
                    throw ValidationException::withMessages(['payment_method' => 'Chuyển khoản QR chưa được cấu hình. Vui lòng chọn COD.']);
                }
                if ($channel === 'online' && $account) {
                    $customer = $account->customer;
                } elseif ($channel === 'online') {
                    // Do not merge people solely by phone number or overwrite another customer's details.
                    $customer = Customer::create(['name' => $data['customer_name'], 'phone' => $data['customer_phone'], 'address' => $data['shipping_address']]);
                }
                $order = SalesOrder::create(['customer_id' => $customer?->id, 'user_id' => $user?->id,
                    'customer_name' => $channel === 'online' ? $data['customer_name'] : ($customer?->name ?? 'Khách lẻ'), 'customer_phone' => $channel === 'online' ? $data['customer_phone'] : $customer?->phone,
                    'shipping_address' => $data['shipping_address'] ?? null, 'note' => $data['note'] ?? null,
                    'channel' => $channel, 'status' => $channel === 'online' ? 'pending' : 'draft',
                    'payment_method' => $method, 'bank_snapshot' => $bank,
                    'customer_email' => $channel === 'online' ? ($account?->email ?? ($data['customer_email'] ?? null)) : null,
                    'payment_expires_at' => $method === 'bank_transfer' ? now()->addMinutes(config('store.qr_hold_minutes')) : null,
                    'checkout_token' => $channel === 'online' ? ($data['checkout_token'] ?? null) : null,
                    'tracking_token' => $channel === 'online' ? Str::random(48) : null]);
                $total = 0;
                foreach ($data['items'] as $line) {
                    $variant = ProductVariant::with(['product', 'size', 'color'])->findOrFail($line['variant_id']);
                    if (! $variant->is_active || ! $variant->product->is_active || $variant->available_stock < (int) $line['quantity']) {
                        throw ValidationException::withMessages(['items' => 'Sản phẩm '.$variant->sku.' ngừng bán hoặc không đủ tồn kho.']);
                    }
                    $order->items()->create(['product_variant_id' => $variant->id, 'product_name' => $variant->product->name,
                        'sku' => $variant->sku, 'variant_label' => $variant->label,
                        'quantity' => $line['quantity'], 'unit_price' => $variant->price]);
                    $total += (int) $line['quantity'] * $variant->price;
                }
                $coupon = ! empty($data['coupon_code']) ? Coupon::where('code', strtoupper($data['coupon_code']))->lockForUpdate()->first() : null;
                if (! empty($data['coupon_code']) && (! $coupon || ! $coupon->availableFor($total))) {
                    throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không hợp lệ, hết lượt, hết hạn hoặc chưa đạt giá trị tối thiểu.']);
                }
                $discount = $coupon?->discountFor($total) ?? 0;
                $shipping = $channel === 'online' && $total < config('store.free_shipping_from') ? config('store.shipping_fee') : 0;
                $order->update(['subtotal' => $total, 'discount' => $discount, 'shipping_fee' => $shipping,
                    'coupon_id' => $coupon?->id, 'coupon_code' => $coupon?->code, 'total' => $total - $discount + $shipping]);
                $coupon?->increment('used_count');
                if ($method === 'bank_transfer') {
                    if ($order->total <= 0) {
                        throw ValidationException::withMessages(['payment_method' => 'Đơn có tổng tiền bằng 0 không cần chuyển khoản. Vui lòng chọn COD.']);
                    }
                    $this->reserveStock($order);
                    $order->update(['stock_reserved' => true]);
                }
                $order->events()->create(['user_id' => $user?->id, 'status' => $order->status, 'description' => $channel === 'online' ? 'Đã tiếp nhận đơn đặt hàng trực tuyến.' : 'Đã tạo đơn bán tại quầy.']);
                if ($channel === 'online' && $order->customer_email) {
                    OrderMessage::create(['sales_order_id' => $order->id, 'recipient' => $order->customer_email]);
                }

                return $order;
            });
        } catch (QueryException $exception) {
            // Concurrent submissions may both pass the initial token lookup. The unique
            // constraint selects the winner; return that committed order to the loser.
            if ($channel === 'online' && ! empty($data['checkout_token'])) {
                $existing = SalesOrder::where('checkout_token', $data['checkout_token'])->first();
                if ($existing) {
                    return $existing;
                }
            }
            throw $exception;
        }
    }

    public function completeSale(SalesOrder $sale, User $user, string $method): SalesOrder
    {
        Validator::make(['method' => $method], ['method' => 'required|in:cash,bank_transfer'])->validate();

        return $this->transaction(function () use ($sale, $user, $method) {
            $order = SalesOrder::lockForUpdate()->findOrFail($sale->id);
            if ($order->status === 'completed') {
                return $order;
            }
            if (! in_array($order->status, ['draft', 'pending'], true)) {
                throw ValidationException::withMessages(['order' => 'Đơn đã hủy không thể thanh toán.']);
            }
            if ($order->channel === 'online' && $order->fulfillment_status !== 'delivered') {
                throw ValidationException::withMessages(['order' => 'Cần xác nhận đã giao hàng trước khi hoàn tất đơn.']);
            }
            $payment = $order->payment;
            if ($order->channel === 'online' && $order->payment_method === 'bank_transfer' && ! $payment) {
                throw ValidationException::withMessages(['payment' => 'Cần đối soát chuyển khoản trước khi hoàn tất đơn QR.']);
            }
            $total = 0;
            foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
                $variant = ProductVariant::with('product')->lockForUpdate()->findOrFail($item->product_variant_id);
                if ((! $variant->is_active || ! $variant->product->is_active) && ! $order->stock_reserved) {
                    throw ValidationException::withMessages(['items' => 'Sản phẩm '.$item->sku.' đã ngừng bán.']);
                }
                // A conditional decrement protects stock even on SQLite, where FOR UPDATE is unavailable.
                $available = $variant->stock - $variant->reserved_stock + ($order->stock_reserved ? $item->quantity : 0);
                $updated = $available >= $item->quantity ? ProductVariant::whereKey($variant->id)->where('stock', '>=', $item->quantity)->decrement('stock', $item->quantity) : 0;
                if (! $updated) {
                    throw ValidationException::withMessages(['items' => 'Không đủ tồn kho cho '.$item->sku.'. Vui lòng kiểm tra lại.']);
                }
                if ($order->stock_reserved) {
                    ProductVariant::whereKey($variant->id)->decrement('reserved_stock', $item->quantity);
                }
                InventoryMovement::create(['product_variant_id' => $variant->id, 'user_id' => $user->id,
                    'kind' => 'sale', 'quantity' => -$item->quantity, 'balance_after' => $variant->stock - $item->quantity,
                    'reference_type' => 'sale', 'reference_id' => $order->id]);
                $total += $item->quantity * $item->unit_price;
            }
            $total = $total - $order->discount + $order->shipping_fee;
            if (! $payment) {
                Payment::create(['sales_order_id' => $order->id, 'method' => $method, 'amount' => $total, 'paid_at' => now(), 'verified_by' => $user->id]);
            }
            $order->update(['status' => 'completed', 'stock_reserved' => false, 'total' => $total, 'completed_at' => now(), 'user_id' => $user->id]);
            $order->events()->create(['user_id' => $user->id, 'status' => 'completed', 'description' => 'Đã nhận đủ tiền, xuất kho và hoàn tất đơn hàng.']);

            return $order;
        }, 3);
    }

    public function cancelSale(SalesOrder $sale, bool $customerRequest = false): void
    {
        $this->transaction(function () use ($sale, $customerRequest) {
            $order = SalesOrder::lockForUpdate()->findOrFail($sale->id);
            if ($order->status === 'completed') {
                throw ValidationException::withMessages(['order' => 'Không thể hủy đơn đã hoàn tất. Vui lòng lập phiếu trả hàng.']);
            }
            if ($customerRequest && ($order->status !== 'pending' || $order->fulfillment_status !== 'pending')) {
                throw ValidationException::withMessages(['order' => 'Chỉ có thể tự hủy đơn trước khi cửa hàng xác nhận.']);
            }
            if ($customerRequest && ($order->payment || $order->transfer_reported_at)) {
                throw ValidationException::withMessages(['order' => 'Đơn đã báo chuyển khoản hoặc đã thu tiền. Vui lòng liên hệ cửa hàng để đối soát và hủy/hoàn tiền.']);
            }
            if ($order->status === 'cancelled') {
                return;
            }
            if (in_array($order->fulfillment_status, ['shipping', 'delivered', 'delivery_failed'])) {
                throw ValidationException::withMessages(['order' => 'Đơn đang giao hoặc đã giao không thể hủy trực tiếp.']);
            }
            if ($order->stock_reserved) {
                foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
                    $variant = ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);
                    $variant->decrement('reserved_stock', $item->quantity);
                }
            }
            if ($order->coupon_id) {
                Coupon::whereKey($order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
            }
            $order->update(['status' => 'cancelled', 'stock_reserved' => false]);
            $order->events()->create(['status' => 'cancelled', 'description' => 'Đã hủy đơn và giải phóng phần hàng giữ chỗ.'.($order->payment ? ' Cửa hàng cần hoàn lại toàn bộ tiền đã thu.' : '')]);
        }, 3);
    }

    public function advanceDelivery(SalesOrder $sale, User $user, string $status, ?string $tracking = null): void
    {
        if ($this->expireUnpaidQr($sale->id)) {
            throw ValidationException::withMessages(['order' => 'Đơn QR đã hết hạn và được hủy. Vui lòng tạo đơn mới.']);
        }
        $this->transaction(function () use ($sale, $user, $status, $tracking) {
            $order = SalesOrder::lockForUpdate()->findOrFail($sale->id);
            if ($order->fulfillment_status === $status) {
                return;
            }
            $next = ['pending' => ['confirmed'], 'confirmed' => ['packing'], 'packing' => ['shipping'], 'shipping' => ['delivered', 'delivery_failed'], 'delivery_failed' => ['shipping', 'returned_to_store']];
            if ($order->channel !== 'online' || $order->status !== 'pending' || ! in_array($status, $next[$order->fulfillment_status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Bước xử lý không hợp lệ. Vui lòng thực hiện tuần tự.']);
            }
            if ($status === 'shipping' && $order->payment_method === 'bank_transfer' && ! $order->payment) {
                throw ValidationException::withMessages(['payment' => 'Đơn QR phải được đối soát đã nhận tiền trước khi bàn giao vận chuyển.']);
            }
            if ($status === 'confirmed' && ! $order->stock_reserved) {
                $this->reserveStock($order);
                $order->stock_reserved = true;
            }
            $order->fulfillment_status = $status;
            $order->user_id ??= $user->id;
            if ($status === 'returned_to_store') {
                foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
                    $variant = ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);
                    $variant->decrement('reserved_stock', $item->quantity);
                }
                if ($order->coupon_id) {
                    Coupon::whereKey($order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
                }
                $order->stock_reserved = false;
                $order->status = 'cancelled';
            }
            if ($tracking !== null) {
                $order->tracking_number = $tracking;
            }
            $order->save();
            $order->events()->create(['user_id' => $user->id, 'status' => $status, 'description' => $order->fulfillment_label.($status === 'confirmed' ? ' · Đã giữ hàng trong kho.' : '.').($status === 'returned_to_store' && $order->payment ? ' Chờ hoàn toàn bộ tiền đã thu.' : '')]);
        });
    }

    private function reserveStock(SalesOrder $order): void
    {
        foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
            $variant = ProductVariant::with('product')->lockForUpdate()->findOrFail($item->product_variant_id);
            if (! $variant->is_active || ! $variant->product->is_active || $variant->available_stock < $item->quantity) {
                throw ValidationException::withMessages(['stock' => 'Không đủ hàng có thể bán cho '.$item->sku.'.']);
            }
            $variant->increment('reserved_stock', $item->quantity);
        }
    }

    public function reportTransfer(SalesOrder $sale): void
    {
        if ($this->expireUnpaidQr($sale->id)) {
            throw ValidationException::withMessages(['payment' => 'Đơn đã hết hạn. Nếu đã chuyển tiền, liên hệ cửa hàng để đối soát và hoàn lại.']);
        }
        $this->transaction(function () use ($sale) {
            $order = SalesOrder::lockForUpdate()->findOrFail($sale->id);
            if ($order->channel !== 'online' || $order->payment_method !== 'bank_transfer' || $order->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Đơn này không nhận thông báo chuyển khoản.']);
            }
            if ($order->transfer_reported_at || $order->payment) {
                return;
            }
            $order->update(['transfer_reported_at' => now()]);
            $order->events()->create(['status' => 'transfer_reported', 'description' => 'Khách thông báo đã chuyển khoản. Đang chờ cửa hàng kiểm tra tiền nhận thực tế.']);
        });
    }

    public function verifyTransfer(SalesOrder $sale, User $user, int $amount, string $reference): void
    {
        $this->expireUnpaidQr($sale->id);
        $reference = strtoupper(trim($reference));
        Validator::make(['amount' => $amount, 'reference' => $reference], ['amount' => 'required|integer|min:1', 'reference' => 'required|string|min:4|max:100|regex:/^[A-Za-z0-9._\/-]+$/'])->validate();
        $this->paymentTransaction(function () use ($sale, $user, $amount, $reference) {
            $order = SalesOrder::lockForUpdate()->findOrFail($sale->id);
            if ($order->channel !== 'online' || $order->payment_method !== 'bank_transfer') {
                throw ValidationException::withMessages(['payment' => 'Chỉ đối soát chuyển khoản cho đơn QR.']);
            }
            if ($order->payment) {
                if ($order->payment->bank_reference === $reference && $order->payment->amount === $amount) {
                    return;
                }
                throw ValidationException::withMessages(['payment' => 'Đơn đã được xác nhận thu tiền.']);
            }
            if (! in_array($order->status, ['pending', 'cancelled'], true) || ($order->status === 'pending' && ! $order->stock_reserved)) {
                throw ValidationException::withMessages(['payment' => 'Đơn đang xử lý phải còn giữ hàng. Tiền nhận muộn của đơn hủy cần được ghi nhận để hoàn lại.']);
            }
            if ($amount !== $order->total || Payment::where('bank_reference', $reference)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Số tiền phải khớp tổng đơn và mã giao dịch chưa được dùng cho đơn khác.']);
            }
            Payment::create(['sales_order_id' => $order->id, 'method' => 'bank_transfer', 'amount' => $order->total, 'paid_at' => now(), 'bank_reference' => $reference, 'verified_by' => $user->id]);
            $order->events()->create(['user_id' => $user->id, 'status' => 'paid', 'description' => 'Cửa hàng đã đối soát nhận đủ tiền chuyển khoản.'.($order->status === 'cancelled' ? ' Đơn đã hủy, chờ hoàn toàn bộ tiền.' : '')]);
        });
    }

    public function expireUnpaidQr(int $id): bool
    {
        return $this->transaction(function () use ($id) {
            $order = SalesOrder::lockForUpdate()->find($id);
            if (! $order || $order->channel !== 'online' || $order->payment_method !== 'bank_transfer'
                || $order->status !== 'pending' || $order->fulfillment_status !== 'pending'
                || ! $order->payment_expires_at || $order->payment_expires_at->isFuture()
                || $order->transfer_reported_at || $order->payment()->exists()) {
                return false;
            }
            $this->cancelSale($order);
            $order->update(['expired_at' => now()]);
            $order->events()->create(['status' => 'expired', 'description' => 'Tự hủy do hết hạn thanh toán QR; đã trả lại hàng giữ chỗ và lượt ưu đãi. Nếu tiền đến muộn, cửa hàng sẽ đối soát và hoàn lại.']);

            return true;
        });
    }

    public function refundCancelled(SalesOrder $sale, User $user, string $reference): void
    {
        $reference = strtoupper(trim($reference));
        Validator::make(['reference' => $reference], ['reference' => 'required|string|min:4|max:100|regex:/^[A-Za-z0-9._\/-]+$/'])->validate();
        $this->paymentTransaction(function () use ($sale, $user, $reference) {
            $order = SalesOrder::lockForUpdate()->findOrFail($sale->id);
            $payment = $order->payment;
            if ($order->status !== 'cancelled' || ! $payment) {
                throw ValidationException::withMessages(['payment' => 'Chỉ hoàn tiền đơn đã hủy và đã thu tiền.']);
            }
            if ($payment->refunded_at) {
                return;
            }
            if (Payment::where('refund_reference', $reference)->exists()) {
                throw ValidationException::withMessages(['reference' => 'Mã giao dịch hoàn tiền đã được sử dụng.']);
            }
            $payment->update(['status' => 'refunded', 'refunded_at' => now(), 'refund_reference' => $reference, 'refunded_by' => $user->id]);
            $order->events()->create(['user_id' => $user->id, 'status' => 'refunded', 'description' => 'Cửa hàng xác nhận đã hoàn toàn bộ tiền của đơn bị hủy.']);
        });
    }

    public function updateShipment(SalesOrder $sale, User $user, array $data): void
    {
        $data = Validator::make($data, ['shipping_carrier' => 'nullable|string|max:100', 'tracking_number' => 'nullable|string|max:100', 'delivery_location' => 'required|string|max:150', 'delivery_note' => 'nullable|string|max:100'])->validate();
        $this->transaction(function () use ($sale, $user, $data) {
            $order = SalesOrder::lockForUpdate()->findOrFail($sale->id);
            if ($order->channel !== 'online' || $order->status !== 'pending') {
                throw ValidationException::withMessages(['order' => 'Chỉ cập nhật vận chuyển cho đơn online đang xử lý.']);
            }
            $order->update(['shipping_carrier' => $data['shipping_carrier'] ?? null, 'tracking_number' => $data['tracking_number'] ?? null, 'delivery_location' => $data['delivery_location']]);
            $description = 'Cập nhật vận chuyển: '.$data['delivery_location'].(! empty($data['delivery_note']) ? ' · '.$data['delivery_note'] : '');
            $order->events()->create(['user_id' => $user->id, 'status' => 'shipment_update', 'description' => $description]);
        });
    }

    public function adjustInventory(array $data, User $user): void
    {
        $data = Validator::make($data, ['variant_id' => 'required|integer|exists:product_variants,id', 'quantity' => 'required|integer|between:-10000,10000|not_in:0', 'reason' => 'required|string|min:5|max:500', 'request_token' => 'required|uuid'])->validate();
        $this->transaction(function () use ($data, $user) {
            $variant = ProductVariant::lockForUpdate()->findOrFail($data['variant_id']);
            if (DB::table('inventory_adjustments')->where('request_token', $data['request_token'])->lockForUpdate()->first()) {
                return;
            }
            $after = $variant->stock + (int) $data['quantity'];
            if ($after < $variant->reserved_stock || $after > 2000000000) {
                throw ValidationException::withMessages(['quantity' => 'Tồn sau điều chỉnh không được âm, thấp hơn lượng đã giữ hoặc vượt giới hạn.']);
            }
            $id = DB::table('inventory_adjustments')->insertGetId(['product_variant_id' => $variant->id, 'user_id' => $user->id, 'quantity' => $data['quantity'], 'balance_before' => $variant->stock, 'balance_after' => $after, 'reason' => $data['reason'], 'request_token' => $data['request_token'], 'created_at' => now(), 'updated_at' => now()]);
            $variant->forceFill(['stock' => $after])->save();
            InventoryMovement::create(['product_variant_id' => $variant->id, 'user_id' => $user->id, 'kind' => 'adjustment', 'quantity' => $data['quantity'], 'balance_after' => $after, 'reference_type' => 'adjustment', 'reference_id' => $id]);
        });
    }

    public function returnSale(SalesOrder $sale, User $user, string $reason, bool $restock): SalesReturn
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:500'])->validate();

        return $this->transaction(function () use ($sale, $user, $reason, $restock) {
            $order = SalesOrder::lockForUpdate()->findOrFail($sale->id);
            if ($existing = $order->saleReturn) {
                return $existing;
            }
            if ($order->status !== 'completed') {
                throw ValidationException::withMessages(['order' => 'Chỉ nhận trả hàng cho đơn đã hoàn tất.']);
            }
            $return = SalesReturn::create(['sales_order_id' => $order->id, 'user_id' => $user->id, 'refund_amount' => $order->subtotal - $order->discount, 'restock' => $restock, 'reason' => $reason]);
            if ($restock) {
                foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
                    $variant = ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);
                    if ($variant->stock + $item->quantity > 2000000000) {
                        throw ValidationException::withMessages(['stock' => 'Tồn kho vượt giới hạn.']);
                    }
                    $variant->increment('stock', $item->quantity);
                    InventoryMovement::create(['product_variant_id' => $variant->id, 'user_id' => $user->id, 'kind' => 'return', 'quantity' => $item->quantity, 'balance_after' => $variant->stock, 'reference_type' => 'return', 'reference_id' => $return->id]);
                }
            }
            $order->events()->create(['user_id' => $user->id, 'status' => 'returned', 'description' => 'Đã nhận trả toàn bộ đơn và xác nhận hoàn tiền hàng.']);

            return $return;
        });
    }
}
