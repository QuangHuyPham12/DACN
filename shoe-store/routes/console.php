<?php

use App\Models\OrderMessage;
use App\Models\SalesOrder;
use App\Services\OrderMessaging;
use App\Services\StoreService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('store:expire-qr', function () {
    $count = 0;
    SalesOrder::where('channel', 'online')->where('payment_method', 'bank_transfer')
        ->where('status', 'pending')->where('fulfillment_status', 'pending')->whereNull('transfer_reported_at')
        ->where('payment_expires_at', '<=', now())->whereDoesntHave('payment')->select('id')->chunkById(100, function ($orders) use (&$count) {
            foreach ($orders as $order) {
                $count += (int) app(StoreService::class)->expireUnpaidQr($order->id);
            }
        });
    $this->info('Đã giải phóng '.$count.' đơn QR hết hạn.');
})->purpose('Hủy đơn QR bỏ dở chưa báo chuyển khoản, chưa thu tiền và chưa xác nhận');

Artisan::command('store:send-order-mail {--limit=25}', function () {
    $messages = OrderMessage::whereIn('status', ['pending', 'failed'])->where('attempts', '<', 3)
        ->where(fn ($q) => $q->whereNull('attempted_at')->orWhere('attempted_at', '<=', now()->subMinutes(5)))
        ->orderBy('id')->limit(max(1, min(100, (int) $this->option('limit'))))->pluck('id');
    foreach ($messages as $id) {
        app(OrderMessaging::class)->deliver($id);
    }
    $this->info('Đã xử lý hàng đợi email đơn hàng.');
})->purpose('Gửi email đơn hàng, giữ hàng đợi nếu SMTP lỗi');

Schedule::command('store:expire-qr')->everyMinute()->withoutOverlapping(5);
Schedule::command('store:send-order-mail')->everyMinute()->withoutOverlapping(10);
Schedule::call(fn () => DB::table('customer_password_reset_tokens')->where('created_at', '<', now()->subMinutes(60))->delete())->hourly()->name('customer-reset-token-cleanup');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
