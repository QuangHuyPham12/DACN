<?php

namespace App\Services;

use App\Mail\OrderConfirmation;
use App\Models\OrderMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderMessaging
{
    public function deliver(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $message = OrderMessage::lockForUpdate()->find($id);
            if (! $message || ! in_array($message->status, ['pending', 'failed']) || $message->attempts >= 3 || ($message->attempted_at && $message->attempted_at->gt(now()->subMinutes(5)))) {
                return false;
            }
            $message->increment('attempts');
            $message->attempted_at = now();
            try {
                Mail::to($message->recipient)->send(new OrderConfirmation($message->order->load('items')));
                $message->status = in_array(config('mail.default'), ['log', 'array']) ? 'preview' : 'sent';
                $message->processed_at = now();
            } catch (\Throwable $exception) {
                $message->status = 'failed';
                Log::warning('order_mail_failed', ['message_id' => $message->id, 'type' => get_class($exception)]);
            }
            $message->save();

            return $message->status !== 'failed';
        }, 3);
    }
}
