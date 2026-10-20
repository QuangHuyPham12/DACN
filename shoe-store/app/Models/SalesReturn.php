<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['refund_amount' => 'integer', 'restock' => 'boolean'];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
