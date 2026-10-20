<?php

return [
    'qr_hold_minutes' => max(5, min(1440, (int) env('STORE_QR_HOLD_MINUTES', 30))),
    'shipping_fee' => 30000,
    'free_shipping_from' => 1500000,
];
