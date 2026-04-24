@extends('booking.layout')

@section('title', 'A minha conta')

@section('body_class', 'booking-page booking-page--conta')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-4 pt-0">
                <main class="pt-1 mx-auto" style="max-width: 28rem;">
                    <h1 class="booking-services-heading h6 fw-semibold text-dark mb-2">A minha conta</h1>
                    <p class="text-muted small mb-4">
                        Sessão iniciada como <span class="fw-semibold text-dark">{{ $user->email }}</span>
                    </p>

                    <div class="card border shadow-sm rounded-3 mb-3">
                        <div class="card-body py-3">
                            <p class="small fw-semibold text-uppercase text-muted mb-2">Conta</p>
                            <ul class="list-unstyled mb-0">
                                <li>
                                    <a href="{{ route('booking.index') }}" class="text-decoration-none d-flex align-items-center justify-content-between gap-2 text-dark">
                                        <span>Nova marcação</span>
                                        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <section id="carteira" class="booking-category-section mb-3">
                        <div
                            class="card border shadow-sm rounded-3"
                            id="booking-cards-wallet"
                            data-setup-intent-url="{{ route('booking.conta.cards.setup_intent') }}"
                            data-sync-url="{{ route('booking.conta.cards.sync') }}"
                            data-default-url-template="{{ route('booking.conta.cards.default', ['card' => '__CARD__']) }}"
                            data-destroy-url-template="{{ route('booking.conta.cards.destroy', ['card' => '__CARD__']) }}"
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

                    <form method="post" action="{{ route('logout') }}" class="d-grid">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Terminar sessão</button>
                    </form>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection

@push('scripts')
    <script src="https://js.stripe.com/v3/"></script>
    <script src="{{ asset('booking-assets/js/account-cards.js') }}?v={{ file_exists(public_path('booking-assets/js/account-cards.js')) ? filemtime(public_path('booking-assets/js/account-cards.js')) : time() }}" defer></script>
@endpush
