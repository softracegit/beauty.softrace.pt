@extends('booking.layout')

@section('title', 'Técnica')

@section('body_class', 'booking-page booking-page--technician')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div class="container booking-container-wide px-3 pb-3 pt-0">
                <div class="row g-4 g-lg-5 align-items-start align-items-lg-stretch booking-services-row">
                    <div class="col-lg-8">
                        <main class="pt-1">
                            <h1 class="booking-services-heading h6 fw-semibold text-dark mb-3 ps-1">Escolha uma técnica para a marcação</h1>

                            <section class="booking-category-section mb-4 pb-1">
                                <div class="card border shadow-sm rounded-3 booking-category-card">
                                    <div class="card-body">
                                        <ul id="booking-technician-list" class="list-unstyled mb-0" role="list">
                                            <li class="booking-technician-row booking-technician-row--any" data-tech-id="any" data-any-staff="1" data-tech-specialization="Sem preferência de técnica" data-tech-avatar="" data-tech-service-ids='[]'>
                                                <label class="booking-technician-row__label" for="booking-technician-any">
                                                    <div class="booking-technician-row__avatar booking-technician-row__avatar--icon">
                                                        <i class="bi bi-people-fill" aria-hidden="true"></i>
                                                    </div>
                                                    <div class="booking-technician-row__text">
                                                        <span class="booking-technician-row__name">Qualquer staff</span>
                                                        <span class="booking-technician-row__meta">Sem preferência de técnica</span>
                                                    </div>
                                                    <div class="booking-technician-row__choice">
                                                        <input id="booking-technician-any" class="form-check-input" type="radio" name="booking-technician" value="any" aria-label="Selecionar qualquer staff">
                                                    </div>
                                                </label>
                                            </li>
                                            @foreach($technicians as $tech)
                                                <li class="booking-technician-row" data-tech-id="{{ $tech['id'] }}" data-tech-specialization="{{ $tech['specialization'] }}" data-tech-avatar="{{ $tech['avatar'] ?? '' }}" data-tech-service-ids='@json($tech['serviceIds'])'>
                                                    <label class="booking-technician-row__label" for="booking-technician-{{ $tech['id'] }}">
                                                        <div class="booking-technician-row__avatar">
                                                            @if($tech['avatar'])
                                                                <img src="{{ $tech['avatar'] }}" alt="{{ $tech['name'] }}" loading="lazy">
                                                            @else
                                                                <span class="booking-technician-row__avatar-fallback">{{ mb_strtoupper(mb_substr($tech['name'], 0, 1)) }}</span>
                                                            @endif
                                                        </div>
                                                        <div class="booking-technician-row__text">
                                                            <span class="booking-technician-row__name">{{ $tech['name'] }}</span>
                                                            <span class="booking-technician-row__meta">{{ $tech['specialization'] }}</span>
                                                        </div>
                                                        <div class="booking-technician-row__choice">
                                                            <input id="booking-technician-{{ $tech['id'] }}" class="form-check-input" type="radio" name="booking-technician" value="{{ $tech['id'] }}" aria-label="Selecionar {{ $tech['name'] }}">
                                                        </div>
                                                    </label>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </section>
                        </main>
                    </div>

                    <div class="col-lg-4 booking-summary-column">
                        @include('booking.partials.summary-panel', [
                            'summaryTitle' => 'Resumo da marcação',
                            'showBackButton' => true,
                            'backUrl' => route('booking.index'),
                            'showNextButton' => true,
                            'nextUrl' => route('booking.datetime'),
                            'nextRequires' => 'technician',
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
