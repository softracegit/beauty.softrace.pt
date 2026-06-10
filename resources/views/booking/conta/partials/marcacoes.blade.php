@php
    use App\Models\CalendarEvent;
    use App\Models\Sale;
    use App\Services\CancellationPolicyService;
    use App\Support\ApplicableFees;

    $marcacoes = $marcacoes ?? collect();
    $enableClientCancel = (bool) ($enableClientCancel ?? false);
    $bookingStoreSlug = (string) ($bookingStoreSlug ?? '');
    $policyService = $enableClientCancel ? app(CancellationPolicyService::class) : null;
    $sectionTitle = $sectionTitle ?? __('booking.account.appointments_history_title');
    $sectionSubtitle = $sectionSubtitle ?? __('booking.account.appointments_history_subtitle');
    $emptyMessage = $emptyMessage ?? __('booking.account.appointments_empty');
    $showSectionHeader = $showSectionHeader ?? true;
    $showStatusBadges = $showStatusBadges ?? true;
    $showNoOnlineDepositNote = $showNoOnlineDepositNote ?? true;
    $actionButtons = $actionButtons ?? [];
    $plainListLayout = (bool) ($plainListLayout ?? false);
    $bookingTz = (string) config('booking.business_timezone', config('app.timezone'));
    $fmtMoney = static function ($value): string {
        return number_format((float) $value, 2, ',', ' ').' €';
    };
@endphp

