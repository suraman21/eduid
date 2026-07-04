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

### Standard deploy (ALWAYS use this — `migrate --force` only runs *pending* migrations, so it is safe even when there are none; seeders are idempotent `updateOrCreate`)
```bash
{ cd /home/arkeonet/arkeoid.arkeonethiopia.com && git fetch origin && git reset --hard origin/main; { [ -f /usr/local/bin/composer ] && /opt/cpanel/ea-php84/root/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader; } || { [ -f ~/composer.phar ] && /opt/cpanel/ea-php84/root/usr/bin/php ~/composer.phar install --no-dev --optimize-autoloader; } || echo "composer skipped (no binary found)"; /opt/cpanel/ea-php84/root/usr/bin/php artisan migrate --force && /opt/cpanel/ea-php84/root/usr/bin/php artisan db:seed --class=FeatureSeeder --force && /opt/cpanel/ea-php84/root/usr/bin/php artisan db:seed --class=SheetTemplateSeeder --force && /opt/cpanel/ea-php84/root/usr/bin/php artisan config:clear && /opt/cpanel/ea-php84/root/usr/bin/php artisan cache:clear && /opt/cpanel/ea-php84/root/usr/bin/php artisan view:clear && /opt/cpanel/ea-php84/root/usr/bin/php artisan route:clear && /opt/cpanel/ea-php84/root/usr/bin/php artisan storage:link; } > /home/arkeonet/deploy_log.txt 2>&1
```

**Composer must NEVER `&&`-chain into `migrate`** — if the composer binary path
is wrong the `&&` aborts the whole deploy *before* migrations run, silently
reproducing "Unknown column … / Base table doesn't exist" 500s (this exact trap
bit us once: `Could not open input file: /usr/local/bin/composer` skipped every
step after it). The block above isolates composer (tries `/usr/local/bin/composer`,
then `~/composer.phar`, else skips) so migrations always run regardless.

**Never use a deploy that skips `migrate --force`** — doing so was the cause of
repeated "Base table … doesn't exist" 500s. Verify after deploy with:
`/opt/cpanel/ea-php84/root/usr/bin/php artisan migrate:status` (no rows should say "Pending").

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
