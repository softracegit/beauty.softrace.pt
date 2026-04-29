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
                        @include('booking.conta.partials.marcacoes', ['marcacoes' => $marcacoes])
                    </div>
                </main>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
