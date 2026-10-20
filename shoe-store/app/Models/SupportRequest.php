<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['handled_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function account()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function getKindLabelAttribute(): string
    {
        return $this->kind === 'exchange' ? 'Đổi size' : 'Trả hàng';
    }

    public function getStatusLabelAttribute(): string
    {
        return ['pending' => 'Chờ tiếp nhận', 'reviewing' => 'Đang trao đổi', 'approved' => 'Đã đồng ý tiếp nhận', 'rejected' => 'Không được chấp thuận', 'resolved' => 'Đã xử lý xong'][$this->status];
    }
}
