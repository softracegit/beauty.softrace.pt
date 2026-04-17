<?php

namespace App\Support;

/**
 * Regras partilhadas para opções de serviço (catálogo, agenda, booking).
 */
final class ServiceOptionValidation
{
    /**
     * @return array<string, mixed>
     */
    public static function optionRowRules(string $prefix = 'options'): array
    {
        return [
            "{$prefix}.*.name" => ['required', 'string', 'max:255'],
            "{$prefix}.*.duration" => ['required', 'integer', 'min:1'],
            "{$prefix}.*.price" => ['required', 'numeric', 'min:0'],
            "{$prefix}.*.online_price" => ['required', 'numeric', 'min:0'],
            "{$prefix}.*.sort_order" => ['sometimes', 'integer', 'min:0'],
            "{$prefix}.*.is_baseline" => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function optionRowMessages(string $prefix = 'options'): array
    {
        return [
            "{$prefix}.*.name.required" => 'O nome da opção é obrigatório.',
            "{$prefix}.*.duration.required" => 'A duração da opção é obrigatória.',
            "{$prefix}.*.price.required" => 'O preço da opção é obrigatório.',
            "{$prefix}.*.online_price.required" => 'O preço online da opção é obrigatório.',
        ];
    }

    /**
     * Garante online_price <= price para cada opção (índices alinhados com o array validado).
     *
     * @param  array<int, array<string, mixed>>  $options
     */
    public static function assertOnlinePriceNotAbovePrice(array $options): void
    {
        foreach ($options as $idx => $row) {
            $price = isset($row['price']) ? (float) $row['price'] : null;
            $online = isset($row['online_price']) ? (float) $row['online_price'] : null;
            if ($price === null || $online === null) {
                continue;
            }
            if ($online > $price) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "options.{$idx}.online_price" => ['O preço online não pode ser superior ao preço normal.'],
                ]);
            }
        }
    }
}
