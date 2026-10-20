<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAccount;
use Illuminate\Database\Seeder;

class ExtensionSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::firstOrCreate(['code' => 'SOLE10'], ['name' => 'Bước chân mới · Giảm 10%', 'type' => 'percent', 'value' => 10, 'minimum_order' => 500000, 'maximum_discount' => 150000, 'usage_limit' => 1000]);
        Coupon::firstOrCreate(['code' => 'WELCOME50'], ['name' => 'Chào SOLE · Giảm 50.000đ', 'type' => 'fixed', 'value' => 50000, 'minimum_order' => 500000, 'usage_limit' => 1000]);
        if (! CustomerAccount::where('email', 'khach@sole.vn')->exists()) {
            $customer = Customer::create(['name' => 'Khách hàng trải nghiệm', 'email' => 'khach@sole.vn', 'phone' => '0901234567', 'address' => '123 Nguyễn Trãi, TP. Hồ Chí Minh']);
            CustomerAccount::create(['customer_id' => $customer->id, 'email' => 'khach@sole.vn', 'password' => 'Demo@12345']);
        }
    }
}
