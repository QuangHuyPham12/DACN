<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
$config['database'] = 'sole_store_test';
config(['database.connections.import_test' => $config]);
$test = DB::connection('import_test');
if ($test->getDatabaseName() !== 'sole_store_test') {
    throw new RuntimeException('Refusing to clear a database outside sole_store_test.');
}
$pdo = $test->getPdo();
foreach (['sole_store_schema.sql', 'sole_store_demo.sql'] as $filename) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec('DROP TABLE `'.str_replace('`', '``', $table).'`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec(file_get_contents(__DIR__.'/../database/sql/'.$filename));
    $products = (int) $test->table('products')->count();
    $orders = (int) $test->table('sales_orders')->count();
    if ($test->table('migrations')->count() !== count(glob(__DIR__.'/../database/migrations/*.php'))) {
        throw new RuntimeException('Migration history mismatch.');
    }
    if ($filename === 'sole_store_schema.sql' && $products !== 0) {
        throw new RuntimeException('Schema export unexpectedly includes data.');
    }
    if ($filename === 'sole_store_demo.sql' && ($products !== DB::table('products')->count() || $orders !== DB::table('sales_orders')->count())) {
        throw new RuntimeException('Demo import differs from its source.');
    }
    $customerCareCounts = [];
    foreach (['product_reviews', 'support_requests', 'order_messages'] as $table) {
        $customerCareCounts[$table] = $test->table($table)->count();
        $expected = $filename === 'sole_store_demo.sql' ? DB::table($table)->count() : 0;
        if ($customerCareCounts[$table] !== $expected) {
            throw new RuntimeException('Customer experience import differs from its source: '.$table);
        }
    }
    if ($test->table('customer_password_reset_tokens')->exists()) {
        throw new RuntimeException('Exports must not include password reset tokens.');
    }
    echo json_encode(['file' => $filename, 'database' => 'sole_store_test', 'products' => $products, 'sales_orders' => $orders, 'customer_care' => $customerCareCounts, 'passed' => true], JSON_UNESCAPED_UNICODE).PHP_EOL;
}
echo 'Verified imports on MySQL '.$test->selectOne('SELECT VERSION() AS version')->version.PHP_EOL;
