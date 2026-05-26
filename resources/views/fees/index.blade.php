@extends('partials.layouts.main')
@section('title', 'Taxas | Beauty CRM')

@section('css')
<link href="{{ asset('template/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
<style>
.fee-item-row.service-item {
    position: relative;
    --service-category-color: #6c757d;
    transition: box-shadow 0.2s;
}
.fee-item-row.service-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: var(--service-category-color);
    border-radius: 4px 0 0 4px;
}
.fee-item-row.service-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.fee-item-row.fee-item-clickable {
    cursor: pointer;
}
.service-item-name,
.service-item-price {
    font-size: 1rem;
    font-weight: 600;
    color: var(--heading-color, #1e293b);
}
.service-item-price {
    white-space: nowrap;
}
.services-list-container {
    padding: 1.5rem 1.25rem 1.25rem 2.25rem;
}
.services-top-bar.contacts-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 0.5rem 0.4rem 0.5rem;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 0 !important;
    background: var(--surface-color);
    border-radius: var(--bs-border-radius-lg) var(--bs-border-radius-lg) 0 0;
}
.services-top-bar .contacts-search {
    flex: 1;
    max-width: none;
}
</style>
@endsection

@section('content')
<div class="contacts-container">
    <div class="contacts-main">
        <div class="services-top-bar contacts-header d-flex align-items-center gap-2 mb-3">
            <div class="contacts-search flex-grow-1">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" class="form-control" placeholder="Pesquisar taxas..." id="feeSearch">
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                <i class="ph ph-plus me-2"></i>Nova taxa
            </button>
        </div>
        <div class="services-list-container" id="feesListContainer">
            <div id="feesList">
                @forelse($fees as $fee)
                    @include('fees.partials.fee-item', ['fee' => $fee])
                @empty
                    <div class="text-center py-5 text-muted" id="feesEmptyState">
                        <i class="ph-duotone ph-coins" style="font-size: 3rem;"></i>
                        <p class="mt-3 mb-0">Nenhuma taxa criada.</p>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#addFeeModal">Criar taxa</button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addFeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="addFeeForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nova taxa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Preço (€) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                        </div>
                    </div>
                    @if(isset($serviceCategories) && $serviceCategories->isNotEmpty())
                    <div class="mb-3">
                        @include('fees.partials.fee-services-association', [
                            'serviceCategories' => $serviceCategories,
                            'selectedServiceIds' => [],
                            'inputIdPrefix' => 'addFee',
                        ])
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editFeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editFeeForm" method="POST" data-update-url="{{ url('fees') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="fee_id" id="editFeeId">
                <div class="modal-header">
                    <h5 class="modal-title">Editar taxa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="editFeeName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Preço (€) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" id="editFeePrice" step="0.01" min="0" required>
                        </div>
                    </div>
                    @if(isset($serviceCategories) && $serviceCategories->isNotEmpty())
                    <div class="mb-3">
                        @include('fees.partials.fee-services-association', [
                            'serviceCategories' => $serviceCategories,
                            'selectedServiceIds' => [],
                            'inputIdPrefix' => 'editFee',
                        ])
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="deleteFeeBtn">
                        <i class="ph ph-trash me-1"></i>Eliminar
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    document.getElementById('feeSearch')?.addEventListener('input', function() {
        var q = (this.value || '').toLowerCase().trim();
        document.querySelectorAll('.fee-item-row[data-fee-id]').forEach(function(row) {
            var name = (row.querySelector('.service-item-name')?.textContent || '').toLowerCase();
            row.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
        });
    });

    function openEditFeeModal(feeId) {
        if (!feeId) return;
        var modal = document.getElementById('editFeeModal');
        var form = document.getElementById('editFeeForm');
        if (!modal || !form) return;
        fetch('{{ url('fees') }}/' + feeId, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                form.action = '{{ url('fees') }}/' + data.id;
                document.getElementById('editFeeId').value = data.id;
                document.getElementById('editFeeName').value = data.name || '';
                document.getElementById('editFeePrice').value = data.price ?? 0;
                document.getElementById('deleteFeeBtn').setAttribute('data-fee-id', String(data.id));
                var rawIds = data.service_ids;
                var serviceIds = Array.isArray(rawIds) ? rawIds : (rawIds && typeof rawIds === 'object' ? Object.values(rawIds) : []);
                var serviceIdSet = new Set(serviceIds.map(function(id) { return Number(id); }).filter(function(n) { return !isNaN(n); }));
                form.querySelectorAll('.fee-service-cb').forEach(function(cb) {
                    cb.checked = serviceIdSet.has(Number(cb.value));
                });
                var block = modal.querySelector('[data-fee-services-block]');
                if (block && block.hasAttribute('data-fee-services-inited')) {
                    var selectAll = block.querySelector('[data-fee-services-select-all]');
                    var catCbs = block.querySelectorAll('.fee-category-select-all');
                    var total = block.querySelectorAll('.fee-service-cb').length;
                    var checked = Array.from(block.querySelectorAll('.fee-service-cb')).filter(function(cb) { return cb.checked; }).length;
                    if (selectAll) {
                        selectAll.checked = total > 0 && checked === total;
                        selectAll.indeterminate = checked > 0 && checked < total;
                    }
                    catCbs.forEach(function(catCb) {
                        var catId = catCb.getAttribute('data-category-id');
                        var inCat = block.querySelectorAll('.fee-service-cb[data-category-id="' + catId + '"]');
                        var catChecked = Array.from(inCat).filter(function(cb) { return cb.checked; }).length;
                        catCb.checked = catChecked === inCat.length && inCat.length > 0;
                        catCb.indeterminate = catChecked > 0 && catChecked < inCat.length;
                    });
                }
                bootstrap.Modal.getOrCreateInstance(modal).show();
            })
            .catch(function() { alert('Erro ao carregar taxa.'); });
    }

    document.getElementById('feesListContainer')?.addEventListener('click', function(e) {
        var row = e.target.closest('.fee-item-row.fee-item-clickable[data-fee-id]');
        if (!row) return;
        if (e.target.closest('a, button, input, select, textarea, label, .dropdown, .dropdown-menu')) return;
        e.preventDefault();
        openEditFeeModal(row.getAttribute('data-fee-id'));
    });

    document.getElementById('addFeeForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(this);
        fd.delete('service_ids[]');
        this.querySelectorAll('.fee-service-cb:checked').forEach(function(cb) { fd.append('service_ids[]', cb.value); });
        fetch('{{ route('fees.store') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function(r) { return r.json(); }).then(function(res) {
            if (res.success) { window.location.reload(); }
            else { alert(res.message || (res.errors ? Object.values(res.errors).flat().join(' ') : 'Erro ao criar taxa.')); }
        }).catch(function() { alert('Erro de ligação.'); });
    });

    document.getElementById('editFeeForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        var f = this;
        var btn = f.querySelector('button[type="submit"]');
        btn.disabled = true;
        var fd = new FormData(f);
        fd.delete('service_ids[]');
        f.querySelectorAll('.fee-service-cb:checked').forEach(function(cb) { fd.append('service_ids[]', cb.value); });
        fd.set('_method', 'PUT');
        fetch(f.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function(r) { return r.json(); }).then(function(res) {
            btn.disabled = false;
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('editFeeModal')).hide();
                window.location.reload();
            } else {
                alert(res.message || 'Erro ao guardar.');
            }
        }).catch(function() { btn.disabled = false; alert('Erro de ligação.'); });
    });

    document.getElementById('deleteFeeBtn')?.addEventListener('click', function() {
        var id = this.getAttribute('data-fee-id') || document.getElementById('editFeeId').value;
        if (!id || !confirm('Eliminar esta taxa?')) return;
        fetch('{{ url('fees') }}/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); }).then(function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('editFeeModal')).hide();
                window.location.reload();
            }
        });
    });
});
</script>
@endsection
