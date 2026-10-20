<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('cod');
            $table->json('bank_snapshot')->nullable();
            $table->timestamp('transfer_reported_at')->nullable();
            $table->string('shipping_carrier', 100)->nullable();
            $table->string('delivery_location', 150)->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->string('bank_reference', 100)->nullable()->unique();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reference', 100)->nullable()->unique();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropUnique(['bank_reference']);
            $table->dropUnique(['refund_reference']);
            $table->dropColumn(['bank_reference', 'refunded_at', 'refund_reference']);
        });
        Schema::table('sales_orders', fn (Blueprint $table) => $table->dropColumn(['payment_method', 'bank_snapshot', 'transfer_reported_at', 'shipping_carrier', 'delivery_location']));
    }
};
