<?php

// Compile the actual migrations with Laravel's MySQL grammar without connecting to a MySQL server.
// Reads the configured source database only when --with-data is explicitly supplied.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = DB::connection();
$connection = new class(fn () => throw new RuntimeException('This exporter must not connect to MySQL.'), 'sole_store', '', ['driver' => 'mysql', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB']) extends MySqlConnection
{
    public function isMaria()
    {
        return false;
    }

    public function getServerVersion(): string
    {
        return '8.0.36';
    }

    protected function escapeString($value)
    {
        // Used only while recording migration queries; the export enables
        // NO_BACKSLASH_ESCAPES and uses SQL-standard doubled apostrophes.
        return "'".str_replace("'", "''", $value)."'";
    }
};
$connection->useDefaultSchemaGrammar();
$builder = $connection->getSchemaBuilder();
Schema::swap($builder);
DB::swap($connection);
$queries = $connection->pretend(function () use ($builder) {
    $builder->create('migrations', function ($table) {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });
    foreach (glob(__DIR__.'/../database/migrations/*.php') as $file) {
        (require $file)->up();
    }
});
$header = "-- SOLE Footwear | MySQL 8.0 / MariaDB 10.6+\n-- Import into a NEW EMPTY database. This script does not drop existing tables.\nSET NAMES utf8mb4;\nSET @SOLE_OLD_SQL_MODE = @@SQL_MODE;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO,NO_BACKSLASH_ESCAPES';\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
$ddl = array_filter(array_column($queries, 'query'), fn ($query) => preg_match('/^(create|alter)\s/i', $query));
$sql = $header.implode(";\n", $ddl).";\n\n";
$footer = "\nSET FOREIGN_KEY_CHECKS = 1;\nSET SQL_MODE = @SOLE_OLD_SQL_MODE;\n";
$directory = __DIR__.'/../database/sql';
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}
$migrationState = '';
foreach (glob(__DIR__.'/../database/migrations/*.php') as $index => $file) {
    $migrationState .= 'INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('.($index + 1).", '".basename($file, '.php')."', 1);\n";
}
file_put_contents($directory.'/sole_store_schema.sql', $sql.$migrationState.$footer);
echo "Created database/sql/sole_store_schema.sql\n";
if (in_array('--with-data', $argv, true)) {
    $tables = ['users', 'categories', 'brands', 'sizes', 'colors', 'products', 'product_images', 'product_variants', 'suppliers', 'customers', 'customer_accounts', 'wishlists', 'coupons', 'purchase_orders', 'purchase_order_items', 'sales_orders', 'sales_order_items', 'payments', 'inventory_movements', 'order_events', 'inventory_adjustments', 'sales_returns', 'product_reviews', 'support_requests', 'order_messages', 'migrations'];
    foreach ($tables as $table) {
        foreach ($source->table($table)->orderBy('id')->cursor() as $row) {
            $values = (array) $row;
            $columns = implode(', ', array_map(fn ($key) => '`'.$key.'`', array_keys($values)));
            $encoded = array_map(fn ($value) => $value === null ? 'NULL' : "'".str_replace("'", "''", (string) $value)."'", array_values($values));
            $sql .= 'INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.implode(', ', $encoded).");\n";
        }
    }
    file_put_contents($directory.'/sole_store_demo.sql', $sql.$footer);
    echo "Created database/sql/sole_store_demo.sql (includes configured database contents)\n";
}
