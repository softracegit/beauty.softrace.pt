<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

class ActivityLogDisplay
{
    /** Atributos omitidos na listagem (ex.: título auto-gerado, já não registado). */
    private const HIDDEN_CHANGE_ATTRIBUTES = ['title', 'password'];

    /** @var array<string, string> */
    private const ATTRIBUTE_LABELS = [
        'start_at' => 'Início',
        'end_at' => 'Fim',
        'status' => 'Estado',
        'user_id' => 'Técnico',
        'client_id' => 'Cliente',
        'title' => 'Título',
        'description' => 'Observações',
        'event_type' => 'Tipo de evento',
        'service_id' => 'Serviço',
        'personal_time_type_id' => 'Tipo tempo pessoal',
        'cancellation_reason' => 'Motivo cancelamento',
        'cancellation_type' => 'Tipo cancelamento',
        'refund_reserva' => 'Reembolso reserva',
        'avisou_dentro_prazo' => 'Avisou dentro do prazo',
        'cancellation_evaluated_at' => 'Avaliação cancelamento',
        'eventable_type' => 'Origem',
        'eventable_id' => 'Origem (ID)',
        'role' => 'Tipo de membro',
        'preferences_notes' => 'Notas de preferências',
        'preferred_schedule' => 'Horário preferido',
        'type' => 'Tipo de cliente',
        'visible_in_agenda' => 'Visível na agenda',
        'visible_in_booking' => 'Visível na marcação online',
        'booking_slug' => 'URL marcação online',
        'agenda_order' => 'Ordem na agenda',
        'weekly_schedule' => 'Horário semanal',
        'commission_rate' => 'Comissão',
        'commission_unit' => 'Unidade comissão',
        'specialization' => 'Especialização',
    ];

    /** @var array<string, string> */
    private const PAYMENT_PROPERTY_LABELS = [
        'valor' => 'Valor',
        'total' => 'Total',
        'valor_pago' => 'Valor pago',
        'sale_id' => 'Venda',
        'numero_fatura' => 'Fatura',
        'payment_method' => 'Pagamento',
        'scope' => 'Tipo',
        'invoice_status' => 'Estado fatura',
        'servicos' => 'Serviços',
        'session_id' => 'Sessão',
        'fundo_maneio' => 'Fundo de maneio',
        'dinheiro_esperado' => 'Dinheiro esperado',
        'dinheiro_contado' => 'Dinheiro contado',
        'diferenca' => 'Diferença',
        'vendas' => 'Vendas',
        'prepagamentos_atribuidos' => 'Pré-pagamentos atribuídos',
        'notas' => 'Notas',
        'motivo' => 'Motivo',
        'vendas_anuladas' => 'Vendas anuladas',
        'nota_credito' => 'Nota de crédito',
        'final_invoice_only' => 'Só fatura final',
    ];

    public static function attributeLabel(string $attr): string
    {
        return self::ATTRIBUTE_LABELS[$attr] ?? ucfirst(str_replace('_', ' ', $attr));
    }

