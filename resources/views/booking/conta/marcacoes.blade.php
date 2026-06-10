@extends('booking.layout')

@section('title', 'Marcações')

@section('body_class', 'booking-page booking-page--conta')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-4 pt-0">
                <main class="pt-1 booking-account-layout">
                    @if ($errors->any())
                        <div class="alert alert-danger small mb-3">
                            {{ $errors->first() }}
                        </div>
                    @endif
                    @if (session('success'))
                        <div class="alert alert-success small mb-3">
                            {{ session('success') }}
                        </div>
                    @endif

                    @include('booking.conta.partials.sidebar', ['accountNavActive' => 'marcacoes'])

                    <div class="booking-account-content">
                        @if (request()->boolean('marcacao_confirmada'))
                            <div class="text-center mb-4">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success mb-3" style="width: 3.5rem; height: 3.5rem;" aria-hidden="true">
                                    <i class="bi bi-check-lg fs-4"></i>
                                </span>
                                <h1 class="booking-services-heading h5 fw-semibold text-dark mb-2">Marcação confirmada</h1>
                                <p class="text-muted small mb-0">
                                    Recebemos o teu pedido. A equipa da <span class="fw-semibold text-dark">{{ $businessName }}</span> pode contactar-te se for necessário.
                                </p>
                            </div>
                            @if (request()->boolean('primeira_marcacao'))
                                <div class="alert alert-info small py-2 px-3 mb-4 text-start" role="status">
                                    Foi criada a tua <strong>conta de marcação</strong>. Nas próximas vezes entra com código enviado por email para preencheres os dados mais rapidamente.
                                </div>
                            @endif
                        @endif

                        @include('booking.conta.partials.marcacoes', [
                            'marcacoes' => $marcacoes,
                            'showSectionHeader' => false,
                            'plainListLayout' => true,
                            'showNoOnlineDepositNote' => false,
                        ])
                    </div>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection

@push('scripts')
    @if (! empty($enableClientCancel) && ! empty($bookingStoreSlug))
        @php
            $accountCancelUrlTemplate = str_replace(
                '999999',
                '__EVENT__',
                route('booking.conta.marcacoes.cancel', [
                    'store' => $bookingStoreSlug,
                    'calendarEvent' => 999999,
                ])
            );
        @endphp
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('accountCancelMarcacaoModal');
                var form = document.getElementById('accountCancelMarcacaoForm');
                if (!modalEl || !form || !window.bootstrap) return;

                var modal = new bootstrap.Modal(modalEl);
                var cancelUrlTemplate = @json($accountCancelUrlTemplate);

                document.querySelectorAll('.account-cancel-marcacao-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var eventId = btn.getAttribute('data-event-id');
                        if (!eventId) return;

                        form.action = cancelUrlTemplate.replace('__EVENT__', eventId);

                        var intro = document.getElementById('accountCancelMarcacaoIntro');
                        var deadline = document.getElementById('accountCancelMarcacaoDeadline');
                        var within = btn.getAttribute('data-within') === '1';
                        var deposit = btn.getAttribute('data-deposit') === '1';
                        var credit = btn.getAttribute('data-credit') || '';
                        var deadlineText = btn.getAttribute('data-deadline') || '';

                        if (intro) {
                            if (within && deposit && credit !== '') {
                                intro.textContent = 'O pré-pagamento de ' + credit + ' € será convertido em créditos na sua carteira (não é reembolso bancário).';
                            } else if (within) {
                                intro.textContent = 'A marcação será cancelada sem penalização.';
                            } else {
                                intro.textContent = 'A marcação será cancelada.';
                            }
                        }
                        if (deadline) {
                            deadline.textContent = deadlineText !== ''
                                ? 'Pode cancelar até ' + deadlineText + '.'
                                : '';
                            deadline.classList.toggle('d-none', deadlineText === '');
                        }

                        modal.show();
                    });
                });
            });
        </script>
    @endif
@endpush
