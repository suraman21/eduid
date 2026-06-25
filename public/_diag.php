<?php
/**
 * Temporary diagnostic + OPcache reset for the Inertia\Middleware 500.
 * Visit https://arkeoid.arkeonethiopia.com/_diag.php?key=fixme2026
 * DELETE THIS FILE after the site is confirmed working.
 */

if (($_GET['key'] ?? '') !== 'fixme2026') {
    http_response_code(403);
    exit('forbidden');
}

header('Content-Type: text/plain');

$base = dirname(__DIR__);

echo "=== EduID Inertia diagnostic ===\n\n";

// 1. OPcache state + reset
echo "[OPcache]\n";
if (function_exists('opcache_get_status')) {
    $cfg = opcache_get_configuration()['directives'] ?? [];
    echo "  enabled              : " . (ini_get('opcache.enable') ? 'yes' : 'no') . "\n";
    echo "  validate_timestamps  : " . (($cfg['opcache.validate_timestamps'] ?? null) ? 'yes' : 'NO (stale-file risk!)') . "\n";
    echo "  revalidate_freq      : " . ($cfg['opcache.revalidate_freq'] ?? '?') . "\n";
    if (function_exists('opcache_reset')) {
        echo "  opcache_reset()      : " . (opcache_reset() ? 'OK — cache cleared' : 'failed') . "\n";
    }
} else {
    echo "  OPcache not available\n";
}
if (function_exists('clearstatcache')) { clearstatcache(true); echo "  clearstatcache()     : done\n"; }

// 2. Physical file presence
echo "\n[Files on disk]\n";
$mw = $base . '/vendor/inertiajs/inertia-laravel/src/Middleware.php';
echo "  Middleware.php exists : " . (is_file($mw) ? 'yes (' . filesize($mw) . " bytes)" : 'NO') . "\n";
$psr4 = $base . '/vendor/composer/autoload_psr4.php';
if (is_file($psr4)) {
    $map = include $psr4;
    echo "  autoload_psr4 Inertia : " . (isset($map['Inertia\\']) ? implode(',', $map['Inertia\\']) : 'MISSING') . "\n";
}
$static = $base . '/vendor/composer/autoload_static.php';
if (is_file($static)) {
    $src = file_get_contents($static);
    echo "  autoload_static map   : " . (strpos($src, "'Inertia\\\\Middleware'") !== false ? 'has Inertia\\Middleware' : 'MISSING Inertia\\Middleware') . "\n";
}

// 3. Try to actually autoload it through Composer
echo "\n[Autoload test]\n";
require $base . '/vendor/autoload.php';
echo "  class_exists(Inertia\\Middleware) : " . (class_exists('Inertia\\Middleware') ? 'YES — resolves!' : 'NO') . "\n";
echo "  class_exists(Inertia\\Inertia)    : " . (class_exists('Inertia\\Inertia') ? 'yes' : 'no') . "\n";

// 4. Bootstrap cache
echo "\n[bootstrap/cache]\n";
foreach (['packages.php', 'services.php', 'config.php', 'routes-v7.php'] as $f) {
    $p = $base . '/bootstrap/cache/' . $f;
    echo "  $f : " . (is_file($p) ? 'present' : 'absent') . "\n";
}

echo "\n=== done. Reload the main site now. Then DELETE this file. ===\n";
