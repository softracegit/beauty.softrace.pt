@extends('partials.layouts.main')
@section('title', 'Editar extra | ' . config('app.name'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Editar extra</h5>
        <a href="{{ route('extras.index') }}" class="btn btn-outline-secondary btn-sm"><i class="ph ph-arrow-left me-1"></i>Voltar</a>
    </div>
    <div class="card-body">
        <form id="editExtraForm" action="{{ route('extras.update', $extra) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label">Categoria <span class="text-danger">*</span></label>
                <select class="form-select" name="extra_category_id" required>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ $extra->extra_category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" value="{{ old('name', $extra->name) }}" required maxlength="255">
            </div>
            <div class="mb-3">
                <label class="form-label">Descrição</label>
                <textarea class="form-control" name="description" rows="3">{{ old('description', $extra->description) }}</textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Preço (€) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="price" step="0.01" min="0" value="{{ old('price', $extra->price) }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Duração (minutos) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="duration" min="0" value="{{ old('duration', $extra->duration) }}" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Associar a serviços</label>
                <div class="border rounded p-3" style="max-height: 220px; overflow-y: auto;">
                    @php $extraServiceIds = $extra->services->pluck('id')->toArray(); @endphp
                    @foreach($services->groupBy(fn($s) => $s->category?->name ?? 'Outros') as $catName => $svcs)
                        <div class="mb-2">
                            <span class="small fw-semibold text-muted">{{ $catName }}</span>
                            @foreach($svcs as $s)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="service_ids[]" value="{{ $s->id }}" id="s{{ $s->id }}" {{ in_array($s->id, $extraServiceIds) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="s{{ $s->id }}">{{ $s->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" id="submitBtn">Guardar</button>
                <a href="{{ route('extras.index') }}" class="btn btn-light">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('editExtraForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    var formData = new FormData(this);
    var serviceIds = [];
    this.querySelectorAll('input[name="service_ids[]"]:checked').forEach(function(cb) { serviceIds.push(cb.value); });
    formData.delete('service_ids[]');
    serviceIds.forEach(function(id) { formData.append('service_ids[]', id); });
    fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) window.location.href = '{{ route('extras.index') }}';
        else { btn.disabled = false; alert(data.message || 'Erro ao guardar.'); }
    }).catch(function() { btn.disabled = false; alert('Erro ao guardar.'); });
});
</script>
@endsection
