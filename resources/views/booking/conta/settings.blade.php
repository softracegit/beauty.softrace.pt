@extends('booking.layout')

@section('title', 'Definições')

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

                    @include('booking.conta.partials.sidebar', ['accountNavActive' => 'definicoes'])

                    <div class="booking-account-content">
                        <section id="notificacoes" class="booking-category-section mb-3">
                            <div class="card border shadow-sm rounded-3">
                                <div class="card-body py-3">
                                    <p class="small fw-semibold text-uppercase text-muted mb-2">Notificações</p>
                                    <form
                                        id="booking-notification-preferences-form"
                                        action="{{ route('booking.conta.notifications.update', ['store' => $bookingStoreSlug], false) }}"
                                        method="post"
                                        novalidate
                                    >
                                        @csrf
                                        <div id="booking-notification-preferences-error" class="alert alert-danger py-2 px-3 small d-none mb-3" role="alert"></div>
                                        <div id="booking-notification-preferences-success" class="alert alert-success py-2 px-3 small d-none mb-3" role="alert"></div>

                                        <div class="small fw-semibold text-dark border-top pt-3 pb-1">Por email</div>
                                        <div class="form-check mb-2">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="notify-email-booking-updates"
                                                name="notify_email_booking_updates"
                                                value="1"
                                                {{ old('notify_email_booking_updates', $client?->notify_email_booking_updates ?? true) ? 'checked' : '' }}
                                            >
                                            <label class="form-check-label small text-muted" for="notify-email-booking-updates">
                                                Notificação de marcação agendada, alterada ou cancelada
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="notify-email-booking-reminders"
                                                name="notify_email_booking_reminders"
                                                value="1"
                                                {{ old('notify_email_booking_reminders', $client?->notify_email_booking_reminders ?? true) ? 'checked' : '' }}
                                            >
                                            <label class="form-check-label small text-muted" for="notify-email-booking-reminders">
                                                Notificação a lembrar da marcação agendada (para confirmar se vai ou não)
                                            </label>
                                        </div>

                                        <div class="small fw-semibold text-dark border-top pt-3 pb-1">Por SMS</div>
                                        <div class="form-check mb-3">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="notify-sms-booking-reminders"
                                                name="notify_sms_booking_reminders"
                                                value="1"
                                                {{ old('notify_sms_booking_reminders', $client?->notify_sms_booking_reminders ?? true) ? 'checked' : '' }}
                                            >
                                            <label class="form-check-label small text-muted" for="notify-sms-booking-reminders">
                                                Notificação a lembrar da marcação agendada (para confirmar se vai ou não)
                                            </label>
                                        </div>

                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-dark btn-sm" id="booking-notification-preferences-submit">
                                                Guardar preferências
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </section>

                        <section id="cartoes" class="booking-category-section mb-3">
                            <div
                                class="card border shadow-sm rounded-3"
                                id="booking-cards-wallet"
                                data-setup-intent-url="{{ route('booking.conta.cards.setup_intent', ['store' => $bookingStoreSlug], false) }}"
                                data-sync-url="{{ route('booking.conta.cards.sync', ['store' => $bookingStoreSlug], false) }}"
                                data-default-url-template="{{ route('booking.conta.cards.default', ['store' => $bookingStoreSlug, 'card' => '__CARD__'], false) }}"
                                data-destroy-url-template="{{ route('booking.conta.cards.destroy', ['store' => $bookingStoreSlug, 'card' => '__CARD__'], false) }}"
                                data-publishable-key="{{ $stripePublishableKey }}"
                            >
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <p class="small fw-semibold text-uppercase text-muted mb-0">Cartões</p>
                                        <button type="button" class="btn btn-sm btn-outline-dark" id="booking-card-open-add">
                                            Adicionar cartão
                                        </button>
                                    </div>

                                    <div id="booking-cards-list">
                                        @forelse($savedCards as $card)
                                            <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-top">
                                                <div class="small">
                                                    <div class="fw-semibold text-dark">{{ strtoupper((string) $card->brand) }} •••• {{ $card->last4 }}</div>
                                                    <div class="text-muted">Validade {{ str_pad((string) $card->exp_month, 2, '0', STR_PAD_LEFT) }}/{{ $card->exp_year }}</div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    @if($card->is_default)
                                                        <span class="badge text-bg-success">Principal</span>
                                                    @else
                                                        <button type="button" class="btn btn-link btn-sm p-0 js-card-default" data-card-id="{{ $card->id }}">Definir principal</button>
                                                    @endif
                                                    <button type="button" class="btn btn-link btn-sm text-danger p-0 js-card-remove" data-card-id="{{ $card->id }}">Remover</button>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="small text-muted mb-0">Sem cartões guardados.</p>
                                        @endforelse
                                    </div>

                                    <div id="booking-card-add-wrap" class="mt-3 border-top pt-3 d-none">
                                        <p class="small text-muted mb-2">Adicione um cartão. Apenas os últimos 4 dígitos ficam visíveis na sua conta.</p>
                                        <div id="booking-card-add-element" class="mb-2"></div>
                                        <p id="booking-card-add-error" class="small text-danger mb-2 d-none"></p>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-dark btn-sm" id="booking-card-add-submit">Guardar cartão</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="booking-card-add-cancel">Cancelar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection

@push('scripts')
    <script src="https://js.stripe.com/v3/"></script>
    <script src="{{ asset('booking-assets/js/account-notifications.js') }}?v={{ file_exists(public_path('booking-assets/js/account-notifications.js')) ? filemtime(public_path('booking-assets/js/account-notifications.js')) : time() }}" defer></script>
    <script src="{{ asset('booking-assets/js/account-cards.js') }}?v={{ file_exists(public_path('booking-assets/js/account-cards.js')) ? filemtime(public_path('booking-assets/js/account-cards.js')) : time() }}" defer></script>
@endpush

