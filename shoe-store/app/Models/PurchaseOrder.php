<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = ['supplier_id', 'supplier_name', 'user_id', 'status', 'total', 'note', 'confirmed_at'];

    protected $casts = ['total' => 'integer', 'confirmed_at' => 'datetime'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function getCodeAttribute(): string
    {
        return 'PN'.str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }
}
