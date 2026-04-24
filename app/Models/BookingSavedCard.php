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
}
