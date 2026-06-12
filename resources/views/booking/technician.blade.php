@extends('booking.layout')

@section('title', __('booking.flow.technician_page_title'))

@section('body_class', 'booking-page booking-page--technician')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div @class(['container booking-container-wide px-3 pb-2 pt-0', 'booking-elegant-container' => ($bookingUsesRefinedLayout ?? false)])>
                <div @class(['row g-4 g-lg-5 align-items-start align-items-lg-stretch booking-services-row', 'booking-elegant-split' => ($bookingUsesRefinedLayout ?? false)])>
                    <div class="col-lg-8 booking-elegant-main">
                        <main class="pt-1">
                            @include('booking.partials.elegant-flow-header', [
                                'elegantActiveStep' => 2,
                                'elegantFlowTitle' => __('booking.flow.technician_heading'),
                            ])
                            <div @class(['d-flex align-items-center mb-3 ps-1 booking-page-main-heading', 'booking-elegant-hide-heading' => ($bookingUsesRefinedLayout ?? false)])>
                                <h1 class="booking-services-heading h6 fw-semibold text-dark mb-0 flex-grow-1 min-width-0">{{ __('booking.flow.technician_heading') }}</h1>
                            </div>

                            <section class="booking-category-section mb-4 pb-1">
                                <div class="card border shadow-sm rounded-3 booking-category-card">
                                    <div class="card-body">
                                        <ul id="booking-technician-list" class="list-unstyled mb-0" role="list">
                                            <li class="booking-technician-row booking-technician-row--any" data-tech-id="any" data-any-staff="1" data-tech-specialization="{{ __('booking.flow.no_preference') }}" data-tech-avatar="" data-tech-service-ids='[]'>
                                                <label class="booking-technician-row__label" for="booking-technician-any">
                                                    <div class="booking-technician-row__avatar booking-technician-row__avatar--icon">
                                                        <i class="bi bi-people-fill" aria-hidden="true"></i>
                                                    </div>
                                                    <div class="booking-technician-row__text">
                                                        <span class="booking-technician-row__name">{{ __('booking.flow.any_staff') }}</span>
                                                        <span class="booking-technician-row__meta">{{ __('booking.flow.no_preference') }}</span>
                                                    </div>
                                                    <div class="booking-technician-row__choice">
                                                        <input id="booking-technician-any" class="form-check-input" type="radio" name="booking-technician" value="any" aria-label="{{ __('booking.flow.select_any_aria') }}">
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
                                                                @php
                                                                    $nameParts = preg_split('/\s+/u', trim((string) $tech['name'])) ?: [];
                                                                    $nameParts = array_values(array_filter($nameParts, fn ($p) => $p !== ''));
                                                                    $firstInitial = $nameParts[0] ?? '';
                                                                    $lastInitial = count($nameParts) > 1 ? ($nameParts[count($nameParts) - 1] ?? '') : '';
                                                                    $avatarInitials = mb_strtoupper(
                                                                        mb_substr($firstInitial, 0, 1).mb_substr($lastInitial, 0, 1)
                                                                    );
                                                                    if ($avatarInitials === '') {
                                                                        $avatarInitials = mb_strtoupper(mb_substr((string) $tech['name'], 0, 1));
                                                                    }
                                                                @endphp
                                                                <span class="booking-technician-row__avatar-fallback">{{ $avatarInitials }}</span>
                                                            @endif
                                                        </div>
                                                        <div class="booking-technician-row__text">
                                                            <span class="booking-technician-row__name">{{ $tech['name'] }}</span>
                                                            <span class="booking-technician-row__meta">{{ $tech['specialization'] }}</span>
                                                        </div>
                                                        <div class="booking-technician-row__choice">
                                                            <input id="booking-technician-{{ $tech['id'] }}" class="form-check-input" type="radio" name="booking-technician" value="{{ $tech['id'] }}" aria-label="{{ __('booking.flow.select_technician_aria', ['name' => $tech['name']]) }}">
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

                    <div @class(['col-lg-4 booking-summary-column', 'booking-elegant-summary' => ($bookingUsesRefinedLayout ?? false)])>
                        @include('booking.partials.summary-panel', [
                            'summaryTitle' => __('booking.partials.summary_title'),
                            'showNextButton' => true,
                            'nextUrl' => route('booking.datetime', ['store' => $bookingStoreSlug], false),
                            'nextRequires' => 'technician',
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection
