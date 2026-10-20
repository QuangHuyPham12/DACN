<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable();
            $table->timestamp('payment_expires_at')->nullable()->index();
            $table->timestamp('expired_at')->nullable();
        });
        Schema::table('customer_accounts', fn (Blueprint $table) => $table->unsignedInteger('auth_version')->default(1));
        Schema::table('products', function (Blueprint $table) {
            $table->string('material', 255)->nullable();
            $table->string('fit_note', 500)->nullable();
            $table->text('care_note')->nullable();
            $table->text('size_note')->nullable();
        });
        Schema::table('product_images', fn (Blueprint $table) => $table->unsignedInteger('sort_order')->default(0));
        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('order_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->string('kind', 30)->default('confirmation');
            $table->string('recipient');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['sales_order_id', 'kind']);
        });
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'customer_account_id']);
        });
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_account_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->text('reason');
            $table->string('desired_size', 30)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('staff_reply')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_requests');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('order_messages');
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['material', 'fit_note', 'care_note', 'size_note']));
        Schema::table('customer_accounts', fn (Blueprint $table) => $table->dropColumn('auth_version'));
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn('sort_order'));
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['payment_expires_at']);
            $table->dropColumn(['customer_email', 'payment_expires_at', 'expired_at']);
        });
    }
};
