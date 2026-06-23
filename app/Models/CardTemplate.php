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
    public const DEFAULT_W = 1012;
    public const DEFAULT_H = 638;

    /** Actual canvas width in pixels — uploaded image dimensions or CR80 fallback. */
    public function canvasW(): int { return $this->canvas_w ?? self::DEFAULT_W; }
    public function canvasH(): int { return $this->canvas_h ?? self::DEFAULT_H; }

    /** Canvas width/height in mm at 300 DPI. */
    public function cardWMm(): float { return self::pxToMm($this->canvasW()); }
    public function cardHMm(): float { return self::pxToMm($this->canvasH()); }

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
