@php
    use App\Models\Booking;
    use App\Models\CalendarEvent;
    use App\Models\Payment;
    use App\Models\Sale;

    $marcacoes = $marcacoes ?? collect();
    $bookingTz = (string) config('booking.business_timezone', config('app.timezone'));
    $fmtMoney = static function ($value): string {
        return number_format((float) $value, 2, ',', ' ').' €';
    };
    $bookingPaymentLabel = static function (?string $status): string {
        return match ($status) {
            Booking::PAYMENT_PAID => 'Pagamento online: concluído',
            Booking::PAYMENT_PENDING => 'Pagamento online: pendente',
            Booking::PAYMENT_FAILED => 'Pagamento online: falhou',
            default => $status ? 'Pagamento online: '.$status : '—',
        };
    };
    $paymentIntentStatusLabel = static function (?string $status): string {
        return match ($status) {
            Payment::STATUS_SUCCEEDED => 'Stripe: concluído',
            Payment::STATUS_PENDING => 'Stripe: pendente',
            Payment::STATUS_FAILED => 'Stripe: falhou',
            Payment::STATUS_CANCELED => 'Stripe: cancelado',
            default => $status ? 'Stripe: '.$status : '',
        };
    };
@endphp

<section id="marcacoes" class="booking-account-marcacoes mb-3">
    <div class="card border shadow-sm rounded-3">
        <div class="card-body py-3">
            <p class="small fw-semibold text-uppercase text-muted mb-3">Histórico de marcações</p>

            @if ($marcacoes->isEmpty())
                <p class="small text-muted mb-0">Ainda não tens marcações registadas nesta conta.</p>
            @else
                <div class="d-flex flex-column gap-3">
                    @foreach ($marcacoes as $ev)
                        @php
                            $start = $ev->start_at?->copy()->timezone($bookingTz);
                            $end = $ev->end_at?->copy()->timezone($bookingTz);
                            $nowTz = now($bookingTz);
                            $statusKey = (string) ($ev->status ?? CalendarEvent::STATUS_AGENDADO);
                            $statusLabel = CalendarEvent::statuses()[$statusKey] ?? $statusKey;

                            $lines = collect();
                            foreach ($ev->eventServiceItems as $row) {
                                $base = trim((string) ($row->service?->name ?? ''));
                                if ($base === '') {
                                    $base = 'Serviço';
                                }
                                if ($row->option_name) {
                                    $base .= ' — '.$row->option_name;
                                }
                                $lines->push($base);
                            }
                            if ($lines->isEmpty() && trim((string) ($ev->title ?? '')) !== '') {
                                $lines->push(trim((string) $ev->title));
                            }
                            if ($lines->isEmpty()) {
                                $lines->push('Marcação');
                            }

                            $pivotTotal = (float) $ev->eventServiceItems->sum(fn ($r) => (float) ($r->price ?? 0));

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

                            $tec = trim((string) ($ev->user?->name ?? ''));
                            if ($tec === '') {
                                $tec = '—';
                            }

                            $ob = $ev->onlineBooking;
                            $sale = $ev->sale;
                        @endphp

                        <article class="booking-marcacao-card border rounded-3 p-3 bg-white">
                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                                <div class="min-w-0">
                                    <div class="small fw-semibold text-dark">
                                        @if ($start)
                                            {{ $start->copy()->locale('pt')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
                                        @else
                                            —
                                        @endif
                                    </div>
                                    <div class="small text-muted">
                                        {{ $start?->format('H:i') ?? '—' }}
                                        @if ($end)
                                            — {{ $end->format('H:i') }}
                                        @endif
                                        <span class="text-muted">({{ $bookingTz }})</span>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-1 justify-content-end">
                                    <span class="badge rounded-pill text-bg-light text-dark border booking-marcacao-badge">{{ $whenLabel }}</span>
                                    <span class="badge rounded-pill text-bg-secondary booking-marcacao-badge">{{ $statusLabel }}</span>
                                </div>
                            </div>

                            <dl class="row small mb-0 booking-marcacao-dl">
                                <dt class="col-sm-4 col-lg-3 text-muted">Serviços</dt>
                                <dd class="col-sm-8 col-lg-9 mb-2">
                                    <ul class="mb-0 ps-3">
                                        @foreach ($lines as $line)
                                            <li>{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                </dd>

                                <dt class="col-sm-4 col-lg-3 text-muted">Técnico</dt>
                                <dd class="col-sm-8 col-lg-9 mb-2">{{ $tec }}</dd>

                                @if ($ev->description)
                                    <dt class="col-sm-4 col-lg-3 text-muted">Notas</dt>
                                    <dd class="col-sm-8 col-lg-9 mb-2 text-break">{{ \Illuminate\Support\Str::limit(strip_tags((string) $ev->description), 400) }}</dd>
                                @endif

                                <dt class="col-sm-4 col-lg-3 text-muted">Valores (serviços)</dt>
                                <dd class="col-sm-8 col-lg-9 mb-2">
                                    Total linhas: {{ $fmtMoney($pivotTotal) }}
                                    @if ($ob)
                                        <span class="text-muted"> · Total reserva online: {{ $fmtMoney($ob->total_price) }}</span>
                                    @endif
                                </dd>

                                @if ($ob)
                                    <dt class="col-sm-4 col-lg-3 text-muted">Reserva online</dt>
                                    <dd class="col-sm-8 col-lg-9 mb-2">
                                        <div>{{ $bookingPaymentLabel($ob->payment_status) }}</div>
                                        <div class="mt-1">Pago online (depósito): <strong>{{ $fmtMoney($ob->paid_amount) }}</strong></div>
                                        <div>
                                            Falta (loja / saldo):
                                            @if ((float) $ob->remaining_amount > 0 && $ob->payment_status === Booking::PAYMENT_PAID)
                                                <strong class="text-warning">{{ $fmtMoney($ob->remaining_amount) }}</strong>
                                                <span class="small text-muted">(a liquidar na loja)</span>
                                            @else
                                                <strong>{{ $fmtMoney($ob->remaining_amount) }}</strong>
                                            @endif
                                        </div>
                                        @if ($ob->deposit_percent_used)
                                            <div class="text-muted small mt-1">Depósito cobrado: {{ (int) $ob->deposit_percent_used }}% do total.</div>
                                        @endif
                                        @if ($ob->payments->isNotEmpty())
                                            <ul class="mb-0 mt-2 ps-3 text-muted small">
                                                @foreach ($ob->payments as $pay)
                                                    <li>
                                                        {{ $fmtMoney($pay->amount) }}
                                                        @if ($pay->stripe_payment_intent_id)
                                                            <span class="text-break"> · PI {{ \Illuminate\Support\Str::limit($pay->stripe_payment_intent_id, 24) }}</span>
                                                        @endif
                                                        @if ($pay->status)
                                                            · {{ $paymentIntentStatusLabel($pay->status) }}
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </dd>
                                @else
                                    <dt class="col-sm-4 col-lg-3 text-muted">Reserva online</dt>
                                    <dd class="col-sm-8 col-lg-9 mb-2 text-muted">Sem registo de depósito online (ex.: marcação sem pagamento antecipado ou criada na receção).</dd>
                                @endif

                                @if ($sale)
                                    <dt class="col-sm-4 col-lg-3 text-muted">Fatura / loja</dt>
                                    <dd class="col-sm-8 col-lg-9 mb-2">
                                        <div>
                                            @if ($sale->numero_fatura)
                                                Doc. {{ $sale->numero_fatura }}
                                                @if ($sale->data_emissao)
                                                    · {{ $sale->data_emissao->format('d/m/Y') }}
                                                @endif
                                            @else
                                                Venda registada
                                            @endif
                                        </div>
                                        <div class="mt-1">Total: {{ $fmtMoney($sale->total) }} · Valor pago: {{ $fmtMoney($sale->valor_pago) }}</div>
                                        @if ((float) $sale->desconto > 0)
                                            <div class="small text-muted">Desconto: {{ $fmtMoney($sale->desconto) }}</div>
                                        @endif
                                        @if ((float) $sale->gorjeta > 0)
                                            <div class="small text-muted">Gorjeta: {{ $fmtMoney($sale->gorjeta) }}</div>
                                        @endif
                                        <div class="small text-muted mt-1">
                                            Estado: {{ Sale::statuses()[$sale->status] ?? $sale->status }}
                                            @if ($sale->payment_method)
                                                · {{ Sale::paymentMethods()[$sale->payment_method] ?? $sale->payment_method }}
                                            @endif
                                        </div>
                                    </dd>
                                @endif

                                @if ($isLocked)
                                    <dt class="col-sm-4 col-lg-3 text-muted">Cancelamento / falta</dt>
                                    <dd class="col-sm-8 col-lg-9 mb-2">
                                        @if ($ev->cancellation_type)
                                            <div>Tipo: {{ $ev->cancellation_type === 'faltou' ? 'Faltou' : 'Cancelamento' }}</div>
                                        @endif
                                        @if ($ev->cancellation_reason)
                                            <div class="text-break mt-1">Motivo: {{ $ev->cancellation_reason }}</div>
                                        @endif
                                        <div class="small text-muted mt-1">
                                            @if ($ev->avisou_dentro_prazo !== null)
                                                Aviso dentro do prazo: {{ $ev->avisou_dentro_prazo ? 'Sim' : 'Não' }}
                                            @endif
                                            @if ($ev->refund_reserva !== null)
                                                · Reembolso reserva: {{ $ev->refund_reserva ? 'Sim' : 'Não' }}
                                            @endif
                                        </div>
                                    </dd>
                                @endif

                                <dt class="col-sm-4 col-lg-3 text-muted">Referência</dt>
                                <dd class="col-sm-8 col-lg-9 mb-0 text-muted small">Evento #{{ $ev->id }}@if ($ob && $ob->public_id) · Reserva {{ $ob->public_id }}@endif</dd>
                            </dl>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
