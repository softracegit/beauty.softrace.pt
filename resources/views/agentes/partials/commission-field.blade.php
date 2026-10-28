@php
    $cu = $commissionUnit ?? \App\Models\Agent::COMMISSION_UNIT_PERCENT;
    $effectiveFrom = $commissionEffectiveFrom ?? '';
    $history = collect($commissionHistory ?? []);
    $rolesWithCommission = \App\Models\User::rolesWithCommission();
    $showCommission = in_array($currentRole ?? '', $rolesWithCommission, true);
@endphp
<div id="agentCommissionFieldWrap" class="col-md-6 mb-3 {{ $showCommission ? '' : 'd-none' }}">
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

    <label class="form-label mt-2 mb-1" for="commissionEffectiveFrom">Aplica-se a partir de</label>
    <input type="date"
           name="commission_effective_from"
           id="commissionEffectiveFrom"
           class="form-control @error('commission_effective_from') is-invalid @enderror"
           value="{{ $effectiveFrom }}">
    @error('commission_effective_from')
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
                            @if($period->hasCommission())
                                {{ $period->formatDisplay() }}
                            @else
                                <span class="text-muted">sem comissão</span>
                            @endif
                            @if(!empty($canDeleteHistory) && !empty($agente) && $period->id)
                                <button type="submit"
                                        form="destroy-commission-period-{{ $period->id }}"
                                        class="btn btn-link btn-sm p-0 text-danger"
                                        title="Eliminar"
                                        aria-label="Eliminar registo"
                                        onclick="return confirm('Eliminar este registo do histórico de comissão?');">
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
