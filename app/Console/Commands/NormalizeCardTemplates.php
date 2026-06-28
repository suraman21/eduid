<?php

namespace App\Console\Commands;

use App\Models\CardTemplate;
use App\Services\CardTemplateNormalizer;
use Illuminate\Console\Command;

/**
 * Batch-convert every legacy (non-CR80) card template to the locked CR80 canvas.
 * Run once after deploying the fixed-ratio editor:
 *   php artisan cards:normalize-templates           (dry run — lists legacy)
 *   php artisan cards:normalize-templates --convert  (actually convert)
 */
class NormalizeCardTemplates extends Command
{
    protected $signature = 'cards:normalize-templates {--convert : Apply the conversion (otherwise dry-run)}';

    protected $description = 'Convert legacy non-CR80 card templates to the CR80 canvas';

    public function handle(CardTemplateNormalizer $normalizer): int
    {
        $legacy = CardTemplate::withoutGlobalScopes()
            ->whereNotNull('canvas_w')
            ->get()
            ->filter(fn (CardTemplate $t) => $t->isLegacy());

        if ($legacy->isEmpty()) {
            $this->info('No legacy templates found — all canvases are CR80.');

            return self::SUCCESS;
        }

        $this->warn("Found {$legacy->count()} legacy template(s):");
        foreach ($legacy as $t) {
            $this->line("  • #{$t->id} school={$t->school_id} canvas={$t->canvasW()}×{$t->canvasH()} (ratio ".round($t->aspectRatio(), 3).')');
        }

        if (! $this->option('convert')) {
            $this->info('Dry run. Re-run with --convert to apply.');

            return self::SUCCESS;
        }

        foreach ($legacy as $t) {
            $orientation = $normalizer->convert($t);
            $this->info("Converted #{$t->id} → CR80 {$orientation}.");
        }

        return self::SUCCESS;
    }
}
