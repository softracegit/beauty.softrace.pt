@php
    $rolesWithSpec = \App\Models\User::rolesWithSpecialization();
    $showSpec = in_array($currentRole, $rolesWithSpec, true);
    $displayValue = \App\Models\Agent::specializationLabel($specializationValue) ?? $specializationValue;
@endphp
<div id="agentSpecializationFieldWrap" class="col-md-6 mb-3 {{ $showSpec ? '' : 'd-none' }}">
    <label class="form-label" for="agentSpecializationInput">Especialização / Descrição</label>
    <input
        type="text"
        id="agentSpecializationInput"
        name="specialization"
        class="form-control @error('specialization') is-invalid @enderror"
        value="{{ old('specialization', $displayValue) }}"
        maxlength="120"
        autocomplete="off"
        placeholder="Ex.: Manicure, Nail Art…"
    >
    <p class="form-text mb-0">Texto mostrado publicamente no Booking junto ao nome da técnica.</p>
    @error('specialization')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
