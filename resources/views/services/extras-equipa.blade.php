@extends('partials.layouts.main')
@section('title', 'Extras por equipa | Beauty CRM')

@section('css')
<style>
.service-tech-checkbox {
    width: 1.75rem !important;
    height: 1.75rem !important;
    border-color: #ccc !important;
    border-width: 2px !important;
}
.service-tech-checkbox:checked {
    background-color: var(--accent-color) !important;
    border-color: var(--accent-color) !important;
}
.service-tech-checkbox:disabled {
    opacity: 0.35;
}
.service-tech-service-col {
    width: 320px;
    min-width: 320px;
}
.users-table tbody tr.table-light th {
    padding: var(--spacing-md) var(--spacing-lg);
    border-bottom: 1px solid var(--border-color-light);
    vertical-align: middle;
    background-color: #f3f4f6;
}
.service-tech-category-row th {
    background-color: #f3f4f6 !important;
}
.extra-tech-service-row td {
    background-color: #fafafa;
}
.service-tech-select-all-wrap {
    margin-top: 0.5rem;
    display: flex;
    justify-content: center;
}
.service-tech-select-all-wrap .form-check-input {
    width: 1.75rem !important;
    height: 1.75rem !important;
    margin: 0;
    border-color: #ccc !important;
    border-width: 2px !important;
    cursor: pointer;
}
.service-tech-select-all-wrap .form-check-input:checked {
    background-color: var(--accent-color) !important;
    border-color: var(--accent-color) !important;
}
.service-tech-select-all-wrap .form-check-input:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
</style>
@endsection

@section('content')

