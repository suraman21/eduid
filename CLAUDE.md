# EduID — Project Notes

Multi-tenant school management SaaS for Ethiopian schools. Laravel 11 on cPanel
shared hosting (no terminal, no queue workers; deploy via a cron job that pulls
from git).

## Deploy

Server: `/home/arkeonet/arkeoid.arkeonethiopia.com`
PHP binary: `/opt/cpanel/ea-php84/root/usr/bin/php`
Composer: `/usr/local/bin/composer` (fallback: `~/composer.phar`)

**STANDING INSTRUCTION:** After every push to GitHub, restate the deploy command
to the user.

### Quick deploy (no new migrations — most common)
```bash
{ cd /home/arkeonet/arkeoid.arkeonethiopia.com && git fetch origin && git reset --hard origin/main && /opt/cpanel/ea-php84/root/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader && /opt/cpanel/ea-php84/root/usr/bin/php artisan config:clear && /opt/cpanel/ea-php84/root/usr/bin/php artisan route:clear && /opt/cpanel/ea-php84/root/usr/bin/php artisan view:clear; } > /home/arkeonet/deploy_log.txt 2>&1
```

### Full deploy (when new migrations are included)
```bash
{ cd /home/arkeonet/arkeoid.arkeonethiopia.com && git fetch origin && git reset --hard origin/main && /opt/cpanel/ea-php84/root/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader && /opt/cpanel/ea-php84/root/usr/bin/php artisan migrate --force && /opt/cpanel/ea-php84/root/usr/bin/php artisan db:seed --class=FeatureSeeder --force && /opt/cpanel/ea-php84/root/usr/bin/php artisan config:clear && /opt/cpanel/ea-php84/root/usr/bin/php artisan cache:clear && /opt/cpanel/ea-php84/root/usr/bin/php artisan view:clear && /opt/cpanel/ea-php84/root/usr/bin/php artisan route:clear && /opt/cpanel/ea-php84/root/usr/bin/php artisan storage:link; } > /home/arkeonet/deploy_log.txt 2>&1
```

## Git workflow
- Develop on `claude/epic-thompson-5gb5mh`, then merge to `main` and push both.

## Conventions / invariants (do not regress)
- Tenant isolation via the `BelongsToSchool` global scope; controllers never
  filter by `school_id` manually. Guard `!auth()->check()` universally (CLI/queue).
- Write controllers extend `BaseSchoolController`; writes call
  `requireWritableSubscription()` and role guards (`requireAdmin()` etc.).
- Student seat counting uses `status='active'` (enum), **not** the legacy
  `is_active` boolean.
- Capacity writes use `lockForUpdate()` on the school row inside a transaction
  to prevent oversell (TOCTOU).
- InnoDB pinned in `config/database.php` (FKs, transactions, row locks).
- Tests run on in-memory SQLite; keep migrations SQLite-compatible.
