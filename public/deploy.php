<?php
$secret = 'arkeoid_deploy_2026';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    http_response_code(403);
    die('Forbidden');
}

$base = '/home/arkeonet/arkeoid.arkeonethiopia.com';
$php = null;
$git = null;
$composer = null;

echo "<pre style='background:#111;color:#0f0;padding:20px;font-size:13px;'>";
echo "=== EduID Server Diagnostic ===\n\n";

// Step 1: Find PHP
foreach (['/usr/local/bin/php', '/usr/bin/php', '/opt/cpanel/ea-php82/root/usr/bin/php', '/opt/cpanel/ea-php81/root/usr/bin/php', '/opt/cpanel/ea-php83/root/usr/bin/php'] as $p) {
    if (file_exists($p)) {
        $php = $p;
        break;
    }
}
echo "PHP binary: " . ($php ?: 'NOT FOUND') . "\n";
echo "PHP version: " . shell_exec("$php -v 2>&1 | head -1") . "\n";

// Step 2: Find git
foreach (['/usr/local/bin/git', '/usr/bin/git'] as $g) {
    if (file_exists($g)) {
        $git = $g;
        break;
    }
}
if (!$git) {
    $git = trim(shell_exec("which git 2>&1"));
}
echo "Git binary: " . ($git ?: 'NOT FOUND') . "\n";
echo "Git version: " . shell_exec("$git --version 2>&1") . "\n";

// Step 3: Find composer
foreach (['/usr/local/bin/composer', '/usr/bin/composer', '/home/arkeonet/composer.phar'] as $c) {
    if (file_exists($c)) {
        $composer = $c;
        break;
    }
}
echo "Composer binary: " . ($composer ?: 'NOT FOUND') . "\n";

// Step 4: Check current git state
echo "\n=== Git Status ===\n";
echo shell_exec("cd $base && $git status 2>&1");

echo "\n=== Git Remote ===\n";
echo shell_exec("cd $base && $git remote -v 2>&1");

echo "\n=== Current Branch ===\n";
echo shell_exec("cd $base && $git branch -a 2>&1");

echo "\n=== Git Log (last 3) ===\n";
echo shell_exec("cd $base && $git log --oneline -3 2>&1");

// Step 5: Check if .env exists and has APP_KEY
echo "\n=== .env Check ===\n";
if (file_exists("$base/.env")) {
    $env = file_get_contents("$base/.env");
    echo ".env exists (" . strlen($env) . " bytes)\n";
    if (preg_match('/^APP_KEY=(.*)$/m', $env, $m)) {
        echo "APP_KEY: " . (strlen(trim($m[1])) > 10 ? 'SET (' . strlen(trim($m[1])) . ' chars)' : 'EMPTY OR TOO SHORT') . "\n";
    } else {
        echo "APP_KEY: MISSING\n";
    }
    if (preg_match('/^DB_CONNECTION=(.*)$/m', $env, $m)) {
        echo "DB_CONNECTION: " . trim($m[1]) . "\n";
    }
    if (preg_match('/^DB_DATABASE=(.*)$/m', $env, $m)) {
        echo "DB_DATABASE: " . trim($m[1]) . "\n";
    }
} else {
    echo ".env DOES NOT EXIST\n";
}

// Step 6: Check directory permissions
echo "\n=== Directory Permissions ===\n";
echo "storage/: " . substr(sprintf('%o', fileperms("$base/storage")), -4) . "\n";
echo "bootstrap/cache/: " . substr(sprintf('%o', fileperms("$base/bootstrap/cache")), -4) . "\n";

// Step 7: Only if ?action=deploy is passed, actually deploy
if (isset($_GET['action']) && $_GET['action'] === 'deploy') {
    echo "\n\n=== DEPLOYING ===\n";

    // Clean up any untracked files that conflict with git
    echo "\n--- Cleaning untracked/modified files ---\n";
    echo shell_exec("cd $base && $git checkout -- . 2>&1");
    echo shell_exec("cd $base && $git clean -fd 2>&1");

    echo "\n--- Fetching from origin ---\n";
    echo shell_exec("cd $base && $git fetch origin 2>&1");

    echo "\n--- Resetting to origin/main ---\n";
    echo shell_exec("cd $base && $git reset --hard origin/main 2>&1");

    echo "\n--- Git log after reset ---\n";
    echo shell_exec("cd $base && $git log --oneline -3 2>&1");

    echo "\n--- Composer install ---\n";
    if ($composer) {
        echo shell_exec("cd $base && $php $composer install --no-dev --optimize-autoloader --no-interaction 2>&1");
    } else {
        echo shell_exec("cd $base && $php $base/artisan package:discover 2>&1");
        echo "Composer not found - skipping install. Vendor exists: " . (is_dir("$base/vendor") ? 'YES' : 'NO') . "\n";
    }

    echo "\n--- Running migrations ---\n";
    echo shell_exec("cd $base && $php $base/artisan migrate --force 2>&1");

    echo "\n--- Seeding features ---\n";
    echo shell_exec("cd $base && $php $base/artisan db:seed --class=FeatureSeeder --force 2>&1");

    echo "\n--- Clearing caches ---\n";
    echo shell_exec("cd $base && $php $base/artisan config:clear 2>&1");
    echo shell_exec("cd $base && $php $base/artisan cache:clear 2>&1");
    echo shell_exec("cd $base && $php $base/artisan view:clear 2>&1");
    echo shell_exec("cd $base && $php $base/artisan route:clear 2>&1");

    // Generate APP_KEY if missing
    $env = file_get_contents("$base/.env");
    if (!preg_match('/^APP_KEY=base64:/m', $env)) {
        echo "\n--- Generating APP_KEY ---\n";
        echo shell_exec("cd $base && $php $base/artisan key:generate --force 2>&1");
    }

    echo "\n--- Storage link ---\n";
    echo shell_exec("cd $base && $php $base/artisan storage:link 2>&1");

    echo "\n\n=== DEPLOY COMPLETE ===\n";
    echo "Visit: https://arkeoid.arkeonethiopia.com/\n";
} else {
    echo "\n\n=== TO DEPLOY ===\n";
    echo "Add &action=deploy to the URL to run the full deployment.\n";
    echo "URL: /deploy.php?key=arkeoid_deploy_2026&action=deploy\n";
}

echo "</pre>";
