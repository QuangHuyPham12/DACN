<?php

namespace Tests\Feature;

use App\Models\CustomerAccount;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_demo_seed_has_balanced_stock_and_reseeding_preserves_existing_data(): void
    {
        $this->seed();

        $this->assertDatabaseCount('products', 24);
        $this->assertDatabaseCount('product_variants', 72);
        $this->assertDatabaseCount('purchase_orders', 5);
        $this->assertDatabaseCount('sales_orders', 30);
        $this->assertDatabaseCount('payments', 27);
        $this->assertDatabaseCount('coupons', 2);
        $this->assertDatabaseCount('customer_accounts', 1);
        $this->assertSame(7, SalesOrder::where('channel', 'online')->where('status', 'completed')->where('fulfillment_status', 'delivered')->count());

        foreach (ProductVariant::with('movements')->get() as $variant) {
            $this->assertSame((int) $variant->movements->sum('quantity'), $variant->stock, $variant->sku);
            $this->assertSame(0, $variant->reserved_stock, $variant->sku);
        }
        $this->assertTrue(Hash::check('Demo@12345', CustomerAccount::firstOrFail()->password));

        $admin = User::where('email', 'admin@sole.vn')->firstOrFail();
        $admin->update(['name' => 'Tên đã chỉnh sửa', 'password' => 'ChangedDemo123']);
        $before = collect(['users', 'customers', 'customer_accounts', 'coupons', 'products', 'product_variants', 'purchase_orders', 'sales_orders', 'payments', 'inventory_movements'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();

        $this->seed();

        foreach ($before as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame('Tên đã chỉnh sửa', $admin->fresh()->name);
        $this->assertTrue(Hash::check('ChangedDemo123', $admin->fresh()->password));
    }
}
