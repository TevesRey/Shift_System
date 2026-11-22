<?php
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_GET['k']) || !hash_equals('sm-mig-4d92b7e1c3a8', (string) $_GET['k'])) {
    http_response_code(404);
    exit('Not found');
}
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$code = $kernel->call('migrate', ['--force' => true]);
echo $kernel->output();
echo "\nexit code: $code\n";