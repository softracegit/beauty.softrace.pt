@php
    use App\Models\CalendarEvent;
    use App\Models\Sale;

    $marcacoes = $marcacoes ?? collect();
    $sectionTitle = $sectionTitle ?? 'Histórico de marcações';
    $sectionSubtitle = $sectionSubtitle ?? 'Resumo das tuas marcações, valores e estado.';
    $emptyMessage = $emptyMessage ?? 'Ainda não tens marcações registadas nesta conta.';
    $showSectionHeader = $showSectionHeader ?? true;
    $showStatusBadges = $showStatusBadges ?? true;
    $showNoOnlineDepositNote = $showNoOnlineDepositNote ?? true;
    $actionButtons = $actionButtons ?? [];
    $bookingTz = (string) config('booking.business_timezone', config('app.timezone'));
    $fmtMoney = static function ($value): string {
        return number_format((float) $value, 2, ',', ' ').' €';
    };
@endphp

<section id="marcacoes" class="booking-account-marcacoes mb-3">
    <div class="card border shadow-sm rounded-3 booking-account-marcacoes__shell">
        <div class="card-body p-3 p-md-4">
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
                            $pivotTotal = (float) $serviceRows->sum(fn ($r) => (float) ($r->price ?? 0));

                            $isLocked = in_array($statusKey, [CalendarEvent::STATUS_CANCELADO, CalendarEvent::STATUS_FALTOU], true);
                            $isDone = $statusKey === CalendarEvent::STATUS_COMPLETO;
                            $whenLabel = '—';
                            if ($start) {
                                if ($isLocked) {
                                    $whenLabel = 'Encerrada';
                                } elseif ($isDone) {
                                    $whenLabel = 'Concluída';
                                } elseif ($start->gt($nowTz)) {
                                    $whenLabel = 'Futura';
                                } elseif ($end && $end->lt($nowTz)) {
                                    $whenLabel = 'Passada';
                                } else {
                                    $whenLabel = 'Hoje / em curso';
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
                                : ($pagoOnline > 0 ? $pagoOnline : $pivotTotal);
                            $hasPaymentRecorded = ($pagoOnline > 0.004) || ($sale !== null);
                            $primaryAmountLabel = $hasPaymentRecorded ? 'Valor pago' : 'Valor total';

                            $showFaltaLoja = $ob
                                && (float) ($ob->remaining_amount ?? 0) > 0.004
                                && $statusKey !== CalendarEvent::STATUS_COMPLETO;
                            if (! $showFaltaLoja && ! $hasPaymentRecorded && $statusKey !== CalendarEvent::STATUS_COMPLETO) {
                                $showFaltaLoja = true;
                            }
                            $faltaAmount = $ob && (float) ($ob->remaining_amount ?? 0) > 0
                                ? (float) $ob->remaining_amount
                                : $pivotTotal;

                            $metodoOnlineLabel = '—';
                            if ($pagoOnline > 0.004) {
                                if ($ob && trim((string) ($ob->stripe_payment_intent_id ?? '')) !== '') {
                                    $metodoOnlineLabel = 'Cartão online';
                                } elseif ($ob) {
                                    $metodoOnlineLabel = 'Online';
                                }
                            }

                            $metodoLojaLabel = '—';
                            if ($pagoLoja > 0.004 && $sale) {
                                $pm = trim((string) ($sale->payment_method ?? ''));
                                if ($pm !== '') {
                                    $metodoLojaLabel = Sale::paymentMethods()[$pm] ?? $pm;
                                }
                            }

                            $totalComGorjeta = $pivotTotal + $gorjeta;
                        @endphp

                        <article class="booking-marcacao-card">
                            <header class="booking-marcacao-card__header">
                                <div class="booking-marcacao-card__when">
                                    <div class="booking-marcacao-card__date text-dark fw-semibold">
                                        @if ($start)
                                            {{ $start->copy()->locale('pt')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
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
                                                if ($optName !== '') {
                                                    $displayName = $optName;
                                                } else {
                                                    $displayName = $parentName !== '' ? $parentName : 'Serviço';
                                                }
                                                $linePrice = (float) ($row->price ?? 0);
                                                $catLabel = trim((string) ($row->service?->category?->name ?? ''));
                                            @endphp
                                            <div class="booking-marcacao-card__service-row">
                                                <div class="booking-marcacao-card__svc-main min-w-0">
                                                    <div class="booking-marcacao-card__svc-name text-dark fw-semibold small">{{ $displayName }}</div>
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
                                        @empty
                                            @php
                                                $fallbackName = trim((string) ($ev->title ?? ''));
                                                if ($fallbackName === '') {
                                                    $fallbackName = 'Marcação';
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

                                    @if ($serviceRows->count() > 1)
                                        <div class="booking-marcacao-card__section booking-marcacao-card__section--boxed booking-marcacao-card__section--total-snapshot">
                                            <div class="booking-marcacao-card__total-line booking-marcacao-card__total-line--lead">
                                                <span class="booking-marcacao-card__total-line__label">Total</span>
                                                <span class="booking-marcacao-card__total-line__value">{{ $fmtMoney($pivotTotal) }}</span>
                                            </div>
                                            @if ($gorjeta > 0.004)
                                                <div class="booking-marcacao-card__total-line booking-marcacao-card__total-line--split">
                                                    <span class="booking-marcacao-card__total-line__label">Gorjeta</span>
                                                    <span class="booking-marcacao-card__total-line__value booking-marcacao-card__total-line__value--soft">{{ $fmtMoney($gorjeta) }}</span>
                                                </div>
                                                <div class="booking-marcacao-card__total-line booking-marcacao-card__total-line--grand">
                                                    <span class="booking-marcacao-card__total-line__label booking-marcacao-card__total-line__label--grand">Total (serviços + gorjeta)</span>
                                                    <span class="booking-marcacao-card__total-line__value">{{ $fmtMoney($totalComGorjeta) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                @if ($ev->description)
                                    <div class="booking-marcacao-card__section booking-marcacao-card__section--notes">
                                        <h3 class="booking-marcacao-card__label">Notas</h3>
                                        <p class="booking-marcacao-card__notes small text-muted mb-0">{{ \Illuminate\Support\Str::limit(strip_tags((string) $ev->description), 400) }}</p>
                                    </div>
                                @endif

                                <div class="booking-marcacao-card__section booking-marcacao-card__section--payments">
                                    <h3 class="booking-marcacao-card__label">Pagamentos</h3>
                                    <div class="booking-marcacao-stats @unless ($showFaltaLoja) booking-marcacao-stats--four @endunless">
                                        @unless ($showFaltaLoja)
                                            <div class="booking-marcacao-stat">
                                                <span class="booking-marcacao-stat__label">{{ $primaryAmountLabel }}</span>
                                                <span class="booking-marcacao-stat__value booking-marcacao-stat__value--total">{{ $fmtMoney($totalPago) }}</span>
                                            </div>
                                        @endunless
                                        <div class="booking-marcacao-stat">
                                            <span class="booking-marcacao-stat__label">Pago online</span>
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
                                                <span class="booking-marcacao-stat__label">Falta</span>
                                                <div class="booking-marcacao-stat__amount-block">
                                                    <span class="booking-marcacao-stat__value text-warning">{{ $fmtMoney($faltaAmount) }}</span>
                                                    <span class="booking-marcacao-stat__method">Por pagar na loja</span>
                                                </div>
                                            </div>
                                        @else
                                            <div class="booking-marcacao-stat">
                                                <span class="booking-marcacao-stat__label">Pago em loja</span>
                                                <div class="booking-marcacao-stat__amount-block">
                                                    <span class="booking-marcacao-stat__value">{{ $fmtMoney($pagoLoja) }}</span>
                                                    <span class="booking-marcacao-stat__method">{{ $metodoLojaLabel }}</span>
                                                </div>
                                            </div>
                                        @endif
                                        @unless ($showFaltaLoja)
                                            <div class="booking-marcacao-stat">
                                                <span class="booking-marcacao-stat__label">Gorjeta</span>
                                                <span class="booking-marcacao-stat__value">{{ $fmtMoney($gorjeta) }}</span>
                                            </div>
                                        @endunless
                                    </div>

                                    @if (! $ob && $showNoOnlineDepositNote)
                                        <p class="small text-muted mb-0 mt-2">Sem registo de depósito online (marcação sem pagamento antecipado ou criada na receção).</p>
                                    @endif
                                </div>

                                @if ($isLocked)
                                    <div class="booking-marcacao-card__alert small">
                                        <div class="fw-semibold text-dark mb-1">Cancelamento / falta</div>
                                        @if ($ev->cancellation_type)
                                            <div class="text-muted">Tipo: {{ $ev->cancellation_type === 'faltou' ? 'Faltou' : 'Cancelamento' }}</div>
                                        @endif
                                        @if ($ev->cancellation_reason)
                                            <div class="text-break mt-1">{{ $ev->cancellation_reason }}</div>
                                        @endif
                                        <div class="text-muted mt-2 small">
                                            @if ($ev->avisou_dentro_prazo !== null)
                                                Aviso no prazo: {{ $ev->avisou_dentro_prazo ? 'Sim' : 'Não' }}
                                            @endif
                                            @if ($ev->refund_reserva !== null)
                                                @if ($ev->avisou_dentro_prazo !== null) · @endif
                                                Reembolso reserva: {{ $ev->refund_reserva ? 'Sim' : 'Não' }}
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

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
                            >{{ $button['label'] ?? 'Confirmar' }}</button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
