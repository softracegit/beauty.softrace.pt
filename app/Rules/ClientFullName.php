<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ClientFullName implements ValidationRule
{
    public const MESSAGE = 'Por favor preencha Nome e Apelido do cliente';

    public function __construct(
        private readonly ?string $message = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail($this->message ?? self::MESSAGE);
        }
    }

    public static function isValid(string $name): bool
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts) || count($parts) < 2) {
            return false;
        }

        foreach ($parts as $part) {
            if (preg_match_all('/\p{L}/u', $part) < 2) {
                return false;
            }
        }

        return true;
    }
}