<section id="marcacoes" class="booking-account-marcacoes mb-3 @if ($plainListLayout) booking-account-marcacoes--plain @endif">
    <div @class([
        'booking-account-marcacoes__shell',
        'card border shadow-sm rounded-3' => ! $plainListLayout,
    ])>
        <div @class([
            'card-body',
            'p-3 p-md-4' => ! $plainListLayout,
            'booking-account-marcacoes__body--plain' => $plainListLayout,
        ])>
            @if ($showSectionHeader)
                <header class="booking-account-marcacoes__head mb-2 mb-md-3">
                    <h2 class="h6 fw-semibold text-dark mb-1">{{ $sectionTitle }}</h2>
                    <p class="small text-muted mb-0">{{ $sectionSubtitle }}</p>
                </header>
            @endif

            @if ($marcacoes->isEmpty())
                <div class="booking-marcacao-empty text-center py-5 px-3 rounded-3 border bg-light bg-opacity-50">
                    <p class="small text-muted mb-0">{{ $emptyMessage }}</p>
                </div>
            @else
                <div class="booking-marcacao-list d-flex flex-column gap-3 gap-md-4">
                    @foreach ($marcacoes as $ev)
                        @php
                            $start = $ev->start_at?->copy()->timezone($bookingTz);
                            $end = $ev->end_at?->copy()->timezone($bookingTz);
                            $nowTz = now($bookingTz);
                            $statusKey = (string) ($ev->status ?? CalendarEvent::STATUS_AGENDADO);
                            $statusLabel = CalendarEvent::statuses()[$statusKey] ?? $statusKey;

                            $tec = trim((string) ($ev->user?->name ?? ''));
                            if ($tec === '') {
                                $tec = '—';
                            }

                            $serviceRows = $ev->eventServiceItems;
                            $pivotTotal = ApplicableFees::servicesExtrasSubtotalFromEventItems($serviceRows);
                            $catalogFees = ApplicableFees::forServiceIds(
                                $serviceRows->pluck('service_id'),
                                (int) $ev->store_id,
                            );
                            $feesTotal = ApplicableFees::sumPrices($catalogFees);
                            $totalComTaxas = round($pivotTotal + $feesTotal, 2);
                            $hasExtrasOnServices = $serviceRows->contains(
                                fn ($row) => $row->extras->isNotEmpty(),
                            );
                            $hasServiceOptions = $serviceRows->contains(
                                fn ($row) => trim((string) ($row->option_name ?? '')) !== '',
                            );
                            $showServicesSubtotalLine = $serviceRows->count() > 1
                                || $hasExtrasOnServices
                                || $hasServiceOptions
                                || $feesTotal > 0.004;

                            $isLocked = in_array($statusKey, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_ANULADO, CalendarEvent::STATUS_FALTOU], true);
                            $isDone = $statusKey === CalendarEvent::STATUS_COMPLETO;
                            $whenLabel = '—';
                            if ($start) {
                                if ($isLocked) {
                                    $whenLabel = __('booking.account.when_closed');
                                } elseif ($isDone) {
                                    $whenLabel = __('booking.account.when_completed');
                                } elseif ($start->gt($nowTz)) {
                                    $whenLabel = __('booking.account.when_future');
                                } elseif ($end && $end->lt($nowTz)) {
                                    $whenLabel = __('booking.account.when_past');
                                } else {
                                    $whenLabel = __('booking.account.when_today');
                                }
                            }

                            $ob = $ev->onlineBooking;
                            $sale = $ev->sale;

                            $gorjeta = $sale ? (float) ($sale->gorjeta ?? 0) : 0.0;
                            $pagoOnline = $ob ? (float) ($ob->paid_amount ?? 0) : 0.0;
                            $pagoLoja = 0.0;
                            if ($sale) {
                                $vp = (float) ($sale->valor_pago ?? 0);
                                $pagoLoja = max(0, $vp - $gorjeta - $pagoOnline);
                            }
                            $totalPago = $sale
                                ? (float) ($sale->valor_pago ?? 0)
                                : ($pagoOnline > 0 ? $pagoOnline : $totalComTaxas);
                            $hasPaymentRecorded = ($pagoOnline > 0.004) || ($sale !== null);
                            $primaryAmountLabel = $hasPaymentRecorded ? __('booking.account.amount_paid') : __('booking.account.amount_total');

                            $showFaltaLoja = $ob
                                && (float) ($ob->remaining_amount ?? 0) > 0.004
                                && $statusKey !== CalendarEvent::STATUS_COMPLETO;
                            if (! $showFaltaLoja && ! $hasPaymentRecorded && $statusKey !== CalendarEvent::STATUS_COMPLETO) {
                                $showFaltaLoja = true;
                            }
                            $faltaAmount = $ob && (float) ($ob->remaining_amount ?? 0) > 0
                                ? (float) $ob->remaining_amount
                                : $totalComTaxas;

                            $metodoOnlineLabel = '—';
                            if ($pagoOnline > 0.004) {
                                if ($ob && trim((string) ($ob->stripe_payment_intent_id ?? '')) !== '') {
                                    $metodoOnlineLabel = __('booking.account.payment_online_card');
                                } elseif ($ob) {
                                    $metodoOnlineLabel = __('booking.account.payment_online');
                                }
                            }

                            $metodoLojaLabel = '—';
                            if ($pagoLoja > 0.004 && $sale) {
                                $pm = trim((string) ($sale->payment_method ?? ''));
                                if ($pm !== '') {
                                    $metodoLojaLabel = Sale::paymentMethods()[$pm] ?? $pm;
                                }
                            }

                            $totalComGorjeta = $totalComTaxas + $gorjeta;
                            $showTotalsSnapshot = $showServicesSubtotalLine;

                            $canClientCancel = false;
                            $cancelPolicy = null;
                            if ($enableClientCancel && $policyService && $start && ! $isLocked && ! $isDone) {
                                $cancelPolicy = $policyService->resolveForEvent($ev);
                                $canClientCancel = $start->gt($nowTz)
                                    && ($cancelPolicy->isWithinNoticePeriod || ! $cancelPolicy->hasPaidDeposit);
                            }
                        @endphp

                        <article class="booking-marcacao-card">
                            <header class="booking-marcacao-card__header">
                                <div class="booking-marcacao-card__when">
                                    <div class="booking-marcacao-card__date text-dark fw-semibold">
                                        @if ($start)
                                            {{ $start->copy()->locale(app()->getLocale())->isoFormat(__('booking.account.date_format')) }}
                                        @else
                                            —
                                        @endif
                                    </div>
                                    <div class="booking-marcacao-card__time text-muted small d-flex align-items-center gap-1 flex-wrap">
                                        <i class="bi bi-clock" aria-hidden="true"></i>
                                        <span>
                                            {{ $start?->format('H:i') ?? '—' }}
                                            @if ($end)
                                                – {{ $end->format('H:i') }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                @if ($showStatusBadges)
                                    <div class="booking-marcacao-card__badges d-flex flex-wrap gap-1 justify-content-md-end">
                                        <span class="badge rounded-pill booking-marcacao-card__badge booking-marcacao-card__badge--muted">{{ $whenLabel }}</span>
                                        <span class="badge rounded-pill booking-marcacao-card__badge booking-marcacao-card__badge--status">{{ $statusLabel }}</span>
                                    </div>
                                @endif
                            </header>

                            <div class="booking-marcacao-card__body">
                                <div class="booking-marcacao-card__services-stack d-flex flex-column gap-2 mb-3">
                                    <div class="booking-marcacao-card__services-list">
                                        @forelse ($serviceRows as $row)
                                            @php
                                                $parentName = trim((string) ($row->service?->name ?? ''));
                                                $optName = trim((string) ($row->option_name ?? ''));
                                                $serviceTitle = $optName !== ''
                                                    ? $optName
                                                    : ($parentName !== '' ? $parentName : __('booking.account.service_fallback'));
                                                $linePrice = (float) ($row->price ?? 0);
                                                $catLabel = trim((string) ($row->service?->category?->name ?? ''));
                                                $rowExtras = $row->relationLoaded('extras') ? $row->extras : collect();
                                            @endphp
                                            <div class="booking-marcacao-card__service-group">
                                                <div class="booking-marcacao-card__service-row">
                                                    <div class="booking-marcacao-card__svc-main min-w-0">
                                                        <div class="booking-marcacao-card__svc-name text-dark fw-semibold small">{{ $serviceTitle }}</div>
                                                        @if ($catLabel !== '' || $tec !== '—')
                                                            <div class="booking-marcacao-card__service-row-meta">
                                                                @if ($catLabel !== '')
                                                                    <span class="booking-marcacao-card__service-row-cat">{{ $catLabel }}</span>
                                                                @endif
                                                                @if ($catLabel !== '' && $tec !== '—')
                                                                    <span class="text-muted" aria-hidden="true">·</span>
                                                                @endif
                                                                @if ($tec !== '—')
                                                                    <span class="booking-marcacao-card__service-row-tech">{{ $tec }}</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="booking-marcacao-card__svc-price text-dark fw-semibold small text-nowrap ps-2">{{ $fmtMoney($linePrice) }}</div>
                                                </div>
                                                @foreach ($rowExtras as $extraPivot)
                                                    @php
                                                        $extraName = trim((string) ($extraPivot->extra?->name ?? ''));
                                                        if ($extraName === '') {
                                                            $extraName = __('booking.account.extra_fallback');
                                                        }
                                                        $extraPrice = (float) ($extraPivot->price ?? $extraPivot->extra?->price ?? 0);
                                                    @endphp
                                                    <div class="booking-marcacao-card__service-row booking-marcacao-card__service-row--extra">
                                                        <div class="booking-marcacao-card__svc-main min-w-0">
                                                            <div class="booking-marcacao-card__svc-name booking-marcacao-card__svc-name--extra small">+ {{ $extraName }}</div>
                                                        </div>
                                                        <div class="booking-marcacao-card__svc-price booking-marcacao-card__svc-price--extra small text-nowrap ps-2">{{ $fmtMoney($extraPrice) }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @empty
                                            @php
                                                $fallbackName = trim((string) ($ev->title ?? ''));
                                                if ($fallbackName === '') {
                                                    $fallbackName = __('booking.account.appointment_fallback');
                                                }
                                                $fallbackCat = trim((string) ($ev->service?->category?->name ?? ''));
                                            @endphp
                                            <div class="booking-marcacao-card__service-row">
                                                <div class="booking-marcacao-card__svc-main min-w-0">
                                                    <div class="booking-marcacao-card__svc-name text-dark fw-semibold small">{{ $fallbackName }}</div>
                                                    @if ($fallbackCat !== '' || $tec !== '—')
                                                        <div class="booking-marcacao-card__service-row-meta">
                                                            @if ($fallbackCat !== '')
                                                                <span class="booking-marcacao-card__service-row-cat">{{ $fallbackCat }}</span>
                                                            @endif
                                                            @if ($fallbackCat !== '' && $tec !== '—')
                                                                <span class="text-muted" aria-hidden="true">·</span>
                                                            @endif
                                                            @if ($tec !== '—')
                                                                <span class="booking-marcacao-card__service-row-tech">{{ $tec }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="booking-marcacao-card__svc-price text-dark fw-semibold small text-nowrap ps-2">{{ $fmtMoney($pivotTotal) }}</div>
                                            </div>
                                        @endforelse
                                    </div>

                                    @if ($showTotalsSnapshot)
                                        <div class="booking-marcacao-card__section booking-marcacao-card__section--boxed booking-marcacao-card__section--total-snapshot">
                                            @if ($showServicesSubtotalLine)
                                                <div class="booking-marcacao-card__total-line booking-marcacao-card__total-line--lead">
                                                    <span class="booking-marcacao-card__total-line__label">{{ __('booking.account.total_services') }}</span>
                                                    <span class="booking-marcacao-card__total-line__value">{{ $fmtMoney($pivotTotal) }}</span>
                                                </div>
                                            @endif
                                            @foreach ($catalogFees as $fee)
                                                <div class="booking-marcacao-card__total-line booking-marcacao-card__total-line--split">
                                                    <span class="booking-marcacao-card__total-line__label">{{ $fee['name'] }}</span>
                                                    <span class="booking-marcacao-card__total-line__value booking-marcacao-card__total-line__value--soft">{{ $fmtMoney($fee['price']) }}</span>
                                                </div>
                                            @endforeach
                                            @if ($feesTotal > 0.004 || $serviceRows->count() > 1)
                                                <div class="booking-marcacao-card__total-line {{ $gorjeta > 0.004 ? 'booking-marcacao-card__total-line--split' : 'booking-marcacao-card__total-line--lead' }}">
                                                    <span class="booking-marcacao-card__total-line__label">{{ __('booking.account.total') }}</span>
                                                    <span class="booking-marcacao-card__total-line__value">{{ $fmtMoney($totalComTaxas) }}</span>
                                                </div>
                                            @endif
                                            @if ($gorjeta > 0.004)
                                                <div class="booking-marcacao-card__total-line booking-marcacao-card__total-line--split">
                                                    <span class="booking-marcacao-card__total-line__label">{{ __('booking.account.tip') }}</span>
                                                    <span class="booking-marcacao-card__total-line__value booking-marcacao-card__total-line__value--soft">{{ $fmtMoney($gorjeta) }}</span>
                                                </div>
                                                <div class="booking-marcacao-card__total-line booking-marcacao-card__total-line--grand">
                                                    <span class="booking-marcacao-card__total-line__label booking-marcacao-card__total-line__label--grand">{{ __('booking.account.total_with_tip') }}</span>
                                                    <span class="booking-marcacao-card__total-line__value">{{ $fmtMoney($totalComGorjeta) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                @if ($ev->description)
                                    <div class="booking-marcacao-card__section booking-marcacao-card__section--notes">
                                        <h3 class="booking-marcacao-card__label">{{ __('booking.account.notes') }}</h3>
                                        <p class="booking-marcacao-card__notes small text-muted mb-0">{{ \Illuminate\Support\Str::limit(strip_tags((string) $ev->description), 400) }}</p>
                                    </div>
                                @endif

                                <div class="booking-marcacao-card__section booking-marcacao-card__section--payments">
                                    <h3 class="booking-marcacao-card__label">{{ __('booking.account.payments') }}</h3>
                                    <div class="booking-marcacao-stats @unless ($showFaltaLoja) booking-marcacao-stats--four @endunless">
                                        @unless ($showFaltaLoja)
                                            <div class="booking-marcacao-stat">
                                                <span class="booking-marcacao-stat__label">{{ $primaryAmountLabel }}</span>
                                                <span class="booking-marcacao-stat__value booking-marcacao-stat__value--total">{{ $fmtMoney($totalPago) }}</span>
                                            </div>
                                        @endunless
                                        <div class="booking-marcacao-stat">
                                            <span class="booking-marcacao-stat__label">{{ __('booking.account.prepayment') }}</span>
                                            <div class="booking-marcacao-stat__amount-block">
                                                <span class="booking-marcacao-stat__value">
                                                    {{ $fmtMoney($pagoOnline) }}
                                                    @if ($ob && $ob->deposit_percent_used)
                                                        <span class="booking-marcacao-stat__suffix">{{ (int) $ob->deposit_percent_used }}%</span>
                                                    @endif
                                                </span>
                                                <span class="booking-marcacao-stat__method">{{ $metodoOnlineLabel }}</span>
                                            </div>
                                        </div>
                                        @if ($showFaltaLoja)
                                            <div class="booking-marcacao-stat">
                                                <span class="booking-marcacao-stat__label">{{ __('booking.account.remaining') }}</span>
                                                <div class="booking-marcacao-stat__amount-block">
                                                    <span class="booking-marcacao-stat__value text-warning">{{ $fmtMoney($faltaAmount) }}</span>
                                                    <span class="booking-marcacao-stat__method">{{ __('booking.account.pay_in_store') }}</span>
                                                </div>
                                            </div>
                                        @else
                                            <div class="booking-marcacao-stat">
                                                <span class="booking-marcacao-stat__label">{{ __('booking.account.paid_in_store') }}</span>
                                                <div class="booking-marcacao-stat__amount-block">
                                                    <span class="booking-marcacao-stat__value">{{ $fmtMoney($pagoLoja) }}</span>
                                                    <span class="booking-marcacao-stat__method">{{ $metodoLojaLabel }}</span>
                                                </div>
                                            </div>
                                        @endif
                                        @unless ($showFaltaLoja)
                                            <div class="booking-marcacao-stat">
                                                <span class="booking-marcacao-stat__label">{{ __('booking.account.tip') }}</span>
                                                <span class="booking-marcacao-stat__value">{{ $fmtMoney($gorjeta) }}</span>
                                            </div>
                                        @endunless
                                    </div>

                                    @if (! $ob && $showNoOnlineDepositNote)
                                        <p class="small text-muted mb-0 mt-2">{{ __('booking.account.no_online_deposit_note') }}</p>
                                    @endif
                                </div>

                                @if ($canClientCancel)
                                    <div class="booking-marcacao-card__actions mt-2 pt-2 border-top">
                                        <button
                                            type="button"
                                            class="btn btn-outline-danger btn-sm account-cancel-marcacao-btn"
                                            data-event-id="{{ $ev->id }}"
                                            data-deadline="{{ $cancelPolicy?->deadlineFormatted() ?? '' }}"
                                            data-within="{{ $cancelPolicy?->isWithinNoticePeriod ? '1' : '0' }}"
                                            data-deposit="{{ $cancelPolicy?->hasPaidDeposit ? '1' : '0' }}"
                                            data-credit="{{ $cancelPolicy && $cancelPolicy->eligibleDepositCreditCents > 0 ? number_format($cancelPolicy->eligibleDepositCreditCents / 100, 2, ',', ' ') : '' }}"
                                        >
                                            {{ __('booking.account.cancel_appointment') }}
                                        </button>
                                    </div>
                                @elseif ($enableClientCancel && $start && $start->gt($nowTz) && ! $isLocked && ! $isDone && $cancelPolicy && ! $cancelPolicy->isWithinNoticePeriod && $cancelPolicy->hasPaidDeposit)
                                    <div class="alert alert-warning small py-2 px-3 mb-0 mt-2">
                                        @if ($cancelPolicy->eligibleDepositCreditCents > 0)
                                            {{ __('booking.account.cannot_cancel_online', [
                                                'amount' => number_format($cancelPolicy->eligibleDepositCreditCents / 100, 2, ',', ' '),
                                                'deadline' => $cancelPolicy->deadlineFormatted(),
                                            ]) }}
                                        @else
                                            {{ __('booking.account.cannot_cancel_online_no_amount', [
                                                'deadline' => $cancelPolicy->deadlineFormatted(),
                                            ]) }}
                                        @endif
                                    </div>
                                @endif

                                @if ($isLocked)
                                    <div class="booking-marcacao-card__alert small">
                                        <div class="fw-semibold text-dark mb-1">{{ __('booking.account.cancellation_section_title') }}</div>
                                        @if ($ev->cancellation_type)
                                            <div class="text-muted">{{ __('booking.account.cancellation_type_label') }}
                                                {{ $ev->cancellation_type === CalendarEvent::STATUS_FALTOU
                                                    ? __('booking.account.cancellation_type_no_show')
                                                    : ($ev->cancellation_type === CalendarEvent::STATUS_ANULADO ? __('booking.account.cancellation_type_voided') : __('booking.account.cancellation_type_cancelled')) }}
                                            </div>
                                        @endif
                                        @if ($ev->cancellation_reason)
                                            <div class="text-break mt-1">{{ $ev->cancellation_reason }}</div>
                                        @endif
                                        <div class="text-muted mt-2 small">
                                            @if ($ev->avisou_dentro_prazo !== null)
                                                {{ __('booking.account.notice_in_time') }} {{ $ev->avisou_dentro_prazo ? __('booking.account.yes') : __('booking.account.no') }}
                                            @endif
                                            @if ($ev->refund_reserva !== null)
                                                @if ($ev->avisou_dentro_prazo !== null) · @endif
                                                {{ __('booking.account.deposit_refund') }} {{ $ev->refund_reserva ? __('booking.account.yes') : __('booking.account.no') }}
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            <div class="booking-marcacao-policy mt-3 pt-3 border-top">
                <h3 class="booking-marcacao-card__label mb-2">{{ __('booking.account.cancellation_policy_heading') }}</h3>
                @include('booking.partials.cancellation-policy-notice')
            </div>

            @if (! empty($actionButtons))
                <div class="d-flex gap-2 flex-wrap mt-3">
                    @foreach ($actionButtons as $button)
                        <form
                            method="{{ strtoupper((string) ($button['method'] ?? 'POST')) === 'GET' ? 'GET' : 'POST' }}"
                            action="{{ $button['action'] ?? '#' }}"
                            @if (! empty($button['form_id'])) id="{{ $button['form_id'] }}" @endif
                            @if (! empty($button['form_class'])) class="{{ $button['form_class'] }}" @endif
                        >
                            @if (strtoupper((string) ($button['method'] ?? 'POST')) !== 'GET')
                                @csrf
                            @endif
                            <button
                                class="{{ $button['class'] ?? 'btn btn-primary btn-sm px-3' }}"
                                type="{{ $button['type'] ?? 'submit' }}"
                                @if (! empty($button['button_id'])) id="{{ $button['button_id'] }}" @endif
                            >{{ $button['label'] ?? __('booking.account.confirm') }}</button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

@if ($enableClientCancel && $bookingStoreSlug !== '')
    <div class="modal fade" id="accountCancelMarcacaoModal" tabindex="-1" aria-labelledby="accountCancelMarcacaoModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header pb-3">
                    <h4 class="modal-title mb-0 fw-semibold" id="accountCancelMarcacaoModalLabel">{{ __('booking.account.cancel_modal_title') }}</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('booking.auth.close_aria') }}"></button>
                </div>
                <form method="POST" action="#" id="accountCancelMarcacaoForm">
                    @csrf
                    <div class="modal-body">
                        <p class="small text-muted mb-2" id="accountCancelMarcacaoIntro"></p>
                        <p class="small mb-3" id="accountCancelMarcacaoDeadline"></p>
                        @include('booking.partials.cancellation-policy-notice')
                        <label for="accountCancelReasonInput" class="form-label mt-3">{{ __('booking.account.cancel_reason_label') }}</label>
                        <textarea
                            class="form-control"
                            id="accountCancelReasonInput"
                            name="cancellation_reason"
                            rows="3"
                            maxlength="1000"
                            placeholder="{{ __('booking.account.cancel_reason_placeholder') }}"
                        ></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('booking.account.cancel_back') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('booking.account.cancel_confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
</section>
