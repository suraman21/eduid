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

    /** Actual canvas width in pixels — uploaded image dimensions or CR80 fallback. */
    public function canvasW(): int { return $this->canvas_w ?? self::DEFAULT_W; }
    public function canvasH(): int { return $this->canvas_h ?? self::DEFAULT_H; }

    /** True when the design canvas is taller than it is wide. */
    public function isPortrait(): bool { return $this->canvasH() > $this->canvasW(); }

    /**
     * PHYSICAL card size in mm. This is decoupled from the canvas resolution: a
     * card always prints at CR80 (oriented to the design's aspect) unless an
     * explicit physical size was stored. The canvas pixels remain the design
     * coordinate space; renderers scale that design onto this physical size.
     *
     * Without this, a high-resolution template (e.g. 1808×2559 px) would be
     * mis-read as a 153×216 mm card and print across multiple pages.
     */
    public function cardWMm(): float
    {
        if ($this->card_width_mm) {
            return (float) $this->card_width_mm;
        }

        return $this->isPortrait() ? self::CR80_SHORT : self::CR80_LONG;
    }

    public function cardHMm(): float
    {
        if ($this->card_height_mm) {
            return (float) $this->card_height_mm;
        }

        return $this->isPortrait() ? self::CR80_LONG : self::CR80_SHORT;
    }

    /** The card's native canvas size in mm (px ÷ 300 DPI) — the design space. */
    public function nativeWMm(): float { return self::pxToMm($this->canvasW()); }
    public function nativeHMm(): float { return self::pxToMm($this->canvasH()); }

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
