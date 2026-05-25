<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientWalletTransaction extends Model
{
    use BelongsToStore;

    public const TYPE_CREDIT_CANCELLATION_IN_POLICY = 'credit_cancellation_in_policy';

    public const TYPE_DEBIT_BOOKING_CHECKOUT = 'debit_booking_checkout';

    public const TYPE_DEBIT_POS_CHECKOUT = 'debit_pos_checkout';

    public const TYPE_CREDIT_MANUAL_TOPUP = 'credit_manual_topup';

    public const TYPE_CREDIT_CASHBACK = 'credit_cashback';

    public const TYPE_CREDIT_ADMIN_ADJUSTMENT = 'credit_admin_adjustment';

    public const TYPE_DEBIT_ADMIN_ADJUSTMENT = 'debit_admin_adjustment';

    public const CREATED_BY_SYSTEM = 'system';

    public const CREATED_BY_CLIENT = 'client';

    public const CREATED_BY_STAFF = 'staff';

    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id',
        'client_id',
        'amount_cents',
        'balance_after_cents',
        'type',
        'idempotency_key',
        'calendar_event_id',
        'booking_id',
        'sale_id',
        'payment_id',
        'description',
        'metadata',
        'created_by_type',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'balance_after_cents' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new \LogicException('Wallet ledger entries are immutable.');
        });

        static::deleting(static function (): void {
            throw new \LogicException('Wallet ledger entries cannot be deleted.');
        });
    }
}
