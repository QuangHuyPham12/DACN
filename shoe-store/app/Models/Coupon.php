<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = ['id', 'used_count'];

    protected $casts = ['is_active' => 'boolean', 'starts_at' => 'date', 'ends_at' => 'date', 'minimum_order' => 'integer', 'maximum_discount' => 'integer'];

    public function availableFor(int $subtotal): bool
    {
        return $this->is_active && (! $this->starts_at || $this->starts_at->lte(today()))
            && (! $this->ends_at || $this->ends_at->gte(today())) && $subtotal >= $this->minimum_order
            && ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }

    public function discountFor(int $subtotal): int
    {
        $discount = $this->type === 'percent' ? intdiv($subtotal * $this->value, 100) : $this->value;

        return min($subtotal, $this->maximum_discount ?? $subtotal, $discount);
    }
}
