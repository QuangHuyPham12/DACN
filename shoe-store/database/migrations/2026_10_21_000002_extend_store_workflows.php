<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->restrictOnDelete();
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_account_id', 'product_id']);
        });
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->enum('type', ['fixed', 'percent']);
            $table->unsignedInteger('value');
            $table->decimal('minimum_order', 15, 0)->default(0);
            $table->decimal('maximum_discount', 15, 0)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('product_variants', fn (Blueprint $table) => $table->unsignedInteger('reserved_stock')->default(0));
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 0)->default(0);
            $table->decimal('discount', 15, 0)->default(0);
            $table->decimal('shipping_fee', 15, 0)->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('coupon_code', 40)->nullable();
            $table->string('fulfillment_status', 30)->default('pending')->index();
            $table->boolean('stock_reserved')->default(false);
            $table->string('tracking_number', 100)->nullable();
        });
        // Preserve the totals of historical orders when upgrading an existing shop.
        DB::table('sales_orders')->update(['subtotal' => DB::raw('total')]);
        DB::table('sales_orders')->where('status', 'completed')->update(['fulfillment_status' => 'delivered']);
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 30);
            $table->string('description', 500);
            $table->timestamps();
        });
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->unsignedInteger('balance_before');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 500);
            $table->uuid('request_token')->unique();
            $table->timestamps();
        });
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('refund_amount', 15, 0);
            $table->boolean('restock');
            $table->string('reason', 500);
            $table->timestamps();
        });
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->string('kind', 30)->change());
    }

    public function down(): void
    {
        foreach (['sales_returns', 'inventory_adjustments', 'order_events', 'wishlists', 'customer_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['subtotal', 'discount', 'shipping_fee', 'coupon_code', 'fulfillment_status', 'stock_reserved', 'tracking_number']);
        });
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('reserved_stock'));
        Schema::dropIfExists('coupons');
    }
};
