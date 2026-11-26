<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CalendarEventService extends Pivot
{
    protected $table = 'calendar_event_services';

    public $incrementing = true;

    protected $fillable = [
        'calendar_event_id',
        'service_id',
        'service_option_id',
        'option_name',
        'option_duration',
        'option_price',
        'option_online_price',
        'duration',
        'price',
        'original_price',
        'sort_order',
    ];

    protected $casts = [
        'service_option_id' => 'integer',
        'option_duration' => 'integer',
        'option_price' => 'decimal:2',
        'option_online_price' => 'decimal:2',
        'duration' => 'integer',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function service(): BelongsTo
    {
        // withTrashed: marcações antigas continuam a resolver o serviço após soft delete.
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function serviceOption(): BelongsTo
    {
        return $this->belongsTo(ServiceOption::class);
    }

    public function extras(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CalendarEventServiceExtra::class, 'calendar_event_service_id');
    }

    /**
     * Rótulo para UI/relatórios: «serviço pai variante» quando há opção; senão só o serviço.
     */
    public function lineLabel(): string
    {
        $this->loadMissing('service');

        return self::formatLineLabel($this->service?->name, $this->option_name);
    }

    public static function formatLineLabel(?string $serviceName, ?string $optionName): string
    {
        $parent = trim((string) ($serviceName ?? ''));
        $opt = trim((string) ($optionName ?? ''));
        if ($opt !== '' && $parent !== '' && strcasecmp($opt, $parent) !== 0) {
            return $parent.' '.$opt;
        }
        if ($opt !== '') {
            return $opt;
        }

        return $parent;
    }
}
