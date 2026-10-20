<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['total' => 'integer', 'subtotal' => 'integer', 'discount' => 'integer', 'shipping_fee' => 'integer', 'stock_reserved' => 'boolean', 'completed_at' => 'datetime', 'bank_snapshot' => 'array', 'transfer_reported_at' => 'datetime', 'payment_expires_at' => 'datetime', 'expired_at' => 'datetime'];

    public function getPaymentLabelAttribute(): string
    {
        if ($this->payment) {
            return $this->payment->refunded_at ? 'Đã hoàn tiền' : ($this->status === 'cancelled' ? 'Chờ hoàn tiền' : 'Đã thanh toán');
        }

        return $this->status === 'cancelled' ? 'Không thu tiền' : ($this->payment_method === 'bank_transfer' ? ($this->transfer_reported_at ? 'Chờ đối soát chuyển khoản' : 'Chờ chuyển khoản') : 'COD · Chưa thu tiền');
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    public function saleReturn()
    {
        return $this->hasOne(SalesReturn::class);
    }

    public function getFulfillmentLabelAttribute(): string
    {
        return ['pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'packing' => 'Đang chuẩn bị', 'shipping' => 'Đang giao hàng', 'delivered' => 'Đã giao hàng', 'delivery_failed' => 'Giao hàng chưa thành công', 'returned_to_store' => 'Đã nhận lại hàng & hủy đơn'][$this->fulfillment_status] ?? $this->fulfillment_status;
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCodeAttribute(): string
    {
        return 'DH'.str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return ['draft' => 'Đơn nháp', 'pending' => 'Chờ xử lý', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã hủy'][$this->status];
    }
}
