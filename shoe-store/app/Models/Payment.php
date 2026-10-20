<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'integer', 'paid_at' => 'datetime', 'refunded_at' => 'datetime'];
}