    public static function paymentPropertyLabel(string $key): string
    {
        return self::PAYMENT_PROPERTY_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function shouldShowChangeAttribute(string $attr): bool
    {
        return ! in_array($attr, self::HIDDEN_CHANGE_ATTRIBUTES, true);
    }

    public static function contextLineForActivity(Activity $activity): ?string
    {
        return ActivityLogContext::resolveForActivity($activity);
    }

    public static function activityTitle(Activity $activity): string
    {
        $title = trim((string) ($activity->description ?? 'Alteração'));
        if ($title === '') {
            $title = 'Alteração';
        }

        if (preg_match('/^Marcação criada$/u', $title)) {
            $origemLabel = ActivityLogMarcacaoOrigin::label(ActivityLogMarcacaoOrigin::inferFromActivity($activity));
            if ($origemLabel !== null) {
                $title = 'Marcação criada ('.$origemLabel.')';
            }
        }

        $subject = $activity->subject;
        $name = match (true) {
            $subject instanceof Agent => trim((string) $subject->name),
            $subject instanceof Client => trim((string) $subject->name),
            $subject instanceof User => trim((string) $subject->name),
            default => '',
        };

        if ($name === '' || str_contains($title, $name)) {
            return $title;
        }

        if (preg_match('/^(Membro|Cliente|Conta de utilizador)\s+(criad[oa]|atualizad[oa]|eliminad[oa]|alterad[oa])/iu', $title)) {
            return $title.': '.$name;
        }

        return $title;
    }

    public static function marcacaoOrigemLabel(Activity $activity): ?string
    {
        if (($activity->event ?? '') !== 'created') {
            return null;
        }

        $subject = $activity->subject;
        if ($subject instanceof CalendarEvent && ($subject->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return null;
        }

        return ActivityLogMarcacaoOrigin::label(ActivityLogMarcacaoOrigin::inferFromActivity($activity));
    }

    public static function causerLabel(Activity $activity): ?string
    {
        $causer = $activity->causer;
        if ($causer instanceof User) {
            if ($causer->isBookingClient()) {
                $name = trim((string) $causer->name);

                return ($name !== '' ? $name : 'Cliente').' (cliente)';
            }

            $name = trim((string) $causer->name);

            return $name !== '' ? $name : null;
        }

        if (($activity->event ?? '') !== 'created') {
            return null;
        }

        if (ActivityLogMarcacaoOrigin::inferFromActivity($activity) === ActivityLogMarcacaoOrigin::ONLINE) {
            return 'Cliente (convidado)';
        }

        return null;
    }

    public static function contextLine(mixed $properties): ?string
    {
        return ActivityLogContext::contextFromProperties($properties);
    }

    public static function formatLogTimestamp(?DateTimeInterface $dateTime, ?int $storeId = null): string
    {
        return DateTimeDisplay::formatInstant($dateTime, $storeId, 'd/m/Y H:i:s');
    }

    public static function formatValue(?string $attr, mixed $value, ?int $storeId = null): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if ($attr === 'status' && is_string($value)) {
            return CalendarEvent::statuses()[$value]
                ?? Agent::statusLabels()[$value]
                ?? $value;
        }

        if ($attr === 'role' && is_string($value)) {
            return User::roleLabel($value);
        }

        if ($attr === 'commission_unit' && is_string($value)) {
            return match ($value) {
                Agent::COMMISSION_UNIT_PERCENT => 'Percentagem',
                Agent::COMMISSION_UNIT_EURO => 'Euro',
                default => $value,
            };
        }

        if ($attr === 'commission_rate' && is_numeric($value)) {
            return number_format((float) $value, 2, ',', ' ');
        }

        if ($attr === 'payment_method' && is_string($value)) {
            return Sale::paymentMethods()[$value] ?? $value;
        }

        if ($attr === 'scope' && is_string($value)) {
            return match ($value) {
                Sale::SCOPE_BOOKING_RESERVA => 'Pré-pagamento',
                Sale::SCOPE_CAIXA_LIQUIDACAO => 'Pagamento em loja',
                default => $value,
            };
        }

        if (in_array($attr, ['valor', 'total', 'valor_pago', 'fundo_maneio', 'dinheiro_esperado', 'dinheiro_contado', 'diferenca'], true) && is_numeric($value)) {
            return number_format((float) $value, 2, ',', ' ').' €';
        }

        if (self::looksLikeDateTime($attr, $value)) {
            return self::formatDateTime($value, $attr, $storeId);
        }

        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $json !== false ? (strlen($json) > 50 ? substr($json, 0, 50).'…' : $json) : '[valor complexo]';
        }

        $str = (string) $value;

        return strlen($str) > 50 ? substr($str, 0, 50).'…' : $str;
    }

    public static function formatChange(string $attr, mixed $oldVal, mixed $newVal, ?int $storeId = null): string
    {
        if (self::looksLikeDateTime($attr, $oldVal) || self::looksLikeDateTime($attr, $newVal)) {
            $old = self::parseDateTime($oldVal, $attr, $storeId);
            $new = self::parseDateTime($newVal, $attr, $storeId);

            if ($old instanceof CarbonInterface && $new instanceof CarbonInterface) {
                if ($old->toDateString() === $new->toDateString()) {
                    return $old->format('d/m/Y').' '.$old->format('H:i').' → '.$new->format('H:i');
                }

                return self::formatDateTime($oldVal, $attr, $storeId).' → '.self::formatDateTime($newVal, $attr, $storeId);
            }
        }

        return self::formatValue($attr, $oldVal, $storeId).' → '.self::formatValue($attr, $newVal, $storeId);
    }

    private static function looksLikeDateTime(?string $attr, mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        if (in_array($attr, ['start_at', 'end_at', 'created_at', 'updated_at', 'cancellation_evaluated_at'], true)) {
            return true;
        }

        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}[T\s]/', $value);
    }

    private static function parseDateTime(mixed $value, ?string $attr = null, ?int $storeId = null): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);

            // Horários de marcação são gravados como relógio de parede (sem offset na BD).
            if (self::isWallClockCalendarField($attr)) {
                return $parsed->utc();
            }

            return $parsed->timezone(self::timezone($storeId));
        } catch (\Throwable) {
            return null;
        }
    }

    private static function formatDateTime(mixed $value, ?string $attr = null, ?int $storeId = null): string
    {
        if (self::isWallClockCalendarField($attr)) {
            try {
                return DateTimeDisplay::marcacaoWallClock(Carbon::parse($value), 'd/m/Y H:i', (string) $value);
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        $dt = self::parseDateTime($value, $attr, $storeId);

        return $dt instanceof CarbonInterface
            ? DateTimeDisplay::formatInstant($dt, $storeId, 'd/m/Y H:i')
            : (string) $value;
    }

    private static function isWallClockCalendarField(?string $attr): bool
    {
        return in_array($attr, ['start_at', 'end_at'], true);
    }

    private static function timezone(?int $storeId = null): string
    {
        if ($storeId !== null && $storeId > 0) {
            return StoreBusinessTime::timezoneForStore($storeId);
        }

        if (function_exists('app')) {
            $currentStoreId = app(CurrentStore::class)->tryId();
            if ($currentStoreId) {
                return StoreBusinessTime::timezoneForStore($currentStoreId);
            }
        }

        return DateTimeDisplay::businessTimezone();
    }
}
