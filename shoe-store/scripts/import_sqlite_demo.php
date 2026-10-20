<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'sole_store') {
    throw new RuntimeException('Use only the empty sole_store MySQL database.');
}
$source = new PDO('sqlite:'.realpath(__DIR__.'/../database/database.sqlite'));
$source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$tables = ['users', 'categories', 'brands', 'sizes', 'colors', 'products', 'product_images', 'product_variants', 'suppliers', 'customers', 'customer_accounts', 'wishlists', 'coupons', 'purchase_orders', 'purchase_order_items', 'sales_orders', 'sales_order_items', 'payments', 'inventory_movements', 'order_events', 'inventory_adjustments', 'sales_returns'];
foreach ($tables as $table) {
    if (DB::table($table)->exists()) {
        throw new RuntimeException('Destination is not empty: '.$table);
    }
}
DB::transaction(function () use ($source, $tables) {
    foreach ($tables as $table) {
        $statement = $source->query('SELECT * FROM "'.$table.'" ORDER BY id');
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            DB::table($table)->insert($row);
        }
    }
});
echo "Copied existing demo to MySQL; SQLite source preserved.\n";
