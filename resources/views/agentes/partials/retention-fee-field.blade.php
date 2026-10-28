@php
    $ru = $retentionFeeUnit ?? \App\Models\Agent::COMMISSION_UNIT_PERCENT;
    $effectiveFrom = $retentionFeeEffectiveFrom ?? '';
    $history = collect($retentionFeeHistory ?? []);
    $rolesWithCommission = \App\Models\User::rolesWithCommission();
    $showRetention = in_array($currentRole ?? '', $rolesWithCommission, true);
@endphp
<div id="agentRetentionFeeFieldWrap" class="col-md-6 mb-3 {{ $showRetention ? '' : 'd-none' }}">
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
    @error('retention_fee_effective_from')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if($history->isNotEmpty())
        <div class="mt-3">
            <div class="form-label small text-muted mb-1">Histórico</div>
            <ul class="list-unstyled small mb-0">
                @foreach($history as $period)
                    <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom border-light">
                        <span>desde {{ optional($period->effective_from)?->format('d/m/Y') ?? '—' }}</span>
                        <span class="d-flex align-items-center gap-2 text-nowrap">
                            @if($period->hasRetention())
                                {{ $period->formatDisplay() }}
                            @else
                                <span class="text-muted">sem retenção</span>
                            @endif
                            @if(!empty($canDeleteHistory) && !empty($agente) && $period->id)
                                <button type="submit"
                                        form="destroy-retention-fee-period-{{ $period->id }}"
                                        class="btn btn-link btn-sm p-0 text-danger"
                                        title="Eliminar"
                                        aria-label="Eliminar registo"
                                        onclick="return confirm('Eliminar este registo do histórico de retenção?');">
                                    <i class="ph ph-trash" aria-hidden="true"></i>
                                </button>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
