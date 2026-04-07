@php
    $rolesWithSpec = \App\Models\User::rolesWithSpecialization();
    $showSpec = in_array($currentRole, $rolesWithSpec, true);
@endphp
<div id="agentSpecializationFieldWrap" class="col-md-6 mb-3 {{ $showSpec ? '' : 'd-none' }}">
    <label class="form-label">Especialização</label>
    <select name="specialization" class="form-select @error('specialization') is-invalid @enderror">
        <option value="">— Selecionar —</option>
        @foreach(\App\Models\Agent::specializations() as $value => $label)
            <option value="{{ $value }}" {{ (string) $specializationValue === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    @error('specialization')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
