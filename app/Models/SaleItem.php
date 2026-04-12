<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    public const TIPO_SERVICO = 'servico';

    public const TIPO_EXTRA = 'extra';

    protected $fillable = [
        'sale_id',
        'tipo',
        'calendar_event_service_id',
        'service_id',
        'extra_id',
        'descricao',
        'quantidade',
        'preco_unitario',
        'subtotal',
        'desconto',
        'sort_order',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'preco_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'desconto' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function calendarEventService(): BelongsTo
    {
        return $this->belongsTo(CalendarEventService::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }
}
