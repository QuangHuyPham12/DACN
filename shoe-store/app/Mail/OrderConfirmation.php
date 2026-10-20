<?php

namespace App\Mail;

use App\Models\SalesOrder;
use Illuminate\Mail\Mailable;

class OrderConfirmation extends Mailable
{
    public function __construct(public SalesOrder $order) {}

    public function build(): static
    {
        $trackingUrl = rtrim(config('app.url'), '/').'/don-hang/'.$this->order->tracking_token;

        return $this->subject('SOLE đã tiếp nhận đơn '.$this->order->code)->view('mail.order-confirmation', compact('trackingUrl'));
    }
}
