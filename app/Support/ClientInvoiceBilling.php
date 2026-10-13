<?php

namespace App\Support;

use App\Models\Client;

/**
 * NIF + morada de faturação no checkout da agenda (Vendus).
 */
final class ClientInvoiceBilling
{
    /**
     * @return array<string, list<string>>
     */
    public static function addressValidationRules(): array
    {
        return [
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_postal_code' => ['nullable', 'string', 'max:20'],
            'billing_locality' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Aplica NIF/morada do checkout à ficha e valida empresa.
     *
     * @return string|null mensagem de erro ou null se OK
     */
    public static function applyToClient(
        Client $client,
        string $fiscalMode,
        ?string $billingNif,
        ?string $billingAddress,
        ?string $billingPostalCode,
        ?string $billingLocality,
    ): ?string {
        if ($fiscalMode !== 'with_nif') {
            return null;
        }

        $clientNif = PortugueseFiscalId::digits($client->nif);
        $billingNifDigits = PortugueseFiscalId::digits($billingNif);
        if (strlen($clientNif) !== 9 && strlen($billingNifDigits) === 9) {
            $client->nif = $billingNifDigits;
            $clientNif = $billingNifDigits;
        }

        $effectiveNif = strlen($clientNif) === 9 ? $clientNif : $billingNifDigits;
        if (! PortugueseFiscalId::isCompany($effectiveNif)) {
            if ($client->isDirty()) {
                $client->save();
            }

            return null;
        }

        $address = trim((string) ($billingAddress ?? ''));
        $postal = trim((string) ($billingPostalCode ?? ''));
        $locality = trim((string) ($billingLocality ?? ''));

        if ($address !== '') {
            $client->address = $address;
        }
        if ($postal !== '') {
            $client->postal_code = $postal;
        }
        if ($locality !== '') {
            $client->locality = $locality;
        }

        if (! $client->hasCompleteVendusAddress()) {
            return 'Para faturar com NIF de empresa, indique morada, código postal e localidade (obrigatório na Vendus).';
        }

        $client->save();

        return null;
    }
}
