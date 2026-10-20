<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'address'];

    public function purchases()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
