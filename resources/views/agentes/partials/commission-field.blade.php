@php
    $cu = $commissionUnit ?? \App\Models\Agent::COMMISSION_UNIT_PERCENT;
@endphp
<div class="col-md-6 mb-3">
    <label class="form-label">Taxa de Comissão</label>
    <div class="input-group">
        <input type="number"
               name="commission_rate"
               id="commissionRateInput"
               class="form-control @error('commission_rate') is-invalid @enderror"
               value="{{ $commissionRate }}"
               placeholder="Ex: 5 ou 12,50"
               step="0.01"
               min="0"
               @if($cu === \App\Models\Agent::COMMISSION_UNIT_PERCENT) max="100" @endif
               inputmode="decimal"
               autocomplete="off">
        <input type="hidden" name="commission_unit" id="commissionUnitHidden" value="{{ $cu }}">
        <div class="btn-group" role="group" aria-label="Unidade da comissão">
            <button type="button"
                    class="btn commission-unit-btn {{ $cu === \App\Models\Agent::COMMISSION_UNIT_PERCENT ? 'btn-primary' : 'btn-outline-secondary' }}"
                    data-unit="{{ \App\Models\Agent::COMMISSION_UNIT_PERCENT }}"
                    id="commissionUnitBtnPercent"
                    aria-pressed="{{ $cu === \App\Models\Agent::COMMISSION_UNIT_PERCENT ? 'true' : 'false' }}">%</button>
            <button type="button"
                    class="btn commission-unit-btn {{ $cu === \App\Models\Agent::COMMISSION_UNIT_EURO ? 'btn-primary' : 'btn-outline-secondary' }}"
                    data-unit="{{ \App\Models\Agent::COMMISSION_UNIT_EURO }}"
                    id="commissionUnitBtnEuro"
                    aria-pressed="{{ $cu === \App\Models\Agent::COMMISSION_UNIT_EURO ? 'true' : 'false' }}">€</button>
        </div>
    </div>
    @error('commission_rate')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('commission_unit')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
