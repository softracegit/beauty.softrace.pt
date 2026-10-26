<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalTimeType extends Model
{
    use BelongsToOrganization;

    /** Nome reservado — «Outro» é sempre virtual na agenda (título livre). */
    public const OUTRO_NAME = 'Outro';

    protected $fillable = [
        'organization_id',
        'name',
        'icon',
        'duration',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'duration' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration >= 60) {
            $h = (int) floor($this->duration / 60);
            $m = $this->duration % 60;

            return $m > 0 ? "{$h}h {$m}min" : "{$h}h";
        }

        return $this->duration.' min';
    }

    public static function isReservedOutroName(?string $name): bool
    {
        return mb_strtolower(trim((string) $name)) === mb_strtolower(self::OUTRO_NAME);
    }

    /**
     * Garante tipos predefinidos para uma organização nova (sem «Outro» — esse é virtual na agenda).
     */
    public static function ensureDefaultsForOrganization(int $organizationId): void
    {
        if ($organizationId <= 0) {
            return;
        }

        if (static::query()->where('organization_id', $organizationId)->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'Refeição', 'icon' => 'ph-cooking-pot', 'duration' => 30, 'sort_order' => 1],
            ['name' => 'Reunião', 'icon' => 'ph-users-three', 'duration' => 60, 'sort_order' => 2],
            ['name' => 'Consulta', 'icon' => 'ph-calendar-check', 'duration' => 60, 'sort_order' => 3],
            ['name' => 'Pessoal', 'icon' => 'ph-user', 'duration' => 60, 'sort_order' => 4],
        ];

        foreach ($defaults as $t) {
            static::query()->create(array_merge($t, [
                'organization_id' => $organizationId,
                'is_active' => true,
            ]));
        }
    }
}
