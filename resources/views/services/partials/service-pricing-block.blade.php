@php
    $prefix = $prefix ?? 'add';
    $isEdit = $prefix === 'edit';
@endphp
<div class="services-pricing-block mb-3" data-pricing-prefix="{{ $prefix }}">
    <div class="form-label fw-semibold mb-3">Preço e duração</div>

    <div id="{{ $prefix }}ServiceSimplePricingWrap">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="{{ $prefix }}ServiceDuration">Duração (minutos) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="duration" id="{{ $prefix }}ServiceDuration" @if(!$isEdit) value="60" @endif min="1" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="{{ $prefix }}ServicePrice">Preço normal (€) <span class="text-danger">*</span>
                    @if($isEdit)
                        <span id="editServicePriceOriginal" class="text-muted text-decoration-line-through small ms-1" style="display:none;"></span>
                    @endif
                </label>
                <input type="number" class="form-control" name="price" id="{{ $prefix }}ServicePrice" step="0.01" min="0" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="{{ $prefix }}ServiceOnlinePrice">Preço online (€)</label>
                <input type="number" class="form-control" name="online_price" id="{{ $prefix }}ServiceOnlinePrice" step="0.01" min="0" placeholder="{{ $isEdit ? 'Opcional' : 'Opcional, mais barato' }}">
            </div>
        </div>
    </div>

    <div id="{{ $prefix }}ServiceVariantsList" class="service-variants-list d-none" aria-live="polite"></div>

    <button type="button" class="btn btn-outline-primary btn-sm service-variant-add-btn" id="{{ $prefix }}ServiceAddVariantBtn">
        <i class="ph ph-plus me-1" aria-hidden="true"></i>Adicionar variante
    </button>
    <input type="hidden" id="{{ $prefix }}ServiceHasOptions" name="has_options" value="0">
</div>
