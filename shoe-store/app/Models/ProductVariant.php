<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'size_id', 'color_id', 'sku', 'price', 'low_stock_threshold', 'is_active'];

    protected $casts = ['price' => 'integer', 'stock' => 'integer', 'reserved_stock' => 'integer', 'is_active' => 'boolean'];

    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->stock - $this->reserved_stock);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->size->name.' / '.$this->color->name;
    }
}
