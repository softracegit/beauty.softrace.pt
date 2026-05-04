{{--
    Exemplo de UI mínima para checkout com Stripe Elements (referência para temas custom).
    Rota opcional: podes registar Route::view('/booking/exemplo-pagamento', 'booking.payment-checkout-example');
--}}
@extends('booking.layout')

@section('title', 'Exemplo — Pagamento')

@section('body_class', 'booking-page')

@section('content')
    <div class="container py-5" style="max-width: 32rem;">
        <h1 class="h5 fw-semibold mb-3">Exemplo de checkout</h1>
        <p class="small text-muted mb-4">
            O fluxo real está em <code>/booking/passo-3</code>: primeiro cria-se o PaymentIntent no servidor,
            depois monta-se o Payment Element e chama-se <code>stripe.confirmPayment</code>.
        </p>
        <div class="card border shadow-sm rounded-3">
            <div class="card-body">
                <p class="small mb-2">Total: <strong>100,00&nbsp;€</strong></p>
                <p class="small mb-3">Depósito (20%): <strong>20,00&nbsp;€</strong> · Restante na loja: <strong>80,00&nbsp;€</strong></p>
                <div class="border rounded-3 p-3 bg-light text-muted small mb-0">
                    Aqui iria o <code>#booking-stripe-mount</code> (Stripe Payment Element), carregado após
                    <code>POST /booking/pagamento/intencao</code> devolver <code>client_secret</code>.
                </div>
            </div>
        </div>
        <p class="small mt-3 mb-0">
            <a href="{{ route('booking.step3', ['store' => $bookingStoreSlug], false) }}">← Voltar ao passo 3 real</a>
        </p>
    </div>
@endsection
