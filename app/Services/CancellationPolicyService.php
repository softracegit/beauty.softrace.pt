<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\CrmSetting;
use App\Models\Store;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CancellationPolicyService
{
    /**
     * Avalia se o cancelamento está dentro do prazo de aviso (fuso de negócio da loja).
     */
    public function resolveForEvent(CalendarEvent $event, ?CarbonInterface $now = null): CancellationPolicyResult
    {
        $storeId = (int) ($event->store_id ?? 0);
        $timezone = $this->businessTimezoneForStore($storeId > 0 ? $storeId : null);
        $noticeHours = CrmSetting::bookingCancellationNoticeHours($storeId > 0 ? $storeId : null);

        $startAt = $event->start_at;
        if (! $startAt) {
            throw new \InvalidArgumentException('Calendar event is missing start_at.');
        }

        $startLocal = Carbon::instance($startAt)->timezone($timezone);
        $nowLocal = $now
            ? Carbon::instance($now)->timezone($timezone)
            : now($timezone);
        $deadlineLocal = $startLocal->copy()->subHours($noticeHours);

        $withinPolicy = $nowLocal->lte($deadlineLocal);
        $deposit = $this->resolvePaidDeposit($event);

        return new CancellationPolicyResult(
            isWithinNoticePeriod: $withinPolicy,
            noticeHoursApplied: $noticeHours,
            businessTimezone: $timezone,
            appointmentStartAtLocal: $startLocal,
            cancellationDeadlineLocal: $deadlineLocal,
            evaluatedAtLocal: $nowLocal,
            hasPaidDeposit: $deposit['has_paid'],
            eligibleDepositCreditCents: $deposit['credit_cents'],
        );
    }

    /**
     * Fuso de negócio: stores.timezone válido ou config/booking.php.
     */
    public function businessTimezoneForStore(?int $storeId): string
    {
        if ($storeId !== null && $storeId > 0) {
            $store = Store::query()->find($storeId, ['id', 'timezone']);
            $tz = is_string($store?->timezone) ? trim($store->timezone) : '';
            if ($tz !== '' && $this->isValidTimezone($tz)) {
                return $tz;
            }
        }

        $fallback = (string) config('booking.business_timezone', config('app.timezone', 'UTC'));

        return $this->isValidTimezone($fallback) ? $fallback : 'UTC';
    }

    /**
     * @return array{has_paid: bool, credit_cents: int}
     */
    private function resolvePaidDeposit(CalendarEvent $event): array
    {
        $booking = $event->relationLoaded('onlineBooking')
            ? $event->onlineBooking
            : $event->onlineBooking()->first();

        if (! $booking instanceof Booking) {
            return ['has_paid' => false, 'credit_cents' => 0];
        }

        if ((string) $booking->payment_status !== Booking::PAYMENT_PAID) {
            return ['has_paid' => false, 'credit_cents' => 0];
        }

        $paidAmount = (float) ($booking->paid_amount ?? 0);
        if ($paidAmount <= 0) {
            return ['has_paid' => false, 'credit_cents' => 0];
        }

        return [
            'has_paid' => true,
            'credit_cents' => (int) round($paidAmount * 100),
        ];
    }

    /**
     * Dados para pré-visualização de cancelamento (agenda, conta, SMS).
     *
     * @return array{
     *     within_notice_period: bool,
     *     notice_hours: int,
     *     deadline_formatted: string,
     *     has_paid_deposit: bool,
     *     deposit_credit_cents: int,
     *     deposit_credit_formatted: string,
     * }
     */
    public function previewPayload(CalendarEvent $event): array
    {
        $policy = $this->resolveForEvent($event);
        $creditCents = $policy->isWithinNoticePeriod ? $policy->eligibleDepositCreditCents : 0;

        return [
            'within_notice_period' => $policy->isWithinNoticePeriod,
            'notice_hours' => $policy->noticeHoursApplied,
            'deadline_formatted' => $policy->deadlineFormatted(),
            'has_paid_deposit' => $policy->hasPaidDeposit,
            'deposit_credit_cents' => $creditCents,
            'deposit_credit_formatted' => number_format($creditCents / 100, 2, ',', ' ').' €',
        ];
    }

    /**
     * Pré-visualização da linha temporal no checkout (data/hora ainda não gravadas).
     *
     * @return array{
     *     ready: bool,
     *     message?: string,
     *     notice_hours?: int,
     *     now_label?: string,
     *     appointment_label?: string,
     *     deadline_badge?: string,
     *     deadline_percent?: float,
     *     description?: string,
     *     description_deadline?: string,
     *     within_notice_period?: bool,
     *     cancellation_unavailable?: bool,
     *     warning_message?: string,
     *     deadline_limit_label?: string,
     *     appointment_time?: string,
     * }
     */
    public function timelinePreviewForAppointment(string $date, string $time, ?int $storeId = null): array
    {
        $storeId = $storeId ?? Store::defaultPublicBookingStoreId();
        $timezone = $this->businessTimezoneForStore($storeId);
        $noticeHours = CrmSetting::bookingCancellationNoticeHours($storeId);

        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '') {
            return ['ready' => false, 'message' => 'Seleciona data e hora para ver o prazo de cancelamento.'];
        }

        try {
            $startLocal = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time, $timezone);
        } catch (\Exception) {
            return ['ready' => false, 'message' => 'Data ou hora inválidas.'];
        }

        $nowLocal = now($timezone);
        if ($startLocal->lte($nowLocal)) {
            return ['ready' => false, 'message' => 'A marcação deve ser numa data futura.'];
        }

        $deadlineLocal = $startLocal->copy()->subHours($noticeHours);
        $policy = new CancellationPolicyResult(
            isWithinNoticePeriod: $nowLocal->lte($deadlineLocal),
            noticeHoursApplied: $noticeHours,
            businessTimezone: $timezone,
            appointmentStartAtLocal: $startLocal,
            cancellationDeadlineLocal: $deadlineLocal,
            evaluatedAtLocal: $nowLocal,
            hasPaidDeposit: true,
            eligibleDepositCreditCents: 0,
        );

        return $this->timelinePayloadFromPolicy($policy);
    }

    /**
     * @return array{
     *     ready: bool,
     *     message?: string,
     *     notice_hours: int,
     *     now_label: string,
     *     appointment_label: string,
     *     deadline_badge: string,
     *     deadline_percent: float,
     *     description: string,
     *     description_deadline: string,
     *     within_notice_period: bool,
     *     cancellation_unavailable: bool,
     *     warning_message: string,
     *     deadline_limit_label: string,
     *     appointment_time: string,
     * }
     */
    private function timelinePayloadFromPolicy(CancellationPolicyResult $policy): array
    {
        $nowLocal = $policy->evaluatedAtLocal;
        $startLocal = $policy->appointmentStartAtLocal;
        $deadlineLocal = $policy->cancellationDeadlineLocal;

        $rangeSeconds = max(1, $startLocal->getTimestamp() - $nowLocal->getTimestamp());
        $deadlineSeconds = $deadlineLocal->getTimestamp() - $nowLocal->getTimestamp();
        $deadlinePercent = round(100 * max(0, min(1, $deadlineSeconds / $rangeSeconds)), 1);

        $startLocalFmt = $startLocal->copy()->locale('pt_PT');
        $deadlineLocalFmt = $deadlineLocal->copy()->locale('pt_PT');

        $appointmentLabel = ucfirst($startLocalFmt->translatedFormat('j M'));
        $appointmentTime = $startLocalFmt->format('H:i');
        $deadlineLimitLabel = ucfirst($deadlineLocalFmt->translatedFormat('j M')).', '.$deadlineLocalFmt->format('H:i');
        $descriptionDeadline = $deadlineLocalFmt->translatedFormat('H:i').' de '.$deadlineLocalFmt->translatedFormat('l, j \d\e F');

        $canCancel = $policy->isWithinNoticePeriod;
        $cancellationUnavailable = ! $canCancel;

        if ($policy->noticeHoursApplied <= 0) {
            $deadlineBadge = 'Cancelar antes do início da marcação';
            $deadlinePercent = 100.0;
            $description = 'Pode cancelar até ao início da marcação. Fora desse momento, o pré-pagamento não é reembolsado.';
            $warningMessage = '';
            $cancellationUnavailable = false;
            $canCancel = true;
        } elseif ($cancellationUnavailable) {
            $deadlineBadge = 'Sem possibilidade de cancelar';
            $deadlinePercent = 0.0;
            $warningMessage = 'O prazo para cancelar sem perder o pré-pagamento ('.$deadlineLimitLabel.') já terminou. '
                .'Ao confirmar a marcação, não poderá cancelar com devolução do pré-pagamento.';
            $description = 'Limite de cancelamento: '.$deadlineLimitLabel.'. '
                .'Após confirmar, o pré-pagamento não é reembolsado em caso de cancelamento.';
        } else {
            $deadlineBadge = 'Cancelar antes de '.ucfirst($deadlineLocalFmt->translatedFormat('j M')).' às '.$deadlineLocalFmt->format('H:i');
            $warningMessage = '';
            $description = 'Cancele ou reagende antes das '.$descriptionDeadline.'. Fora deste prazo, o pré-pagamento não é reembolsado.';
        }

        return [
            'ready' => true,
            'notice_hours' => $policy->noticeHoursApplied,
            'now_label' => 'Hoje',
            'appointment_label' => $appointmentLabel,
            'appointment_time' => $appointmentTime,
            'deadline_badge' => $deadlineBadge,
            'deadline_percent' => $deadlinePercent,
            'description' => $description,
            'description_deadline' => $descriptionDeadline,
            'within_notice_period' => $canCancel,
            'cancellation_unavailable' => $cancellationUnavailable,
            'warning_message' => $warningMessage,
            'deadline_limit_label' => $deadlineLimitLabel,
        ];
    }

    private function isValidTimezone(string $timezone): bool
    {
        try {
            new \DateTimeZone($timezone);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
