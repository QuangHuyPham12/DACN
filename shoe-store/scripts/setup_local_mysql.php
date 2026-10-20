<?php

// Provision only the isolated MySQL instance initialized for this workspace.
$runtime = dirname(__DIR__, 2).'/.tools/mysql-local';
if (! is_file($runtime.'/data/auto.cnf')) {
    throw new RuntimeException('Project MySQL instance has not been initialized.');
}
$secretPath = $runtime.'/admin-secret.txt';
$adminPassword = is_file($secretPath) ? trim(file_get_contents($secretPath)) : '';
$pdo = null;
for ($attempt = 0; $attempt < 20; $attempt++) {
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;port=3309;charset=utf8mb4', 'root', $adminPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (PDOException $e) {
        if ($attempt === 19) {
            throw $e;
        } usleep(250000);
    }
}
// Verify the server owns precisely the new project datadir before any mutation.
$actual = str_replace('\\', '/', strtolower($pdo->query('SELECT @@datadir')->fetchColumn()));
$expected = str_replace('\\', '/', strtolower(realpath($runtime.'/data'))).'/';
if (rtrim($actual, '/') !== rtrim($expected, '/')) {
    throw new RuntimeException('Refusing to provision a different MySQL instance.');
}
$envPath = dirname(__DIR__).'/.env';
$env = file_get_contents($envPath);
if (is_file($secretPath)) {
    echo "Project MySQL is already provisioned. No passwords changed.\n";
    exit;
}
$appPassword = bin2hex(random_bytes(24));
$rootPassword = bin2hex(random_bytes(32));
foreach (['sole_store', 'sole_store_test'] as $database) {
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}
$pdo->exec("CREATE USER 'sole_app'@'localhost' IDENTIFIED BY ".$pdo->quote($appPassword));
foreach (['sole_store', 'sole_store_test'] as $database) {
    $pdo->exec('GRANT ALL PRIVILEGES ON `'.$database."`.* TO 'sole_app'@'localhost'");
}
$pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($rootPassword));
file_put_contents($secretPath, $rootPassword);
foreach (['DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3309', 'DB_DATABASE' => 'sole_store', 'DB_USERNAME' => 'sole_app', 'DB_PASSWORD' => $appPassword] as $key => $value) {
    $line = $key.'='.$value;
    if (preg_match('/^#?\s*'.preg_quote($key, '/').'\s*=.*$/m', $env)) {
        $env = preg_replace('/^#?\s*'.preg_quote($key, '/').'\s*=.*$/m', $line, $env);
    } else {
        $env .= "\n".$line;
    }
}
file_put_contents($envPath, $env);
echo "Provisioned project MySQL; app configuration saved without printing secrets.\n";
