<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZappyImportRef extends Model
{
    public const TYPE_SERVICE = 'service';

    public const TYPE_CLIENT = 'client';

    public const TYPE_APPOINTMENT = 'appointment';

    public const TYPE_APPOINTMENT_ZAPPY = 'appointment_zappy';

    public const TYPE_SALE = 'sale';

    protected $fillable = [
        'store_id',
        'entity_type',
        'zappy_key',
        'local_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'local_id' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
