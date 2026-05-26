@php
    $fees = $fees ?? collect();
    $selectedFeeIds = $selectedFeeIds ?? [];
    $inputIdPrefix = $inputIdPrefix ?? 'service';
@endphp
<div class="uedit-section service-fees-block" data-service-fees-block>
    <div class="uedit-section-title">Taxas associadas</div>
    <p class="text-muted small mb-3">Taxas aplicadas automaticamente na caixa quando este serviço estiver na marcação (cada taxa só é cobrada uma vez por marcação, mesmo com vários serviços).</p>

    @if($fees->isEmpty())
        <p class="text-muted mb-0">Nenhuma taxa criada. Crie taxas em <a href="{{ route('fees.index') }}">Taxas</a>.</p>
    @else
        <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
            <input type="checkbox" class="form-check-input" id="{{ $inputIdPrefix }}FeesSelectAll" data-service-fees-select-all aria-label="Selecionar todas as taxas">
            <label class="form-check-label fw-semibold mb-0" for="{{ $inputIdPrefix }}FeesSelectAll">Todas as taxas</label>
            <span class="badge bg-light text-dark">{{ $fees->count() }}</span>
        </div>
        @foreach($fees as $fee)
            <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom">
                <div class="form-check mb-0 flex-grow-1">
                    <input class="form-check-input service-fee-cb" type="checkbox" name="fee_ids[]" value="{{ $fee->id }}" id="{{ $inputIdPrefix }}Fee{{ $fee->id }}" {{ in_array($fee->id, $selectedFeeIds) ? 'checked' : '' }}>
                    <label class="form-check-label" for="{{ $inputIdPrefix }}Fee{{ $fee->id }}">{{ $fee->name }}</label>
                </div>
                <span class="fw-semibold text-nowrap">{{ $fee->formatted_price }}</span>
            </div>
        @endforeach
    @endif
</div>
@if($fees->isNotEmpty())
<script>
(function() {
    function initServiceFeesBlock(block) {
        if (block.hasAttribute('data-service-fees-inited')) return;
        block.setAttribute('data-service-fees-inited', '1');
        var selectAll = block.querySelector('[data-service-fees-select-all]');
        var checkboxes = block.querySelectorAll('.service-fee-cb');
        function updateSelectAll() {
            if (!selectAll) return;
            var total = checkboxes.length;
            var checked = Array.from(checkboxes).filter(function(cb) { return cb.checked; }).length;
            selectAll.checked = total > 0 && checked === total;
            selectAll.indeterminate = checked > 0 && checked < total;
        }
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            });
        }
        checkboxes.forEach(function(cb) { cb.addEventListener('change', updateSelectAll); });
        updateSelectAll();
    }
    document.querySelectorAll('[data-service-fees-block]').forEach(initServiceFeesBlock);
})();
</script>
@endif
