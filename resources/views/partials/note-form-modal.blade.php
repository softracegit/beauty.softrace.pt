@php
    $route = $route ?? '#';
    $modelName = $modelName ?? 'item';
@endphp

<!-- Modal para adicionar nota -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Nota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addNoteForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="noteType" class="form-label">Tipo de Nota <span class="text-danger">*</span></label>
                            <select name="type" id="noteType" class="form-select" required>
                                @foreach(\App\Models\Note::types() as $value => $label)
                                    <option value="{{ $value }}" {{ $value === 'geral' ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">
                                @foreach(\App\Models\Note::types() as $value => $label)
                                    <i class="{{ \App\Models\Note::getIconForType($value) }} {{ \App\Models\Note::getColorForType($value) }}"></i> {{ $label }}
                                    @if(!$loop->last) | @endif
                                @endforeach
                            </small>
                        </div>
                        <div class="col-12">
                            <label for="noteText" class="form-label">Nota <span class="text-danger">*</span></label>
                            <textarea name="note" id="noteText" class="form-control" rows="4" required placeholder="Digite sua nota aqui..."></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="hasReminder">
                                <label class="form-check-label" for="hasReminder">
                                    Adicionar lembrete
                                </label>
                            </div>
                        </div>
                        <div id="reminderFields" class="col-12" style="display: none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="reminderAt" class="form-label">Data e Hora do Lembrete</label>
                                    <input type="datetime-local" name="reminder_at" id="reminderAt" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label for="reminderAdvance" class="form-label">Antecedência (minutos)</label>
                                    <input type="number" name="reminder_advance_minutes" id="reminderAdvance" class="form-control" value="15" min="0" step="5">
                                    <small class="text-muted">Tempo antes do evento para receber o lembrete</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-floppy-disk me-1"></i> Adicionar Nota
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const checkbox = document.getElementById('hasReminder');
        const fields = document.getElementById('reminderFields');
        const reminderAt = document.getElementById('reminderAt');
        
        if (checkbox && fields && reminderAt) {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    fields.style.display = 'block';
                    reminderAt.required = true;
                } else {
                    fields.style.display = 'none';
                    reminderAt.required = false;
                    reminderAt.value = '';
                }
            });
        }
    })();

    document.getElementById('addNoteForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            note: document.getElementById('noteText').value,
            type: document.getElementById('noteType').value,
        };
        
        if (document.getElementById('hasReminder').checked) {
            formData.reminder_at = document.getElementById('reminderAt').value;
            formData.reminder_advance_minutes = document.getElementById('reminderAdvance').value || 15;
        }

        fetch('{{ $route }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao adicionar nota: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erro ao adicionar nota');
        });
    });
</script>
