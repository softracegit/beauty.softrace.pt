<?php

namespace App\Http\Controllers;

use App\Exceptions\AppointmentCancellationException;
use App\Models\BookingSmsActionLink;
use App\Models\CalendarEvent;
use App\Models\ClientWalletTransaction;
use App\Models\CrmSetting;
use App\Services\AppointmentCancellationService;
use App\Services\CancellationPolicyService;
use App\Support\BookingLocale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingSmsActionController extends Controller
{
    public function __construct(
        private AppointmentCancellationService $cancellationService,
        private CancellationPolicyService $policyService,
    ) {}

    public function manage(string $token): View
    {
        $resolved = $this->resolveLink($token);
        if (! $resolved['ok']) {
            return $this->resultView(
                false,
                __('booking.sms_action_result.invalid_link_title'),
                __('booking.sms_action_result.invalid_link_message'),
                '',
                config('app.name', 'Loja'),
                'error'
            );
        }

        $event = $resolved['event'];
        $event->loadMissing('store');
        $store = $event->store;
        $storeId = (int) ($event->store_id ?? 0);
        $policy = $this->policyService->resolveForEvent($event);

        return view('booking.sms-manage', [
            'token' => $token,
            'event' => $event,
            'bookingStore' => $store,
            'businessName' => (string) ($store?->name ?? config('app.name', 'Loja')),
            'bookingStoreSlug' => (string) ($store?->slug ?? \App\Models\Store::defaultPublicBookingStoreSlug()),
            'bookingCancellationPolicyNotice' => CrmSetting::bookingCancellationPolicyNoticeText($storeId ?: null),
            'cancellationPolicy' => $policy,
            'canCancelOnline' => $policy->isWithinNoticePeriod || ! $policy->hasPaidDeposit,
        ]);
    }

    public function confirm(string $token): View
    {
        return $this->handleAction($token, CalendarEvent::STATUS_CONFIRMADO);
    }

    public function cancel(Request $request, string $token): View
    {
        $reason = trim((string) $request->input('cancellation_reason', ''));

        return $this->handleAction($token, CalendarEvent::STATUS_CANCELADO, $reason);
    }

    private function handleAction(string $token, string $targetStatus, string $cancellationReason = ''): View
    {
        $resolved = $this->resolveLink($token);
        if (! $resolved['ok']) {
            return $this->resultView(
                false,
                __('booking.sms_action_result.invalid_link_title'),
                __('booking.sms_action_result.invalid_link_message'),
                '',
                config('app.name', 'Loja'),
                'error'
            );
        }

        /** @var CalendarEvent $calendarEvent */
        $calendarEvent = $resolved['event'];

        $currentStatus = (string) ($calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO);
        $result = [
            'ok' => false,
            'title' => __('booking.sms_action_result.update_failed_title'),
            'message' => __('booking.sms_action_result.update_failed_message'),
        ];

        if (in_array($currentStatus, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU], true)) {
            $result = [
                'ok' => false,
                'title' => __('booking.sms_action_result.already_closed_title'),
                'message' => __('booking.sms_action_result.already_closed_message'),
            ];
        } elseif ($targetStatus === CalendarEvent::STATUS_CANCELADO) {
            $finalReason = $cancellationReason !== ''
                ? $cancellationReason
                : __('booking.sms_action_result.default_cancel_reason');

            try {
                $cancelResult = $this->cancellationService->cancel($calendarEvent, [
                    'cancellation_reason' => $finalReason,
                    'block_if_outside_notice_period' => true,
                    'notify_client' => false,
                    'created_by_type' => ClientWalletTransaction::CREATED_BY_CLIENT,
                ]);
                $calendarEvent = $cancelResult->event;

                $result = [
                    'ok' => true,
                    'title' => __('booking.sms_action_result.cancelled_title'),
                    'message' => $this->cancellationSuccessMessage($cancelResult),
                ];
            } catch (AppointmentCancellationException $e) {
                $result = [
                    'ok' => false,
                    'title' => __('booking.sms_action_result.cancel_failed_title'),
                    'message' => $e->getMessage(),
                ];
            }
        } elseif ($currentStatus === CalendarEvent::STATUS_CONFIRMADO) {
            $result = [
                'ok' => true,
                'title' => __('booking.sms_action_result.already_confirmed_title'),
                'message' => __('booking.sms_action_result.already_confirmed_message'),
            ];
        } else {
            if (! $calendarEvent->canTransitionTo(CalendarEvent::STATUS_CONFIRMADO)) {
                $result = [
                    'ok' => false,
                    'title' => __('booking.sms_action_result.confirm_failed_title'),
                    'message' => __('booking.sms_action_result.confirm_failed_message'),
                ];
            } else {
                $calendarEvent->forceFill([
                    'status' => CalendarEvent::STATUS_CONFIRMADO,
                    'cancellation_type' => null,
                    'cancellation_reason' => null,
                    'refund_reserva' => null,
                    'avisou_dentro_prazo' => null,
                ])->save();

                $result = [
                    'ok' => true,
                    'title' => __('booking.sms_action_result.confirmed_title'),
                    'message' => __('booking.sms_action_result.confirmed_message'),
                ];
            }
        }

        $storeId = (int) ($calendarEvent->store_id ?? 0);

        return $this->resultView(
            (bool) $result['ok'],
            (string) $result['title'],
            (string) $result['message'],
            (string) ($calendarEvent->store?->slug ?? ''),
            (string) ($calendarEvent->store?->name ?? config('app.name', 'Loja')),
            $targetStatus === CalendarEvent::STATUS_CANCELADO ? 'cancel' : 'confirm',
            $storeId > 0 ? CrmSetting::bookingCancellationPolicyNoticeText($storeId) : null,
        );
    }

    /**
     * @return array{ok: bool, event?: CalendarEvent}
     */
    private function resolveLink(string $token): array
    {
        $link = BookingSmsActionLink::query()
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->with([
                'calendarEvent.store:id,slug,name',
                'calendarEvent.user:id,name',
                'calendarEvent.client:id,name,email,phone',
                'calendarEvent.service:id,name',
                'calendarEvent.eventServiceItems.service:id,name,category_id',
                'calendarEvent.eventServiceItems.service.category:id,name',
                'calendarEvent.eventServiceItems.extras' => fn ($q) => $q->orderBy('sort_order'),
                'calendarEvent.eventServiceItems.extras.extra:id,name,price,duration',
                'calendarEvent.onlineBooking',
                'calendarEvent.sale',
            ])
            ->first();

        if (! $link || ! $link->calendarEvent || $link->calendarEvent->event_type !== CalendarEvent::TYPE_MARCACAO) {
            return ['ok' => false];
        }

        $phone = $link->calendarEvent->client?->phone;
        if (is_string($phone) && trim($phone) !== '') {
            BookingLocale::apply(BookingLocale::fromPhone($phone));
        }

        return [
            'ok' => true,
            'event' => $link->calendarEvent,
        ];
    }

    private function cancellationSuccessMessage(\App\Services\AppointmentCancellationResult $result): string
    {
        if ($result->walletCredited && $result->walletCreditAmountCents > 0) {
            $amount = number_format($result->walletCreditAmountCents / 100, 2, ',', ' ');

            return __('booking.sms_action_result.cancelled_with_credit', ['amount' => $amount]);
        }

        return __('booking.sms_action_result.cancelled_success');
    }

    private function resultView(
        bool $ok,
        string $title,
        string $message,
        string $storeSlug = '',
        string $businessName = 'Loja',
        string $resultType = 'confirm',
        ?string $bookingCancellationPolicyNotice = null,
    ): View
    {
        return view('booking.sms-action-result', [
            'storeSlug' => $storeSlug,
            'title' => $title,
            'message' => $message,
            'ok' => $ok,
            'businessName' => $businessName,
            'bookingStoreSlug' => $storeSlug !== '' ? $storeSlug : \App\Models\Store::defaultPublicBookingStoreSlug(),
            'resultType' => $resultType,
            'bookingCancellationPolicyNotice' => $bookingCancellationPolicyNotice,
        ]);
    }
}
