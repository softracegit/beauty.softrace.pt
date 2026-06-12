@php
    use App\Support\BookingTheme;

    if (! BookingTheme::usesRefinedLayout($bookingTheme ?? null)) {
        return;
    }

    $elegantActiveStep = (int) ($elegantActiveStep ?? 1);
    $elegantFlowTitle = $elegantFlowTitle ?? __('booking.elegant.services_title');
    $elegantSteps = [
        ['num' => 1, 'label' => __('booking.nav.step_services'), 'route' => 'booking.index'],
        ['num' => 2, 'label' => __('booking.elegant.step_staff'), 'route' => 'booking.technician'],
        ['num' => 3, 'label' => __('booking.elegant.step_date'), 'route' => 'booking.datetime'],
        ['num' => 4, 'label' => __('booking.elegant.step_payment'), 'route' => 'booking.step3'],
    ];
@endphp
<header class="booking-elegant-flow-header d-none d-lg-flex">
    <h1 class="booking-elegant-flow-header__title">{{ $elegantFlowTitle }}</h1>
    <ol class="booking-elegant-steps" aria-label="{{ __('booking.nav.steps_aria') }}">
        @foreach ($elegantSteps as $step)
            @php
                $isPast = $step['num'] < $elegantActiveStep;
                $isCurrent = $step['num'] === $elegantActiveStep;
                $stepUrl = route($step['route'], ['store' => $bookingStoreSlug], false);
            @endphp
            <li class="booking-elegant-steps__item">
                @if ($isPast)
                    <a href="{{ $stepUrl }}" class="booking-elegant-steps__link booking-elegant-steps__link--past">
                        <span class="booking-elegant-steps__badge" aria-hidden="true">{{ $step['num'] }}</span>
                        <span class="booking-elegant-steps__label">{{ $step['label'] }}</span>
                    </a>
                @else
                    <span @class([
                        'booking-elegant-steps__link',
                        'booking-elegant-steps__link--current' => $isCurrent,
                        'booking-elegant-steps__link--future' => ! $isCurrent,
                    ]) @if($isCurrent) aria-current="step" @endif>
                        <span class="booking-elegant-steps__badge" aria-hidden="true">{{ $step['num'] }}</span>
                        <span class="booking-elegant-steps__label">{{ $step['label'] }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</header>
