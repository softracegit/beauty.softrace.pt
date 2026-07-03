<?php

namespace App\Rules;

use App\Models\Client;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueClientPhone implements ValidationRule
{
    public function __construct(
        private readonly ?int $exceptClientId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (Client::existsWithSamePhoneAs($value, current_store_id(), $this->exceptClientId)) {
            $fail('Este telemóvel já está registado noutro cliente.');
        }
    }
}
