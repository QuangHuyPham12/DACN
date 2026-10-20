<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['moderated_at' => 'datetime', 'rating' => 'integer'];

    public function contentHash(): string
    {
        return hash('sha256', $this->rating.'|'.$this->body);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function account()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return ['pending' => 'Chờ duyệt', 'approved' => 'Đã hiển thị', 'rejected' => 'Đã ẩn'][$this->status];
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = preg_split('/\s+/u', trim($this->account->customer->name));

        return end($parts).' · Khách đã mua';
    }
}
