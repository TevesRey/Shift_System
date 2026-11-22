<?php
// TEMPORARY diagnostic for ShiftMate on Wasmer. DELETE THIS FILE after use.
// Open: /diag.php?k=sm-7f3a9c21e5b8
// Does not print passwords or APP_KEY.

header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

if (!isset($_GET['k']) || !hash_equals('sm-7f3a9c21e5b8', (string) $_GET['k'])) {
    http_response_code(404);
    exit('Not found');
}

function envv($name)
{
    $v = getenv($name);
    if ($v === false || $v === '') {
        $v = isset($_SERVER[$name]) ? $_SERVER[$name] : (isset($_ENV[$name]) ? $_ENV[$name] : null);
    }
    if ($v === false || $v === null || $v === '') {
        return null;
    }
    return (string) $v;
}

function line($label, $value)
{
    echo str_pad($label, 30) . $value . "\n";
}

function ok($cond)
{
    return $cond ? 'OK' : 'PROBLEM';
}

$root = dirname(__DIR__);

echo "ShiftMate diagnostic\n";
echo str_repeat('=', 50) . "\n\n";

echo "[PHP]\n";
line('version', PHP_VERSION . ' ' . ok(PHP_VERSION_ID >= 80300) . ' (need 8.3+)');
foreach (array('pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'dom', 'ctype', 'fileinfo', 'curl') as $ext) {
    line('ext ' . $ext, ok(extension_loaded($ext)));
}
line('vendor/autoload.php', ok(is_file($root . '/vendor/autoload.php')));
echo "\n";

echo "[Environment variables]\n";
line('APP_KEY set', ok(envv('APP_KEY') !== null));
foreach (array('APP_ENV', 'APP_DEBUG', 'SESSION_DRIVER', 'CACHE_STORE', 'LOG_CHANNEL', 'LARAVEL_STORAGE_PATH', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_NAME') as $n) {
    $v = envv($n);
    line($n, $v === null ? '(not set)' : $v);
}
line('DB_USERNAME set', envv('DB_USERNAME') === null ? '(not set)' : 'yes');
line('DB_PASSWORD set', envv('DB_PASSWORD') === null ? '(not set)' : 'yes');
echo "\n";

$storage = envv('LARAVEL_STORAGE_PATH');
if ($storage === null) {
    $storage = $root . '/storage';
}

echo "[Folders]\n";
$paths = array(
    'storage path' => $storage,
    'storage/framework/views' => $storage . '/framework/views',
    'storage/framework/sessions' => $storage . '/framework/sessions',
    'storage/logs' => $storage . '/logs',
    'bootstrap/cache' => $root . '/bootstrap/cache',
);
foreach ($paths as $label => $p) {
    $state = !is_dir($p) ? 'missing' : (is_writable($p) ? 'writable' : 'NOT writable');
    line($label, $state);
}
echo "\n";

echo "[Database]\n";
$dbName = envv('DB_DATABASE');
if ($dbName === null) {
    $dbName = envv('DB_NAME');
}
$host = envv('DB_HOST');
$port = envv('DB_PORT');
if ($port === null) {
    $port = '3306';
}
if ($host === null || $dbName === null) {
    line('connection', 'PROBLEM: DB_HOST or database name not set');
} else {
    try {
        $pdo = new PDO(
            'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName,
            (string) envv('DB_USERNAME'),
            (string) envv('DB_PASSWORD'),
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5)
        );
        line('connection', 'OK (database: ' . $dbName . ')');
        $tables = array();
        foreach ($pdo->query('SHOW TABLES') as $row) {
            $tables[] = $row[0];
        }
        line('table count', (string) count($tables));
        $expected = array('users', 'sessions', 'cache', 'personal_access_tokens', 'migrations', 'employees', 'shifts', 'schedules', 'rest_day_requests', 'absence_requests', 'notifications');
        $missing = array();
        foreach ($expected as $t) {
            if (!in_array($t, $tables, true)) {
                $missing[] = $t;
            }
        }
        line('missing tables', count($missing) ? 'PROBLEM: ' . implode(', ', $missing) : 'none');
        if (in_array('migrations', $tables, true)) {
            $n = $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
            line('migrations run', (string) $n);
        }
    } catch (Throwable $e) {
        line('connection', 'PROBLEM');
        echo '  ' . $e->getMessage() . "\n";
    }
}
echo "\n";

echo "[Recent errors in laravel.log]\n";
$logFile = $storage . '/logs/laravel.log';
if (is_file($logFile) && is_readable($logFile)) {
    $size = filesize($logFile);
    $fh = fopen($logFile, 'rb');
    fseek($fh, max(0, $size - 200000));
    $chunk = stream_get_contents($fh);
    fclose($fh);
    preg_match_all('/^\[\d{4}-\d{2}-\d{2} [\d:]+\] \S+\.(?:ERROR|CRITICAL|EMERGENCY|ALERT): .*/m', $chunk, $m);
    $errs = array_slice($m[0], -5);
    if (count($errs) === 0) {
        echo "(no errors found)\n";
    }
    foreach ($errs as $e) {
        echo substr($e, 0, 500) . "\n\n";
    }
} else {
    echo "(no log file; if LOG_CHANNEL=stderr, check the Wasmer Logs tab)\n";
}
echo "\n";

echo "[Laravel boot test: GET /]\n";
try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle(Illuminate\Http\Request::create('/', 'GET'));
    line('status', (string) $response->getStatusCode());
    if (isset($response->exception) && $response->exception) {
        $ex = $response->exception;
        line('exception', get_class($ex));
        echo '  ' . substr($ex->getMessage(), 0, 600) . "\n";
        echo '  at ' . $ex->getFile() . ':' . $ex->getLine() . "\n";
    }
} catch (Throwable $e) {
    line('exception', get_class($e));
    echo '  ' . substr($e->getMessage(), 0, 600) . "\n";
    echo '  at ' . $e->getFile() . ':' . $e->getLine() . "\n";
}
echo "\nDONE. Delete public/diag.php now.\n";
