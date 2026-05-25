<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSavedCard extends Model
{
    protected $fillable = [
        'client_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'fingerprint',
        'is_default',
        'detached_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'detached_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return array<int, array{id: int, brand: string, last4: string, exp_month: int|null, exp_year: int|null, is_default: bool}>
     */
    public static function payloadListForClient(Client $client): array
    {
        return static::query()
            ->where('client_id', $client->id)
            ->whereNull('detached_at')
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (self $row): array => [
                'id' => (int) $row->id,
                'brand' => (string) ($row->brand ?? ''),
                'last4' => (string) ($row->last4 ?? ''),
                'exp_month' => $row->exp_month !== null ? (int) $row->exp_month : null,
                'exp_year' => $row->exp_year !== null ? (int) $row->exp_year : null,
                'is_default' => (bool) $row->is_default,
            ])
            ->values()
            ->all();
    }
}
