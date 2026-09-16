<?php
// Mirza Bot — diagnostic page. Delete after use.
// Shows the real reason table.php (and the bot) returns HTTP 500.
header('Content-Type: text/plain; charset=utf-8');
echo "=== Mirza diag ===\n";
echo "PHP: " . PHP_VERSION . " (need >= 8.2)\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "doc_root: " . (__DIR__) . "\n\n";

echo "-- vendor/autoload.php: ";
echo is_file(__DIR__ . '/vendor/autoload.php') ? "FOUND\n" : "MISSING  <-- run composer install\n";

echo "-- config.php: ";
$cfg = @file_get_contents(__DIR__ . '/config.php');
if ($cfg === false) { echo "MISSING\n"; }
elseif (strpos($cfg, '{') !== false && preg_match('/\{[a-z_]+\}/i', $cfg)) { echo "PLACEHOLDERS  <-- installer not run\n"; }
else { echo "OK (configured)\n"; }

echo "-- install/ folder: ";
echo is_dir(__DIR__ . '/install') ? "EXISTS  <-- .htaccess blocks site (403/500)\n" : "removed OK\n";

echo "\n=== required PHP extensions ===\n";
foreach (['pdo_mysql','curl','mbstring','json','openssl','fileinfo'] as $e) {
    echo str_pad($e, 14) . (extension_loaded($e) ? "ok" : "MISSING") . "\n";
}

echo "\n=== load chain (first error wins) ===\n";
$steps = ['config.php','botapi.php','function.php','db/Schema.php','db/bootstrap.php','table.php'];
foreach ($steps as $f) {
    $path = __DIR__ . '/' . $f;
    echo str_pad($f, 20);
    if (!is_file($path)) { echo "MISSING\n"; continue; }
    echo "present  ";
    if (!is_readable($path)) { echo " UNREADABLE(perm)\n"; continue; }
    $t = @php_check_syntax_or_load($path);
    echo "\n";
}

echo "\n=== try the real thing ===\n";
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_error_handler(function($no,$str,$file,$line){ echo "ERROR: $str @ $file:$line\n"; return true; });
try {
    ob_start();
    $ok = @include __DIR__ . '/table.php';
    $out = ob_get_clean();
    echo "table.php included, exit code: " . var_export($ok, true) . "\n";
    if ($out !== '') echo "output: " . substr($out, 0, 500) . "\n";
    echo "\n*** table.php ran without fatal error ***\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "FATAL: " . get_class($e) . "\n" . $e->getMessage() . "\n  at " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n  Trace (first 3):\n";
    foreach (array_slice($e->getTrace(), 0, 3) as $t) {
        echo "   - " . ($t['file'] ?? '?') . ":" . ($t['line'] ?? '?') . " " . ($t['function'] ?? '') . "()\n";
    }
}

function php_check_syntax_or_load($p) {
    $src = file_get_contents($p);
    $depth = 0; $inS = null; $line = 1;
    for ($i = 0; $i < strlen($src); $i++) {
        $c = $src[$i];
        if ($c === "\n") $line++;
        if ($inS) { if ($c === '\\') { $i++; continue; } if ($c === $inS) $inS = null; continue; }
        if ($c === '"' || $c === "'") { $inS = $c; continue; }
        if ($c === '{') $depth++;
        if ($c === '}') $depth--;
        if ($depth < 0) { echo "  brace imbalance at line $line"; return; }
    }
    if ($depth !== 0) echo "  UNBALANCED braces (delta $depth)";
    return;
}
