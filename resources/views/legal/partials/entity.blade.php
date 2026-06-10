<p class="legal-meta">
    <strong>{{ $companyName }}</strong>
    @if ($companyNif !== '')
        · NIF {{ $companyNif }}
    @endif
    @if ($companyAddress !== '')
        · {{ $companyAddress }}
    @endif
    · <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
</p>
