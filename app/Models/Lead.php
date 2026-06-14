<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'name',
        'school_name',
        'phone',
        'email',
        'message',
        'preferred_plan',
        'status',
        'admin_notes',
        'contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
        ];
    }

    // ── Status Helpers ────────────────────────────────────────

    public function isNew(): bool
    {
        return $this->status === 'new';
    }

    public function markContacted(?string $notes = null): void
    {
        $this->update([
            'status'       => 'contacted',
            'contacted_at' => now(),
            'admin_notes'  => $notes ?? $this->admin_notes,
        ]);
    }
}
