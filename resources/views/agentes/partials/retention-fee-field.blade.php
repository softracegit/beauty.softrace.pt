@php
    $ru = $retentionFeeUnit ?? \App\Models\Agent::COMMISSION_UNIT_PERCENT;
    $effectiveFrom = $retentionFeeEffectiveFrom ?? '';
@endphp
<div class="col-md-6 mb-3">
    <label class="form-label">Taxa de retenção</label>
    <div class="input-group">
        <input type="number"
               name="retention_fee_rate"
               id="retentionFeeRateInput"
               class="form-control @error('retention_fee_rate') is-invalid @enderror"
               value="{{ $retentionFeeRate }}"
               placeholder="Ex: 1,50 ou 5"
               step="0.01"
               min="0"
               @if($ru === \App\Models\Agent::COMMISSION_UNIT_PERCENT) max="100" @endif
               inputmode="decimal"
               autocomplete="off">
        <input type="hidden" name="retention_fee_unit" id="retentionFeeUnitHidden" value="{{ $ru }}">
        <div class="btn-group" role="group" aria-label="Unidade da taxa de retenção">
            <button type="button"
                    class="btn retention-fee-unit-btn {{ $ru === \App\Models\Agent::COMMISSION_UNIT_PERCENT ? 'btn-primary' : 'btn-outline-secondary' }}"
                    data-unit="{{ \App\Models\Agent::COMMISSION_UNIT_PERCENT }}"
                    id="retentionFeeUnitBtnPercent"
                    aria-pressed="{{ $ru === \App\Models\Agent::COMMISSION_UNIT_PERCENT ? 'true' : 'false' }}">%</button>
            <button type="button"
                    class="btn retention-fee-unit-btn {{ $ru === \App\Models\Agent::COMMISSION_UNIT_EURO ? 'btn-primary' : 'btn-outline-secondary' }}"
                    data-unit="{{ \App\Models\Agent::COMMISSION_UNIT_EURO }}"
                    id="retentionFeeUnitBtnEuro"
                    aria-pressed="{{ $ru === \App\Models\Agent::COMMISSION_UNIT_EURO ? 'true' : 'false' }}">€</button>
        </div>
    </div>
    @error('retention_fee_rate')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('retention_fee_unit')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    <label class="form-label mt-2 mb-1" for="retentionFeeEffectiveFrom">Aplica-se a partir de</label>
    <input type="date"
           name="retention_fee_effective_from"
           id="retentionFeeEffectiveFrom"
           class="form-control @error('retention_fee_effective_from') is-invalid @enderror"
           value="{{ $effectiveFrom }}">
    <div class="form-text">Descontada ao valor da comissão (depois da %), não ao cliente. Só em vendas com data ≥ este dia — ao mudar a taxa, actualize a data para não afectar o passado.</div>
    @error('retention_fee_effective_from')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
