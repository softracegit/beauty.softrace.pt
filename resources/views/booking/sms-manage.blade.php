@extends('booking.layout')

@section('title', 'Gerir marcação')

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
                            'sectionTitle' => 'Gerir marcação',
                            'sectionSubtitle' => 'Confirme ou cancele a sua marcação',
                            'showStatusBadges' => false,
                            'showNoOnlineDepositNote' => false,
                            'actionButtons' => [
                                [
                                    'action' => route('booking.sms.confirm', ['token' => $token]),
                                    'label' => 'Confirmar',
                                    'class' => 'btn btn-success btn-sm px-3',
                                    'button_id' => 'smsManageConfirmBtn',
                                ],
                                [
                                    'action' => '#',
                                    'label' => 'Cancelar',
                                    'class' => 'btn btn-danger btn-sm px-3',
                                    'type' => 'button',
                                    'button_id' => 'smsManageCancelBtn',
                                ],
                            ],
                        ])
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
                    <h4 class="modal-title mb-0 fw-semibold" id="smsCancelReasonModalLabel">Cancelar marcação</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="{{ route('booking.sms.cancel', ['token' => $token]) }}" id="smsCancelReasonForm">
                    @csrf
                    <div class="modal-body">
                        <label for="smsCancelReasonInput" class="form-label">Razão do cancelamento</label>
                        <textarea
                            class="form-control"
                            id="smsCancelReasonInput"
                            name="cancellation_reason"
                            rows="3"
                            maxlength="1000"
                            placeholder="Indique a razão (opcional)"
                        ></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Voltar</button>
                        <button type="submit" class="btn btn-danger">Confirmar cancelamento</button>
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