<div class="card">
    <div class="users-toolbar">
        <div class="users-toolbar-left d-flex align-items-center gap-2 flex-wrap min-w-0">
            @include('partials.store-context-filter', [
                'selected' => $matrixStoreId ?? current_store_id(),
                'allowAll' => false,
                'inputId' => 'extras_equipa_store_filter',
            ])
            @php
                $showMatrixStoreLabel = ! (($canChooseStoreContext ?? false) && ($selectableStores ?? collect())->count() > 1);
                $hasExtraRows = $categories->contains(fn ($category) => $category->services->contains(fn ($service) => $service->extras->isNotEmpty()));
            @endphp
            @if ($showMatrixStoreLabel && ! empty($matrixStore?->name))
                <span class="text-muted small text-nowrap">Loja: <strong class="text-body">{{ $matrixStore->name }}</strong></span>
            @endif
        </div>
    </div>
    <div class="users-table-wrap">
        @if($agents->isEmpty())
            <div class="p-4 text-center text-muted">
                <p class="mb-2">Não há técnicos ou prestadores de serviços nesta loja.</p>
                <p class="small mb-0">Adicione membros com o perfil adequado em <a href="{{ route('equipa.index') }}">Equipa</a>.</p>
            </div>
        @elseif(! $hasExtraRows)
            <div class="p-4 text-center text-muted">
                <p class="mb-0">Ainda não há extras associados a serviços. Liga extras aos serviços em <a href="{{ route('extras.index') }}">Extras</a>.</p>
            </div>
        @else
            <form id="extras-matrix-form" action="{{ route('services.extras-equipa.sync') }}" method="post">
                @csrf
                <input type="hidden" name="{{ \App\Support\StoreContextPreference::QUERY_STORE }}" value="{{ $matrixStoreId }}">
                <table class="users-table">
                    <tbody>
                        @foreach($categories as $category)
                            @php
                                $servicesWithExtras = $category->services->filter(fn ($service) => $service->extras->isNotEmpty());
                            @endphp
                            @if($servicesWithExtras->isEmpty())
                                @continue
                            @endif
                            <tr class="table-light service-tech-category-row">
                                <th scope="col" class="service-tech-service-col">
                                    <span class="contacts-group-dot d-inline-block rounded-circle align-middle me-2" style="width:8px;height:8px;background:{{ $category->color ?? 'var(--bs-secondary)' }};"></span>
                                    <span>{{ $category->name }}</span>
                                </th>
                                @foreach($agents as $agent)
                                    @php
                                        $nameParts = preg_split('/\s+/u', trim((string) $agent->name));
                                        $nameParts = array_values(array_filter($nameParts, fn ($part) => $part !== ''));
                                        if (count($nameParts) >= 2) {
                                            $avatarInitial = mb_strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8').mb_substr($nameParts[count($nameParts) - 1], 0, 1, 'UTF-8'), 'UTF-8');
                                        } elseif (count($nameParts) === 1) {
                                            $avatarInitial = mb_strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'), 'UTF-8');
                                        } else {
                                            $avatarInitial = '—';
                                        }
                                        $avatarSrc = $agent->avatar ? asset('storage/'.$agent->avatar) : null;
                                    @endphp
                                    <th scope="col" class="text-center" title="{{ $agent->name }}">
                                        <span class="d-inline-flex align-items-center gap-2">
                                            @if($avatarSrc)
                                                <img src="{{ $avatarSrc }}" alt="{{ $agent->name }}" class="rounded-circle" style="width:24px;height:24px;object-fit:cover;">
                                            @else
                                                <span class="users-cell-avatar-initial" style="width:24px;height:24px;font-size:0.625rem;">{{ $avatarInitial }}</span>
                                            @endif
                                            <span>{{ $agent->name }}</span>
                                        </span>
                                    </th>
                                @endforeach
                            </tr>
                            @foreach($servicesWithExtras as $service)
                                @php
                                    $serviceAgentIds = $service->agents->pluck('id')->map(fn ($id) => (int) $id)->all();
                                @endphp
                                <tr class="extra-tech-service-row">
                                    <td class="service-tech-service-col">
                                        <span class="d-block fw-semibold">{{ $service->name }}</span>
                                        <span class="text-muted small">Todos os extras</span>
                                    </td>
                                    @foreach($agents as $agent)
                                        @php
                                            $agentDoesService = in_array((int) $agent->id, $serviceAgentIds, true);
                                        @endphp
                                        <td class="text-center">
                                            <div class="service-tech-select-all-wrap">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input extra-tech-service-select-all"
                                                    data-service-id="{{ $service->id }}"
                                                    data-agent-id="{{ $agent->id }}"
                                                    @disabled(! $agentDoesService)
                                                    aria-label="Seleccionar todos os extras de {{ $service->name }} para {{ $agent->name }}"
                                                >
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                                @foreach($service->extras as $extra)
                                    <tr>
                                        <td class="service-tech-service-col">
                                            <span class="d-block ps-3">{{ $extra->name }}</span>
                                        </td>
                                        @foreach($agents as $agent)
                                            @php
                                                $agentDoesService = in_array((int) $agent->id, $serviceAgentIds, true);
                                                $isAssigned = isset($assigned[(int) $service->id.'-'.(int) $extra->id.'-'.(int) $agent->id]);
                                            @endphp
                                            <td class="text-center">
                                                <div class="form-check d-flex justify-content-center mb-0">
                                                    <input
                                                        class="form-check-input service-tech-checkbox extra-tech-checkbox"
                                                        type="checkbox"
                                                        name="assignments[{{ $service->id }}][{{ $extra->id }}][]"
                                                        value="{{ $agent->id }}"
                                                        id="e{{ $service->id }}x{{ $extra->id }}a{{ $agent->id }}"
                                                        data-service-id="{{ $service->id }}"
                                                        data-agent-id="{{ $agent->id }}"
                                                        @checked($agentDoesService && $isAssigned)
                                                        @disabled(! $agentDoesService)
                                                        aria-label="{{ $extra->name }} em {{ $service->name }} — {{ $agent->name }}"
                                                        @if(! $agentDoesService) title="Este técnico não faz {{ $service->name }}" @endif
                                                    >
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
                <div class="users-pagination">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-floppy-disk me-1"></i> Guardar alterações
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('extras-matrix-form');
    if (!form) {
        return;
    }

    function extraCheckboxes(serviceId, agentId) {
        return form.querySelectorAll(
            '.extra-tech-checkbox[data-service-id="' + serviceId + '"][data-agent-id="' + agentId + '"]:not(:disabled)'
        );
    }

    function updateServiceSelectAll(serviceId, agentId) {
        var boxes = extraCheckboxes(serviceId, agentId);
        var selectAll = form.querySelector(
            '.extra-tech-service-select-all[data-service-id="' + serviceId + '"][data-agent-id="' + agentId + '"]'
        );
        if (!selectAll || selectAll.disabled || boxes.length === 0) {
            return;
        }

        var checkedCount = Array.from(boxes).filter(function(cb) { return cb.checked; }).length;
        selectAll.checked = checkedCount === boxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    }

    form.querySelectorAll('.extra-tech-service-select-all').forEach(function(selectAll) {
        selectAll.addEventListener('change', function() {
            if (selectAll.disabled) {
                return;
            }
            var serviceId = this.getAttribute('data-service-id');
            var agentId = this.getAttribute('data-agent-id');
            extraCheckboxes(serviceId, agentId).forEach(function(cb) {
                cb.checked = selectAll.checked;
            });
            selectAll.indeterminate = false;
        });

        updateServiceSelectAll(
            selectAll.getAttribute('data-service-id'),
            selectAll.getAttribute('data-agent-id')
        );
    });

    form.querySelectorAll('.extra-tech-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            updateServiceSelectAll(
                this.getAttribute('data-service-id'),
                this.getAttribute('data-agent-id')
            );
        });
    });
});
</script>
@if (session('success'))
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.showToast === 'function') {
        window.showToast(@json(session('success')), 'success');
    }
});
</script>
@endif
@endsection
