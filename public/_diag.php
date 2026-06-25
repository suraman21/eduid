<?php
/**
 * EduID Inertia diagnostic v2 — captures the actual fatal error.
 * Visit https://arkeoid.arkeonethiopia.com/_diag.php?key=fixme2026
 * DELETE THIS FILE after the site is confirmed working.
 */

if (($_GET['key'] ?? '') !== 'fixme2026') {
    http_response_code(403);
    exit('forbidden');
}

header('Content-Type: text/plain');
// Force all errors to display
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Capture fatal errors too
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "\n!!! FATAL: [{$e['type']}] {$e['message']}\n";
        echo "    in {$e['file']} line {$e['errstr']}\n";
        echo "    FILE: {$e['file']}\n";
        echo "    LINE: {$e['line']}\n";
    }
});

$base = dirname(__DIR__);

echo "=== EduID Inertia diagnostic v2 ===\n\n";

// 1. OPcache
echo "[OPcache]\n";
if (function_exists('opcache_reset')) {
    echo "  opcache_reset() : " . (opcache_reset() ? 'OK' : 'failed') . "\n";
}
clearstatcache(true);

// 2. Physical files
echo "\n[Files]\n";
$mw = $base . '/vendor/inertiajs/inertia-laravel/src/Middleware.php';
echo "  Middleware.php : " . (is_file($mw) ? 'exists (' . filesize($mw) . ' bytes)' : 'MISSING') . "\n";

$autoloadPhp = $base . '/vendor/autoload.php';
echo "  vendor/autoload.php : " . (is_file($autoloadPhp) ? 'exists (' . filesize($autoloadPhp) . ' bytes)' : 'MISSING') . "\n";

$realPhp = $base . '/vendor/composer/autoload_real.php';
echo "  autoload_real.php : " . (is_file($realPhp) ? 'exists' : 'MISSING') . "\n";

// Show what class name autoload_real.php defines
if (is_file($realPhp)) {
    $src = file_get_contents($realPhp);
    preg_match('/class\s+(ComposerAutoloaderInit\w+)/', $src, $m);
    echo "  autoload_real class : " . ($m[1] ?? 'NOT FOUND') . "\n";
}

// Show what class name vendor/autoload.php references
if (is_file($autoloadPhp)) {
    $src = file_get_contents($autoloadPhp);
    preg_match('/(ComposerAutoloaderInit\w+)::/', $src, $m);
    echo "  autoload.php calls  : " . ($m[1] ?? 'NOT FOUND') . "\n";
}

// 3. PSR-4 map
$psr4 = $base . '/vendor/composer/autoload_psr4.php';
if (is_file($psr4)) {
    $map = include $psr4;
    echo "  psr4 Inertia\\ : " . (isset($map['Inertia\\']) ? implode(',', $map['Inertia\\']) : 'MISSING') . "\n";
}

// 4. Require autoload — errors now visible
echo "\n[Autoload require]\n";
echo "  about to require vendor/autoload.php...\n";
flush();

try {
    require $base . '/vendor/autoload.php';
    echo "  require: OK\n";
} catch (Throwable $t) {
    echo "  EXCEPTION: " . get_class($t) . ": " . $t->getMessage() . "\n";
    echo "  in " . $t->getFile() . " line " . $t->getLine() . "\n";
}

// 5. Class resolution
echo "\n[Class resolution]\n";
echo "  class_exists(Inertia\\Middleware)    : " . (class_exists('Inertia\\Middleware') ? 'YES' : 'NO') . "\n";
echo "  class_exists(Inertia\\Inertia)       : " . (class_exists('Inertia\\Inertia') ? 'YES' : 'NO') . "\n";
echo "  class_exists(Inertia\\ServiceProvider): " . (class_exists('Inertia\\ServiceProvider') ? 'YES' : 'NO') . "\n";

// 6. PHP version
echo "\n[Environment]\n";
echo "  PHP    : " . PHP_VERSION . "\n";
echo "  OS     : " . PHP_OS . "\n";
echo "  SAPI   : " . PHP_SAPI . "\n";

echo "\n=== done ===\n";
