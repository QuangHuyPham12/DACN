<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['unit_price' => 'integer'];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
