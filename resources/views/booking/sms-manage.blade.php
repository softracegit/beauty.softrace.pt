@extends('booking.layout')

@section('title', __('booking.sms_manage.page_title'))

@section('body_class', 'booking-page booking-page--conta booking-page--sms-manage')

@push('head')
    <style>
        .booking-page--sms-manage .booking-account-layout {
            display: block;
        }
        .booking-page--sms-manage .booking-account-content {
            width: 100%;
            max-width: 920px;
            margin: 0 auto;
        }
        .booking-page--sms-manage .booking-account-marcacoes {
            margin-inline: auto;
        }
        @media (max-width: 767.98px) {
            .booking-page--sms-manage #marcacoes .d-flex.gap-2.flex-wrap.mt-3 > form {
                flex: 0 0 calc(50% - 0.25rem);
            }
            .booking-page--sms-manage #marcacoes .d-flex.gap-2.flex-wrap.mt-3 > form > button {
                width: 100%;
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $marcacao = collect([$event]);
    @endphp
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar', ['bookingDisableAuthModal' => true])

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-4 pt-0">
                <main class="pt-1 booking-account-layout">
                    <div class="booking-account-content w-100">
                        @include('booking.conta.partials.marcacoes', [
                            'marcacoes' => $marcacao,
                            'showSectionHeader' => true,
                            'sectionTitle' => __('booking.sms_manage.section_title'),
                            'sectionSubtitle' => __('booking.sms_manage.section_subtitle'),
                            'showStatusBadges' => false,
                            'showNoOnlineDepositNote' => false,
                            'actionButtons' => array_filter([
                                ($canCancelOnline ?? true) ? [
                                    'action' => '#',
                                    'label' => __('booking.sms_manage.btn_wont_go'),
                                    'class' => 'btn btn-outline-danger btn-sm px-3',
                                    'type' => 'button',
                                    'button_id' => 'smsManageCancelBtn',
                                ] : null,
                                [
                                    'action' => route('booking.sms.confirm', ['token' => $token]),
                                    'label' => __('booking.sms_manage.btn_will_go'),
                                    'class' => 'btn btn-success btn-sm px-3',
                                    'button_id' => 'smsManageConfirmBtn',
                                ],
                            ]),
                        ])

                        @if (! ($canCancelOnline ?? true) && isset($cancellationPolicy) && $cancellationPolicy->hasPaidDeposit)
                            <div class="alert alert-warning small py-2 px-3 mt-3 mb-0">
                                @if ($cancellationPolicy->eligibleDepositCreditCents > 0)
                                    {{ __('booking.sms_manage.cannot_cancel_warning', [
                                        'amount' => number_format($cancellationPolicy->eligibleDepositCreditCents / 100, 2, ',', ' ').' €',
                                        'deadline' => $cancellationPolicy->deadlineFormatted(),
                                    ]) }}
                                @else
                                    {{ __('booking.sms_manage.cannot_cancel_warning_no_amount', [
                                        'deadline' => $cancellationPolicy->deadlineFormatted(),
                                    ]) }}
                                @endif
                            </div>
                        @endif
                    </div>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')

    <div class="modal fade" id="smsCancelReasonModal" tabindex="-1" aria-labelledby="smsCancelReasonModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header pb-3">
                    <h4 class="modal-title mb-0 fw-semibold" id="smsCancelReasonModalLabel">{{ __('booking.sms_manage.cancel_modal_title') }}</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('booking.sms_manage.close_aria') }}"></button>
                </div>
                <form method="POST" action="{{ route('booking.sms.cancel', ['token' => $token]) }}" id="smsCancelReasonForm">
                    @csrf
                    <div class="modal-body">
                        @if (isset($cancellationPolicy))
                            <p class="small mb-2">
                                {{ __('booking.sms_manage.cancel_modal_deadline', ['deadline' => $cancellationPolicy->deadlineFormatted()]) }}
                            </p>
                            @if ($cancellationPolicy->hasPaidDeposit && $cancellationPolicy->isWithinNoticePeriod && $cancellationPolicy->eligibleDepositCreditCents > 0)
                                <p class="small text-muted mb-2">
                                    {{ __('booking.sms_manage.cancel_modal_deposit_credit', [
                                        'amount' => number_format($cancellationPolicy->eligibleDepositCreditCents / 100, 2, ',', ' '),
                                    ]) }}
                                </p>
                            @endif
                        @endif
                        <p class="small text-muted mb-3">
                            @include('booking.partials.cancellation-policy-notice', [
                                'storeId' => (int) ($event->store_id ?? 0),
                            ])
                        </p>
                        <label for="smsCancelReasonInput" class="form-label">{{ __('booking.sms_manage.cancel_reason_label') }}</label>
                        <textarea
                            class="form-control"
                            id="smsCancelReasonInput"
                            name="cancellation_reason"
                            rows="3"
                            maxlength="1000"
                            placeholder="{{ __('booking.sms_manage.cancel_reason_placeholder') }}"
                        ></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('booking.sms_manage.cancel_back') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('booking.sms_manage.cancel_confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            var cancelBtn = document.getElementById('smsManageCancelBtn');
            if (!cancelBtn || !window.bootstrap) return;

            var modalEl = document.getElementById('smsCancelReasonModal');
            if (!modalEl) return;
            var modal = new bootstrap.Modal(modalEl);

            cancelBtn.addEventListener('click', function () {
                modal.show();
            });
        });
    </script>
@endpush
