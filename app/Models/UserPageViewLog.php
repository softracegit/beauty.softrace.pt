<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPageViewLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id',
        'user_id',
        'route_name',
        'path',
        'subject_type',
        'subject_id',
        'route_params',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'route_params' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
