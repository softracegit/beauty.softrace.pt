@if ($errors->any() || session('success'))
    <div class="booking-account-layout__flash">
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
    </div>
@endif
