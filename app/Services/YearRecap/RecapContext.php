<?php

namespace App\Services\YearRecap;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Quem está a ver, de que loja, que ano e com que âmbito.
 *
 * - Âmbito "loja" (admin, receção): todas as marcações da loja.
 * - Âmbito "pessoal" (prestadora): só as marcações dela; texto em "tu".
 */
final class RecapContext
{
    public const AUDIENCE_ADMIN = 'admin';

    public const AUDIENCE_RECECAO = 'rececao';

    public const AUDIENCE_PRESTADOR = 'prestador';

    /** Início do ano no fuso da loja. */
    public readonly Carbon $start;

    /** Fim do ano no fuso da loja, ou "agora" se o ano ainda decorre. */
    public readonly Carbon $end;

    public readonly bool $isPartialYear;

    public function __construct(
        public readonly User $viewer,
        public readonly string $audience,
        public readonly int $storeId,
        public readonly string $storeName,
        public readonly int $year,
        public readonly string $timezone,
        ?CarbonInterface $now = null,
    ) {
        $now = $now !== null ? Carbon::instance($now)->setTimezone($timezone) : Carbon::now($timezone);

        $this->start = Carbon::create($year, 1, 1, 0, 0, 0, $timezone);
        $yearEnd = Carbon::create($year, 12, 31, 23, 59, 59, $timezone);
        $this->isPartialYear = $now->lt($yearEnd);
        $this->end = $this->isPartialYear ? $now->copy() : $yearEnd;
    }

    public static function audienceFor(User $user): ?string
    {
        if ($user->isPrestador()) {
            return self::AUDIENCE_PRESTADOR;
        }
        if ($user->isRececao()) {
            return self::AUDIENCE_RECECAO;
        }
        if ($user->isAdmin() || $user->isGerente() || $user->isSuperAdmin()) {
            return self::AUDIENCE_ADMIN;
        }

        return null;
    }

    /** Âmbito pessoal (só as marcações da própria prestadora). */
    public function isPersonal(): bool
    {
        return $this->audience === self::AUDIENCE_PRESTADOR;
    }

    /** user_id a filtrar nas marcações, ou null para a loja inteira. */
    public function subjectUserId(): ?int
    {
        return $this->isPersonal() ? (int) $this->viewer->id : null;
    }

    /** Texto em "tu" (pessoal) ou em "vocês" (loja). */
    public function t(string $personal, string $store): string
    {
        return $this->isPersonal() ? $personal : $store;
    }

    public function viewerFirstName(): string
    {
        $this->viewer->loadMissing('agent');
        $name = trim((string) ($this->viewer->agent?->name ?: $this->viewer->name));

        return $name !== '' ? explode(' ', $name)[0] : '';
    }

    public function startUtc(): Carbon
    {
        return $this->start->copy()->utc();
    }

    public function endUtc(): Carbon
    {
        return $this->end->copy()->utc();
    }

    /** Mesmo período do ano anterior (até ao mesmo dia, se o ano ainda decorre). */
    public function previousStartUtc(): Carbon
    {
        return $this->start->copy()->subYear()->utc();
    }

    public function previousEndUtc(): Carbon
    {
        return $this->end->copy()->subYear()->utc();
    }
}
