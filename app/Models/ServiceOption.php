<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServiceOption extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['service_id', 'name', 'description', 'duration', 'price', 'online_price', 'sort_order', 'is_baseline', 'type'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Opção de serviço criada',
                'updated' => 'Opção de serviço atualizada',
                'deleted' => 'Opção de serviço eliminada',
                default => 'Opção de serviço alterada',
            });
    }

    /** Escolha única (radio): define o preço e a duração do serviço. */
    public const TYPE_CHOICE = 'choice';

    /** Adicional (interruptor): soma-se à variante escolhida. */
    public const TYPE_ADDON = 'addon';

    public function isAddon(): bool
    {
        return $this->type === self::TYPE_ADDON;
    }

    public static function normalizeType(mixed $type): string
    {
        return $type === self::TYPE_ADDON ? self::TYPE_ADDON : self::TYPE_CHOICE;
    }

    protected $fillable = [
        'service_id',
        'name',
        'description',
        'duration',
        'price',
        'online_price',
        'sort_order',
        'is_baseline',
        'type',
    ];

    protected $casts = [
        'service_id' => 'integer',
        'duration' => 'integer',
        'price' => 'decimal:2',
        'online_price' => 'decimal:2',
        'sort_order' => 'integer',
        'is_baseline' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format((float) $this->price, 2, ',', '.').' €';
    }

    public function getFormattedOnlinePriceAttribute(): string
    {
        return number_format((float) $this->online_price, 2, ',', '.').' €';
    }

    public function getFormattedDurationAttribute(): string
    {
        $hours = (int) floor($this->duration / 60);
        $minutes = (int) ($this->duration % 60);

        if ($hours > 0 && $minutes > 0) {
            return $hours.'h '.$minutes.'min';
        }
        if ($hours > 0) {
            return $hours.'h';
        }

        return $minutes.'min';
    }
}
