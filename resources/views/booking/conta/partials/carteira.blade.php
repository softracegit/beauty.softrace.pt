@php
    use App\Support\ClientWalletTransactionLabel;

    $fmtMoney = static function (int $cents, bool $showSign = false): string {
        $prefix = '';
        if ($showSign && $cents > 0) {
            $prefix = '+';
        } elseif ($showSign && $cents < 0) {
            $prefix = '−';
        }

        return $prefix.number_format(abs($cents) / 100, 2, ',', ' ').' €';
    };
    $labelResolver = app(ClientWalletTransactionLabel::class);
    $bookingTz = (string) config('booking.business_timezone', config('app.timezone'));
    $balanceCents = (int) ($balanceCents ?? 0);
    $transactions = $transactions ?? null;
@endphp

<section id="carteira" class="booking-account-wallet mb-3">
    <div class="card border shadow-sm rounded-3 mb-3">
        <div class="card-body py-4 px-3 px-md-4 text-center">
            <p class="small fw-semibold text-uppercase text-muted mb-2">Saldo disponível</p>
            <p class="display-6 fw-semibold text-dark mb-2">{{ $fmtMoney($balanceCents) }}</p>
            <p class="small text-muted mb-0 mx-auto" style="max-width: 28rem;">
                Créditos da {{ $businessName }} para usar em marcações ou pagamentos na loja.
                Não são reembolsos bancários.
            </p>
        </div>
    </div>

    <div class="card border shadow-sm rounded-3">
        <div class="card-body p-3 p-md-4">
            <header class="mb-3">
                <h2 class="h6 fw-semibold text-dark mb-1">Histórico</h2>
                <p class="small text-muted mb-0">Movimentos de créditos na sua carteira.</p>
            </header>

            @if (! $transactions || $transactions->isEmpty())
                <div class="text-center py-5 px-3 rounded-3 border bg-light bg-opacity-50">
                    <p class="small text-muted mb-0">Ainda não tem movimentos na carteira.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 booking-account-wallet__table">
                        <thead>
                            <tr class="small text-muted">
                                <th scope="col" class="fw-semibold">Data</th>
                                <th scope="col" class="fw-semibold">Descrição</th>
                                <th scope="col" class="fw-semibold text-end">Valor</th>
                                <th scope="col" class="fw-semibold text-end d-none d-md-table-cell">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $tx)
                                @php
                                    $amountCents = (int) $tx->amount_cents;
                                    $isCredit = $amountCents > 0;
                                    $when = $tx->created_at?->copy()->timezone($bookingTz);
                                @endphp
                                <tr>
                                    <td class="small text-nowrap text-muted">
                                        @if ($when)
                                            <span>{{ $when->format('d/m/Y') }}</span>
                                            <span class="d-block">{{ $when->format('H:i') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="small text-dark">
                                        {{ $labelResolver->forTransaction($tx) }}
                                    </td>
                                    <td class="small text-end fw-semibold text-nowrap {{ $isCredit ? 'text-success' : 'text-danger' }}">
                                        {{ $fmtMoney($amountCents, true) }}
                                    </td>
                                    <td class="small text-end text-muted text-nowrap d-none d-md-table-cell">
                                        {{ $fmtMoney((int) $tx->balance_after_cents) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($transactions->hasPages())
                    <div class="d-flex justify-content-center mt-3">
                        {{ $transactions->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</section>
