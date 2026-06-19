@extends('partials.layouts.main')
@section('title', 'Serviços — Técnicos | Beauty CRM')

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
</style>
@endsection

@section('content')

<div class="card">
    <div class="users-table-wrap">
        @if(!$agents->isEmpty() && $categories->isNotEmpty() && $categories->sum(fn ($c) => $c->services->count()) > 0)
            <p class="text-muted small px-3 pt-3 mb-0">As marcações aplicam-se ao <strong>serviço</strong> (título na lista). Se o serviço tiver variantes, a mesma equipa técnica aplica-se a todas as opções.</p>
        @endif
        @if($agents->isEmpty())
            <div class="p-4 text-center text-muted">
                <p class="mb-2">Não há técnicos ou prestadores de serviços na equipa.</p>
                <p class="small mb-0">Adicione membros com o perfil adequado em <a href="{{ route('equipa.index') }}">Equipa</a>.</p>
            </div>
        @elseif($categories->isEmpty() || $categories->sum(fn ($c) => $c->services->count()) === 0)
            <div class="p-4 text-center text-muted">
                <p class="mb-0">Ainda não existem serviços. Crie categorias e serviços em <a href="{{ route('services.index') }}">Serviços</a>.</p>
            </div>
        @else
            <form id="services-matrix-form" action="{{ route('services.tecnicos.sync') }}" method="post">
                @csrf
                <table class="users-table">
                    <tbody>
                        @foreach($categories as $category)
                            @if($category->services->isEmpty())
                                @continue
                            @endif
                            <tr class="table-light service-tech-category-row" data-category-id="{{ $category->id }}">
                                <th scope="col" class="service-tech-service-col">
                                    <span class="contacts-group-dot d-inline-block rounded-circle align-middle me-2" style="width:8px;height:8px;background:{{ $category->color ?? 'var(--bs-secondary)' }};"></span>
                                    <span>{{ $category->name }} ({{ $category->services->count() }})</span>
                                </th>
                                @foreach($agents as $agent)
                                    @php
                                        $nameParts = preg_split('/\s+/u', trim((string) $agent->name));
                                        $nameParts = array_values(array_filter($nameParts, fn ($part) => $part !== ''));
                                        if (count($nameParts) >= 2) {
                                            $firstInitial = mb_substr($nameParts[0], 0, 1, 'UTF-8');
                                            $lastInitial = mb_substr($nameParts[count($nameParts) - 1], 0, 1, 'UTF-8');
                                            $avatarInitial = mb_strtoupper($firstInitial . $lastInitial, 'UTF-8');
                                        } elseif (count($nameParts) === 1) {
                                            $avatarInitial = mb_strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'), 'UTF-8');
                                        } else {
                                            $avatarInitial = '—';
                                        }
                                        $avatarSrc = $agent->avatar ? asset('storage/' . $agent->avatar) : null;
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
                                        <div class="service-tech-select-all-wrap">
                                            <input
                                                type="checkbox"
                                                class="form-check-input service-tech-category-select-all"
                                                data-category-id="{{ $category->id }}"
                                                data-agent-id="{{ $agent->id }}"
                                                aria-label="Seleccionar todos os serviços de {{ $category->name }} para {{ $agent->name }}"
                                            >
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                            @foreach($category->services as $service)
                                @php
                                    $assignedIds = $service->agents->pluck('id')->all();
                                @endphp
                                <tr>
                                    <td class="service-tech-service-col">
                                        <span class="d-block fw-semibold">{{ $service->name }}</span>
                                    </td>
                                    @foreach($agents as $agent)
                                        <td class="text-center">
                                            <div class="form-check d-flex justify-content-center mb-0">
                                                <input
                                                    class="form-check-input service-tech-checkbox"
                                                    type="checkbox"
                                                    name="assignments[{{ $service->id }}][]"
                                                    value="{{ $agent->id }}"
                                                    id="m{{ $service->id }}a{{ $agent->id }}"
                                                    data-category-id="{{ $category->id }}"
                                                    data-agent-id="{{ $agent->id }}"
                                                    @checked(in_array($agent->id, $assignedIds, true))
                                                    aria-label="{{ $service->name }} — {{ $agent->name }}"
                                                >
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
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
    var form = document.getElementById('services-matrix-form');
    if (!form) {
        return;
    }

    function serviceCheckboxes(categoryId, agentId) {
        return form.querySelectorAll(
            '.service-tech-checkbox[data-category-id="' + categoryId + '"][data-agent-id="' + agentId + '"]'
        );
    }

    function updateCategorySelectAll(categoryId, agentId) {
        var boxes = serviceCheckboxes(categoryId, agentId);
        var selectAll = form.querySelector(
            '.service-tech-category-select-all[data-category-id="' + categoryId + '"][data-agent-id="' + agentId + '"]'
        );
        if (!selectAll || boxes.length === 0) {
            return;
        }

        var checkedCount = Array.from(boxes).filter(function(cb) { return cb.checked; }).length;
        selectAll.checked = checkedCount === boxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    }

    form.querySelectorAll('.service-tech-category-select-all').forEach(function(selectAll) {
        selectAll.addEventListener('change', function() {
            var categoryId = this.getAttribute('data-category-id');
            var agentId = this.getAttribute('data-agent-id');
            serviceCheckboxes(categoryId, agentId).forEach(function(cb) {
                cb.checked = selectAll.checked;
            });
            selectAll.indeterminate = false;
        });

        updateCategorySelectAll(
            selectAll.getAttribute('data-category-id'),
            selectAll.getAttribute('data-agent-id')
        );
    });

    form.querySelectorAll('.service-tech-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            updateCategorySelectAll(
                this.getAttribute('data-category-id'),
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

