<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMessage extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['attempted_at' => 'datetime', 'processed_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
}
