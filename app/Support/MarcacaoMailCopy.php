<?php

namespace App\Support;

use App\Models\CalendarEvent;
use App\Models\Store;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\HtmlString;

/**
 * Textos e formatação partilhados pelos emails de marcação (técnica, receção, cliente).
 */
final class MarcacaoMailCopy
{
    /** @var array<int, string> */
    private const MONTHS_PT = [
        1 => 'janeiro',
        2 => 'fevereiro',
        3 => 'março',
        4 => 'abril',
        5 => 'maio',
        6 => 'junho',
        7 => 'julho',
        8 => 'agosto',
        9 => 'setembro',
        10 => 'outubro',
        11 => 'novembro',
        12 => 'dezembro',
    ];

    public static function storeName(?Store $store): string
    {
        $name = trim((string) ($store?->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return StoreMailBranding::resolve($store)['name'];
    }

    public static function subject(string $base, ?Store $store): string
    {
        return $base.' - '.self::storeName($store);
    }

    /**
     * Bloco de campos com 1 quebra entre linhas (HTML &lt;br&gt;).
     * Cada chamada a MailMessage::line() cria parágrafo → ~2 quebras entre blocos.
     *
     * @param  list<string|null>  $lines
     */
    public static function block(array $lines): HtmlString
    {
        $parts = [];
        foreach ($lines as $line) {
            if ($line === null) {
                continue;
            }
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $parts[] = e($line);
        }

        return new HtmlString(implode('<br>', $parts));
    }

    /** Parágrafo vazio — espaço extra antes de frases finais (ex.: «Se tiver alguma questão…»). */
    public static function spacer(): HtmlString
    {
        return new HtmlString('&nbsp;');
    }

    /**
     * Ex.: «23 julho 2026 17:00» no fuso da loja.
     */
    public static function dateTime(?DateTimeInterface $dateTime, ?int $storeId, string $fallback = '-'): string
    {
        $carbon = DateTimeDisplay::inBusiness($dateTime, $storeId);
        if (! $carbon) {
            return $fallback;
        }

        $month = self::MONTHS_PT[(int) $carbon->month] ?? $carbon->format('F');

        return $carbon->day.' '.$month.' '.$carbon->year.' '.$carbon->format('H:i');
    }

    /**
     * Ex.: «1h 30min», «45min», «2h».
     */
    public static function duration(
        ?DateTimeInterface $start,
        ?DateTimeInterface $end,
        string $fallback = '-',
    ): string {
        if (! $start || ! $end) {
            return $fallback;
        }

        $minutes = (int) abs(Carbon::instance($start)->diffInMinutes(Carbon::instance($end)));
        if ($minutes <= 0) {
            return $fallback;
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return $hours.'h '.$mins.'min';
        }
        if ($hours > 0) {
            return $hours.'h';
        }

        return $mins.'min';
    }

    public static function servicesLine(CalendarEvent $event, string $fallback = '-'): string
    {
        if ($event->eventServices->isNotEmpty()) {
            $line = $event->eventServices
                ->map(function ($service) {
                    $optionName = trim((string) ($service->pivot->option_name ?? ''));

                    return $optionName !== '' ? $optionName : $service->name;
                })
                ->filter()
                ->implode(', ');

            return $line !== '' ? $line : $fallback;
        }

        $name = trim((string) ($event->service?->name ?? ''));

        return $name !== '' ? $name : $fallback;
    }

    public static function clientName(CalendarEvent $event, string $fallback = '-'): string
    {
        $name = trim((string) ($event->client?->name ?? ''));

        return $name !== '' ? $name : $fallback;
    }

    public static function firstName(?string $fullName): string
    {
        $fullName = trim((string) $fullName);
        if ($fullName === '') {
            return '';
        }

        return explode(' ', $fullName, 2)[0];
    }

    public static function originLabel(CalendarEvent $event): string
    {
        return $event->isOnlineMarcacao()
            ? 'Marcação feita no Booking'
            : 'Marcação feita na agenda';
    }

    public static function reason(?CalendarEvent $event, string $fallback = '-'): string
    {
        $reason = trim((string) ($event?->cancellation_reason ?? ''));

        return $reason !== '' ? $reason : $fallback;
    }

    /**
     * Compara só o início (minuto) — a duração/end_at muda quando se alteram serviços
     * e não deve ser tratada como «mudança de data» no email.
     */
    public static function startsDiffer(
        ?DateTimeInterface $previousStart,
        ?DateTimeInterface $newStart,
        ?int $storeId = null,
    ): bool {
        if ($previousStart === null) {
            return false;
        }

        return self::dateTime($previousStart, $storeId) !== self::dateTime($newStart, $storeId);
    }

    /**
     * @deprecated Preferir {@see startsDiffer()} para emails de marcação.
     */
    public static function datesDiffer(
        ?DateTimeInterface $previousStart,
        ?DateTimeInterface $previousEnd,
        ?DateTimeInterface $newStart,
        ?DateTimeInterface $newEnd,
        ?int $storeId = null,
    ): bool {
        return self::startsDiffer($previousStart, $newStart, $storeId);
    }

    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     * @return list<string>
     */
    public static function serviceChangeLines(array $added, array $removed): array
    {
        $lines = [];
        $added = array_values(array_filter(array_map('trim', $added)));
        $removed = array_values(array_filter(array_map('trim', $removed)));

        if ($added !== []) {
            $lines[] = 'Serviços adicionados: '.implode(', ', $added);
        }
        if ($removed !== []) {
            $lines[] = 'Serviços removidos: '.implode(', ', $removed);
        }

        return $lines;
    }

    public static function parseIso(?string $iso): ?Carbon
    {
        if ($iso === null || trim($iso) === '') {
            return null;
        }

        try {
            return Carbon::parse($iso);
        } catch (\Throwable) {
            return null;
        }
    }
}
