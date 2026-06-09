<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    use BelongsToStore;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'store_id',
        'opened_by_user_id',
        'opened_at',
        'opening_float_cents',
        'closed_by_user_id',
        'closed_at',
        'closing_cash_counted_cents',
        'closing_summary',
        'notes',
        'status',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_float_cents' => 'integer',
        'closing_cash_counted_cents' => 'integer',
        'closing_summary' => 'array',
    ];

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function openingFloatEur(): float
    {
        return round($this->opening_float_cents / 100, 2);
    }

    public function closingCashCountedEur(): ?float
    {
        if ($this->closing_cash_counted_cents === null) {
            return null;
        }

        return round($this->closing_cash_counted_cents / 100, 2);
    }
}
