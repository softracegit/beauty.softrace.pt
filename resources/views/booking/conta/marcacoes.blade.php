@extends('booking.layout')

@section('title', __('booking.account.appointments_page_title'))

@section('body_class', 'booking-page booking-page--conta')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div @class(['container booking-container-wide px-3 pb-4 pt-0', 'booking-elegant-container' => ($bookingUsesRefinedLayout ?? false)])>
                <main @class(['pt-1 booking-account-layout', 'booking-elegant-account-layout' => ($bookingUsesRefinedLayout ?? false)])>

                    @include('booking.conta.partials.sidebar', ['accountNavActive' => 'marcacoes'])

                    <div class="booking-account-content">
                        @include('booking.conta.partials.flash-messages')
                        @if (request()->boolean('marcacao_confirmada'))
                            {{-- Marcação acabada de fazer: é a primeira coisa que o cliente vê --}}
                            <section class="booking-account-confirmed" role="status">
                                <span class="booking-account-confirmed__icon" aria-hidden="true">
                                    <i class="bi bi-check-lg"></i>
                                </span>
                                <div class="booking-account-confirmed__body">
                                    <h2 class="booking-account-confirmed__title">{{ __('booking.account.appointment_confirmed') }}</h2>
                                    <p class="booking-account-confirmed__text">
                                        {!! str_replace(
                                            ':business',
                                            '<strong>' . e($businessName) . '</strong>',
                                            __('booking.account.appointment_confirm_body')
                                        ) !!}
                                    </p>
                                </div>
                            </section>
                        @endif
                        @include('booking.conta.partials.resume-booking')
                        @include('booking.partials.elegant-account-header', [
                            'elegantAccountTitle' => __('booking.account.appointments_history_title'),
                            'elegantAccountSubtitle' => __('booking.account.appointments_history_subtitle'),
                        ])

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
                var accountI18n = @json(trans('booking.account'));

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
                                intro.textContent = accountI18n.cancel_modal_intro_credit.replace(':amount', credit);
                            } else if (within) {
                                intro.textContent = accountI18n.cancel_modal_intro_no_penalty;
                            } else {
                                intro.textContent = accountI18n.cancel_modal_intro_default;
                            }
                        }
                        if (deadline) {
                            deadline.textContent = deadlineText !== ''
                                ? accountI18n.cancel_modal_deadline.replace(':deadline', deadlineText)
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
