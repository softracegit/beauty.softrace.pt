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
            return ['ready' => false, 'message' => __('booking.cancellation_policy.preview_select_datetime')];
        }

        try {
            $startLocal = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time, $timezone);
        } catch (\Exception) {
            return ['ready' => false, 'message' => __('booking.cancellation_policy.preview_invalid_datetime')];
        }

        $nowLocal = now($timezone);
        if ($startLocal->lte($nowLocal)) {
            return ['ready' => false, 'message' => __('booking.cancellation_policy.preview_must_be_future')];
        }

        $deadlineLocal = $startLocal->copy()->subHours($noticeHours);
        $paymentRequired = CrmSetting::onlineBookingPaymentRequired($storeId);
        $policy = new CancellationPolicyResult(
            isWithinNoticePeriod: $nowLocal->lte($deadlineLocal),
            noticeHoursApplied: $noticeHours,
            businessTimezone: $timezone,
            appointmentStartAtLocal: $startLocal,
            cancellationDeadlineLocal: $deadlineLocal,
            evaluatedAtLocal: $nowLocal,
            hasPaidDeposit: $paymentRequired,
            eligibleDepositCreditCents: 0,
        );

        return $this->timelinePayloadFromPolicy($policy, $storeId);
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
    private function timelinePayloadFromPolicy(CancellationPolicyResult $policy, ?int $storeId = null): array
    {
        $nowLocal = $policy->evaluatedAtLocal;
        $startLocal = $policy->appointmentStartAtLocal;
        $deadlineLocal = $policy->cancellationDeadlineLocal;
        $paymentRequired = $storeId !== null
            ? CrmSetting::onlineBookingPaymentRequired($storeId)
            : true;

        $rangeSeconds = max(1, $startLocal->getTimestamp() - $nowLocal->getTimestamp());
        $deadlineSeconds = $deadlineLocal->getTimestamp() - $nowLocal->getTimestamp();
        $deadlinePercent = round(100 * max(0, min(1, $deadlineSeconds / $rangeSeconds)), 1);

        $uiLocale = app()->getLocale();
        $carbonLocale = match ($uiLocale) {
            'en' => 'en_GB',
            'es' => 'es_ES',
            default => 'pt_PT',
        };
        $startLocalFmt = $startLocal->copy()->locale($carbonLocale);
        $deadlineLocalFmt = $deadlineLocal->copy()->locale($carbonLocale);

        $appointmentLabel = ucfirst($startLocalFmt->translatedFormat($uiLocale === 'en' ? 'M j' : 'j M'));
        $appointmentTime = $startLocalFmt->format('H:i');
        $deadlineLimitLabel = $this->formatDeadlineLimitLabel($deadlineLocalFmt);
        $descriptionDeadline = $this->formatDescriptionDeadline($deadlineLocalFmt);

        $canCancel = $policy->isWithinNoticePeriod;
        $cancellationUnavailable = ! $canCancel;

        if ($policy->noticeHoursApplied <= 0) {
            $deadlineBadge = __('booking.cancellation_policy.badge_before_start');
            $deadlinePercent = 100.0;
            $description = $paymentRequired
                ? __('booking.cancellation_policy.description_before_start')
                : __('booking.cancellation_policy.description_before_start_no_payment');
            $warningMessage = '';
            $cancellationUnavailable = false;
            $canCancel = true;
        } elseif ($cancellationUnavailable) {
            $deadlineBadge = __('booking.cancellation_policy.badge_no_cancel');
            $deadlinePercent = 0.0;
            $warningMessage = $paymentRequired
                ? __('booking.cancellation_policy.warning_past_deadline', [
                    'deadline' => $deadlineLimitLabel,
                ])
                : __('booking.cancellation_policy.warning_past_deadline_no_payment', [
                    'deadline' => $deadlineLimitLabel,
                ]);
            $description = $paymentRequired
                ? __('booking.cancellation_policy.description_past_deadline', [
                    'deadline' => $deadlineLimitLabel,
                ])
                : __('booking.cancellation_policy.description_past_deadline_no_payment', [
                    'deadline' => $deadlineLimitLabel,
                ]);
        } else {
            $deadlineBadge = __('booking.cancellation_policy.badge_before_deadline', [
                'date' => ucfirst($deadlineLocalFmt->translatedFormat($uiLocale === 'en' ? 'M j' : 'j M')),
                'time' => $deadlineLocalFmt->format('H:i'),
            ]);
            $warningMessage = '';
            $description = $paymentRequired
                ? __('booking.cancellation_policy.description_within_deadline', [
                    'deadline' => $descriptionDeadline,
                ])
                : __('booking.cancellation_policy.description_within_deadline_no_payment', [
                    'deadline' => $descriptionDeadline,
                ]);
        }

        return [
            'ready' => true,
            'notice_hours' => $policy->noticeHoursApplied,
            'now_label' => __('booking.cancellation_policy.now_label'),
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

    private function formatDeadlineLimitLabel(CarbonInterface $deadlineLocalFmt): string
    {
        if (app()->getLocale() === 'en') {
            return ucfirst($deadlineLocalFmt->translatedFormat('M j')).', '.$deadlineLocalFmt->format('H:i');
        }

        return ucfirst($deadlineLocalFmt->translatedFormat('j M')).', '.$deadlineLocalFmt->format('H:i');
    }

    private function formatDescriptionDeadline(CarbonInterface $deadlineLocalFmt): string
    {
        if (app()->getLocale() === 'en') {
            return __('booking.cancellation_policy.deadline_detail', [
                'time' => $deadlineLocalFmt->format('H:i'),
                'weekday_date' => $deadlineLocalFmt->translatedFormat('l, F j'),
            ]);
        }

        return __('booking.cancellation_policy.deadline_detail', [
            'time' => $deadlineLocalFmt->format('H:i'),
            'weekday_date' => $deadlineLocalFmt->translatedFormat('l, j \d\e F'),
        ]);
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
