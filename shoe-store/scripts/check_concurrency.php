<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StoreService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// An isolated two-process test. No connection to the application's normal database.
$worker = ($argv[1] ?? '') === '--worker';
$sameOrder = in_array('--same-order', $argv, true);
$qrVerify = in_array('--qr-verify', $argv, true);
$mysql = in_array('--mysql', $argv, true);
$database = $worker ? $argv[2] : tempnam(sys_get_temp_dir(), 'sole-race-');
putenv('DB_CONNECTION='.($mysql ? 'mysql' : 'sqlite'));
putenv('DB_DATABASE='.($mysql ? 'sole_store_test' : $database));
putenv('DB_URL=');
putenv('APP_ENV=testing');
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=array');
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.connections.sqlite.busy_timeout' => 5000]);
$service = app(StoreService::class);
if ($worker) {
    $deadline = microtime(true) + 10;
    while (! file_exists($database.'.start')) {
        if (microtime(true) > $deadline) {
            exit(2);
        }
        usleep(1000);
    }
    try {
        $order = SalesOrder::findOrFail($argv[3]);
        if ($qrVerify) {
            $service->verifyTransfer($order, User::firstOrFail(), $order->total, 'CONCURRENT-BANK-REFERENCE');
            echo 'verified';
        } else {
            $service->completeSale($order, User::firstOrFail(), 'cash');
            echo 'completed';
        }
    } catch (ValidationException $e) {
        echo 'rejected';
    } catch (Throwable $e) {
        fwrite(STDERR, get_class($e).': '.$e->getMessage());
        exit(1);
    }
    exit;
}

try {
    if ($mysql && DB::connection()->getDatabaseName() !== 'sole_store_test') {
        throw new RuntimeException('Refusing to use a non-test MySQL database.');
    }
    Artisan::call($mysql ? 'migrate:fresh' : 'migrate', ['--force' => true]);
    $user = User::create(['name' => 'Race test', 'email' => 'race@example.com', 'password' => 'Testpass123', 'role' => 'admin', 'is_active' => true]);
    $category = Category::create(['name' => 'Test']);
    $brand = Brand::create(['name' => 'Test']);
    $size = Size::create(['name' => '40']);
    $color = Color::create(['name' => 'Test']);
    $product = Product::create(['name' => 'Race test', 'category_id' => $category->id, 'brand_id' => $brand->id]);
    $variant = $product->variants()->create(['sku' => 'RACE-40', 'size_id' => $size->id, 'color_id' => $color->id, 'price' => 100000]);
    $supplier = Supplier::create(['name' => 'Test']);
    $purchase = $service->createPurchase(['supplier_id' => $supplier->id, 'items' => [['variant_id' => $variant->id, 'quantity' => $qrVerify ? 2 : 1, 'unit_cost' => 50000]]], $user);
    $service->confirmPurchase($purchase, $user);
    $data = ['items' => [['variant_id' => $variant->id, 'quantity' => 1]]];
    if ($qrVerify) {
        config(['payment.bank_bin' => '970436', 'payment.bank_name' => 'Test Bank', 'payment.account_number' => '0000000000000', 'payment.account_name' => 'TEST ONLY']);
        $data += ['customer_name' => 'Concurrency test', 'customer_phone' => '0000000000', 'shipping_address' => 'Test fixture', 'payment_method' => 'bank_transfer'];
    }
    $first = $service->createSale($data, $user, $qrVerify ? 'online' : 'pos');
    $second = $service->createSale($data, $user, $qrVerify ? 'online' : 'pos');
    $processes = [];
    foreach ([$first, $sameOrder ? $first : $second] as $order) {
        $arguments = [PHP_BINARY, __FILE__, '--worker', $database, (string) $order->id];
        if ($mysql) {
            $arguments[] = '--mysql';
        }
        if ($qrVerify) {
            $arguments[] = '--qr-verify';
        }
        $process = proc_open($arguments, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (! is_resource($process)) {
            throw new RuntimeException('Cannot start worker.');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    touch($database.'.start');
    $results = [];
    foreach ($processes as [$process,$pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException($error ?: 'Worker failed.');
        }
        $results[] = trim($output);
    }
    sort($results);
    $stock = $variant->fresh()->stock;
    $payments = Payment::count();
    $expected = $qrVerify ? ($sameOrder ? ['verified', 'verified'] : ['rejected', 'verified']) : ($sameOrder ? ['completed', 'completed'] : ['completed', 'rejected']);
    $passed = $results === $expected && $stock === ($qrVerify ? 2 : 0) && $payments === 1;
    $scenario = $qrVerify ? ($sameOrder ? 'Two processes verify the same QR order' : 'Two orders compete for one bank reference') : ($sameOrder ? 'Two PHP processes confirm the same order' : 'Two PHP processes buy the last pair');
    echo json_encode(['database' => $mysql ? 'mysql / sole_store_test' : 'sqlite / isolated temp file', 'scenario' => $scenario, 'results' => $results, 'stock' => $stock, 'payments' => $payments, 'passed' => $passed], JSON_PRETTY_PRINT).PHP_EOL;
    $exitCode = $passed ? 0 : 1;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    DB::disconnect();
    foreach ([$database.'.start', $database.'-wal', $database.'-shm', $database.'-journal', $database] as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
exit($exitCode);
