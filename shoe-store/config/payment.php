<?php

return [
    // VietQR / NAPAS BIN: 6 digits, e.g. consult the receiving bank's official BIN.
    'bank_bin' => env('PAYMENT_BANK_BIN', ''),
    'bank_name' => env('PAYMENT_BANK_NAME', ''),
    'account_number' => env('PAYMENT_ACCOUNT_NUMBER', ''),
    'account_name' => env('PAYMENT_ACCOUNT_NAME', ''),
];
