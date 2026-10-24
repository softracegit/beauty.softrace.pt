<?php

namespace App\Support;

use App\Models\CalendarEvent;
use App\Models\Client;
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
     * Ex.: «17:00» no fuso da loja.
     */
    public static function timeOnly(?DateTimeInterface $dateTime, ?int $storeId, string $fallback = '-'): string
    {
        $carbon = DateTimeDisplay::inBusiness($dateTime, $storeId);

        return $carbon ? $carbon->format('H:i') : $fallback;
    }

    /**
     * Ex.: «23 de outubro de 2026» (relógio de parede já no fuso pretendido).
     */
    public static function dayLabel(?DateTimeInterface $dateTime, string $fallback = '-'): string
    {
        if (! $dateTime) {
            return $fallback;
        }

        $carbon = Carbon::instance($dateTime);
        $month = self::MONTHS_PT[(int) $carbon->month] ?? $carbon->format('F');

        return $carbon->day.' de '.$month.' de '.$carbon->year;
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

            if ($line !== '') {
                return $line;
            }
        }

        if ($event->eventServiceItems->isNotEmpty()) {
            $line = $event->eventServiceItems
                ->map(function ($item) {
                    $optionName = trim((string) ($item->option_name ?? ''));
                    if ($optionName !== '') {
                        return $optionName;
                    }

                    return trim((string) ($item->service?->name ?? ''));
                })
                ->filter()
                ->implode(', ');

            if ($line !== '') {
                return $line;
            }
        }

        $name = trim((string) ($event->service?->name ?? ''));

        return $name !== '' ? $name : $fallback;
    }

    public static function clientName(CalendarEvent $event, string $fallback = '-'): string
    {
        $name = trim((string) ($event->client?->name ?? ''));

        return $name !== '' ? $name : $fallback;
    }

    /**
     * Bloco de uma marcação no digest diário da técnica.
     *
     * @param  bool  $withBottomGap  Spacer de 15px abaixo do card (exceto no último).
     */
    public static function digestEventBlock(CalendarEvent $event, ?int $storeId, bool $withBottomGap = false): HtmlString
    {
        $statusKey = (string) ($event->status ?? CalendarEvent::STATUS_AGENDADO);
        $statusLabel = CalendarEvent::statuses()[$statusKey] ?? ($statusKey !== '' ? $statusKey : '—');
        $hora = self::timeOnly($event->start_at, $storeId);
        $cliente = self::clientName($event);
        $servico = self::servicesLine($event);
        $duracao = self::duration($event->start_at, $event->end_at);
        $marcadaEm = $event->created_at
            ? DateTimeDisplay::formatInstant($event->created_at, $storeId, 'd/m/Y H:i')
            : null;

        $linha1 = '<strong style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.4;color:#111827;">'
            .e($hora)
            .'</strong>'
            .' <span style="color:#94a3b8;">&bull;</span> '
            .'<span style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.4;color:#111827;">'
            .e($servico)
            .'</span>'
            .' <span style="color:#94a3b8;">&bull;</span> '
            .'<span style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.4;color:#111827;">'
            .e($duracao)
            .'</span>';

        $meta = e($statusLabel);
        if ($marcadaEm && $marcadaEm !== '—') {
            $meta .= ' <span style="color:#94a3b8;">&bull;</span> Marcada em '.e($marcadaEm);
        }

        $inner = $linha1
            .'<br>'
            .'<span style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.45;font-weight:600;color:#111827;">'
            .e($cliente)
            .'</span><br>'
            .'<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.45;color:#64748b;">'
            .$meta
            .'</span>';

        $alerts = self::alertsBox($event->client, withTitle: false, withBorder: false);
        if ($alerts !== null) {
            $inner .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;margin:0;mso-table-lspace:0pt;mso-table-rspace:0pt;">'
                .'<tr><td style="padding:6px 0 0 0;font-size:0;line-height:0;">'.$alerts.'</td></tr>'
                .'</table>';
        }

        $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:separate;border-spacing:0;margin:0;mso-table-lspace:0pt;mso-table-rspace:0pt;">'
            .'<tr>'
            .'<td style="border:1px solid #e5e7eb;border-radius:6px;-webkit-border-radius:6px;padding:12px 14px;font-family:Arial,Helvetica,sans-serif;">'
            .$inner
            .'</td>'
            .'</tr>'
            .'</table>';

        if ($withBottomGap) {
            $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;margin:0;">'
                .'<tr><td height="15" style="height:15px;line-height:15px;font-size:15px;">&nbsp;</td></tr>'
                .'</table>';
        }

        return new HtmlString($html);
    }

    /**
     * Caixa vermelha com alertas do cliente (alergias / contraindicações) para emails à equipa.
     * HTML em tabela + atributos legados para renderizar bem no Outlook.
     */
    public static function alertsBox(?Client $client, bool $withTitle = true, bool $withBorder = true): ?HtmlString
    {
        if (! $client) {
            return null;
        }

        $parts = collect([
            $client->allergies,
            $client->contraindications,
        ])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return null;
        }

        $body = $parts->map(fn (string $t) => nl2br(e($t)))->implode('<br>');
        $titleHtml = $withTitle
            ? '<strong style="display:block;margin:0 0 4px 0;color:#991b1b;">Alertas</strong>'
            : '';
        $borderCss = $withBorder
            ? 'border:1px solid #fecaca;'
            : 'border:0;';

        return new HtmlString(
            '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:separate;border-spacing:0;margin:0;">'
            .'<tr>'
            .'<td bgcolor="#fef2f2" style="background-color:#fef2f2;'.$borderCss.'border-radius:4px;-webkit-border-radius:4px;padding:8px 10px;color:#991b1b;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.45;">'
            .$titleHtml
            .$body
            .'</td>'
            .'</tr>'
            .'</table>'
        );
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
        $notes = trim((string) ($event?->cancellation_notes ?? ''));

        if ($reason === '' && $notes === '') {
            return $fallback;
        }

        if ($notes !== '') {
            return $reason !== '' ? $reason.'. Notas: '.$notes : 'Notas: '.$notes;
        }

        return $reason;
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
