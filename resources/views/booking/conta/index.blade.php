@extends('booking.layout')

@section('title', __('booking.account.profile_page_title'))

@section('body_class', 'booking-page booking-page--conta')

@section('content')
    <div class="booking-app d-flex flex-column min-vh-100">
        @include('booking.partials.navbar')

        <div class="flex-grow-1 booking-main-body">
            <div @class(['container booking-container-wide px-3 pb-4 pt-0', 'booking-elegant-container' => ($bookingUsesRefinedLayout ?? false)])>
                <main @class(['pt-1 booking-account-layout', 'booking-elegant-account-layout' => ($bookingUsesRefinedLayout ?? false)])>
                    @include('booking.conta.partials.flash-messages')

                    @php
                        $emailVerified = !empty($user->email_verified_at);
                        $phoneVerified = !empty($client?->phone_verified_at);
                        $hasProfileName = trim((string) ($client?->name ?? '')) !== '';
                        $hasProfileGender = $client?->gender && isset(\App\Models\Client::genders()[$client->gender]);
                        $hasProfileBirth = !empty($client?->birth_date);
                        $profileSteps = [
                            'name' => $hasProfileName,
                            'gender' => $hasProfileGender,
                            'birth' => $hasProfileBirth,
                            'email' => $emailVerified,
                            'phone' => $phoneVerified,
                        ];
                        $profileDone = count(array_filter($profileSteps));
                        $profileTotal = count($profileSteps);
                        $profilePercent = $profileTotal > 0 ? (int) round(($profileDone / $profileTotal) * 100) : 0;
                        $profilePersonalMissing = [];
                        if (!$hasProfileName) {
                            $profilePersonalMissing[] = __('booking.account.field_name');
                        }
                        if (!$hasProfileGender) {
                            $profilePersonalMissing[] = __('booking.account.field_gender');
                        }
                        if (!$hasProfileBirth) {
                            $profilePersonalMissing[] = __('booking.account.field_birth_date');
                        }
                    @endphp

                    @include('booking.conta.partials.sidebar', ['accountNavActive' => 'perfil'])

                    <div class="booking-account-content">
                        @include('booking.partials.elegant-account-header', [
                            'elegantAccountEyebrow' => __('booking.elegant.account_profile_eyebrow'),
                            'elegantAccountTitle' => __('booking.nav.profile'),
                            'elegantAccountSubtitle' => __('booking.elegant.account_profile_subtitle'),
                        ])
                        <section id="perfil" class="mb-3">
                            <div class="card border shadow-sm rounded-3 mb-3 booking-profile-completion">
                                <div class="card-body py-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <div class="d-flex align-items-center gap-1 min-w-0">
                                            <p class="small fw-semibold text-uppercase text-muted mb-0">{{ __('booking.account.profile_completion') }}</p>
                                            @if ($profilePercent < 100)
                                                <button
                                                    type="button"
                                                    class="btn btn-link btn-sm text-muted p-0 lh-1 booking-profile-completion__info"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#booking-profile-completion-suggestions-modal"
                                                    aria-label="{{ __('booking.account.profile_completion_info_aria') }}"
                                                >
                                                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>
                                        <span class="small fw-semibold text-dark">{{ $profilePercent }}%</span>
                                    </div>
                                    <div class="booking-profile-completion__bar {{ $profilePercent >= 100 ? 'mb-2' : 'mb-0' }}" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $profilePercent }}" aria-label="{{ __('booking.account.profile_complete_percent_aria', ['percent' => $profilePercent]) }}">
                                        <div class="booking-profile-completion__fill" style="width: {{ $profilePercent }}%;"></div>
                                    </div>
                                    @if ($profilePercent >= 100)
                                        <p class="small text-success mb-0"><i class="bi bi-check-circle-fill me-1"></i>{{ __('booking.account.profile_complete_success') }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($profilePercent < 100)
                                <div class="modal fade booking-auth-modal" id="booking-profile-completion-suggestions-modal" tabindex="-1" aria-labelledby="booking-profile-completion-suggestions-title" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header border-0 pb-0">
                                                <h2 class="h5 mb-0" id="booking-profile-completion-suggestions-title">{{ __('booking.account.complete_profile_modal_title') }}</h2>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('booking.auth.close_aria') }}"></button>
                                            </div>
                                            <div class="modal-body pt-2">
                                                <p class="small text-muted mb-3">{{ __('booking.account.complete_profile_modal_intro') }}</p>
                                                <ul class="list-unstyled small mb-0 text-muted">
                                                    @if (count($profilePersonalMissing) > 0)
                                                        <li class="py-2 border-bottom">{{ __('booking.account.complete_profile_missing') }} <strong class="text-dark">{{ implode(', ', $profilePersonalMissing) }}</strong>.</li>
                                                    @endif
                                                    @if (!$emailVerified)
                                                        <li class="py-2 border-bottom">{{ __('booking.account.complete_profile_verify_email') }}</li>
                                                    @endif
                                                    @if (!$phoneVerified)
                                                        <li class="py-2">{{ __('booking.account.complete_profile_verify_phone') }}</li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="card border shadow-sm rounded-3 mb-3" id="booking-dados-pessoais">
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <p class="small fw-semibold text-uppercase text-muted mb-0">{{ __('booking.account.personal_data') }}</p>
                                        <button
                                            type="button"
                                            class="btn btn-outline-dark btn-sm flex-shrink-0"
                                            data-bs-toggle="modal"
                                            data-bs-target="#booking-profile-personal-modal"
                                        >
                                            {{ __('booking.account.edit') }}
                                        </button>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-start gap-2 py-2 border-top">
                                        <div>
                                            <div class="small fw-semibold text-dark">{{ __('booking.account.name') }}</div>
                                            <div class="small text-muted">{{ trim((string) ($client?->name ?? '')) !== '' ? $client->name : '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-start gap-2 py-2 border-top">
                                        <div>
                                            <div class="small fw-semibold text-dark">{{ __('booking.account.gender') }}</div>
                                            <div class="small text-muted">
                                                {{ ($client?->gender && isset(\App\Models\Client::genders()[$client->gender])) ? __('booking.genders.' . $client->gender) : '—' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-start gap-2 py-2 border-top">
                                        <div>
                                            <div class="small fw-semibold text-dark">{{ __('booking.account.birth_date') }}</div>
                                            <div class="small text-muted">{{ $client?->birth_date?->format('d/m/Y') ?? '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="card border shadow-sm rounded-3"
                                id="booking-contact-verification"
                                data-request-url="{{ route('booking.conta.verification.request', ['store' => $bookingStoreSlug], false) }}"
                                data-confirm-url="{{ route('booking.conta.verification.confirm', ['store' => $bookingStoreSlug], false) }}"
                            >
                                <div class="card-body py-3">
                                    <p class="small fw-semibold text-uppercase text-muted mb-2">{{ __('booking.account.contacts') }}</p>

                                    <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-top">
                                        <div>
                                            <div class="small fw-semibold text-dark">{{ __('booking.account.email') }}</div>
                                            <div class="small text-muted">{{ $user->email }}</div>
                                        </div>
                                        @if($emailVerified)
                                            <span class="badge text-bg-success booking-contact-status-badge"><i class="bi bi-check-circle-fill me-1"></i>{{ __('booking.account.verified') }}</span>
                                        @else
                                            <div class="d-flex align-items-center gap-2">
                                                <button type="button" class="btn btn-outline-dark btn-sm booking-contact-verify-btn js-open-contact-verification" data-channel="email">{{ __('booking.account.verify') }}</button>
                                                <span class="badge text-bg-warning text-dark booking-contact-status-badge">{{ __('booking.account.pending_verification') }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-top">
                                        <div>
                                            <div class="small fw-semibold text-dark">{{ __('booking.account.phone') }}</div>
                                            <div class="small text-muted">{{ $client?->formatted_phone ?: __('booking.account.no_phone_set') }}</div>
                                        </div>
                                        @if($phoneVerified)
                                            <span class="badge text-bg-success booking-contact-status-badge"><i class="bi bi-check-circle-fill me-1"></i>{{ __('booking.account.verified') }}</span>
                                        @else
                                            <div class="d-flex align-items-center gap-2">
                                                <button type="button" class="btn btn-outline-dark btn-sm booking-contact-verify-btn js-open-contact-verification" data-channel="phone">{{ __('booking.account.verify') }}</button>
                                                <span class="badge text-bg-warning text-dark booking-contact-status-badge">{{ __('booking.account.pending_verification') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </section>

                    </div>
                </main>
            </div>
        </div>
    </div>

    <div class="modal fade booking-auth-modal" id="booking-profile-personal-modal" tabindex="-1" aria-labelledby="booking-profile-personal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h5 mb-0" id="booking-profile-personal-title">{{ __('booking.account.edit_personal_title') }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('booking.auth.close_aria') }}"></button>
                </div>
                <div class="modal-body pt-2">
                    <div id="booking-profile-personal-error" class="alert alert-danger py-2 px-3 small d-none mb-3" role="alert"></div>
                    <form id="booking-profile-personal-form" action="{{ route('booking.conta.profile.personal', ['store' => $bookingStoreSlug], false) }}" method="post" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="booking-profile-personal-name" class="form-label small fw-semibold">{{ __('booking.account.name') }}</label>
                            <input
                                type="text"
                                class="form-control"
                                id="booking-profile-personal-name"
                                name="name"
                                value="{{ old('name', $client?->name ?? '') }}"
                                required
                                maxlength="255"
                                autocomplete="name"
                            >
                        </div>
                        <div class="mb-3">
                            <label for="booking-profile-personal-gender" class="form-label small fw-semibold">{{ __('booking.account.gender') }}</label>
                            <select class="form-select" id="booking-profile-personal-gender" name="gender" required>
                                <option value="" disabled {{ old('gender', $client?->gender) ? '' : 'selected' }}>{{ __('booking.account.select_placeholder') }}</option>
                                @foreach (\App\Models\Client::genders() as $gKey => $gLabel)
                                    <option value="{{ $gKey }}" {{ old('gender', $client?->gender) === $gKey ? 'selected' : '' }}>{{ __('booking.genders.' . $gKey) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="booking-profile-personal-birth" class="form-label small fw-semibold">{{ __('booking.account.birth_date') }}</label>
                            <input
                                type="date"
                                class="form-control"
                                id="booking-profile-personal-birth"
                                name="birth_date"
                                value="{{ old('birth_date', $client?->birth_date?->format('Y-m-d') ?? '') }}"
                                required
                                max="{{ now()->format('Y-m-d') }}"
                                min="1900-01-01"
                            >
                        </div>
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('booking.account.cancel') }}</button>
                            <button type="submit" class="btn btn-dark" id="booking-profile-personal-submit">{{ __('booking.account.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade booking-auth-modal" id="booking-contact-verification-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h5 mb-0" id="booking-contact-verification-title">{{ __('booking.account.verify_contact_title') }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('booking.auth.close_aria') }}"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-3" id="booking-contact-verification-subtitle"></p>
                    <div id="booking-contact-verification-error" class="alert alert-danger py-2 px-3 small d-none mb-3" role="alert"></div>
                    <input id="booking-contact-verification-code" type="hidden" autocomplete="one-time-code">
                    <div class="booking-auth-otp-inputs mb-3" aria-label="{{ __('booking.auth.code_digits_aria') }}">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-contact-code-digit" data-idx="0" autocomplete="one-time-code">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-contact-code-digit" data-idx="1" autocomplete="off">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-contact-code-digit" data-idx="2" autocomplete="off">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-contact-code-digit" data-idx="3" autocomplete="off">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-contact-code-digit" data-idx="4" autocomplete="off">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="form-control booking-auth-otp-input js-booking-contact-code-digit" data-idx="5" autocomplete="off">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-dark w-100" id="booking-contact-verification-submit">{{ __('booking.account.confirm') }}</button>
                        <button type="button" class="btn btn-outline-secondary" id="booking-contact-verification-resend">{{ __('booking.account.resend') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('booking.partials.store-offcanvas')
@endsection

@push('scripts')
    <script src="{{ asset('booking-assets/js/account-profile-personal.js') }}?v={{ file_exists(public_path('booking-assets/js/account-profile-personal.js')) ? filemtime(public_path('booking-assets/js/account-profile-personal.js')) : time() }}" defer></script>
    <script src="{{ asset('booking-assets/js/account-verification.js') }}?v={{ file_exists(public_path('booking-assets/js/account-verification.js')) ? filemtime(public_path('booking-assets/js/account-verification.js')) : time() }}" defer></script>
@endpush
