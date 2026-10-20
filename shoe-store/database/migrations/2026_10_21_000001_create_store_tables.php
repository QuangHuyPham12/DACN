<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff'])->default('staff');
            $table->boolean('is_active')->default(true);
        });
        foreach (['categories', 'brands', 'sizes', 'colors'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->timestamps();
            });
        }
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('size_id')->constrained()->restrictOnDelete();
            $table->foreignId('color_id')->constrained()->restrictOnDelete();
            $table->string('sku', 80)->unique();
            // Whole VND; all arithmetic in the application uses integers.
            $table->decimal('price', 15, 0);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'size_id', 'color_id']);
        });
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('supplier_name', 200);
            $table->enum('status', ['draft', 'confirmed'])->default('draft')->index();
            $table->decimal('total', 15, 0)->default(0);
            $table->text('note')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('sku', 80);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 15, 0);
            $table->unique(['purchase_order_id', 'product_variant_id']);
        });
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('shipping_address')->nullable();
            $table->enum('channel', ['pos', 'online'])->default('pos');
            $table->enum('status', ['draft', 'pending', 'completed', 'cancelled'])->default('draft')->index();
            $table->decimal('total', 15, 0)->default(0);
            $table->text('note')->nullable();
            $table->uuid('checkout_token')->nullable()->unique();
            $table->string('tracking_token', 64)->nullable()->unique();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('sku', 80);
            $table->string('variant_label');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 0);
            $table->unique(['sales_order_id', 'product_variant_id']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->unique()->constrained()->restrictOnDelete();
            $table->enum('method', ['cash', 'bank_transfer']);
            $table->decimal('amount', 15, 0);
            $table->string('status')->default('paid');
            $table->timestamp('paid_at');
            $table->timestamps();
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('kind', ['purchase', 'sale']);
            $table->integer('quantity');
            $table->unsignedInteger('balance_after');
            $table->string('reference_type', 30);
            $table->unsignedBigInteger('reference_id');
            $table->timestamps();
            $table->unique(['reference_type', 'reference_id', 'product_variant_id'], 'movement_reference_unique');
        });
    }

    public function down(): void
    {
        foreach (['inventory_movements', 'payments', 'sales_order_items', 'sales_orders', 'purchase_order_items', 'purchase_orders', 'customers', 'suppliers', 'product_variants', 'product_images', 'products', 'colors', 'sizes', 'brands', 'categories'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'is_active']));
    }
};
