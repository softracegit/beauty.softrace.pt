@extends('partials.layouts.main')
@section('title', 'Migrar loja | Beauty CRM')
@section('content')

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('migrate_conflicts'))
    <div class="alert alert-warning">
        <div class="fw-semibold mb-2">Conflitos</div>
        <ul class="mb-0 small">
            @foreach (session('migrate_conflicts') as $msg)
                <li>{{ $msg }}</li>
            @endforeach
        </ul>
        <p class="small mb-0 mt-2">Resolva os conflitos (ou escolha outro membro para as marcações) e tente novamente.</p>
    </div>
@endif

<div class="mb-4">
    <a href="{{ route('equipa.index') }}" class="text-decoration-none small">← Equipa</a>
    <h1 class="h3 mt-2 mb-0">Mudar de loja</h1>
    <p class="text-muted small mb-0">
        <strong>{{ $agente->name }}</strong> —
        loja actual: <strong>{{ $agente->store?->name ?? '—' }}</strong>.
        @if (($futureCount ?? 0) > 0)
            Tem {{ $futureCount }} marcação(ões) futura(s).
        @endif
    </p>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        @if ($stores->isEmpty())
            <p class="text-muted mb-0">Não há outras lojas nesta organização. Crie ou duplique uma loja primeiro.</p>
        @else
            <form method="POST" action="{{ route('equipa.migrate-store', $agente) }}" class="row g-3" id="migrateStorePageForm">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Loja destino <span class="text-danger">*</span></label>
                    <select name="store_id" class="form-select @error('store_id') is-invalid @enderror" required>
                        <option value="">Seleccione…</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected((string) old('store_id') === (string) $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                    @error('store_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <fieldset>
                        <legend class="form-label mb-2">Marcações futuras</legend>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="future_mode" id="page_mode_future"
                                value="{{ \App\Services\MigrateAgentToStoreService::MODE_MIGRATE_FUTURE }}"
                                @checked(old('future_mode', \App\Services\MigrateAgentToStoreService::MODE_MIGRATE_FUTURE) === \App\Services\MigrateAgentToStoreService::MODE_MIGRATE_FUTURE)>
                            <label class="form-check-label" for="page_mode_future">
                                Migrar as marcações futuras com este membro
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="future_mode" id="page_mode_reassign"
                                value="{{ \App\Services\MigrateAgentToStoreService::MODE_REASSIGN }}"
                                @checked(old('future_mode') === \App\Services\MigrateAgentToStoreService::MODE_REASSIGN)>
                            <label class="form-check-label" for="page_mode_reassign">
                                Deixar as marcações na loja e atribuir a outro membro
                            </label>
                        </div>
                    </fieldset>
                </div>

                <div class="col-md-6" id="pageTakeOverWrap" style="{{ old('future_mode') === \App\Services\MigrateAgentToStoreService::MODE_REASSIGN ? '' : 'display:none' }}">
                    <label class="form-label">Membro que fica com as marcações</label>
                    <select name="take_over_agent_id" id="page_take_over_agent_id" class="form-select @error('take_over_agent_id') is-invalid @enderror">
                        <option value="">Seleccione…</option>
                        @foreach ($takeOverCandidates ?? [] as $cand)
                            <option value="{{ $cand->id }}" @selected((string) old('take_over_agent_id') === (string) $cand->id)>{{ $cand->name }}</option>
                        @endforeach
                    </select>
                    @error('take_over_agent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if (($takeOverCandidates ?? collect())->isEmpty())
                        <div class="form-text text-warning">Não há outros prestadores nesta loja.</div>
                    @endif
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Transferir</button>
                    <a href="{{ route('equipa.index') }}" class="btn btn-link">Cancelar</a>
                </div>
            </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function () {
    const future = document.getElementById('page_mode_future');
    const reassign = document.getElementById('page_mode_reassign');
    const wrap = document.getElementById('pageTakeOverWrap');
    const select = document.getElementById('page_take_over_agent_id');
    if (!future || !reassign || !wrap) return;
    function sync() {
        const on = reassign.checked;
        wrap.style.display = on ? '' : 'none';
        if (select) select.required = on && select.options.length > 1;
    }
    future.addEventListener('change', sync);
    reassign.addEventListener('change', sync);
    sync();
    document.getElementById('migrateStorePageForm')?.addEventListener('submit', function (e) {
        if (!confirm('Confirmar transferência deste membro?')) e.preventDefault();
    });
})();
</script>
@endpush
@endsection
