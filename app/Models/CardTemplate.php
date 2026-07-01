<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardTemplate extends Model
{
    use BelongsToSchool;

    protected $table = 'card_templates';

    protected $fillable = [
        'school_id',
        'name',
        'template_type',
        'scope',
        'front_image_path',
        'back_image_path',
        'canvas_w',
        'canvas_h',
        'card_width_mm',
        'card_height_mm',
        'orientation',
        'is_active',
        'version',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'version'        => 'integer',
            'canvas_w'       => 'integer',
            'canvas_h'       => 'integer',
            'card_width_mm'  => 'float',
            'card_height_mm' => 'float',
        ];
    }

    /** Fallback CR80 @ 300 DPI dimensions (used when no image has been uploaded yet). */
    public const DEFAULT_W = 1011;
    public const DEFAULT_H = 638;

    /** CR80 (ISO/IEC 7810 ID-1) physical dimensions in mm. */
    public const CR80_LONG  = 85.6;
    public const CR80_SHORT = 53.98;

    /**
     * Fixed CR80 design-canvas resolutions (px @ ~300 DPI). The canvas aspect is
     * LOCKED to CR80 — designers crop their artwork into this frame; the canvas
     * itself never changes ratio. Internal resolution may grow later, but the
     * 1.5858 aspect must never vary.
     */
    public const CANVAS_LANDSCAPE_W = 1011;
    public const CANVAS_LANDSCAPE_H = 638;
    public const CANVAS_PORTRAIT_W  = 638;
    public const CANVAS_PORTRAIT_H  = 1011;

    /** CR80 aspect ratio (long ÷ short) and the tolerance for "is this CR80?". */
    public const CR80_RATIO       = self::CR80_LONG / self::CR80_SHORT; // ≈ 1.58577
    public const ASPECT_TOLERANCE = 0.04;

    /** Actual canvas width in pixels — uploaded image dimensions or CR80 fallback. */
    public function canvasW(): int { return $this->canvas_w ?? self::DEFAULT_W; }
    public function canvasH(): int { return $this->canvas_h ?? self::DEFAULT_H; }

    /** True when the design canvas is taller than it is wide. */
    public function isPortrait(): bool { return $this->canvasH() > $this->canvasW(); }

    /**
     * PHYSICAL card size in mm, ALWAYS oriented to the design canvas.
     *
     * card_width_mm / card_height_mm define the physical card *size* only
     * (long × short edge — CR80 by default); the CANVAS decides orientation.
     * The columns carry a landscape 85.6×54 default from their migration, so
     * trusting their order verbatim made every template print landscape no
     * matter what the editor said (portrait proofs on landscape pages, and the
     * sheet engine's rotation check never firing → squished cards). Orienting
     * long/short to the canvas heals every existing row with no data migration.
     */
    public function cardWMm(): float
    {
        return $this->isPortrait() ? $this->physicalShortMm() : $this->physicalLongMm();
    }

    public function cardHMm(): float
    {
        return $this->isPortrait() ? $this->physicalLongMm() : $this->physicalShortMm();
    }

    /** Long physical edge in mm (stored size if present, else CR80). */
    private function physicalLongMm(): float
    {
        $w = (float) ($this->card_width_mm ?: 0);
        $h = (float) ($this->card_height_mm ?: 0);

        return ($w > 0 && $h > 0) ? max($w, $h) : self::CR80_LONG;
    }

    /** Short physical edge in mm (stored size if present, else CR80). */
    private function physicalShortMm(): float
    {
        $w = (float) ($this->card_width_mm ?: 0);
        $h = (float) ($this->card_height_mm ?: 0);

        return ($w > 0 && $h > 0) ? min($w, $h) : self::CR80_SHORT;
    }

    /** The card's native canvas size in mm (px ÷ 300 DPI) — the design space. */
    public function nativeWMm(): float { return self::pxToMm($this->canvasW()); }
    public function nativeHMm(): float { return self::pxToMm($this->canvasH()); }

    /** Long ÷ short side of the current canvas. */
    public function aspectRatio(): float
    {
        $w = $this->canvasW();
        $h = $this->canvasH();

        return min($w, $h) > 0 ? max($w, $h) / min($w, $h) : 0.0;
    }

    /** Whether a width×height pair matches the CR80 aspect (within tolerance). */
    public static function isRatioCr80(int $w, int $h): bool
    {
        if ($w <= 0 || $h <= 0) {
            return false;
        }

        return abs(max($w, $h) / min($w, $h) - self::CR80_RATIO) <= self::ASPECT_TOLERANCE;
    }

    /** Whether this template's canvas is CR80 aspect. */
    public function isCr80Aspect(): bool
    {
        return self::isRatioCr80($this->canvasW(), $this->canvasH());
    }

    /**
     * A legacy template has an uploaded canvas that is NOT CR80 aspect — it
     * predates the fixed-ratio editor and must be migrated before it prints
     * without distortion.
     */
    public function isLegacy(): bool
    {
        return $this->canvas_w !== null && ! $this->isCr80Aspect();
    }

    /** The locked CR80 canvas [w, h] in px for an orientation. */
    public static function cr80Canvas(string $orientation): array
    {
        return $orientation === 'portrait'
            ? [self::CANVAS_PORTRAIT_W, self::CANVAS_PORTRAIT_H]
            : [self::CANVAS_LANDSCAPE_W, self::CANVAS_LANDSCAPE_H];
    }

    /** px / 300 * 25.4 — convert storage pixels to TCPDF mm. */
    public static function pxToMm(float $px): float
    {
        return $px / 300 * 25.4;
    }

    /** mm / 25.4 * 300 — convert mm back to preview pixels. */
    public static function mmToPx(float $mm): float
    {
        return $mm / 25.4 * 300;
    }

    /** Field types keyed by field_key. */
    public static function fieldTypeFor(string $key): string
    {
        return match ($key) {
            'photo'   => 'image',
            'qr_code' => 'qr',
            default   => 'text',
        };
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CardTemplateField::class, 'template_id')->orderBy('z_index');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
