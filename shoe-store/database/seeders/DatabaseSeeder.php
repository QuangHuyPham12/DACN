<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StoreService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ExtensionSeeder::class);
        // Never reset an existing shop or overwrite real accounts on a second seed run.
        if (Product::exists() || User::whereIn('email', ['admin@sole.vn', 'staff@sole.vn'])->exists()) {
            $this->command?->warn('Dữ liệu đã tồn tại. Bỏ qua dữ liệu demo để bảo toàn cửa hàng.');

            return;
        }
        DB::transaction(function () {
            $admin = User::create(['name' => 'Phạm Quang Huy', 'email' => 'admin@sole.vn', 'password' => 'Demo@12345', 'role' => 'admin', 'is_active' => true]);
            $staff = User::create(['name' => 'Đào Ngọc Tú', 'email' => 'staff@sole.vn', 'password' => 'Demo@12345', 'role' => 'staff', 'is_active' => true]);
            $categories = collect(['Sneaker', 'Giày chạy bộ', 'Giày thể thao', 'Giày casual'])->map(fn ($name) => Category::firstOrCreate(['name' => $name]));
            $brands = collect(['Nike', 'Adidas', 'New Balance', 'Converse', 'Vans', 'Puma'])->map(fn ($name) => Brand::firstOrCreate(['name' => $name]));
            $sizes = collect(['38', '39', '40', '41', '42', '43'])->map(fn ($name) => Size::firstOrCreate(['name' => $name]));
            $colors = collect(['Trắng', 'Đen', 'Kem'])->map(fn ($name) => Color::firstOrCreate(['name' => $name]));
            $suppliers = collect(['Công ty Giày Việt', 'Phân phối Thể thao Bắc Ninh', 'Sneaker Distribution', 'Công ty An Phát'])->map(fn ($name, $i) => Supplier::create(['name' => $name, 'phone' => '090000000'.($i + 1), 'email' => 'supplier'.($i + 1).'@example.com', 'address' => 'Địa chỉ nhà cung cấp giả lập '.($i + 1).', Bắc Ninh']));
            $customers = collect(['Nguyễn Minh Anh', 'Trần Đức Nam', 'Lê Thu Hà', 'Phạm Hoàng Long', 'Đỗ Ngọc Mai', 'Vũ Thanh Tùng', 'Ngô Hải Yến', 'Bùi Quang Minh'])->map(fn ($name, $i) => Customer::create(['name' => $name, 'phone' => '091000000'.($i + 1), 'email' => 'customer'.($i + 1).'@example.com', 'address' => 'Địa chỉ khách hàng giả lập '.($i + 1).', Hà Nội']));
            $names = ['Air Force Everyday', 'Court Vision Low', 'Air Max Motion', 'Revolution Runner', 'Stan Smith Classic', 'Grand Court Base', 'Runfalcon Daily', 'Lite Racer Adapt', '574 Heritage', '327 Lifestyle', 'Fresh Foam Arishi', '480 Court', 'Chuck Taylor Low', 'Chuck 70 Canvas', 'Star Player Classic', 'Run Star Motion', 'Old Skool Essential', 'Authentic Canvas', 'Slip On Classic', 'Sk8 Low Daily', 'Caven Court', 'Smash Casual', 'RS Move Runner', 'Suede Classic'];
            foreach ($names as $i => $name) {
                $product = Product::create(['name' => $name, 'brand_id' => $brands[intdiv($i, 4)]->id, 'category_id' => $categories[$i % 4]->id,
                    'description' => 'Thiết kế dễ phối đồ cho nhịp sống hằng ngày. Phom giày gọn gàng, đế êm và bảng màu trung tính. Chọn đúng kích cỡ và màu sắc trong các lựa chọn bên dưới. Sản phẩm và hình minh họa dùng cho dữ liệu demo đồ án.', 'is_active' => true]);
                foreach ([0, 1, 2] as $j) {
                    $product->variants()->create(['size_id' => $sizes[$j + 1]->id, 'color_id' => $colors[$j]->id,
                        'sku' => 'SOLE-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT).'-'.($j + 39), 'price' => 650000 + ($i % 8) * 150000,
                        'low_stock_threshold' => 5, 'is_active' => true]);
                }
            }
            $service = app(StoreService::class);
            $variants = ProductVariant::orderBy('id')->get();
            $baseTime = now()->startOfDay();
            try {
                Carbon::setTestNow($baseTime->copy()->subDays(20)->setTime(9, 0));
                foreach ($variants->chunk(18) as $i => $batch) {
                    $purchase = $service->createPurchase(['supplier_id' => $suppliers[$i]->id, 'note' => 'Nhập hàng đầu kỳ · Dữ liệu demo', 'items' => $batch->map(fn ($v) => ['variant_id' => $v->id, 'quantity' => $v->id > 66 ? 3 : 22 + ($v->id % 8), 'unit_cost' => (int) ($v->price * 6 / 10)])->values()->all()], $admin);
                    $service->confirmPurchase($purchase, $admin);
                }
                for ($i = 0; $i < 30; $i++) {
                    Carbon::setTestNow($baseTime->copy()->subDays(14 - intdiv($i, 2))->setTime(10 + ($i % 6), 15));
                    $lines = [['variant_id' => $variants[($i * 2) % 60]->id, 'quantity' => 1], ['variant_id' => $variants[($i * 2 + 1) % 60]->id, 'quantity' => 1 + ($i % 2)]];
                    $data = ['customer_id' => $customers[$i % 8]->id, 'items' => $lines];
                    $channel = $i % 4 === 0 ? 'online' : 'pos';
                    if ($channel === 'online') {
                        $data += ['customer_name' => $customers[$i % 8]->name, 'customer_phone' => $customers[$i % 8]->phone, 'shipping_address' => $customers[$i % 8]->address];
                    }
                    $order = $service->createSale($data, $channel === 'online' ? null : $staff, $channel);
                    if ($i < 27) {
                        if ($channel === 'online') {
                            foreach (['confirmed', 'packing', 'shipping', 'delivered'] as $step) {
                                $service->advanceDelivery($order, $staff, $step);
                            }
                        }
                        $service->completeSale($order, $staff, $i % 3 === 0 ? 'bank_transfer' : 'cash');
                    } elseif ($i === 27) {
                        $service->cancelSale($order);
                    }
                }
                Carbon::setTestNow($baseTime->copy()->setTime(9, 30));
                $service->createPurchase(['supplier_id' => $suppliers[0]->id, 'items' => [['variant_id' => $variants[70]->id, 'quantity' => 15, 'unit_cost' => 480000]], 'note' => 'Phiếu chờ xác nhận để demo'], $admin);
            } finally {
                Carbon::setTestNow();
            }
        });
    }
}
