{{-- Modal partilhado: mudar prestador de loja --}}
<div class="modal fade" id="migrateStoreModal" tabindex="-1" aria-labelledby="migrateStoreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="migrateStoreForm" action="#">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="migrateStoreModalLabel">Mudar de loja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        A transferir <strong id="migrateStoreAgentName">—</strong>.
                        O histórico de marcações permanece na loja actual.
                    </p>

                    @if (session('migrate_conflicts'))
                        <div class="alert alert-warning small">
                            <div class="fw-semibold mb-1">Conflitos</div>
                            <ul class="mb-0">
                                @foreach (session('migrate_conflicts') as $msg)
                                    <li>{{ $msg }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if (session('error') && request()->routeIs('equipa.index'))
                        <div class="alert alert-danger small">{{ session('error') }}</div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label" for="migrate_store_id">Loja destino <span class="text-danger">*</span></label>
                        <select name="store_id" id="migrate_store_id" class="form-select" required>
                            <option value="">Seleccione…</option>
                            @foreach ($migrateStores ?? [] as $store)
                                <option value="{{ $store->id }}">{{ $store->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <fieldset class="mb-3">
                        <legend class="form-label mb-2">Marcações futuras</legend>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="future_mode" id="migrate_mode_future"
                                value="{{ \App\Services\MigrateAgentToStoreService::MODE_MIGRATE_FUTURE }}" checked>
                            <label class="form-check-label" for="migrate_mode_future">
                                Migrar as marcações futuras com este membro
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="future_mode" id="migrate_mode_reassign"
                                value="{{ \App\Services\MigrateAgentToStoreService::MODE_REASSIGN }}">
                            <label class="form-check-label" for="migrate_mode_reassign">
                                Deixar as marcações na loja e atribuir a outro membro
                            </label>
                        </div>
                    </fieldset>

                    <div class="mb-0" id="migrateTakeOverWrap" style="display:none">
                        <label class="form-label" for="migrate_take_over_agent_id">Membro que fica com as marcações</label>
                        <select name="take_over_agent_id" id="migrate_take_over_agent_id" class="form-select">
                            <option value="">Seleccione…</option>
                        </select>
                        <div class="form-text" id="migrateTakeOverHint"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Transferir</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('migrateStoreModal');
    if (!modalEl) return;

    const form = document.getElementById('migrateStoreForm');
    const nameEl = document.getElementById('migrateStoreAgentName');
    const takeOverWrap = document.getElementById('migrateTakeOverWrap');
    const takeOverSelect = document.getElementById('migrate_take_over_agent_id');
    const takeOverHint = document.getElementById('migrateTakeOverHint');
    const modeFuture = document.getElementById('migrate_mode_future');
    const modeReassign = document.getElementById('migrate_mode_reassign');
    const candidatesByAgent = @json($migrateCandidatesByAgent ?? []);

    function syncTakeOverVisibility() {
        const reassign = modeReassign.checked;
        takeOverWrap.style.display = reassign ? '' : 'none';
        takeOverSelect.required = reassign && takeOverSelect.options.length > 1;
    }

    function fillCandidates(agentId) {
        const list = candidatesByAgent[String(agentId)] || candidatesByAgent[agentId] || [];
        takeOverSelect.innerHTML = '<option value="">Seleccione…</option>';
        list.forEach(function (c) {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            takeOverSelect.appendChild(opt);
        });
        if (list.length === 0) {
            takeOverHint.textContent = 'Não há outros prestadores nesta loja para ficar com as marcações.';
            takeOverSelect.required = false;
        } else {
            takeOverHint.textContent = '';
        }
        syncTakeOverVisibility();
    }

    modeFuture.addEventListener('change', syncTakeOverVisibility);
    modeReassign.addEventListener('change', syncTakeOverVisibility);

    modalEl.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        const agentId = btn.getAttribute('data-agent-id');
        const agentName = btn.getAttribute('data-agent-name') || '—';
        const action = btn.getAttribute('data-action');
        form.action = action;
        nameEl.textContent = agentName;
        modeFuture.checked = true;
        document.getElementById('migrate_store_id').value = '';
        fillCandidates(agentId);
    });

    form.addEventListener('submit', function (e) {
        if (modeReassign.checked && takeOverSelect.options.length <= 1) {
            e.preventDefault();
            alert('Não há outro prestador nesta loja para ficar com as marcações futuras.');
            return;
        }
        if (!confirm('Confirmar transferência deste membro?')) {
            e.preventDefault();
        }
    });
})();
</script>
@endpush
