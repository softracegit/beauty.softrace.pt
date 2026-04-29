<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingContactVerificationCode extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'target',
        'code_hash',
        'expires_at',
        'consumed_at',
        'attempts',
        'requested_ip',
        'requested_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
