<style>
  .client-tag-settings-chip {
    display: inline-block;
    padding: 0.12rem 0.55rem;
    border-radius: 50rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: #1e40af;
    background: rgba(219, 234, 254, 0.35);
    border: 1px dashed #93c5fd;
  }
</style>

<div class="card" id="etiquetas-clientes">
  <div class="card-header">
    <h5 class="card-title mb-0">Etiquetas</h5>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-4">
      Classifique clientes (ex.: turista, estudante). As etiquetas podem ser associadas na listagem, ficha ou agenda.
    </p>

    <div id="clientTagCreateForm" class="row g-2 align-items-end mb-4">
      <div class="col-md-8">
        <label class="form-label small mb-1" for="newTagName">Nova etiqueta</label>
        <input type="text" class="form-control form-control-sm" id="newTagName" maxlength="80" placeholder="Ex.: Turista" required>
      </div>
      <div class="col-md-4">
        <button type="button" class="btn btn-primary btn-sm w-100" id="clientTagCreateSubmit">
          <i class="ph ph-plus me-1"></i> Adicionar
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle" id="clientTagsTable">
        <thead>
          <tr>
            <th>Etiqueta</th>
            <th class="text-center">Clientes</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($clientTags as $tag)
            <tr data-tag-id="{{ $tag->id }}">
              <td>
                <span class="client-tag-settings-chip client-tag-name-display">{{ $tag->name }}</span>
                <input type="text" class="form-control form-control-sm d-none client-tag-name-input" value="{{ $tag->name }}" maxlength="80">
              </td>
              <td class="text-center text-muted">{{ $tag->clients_count ?? 0 }}</td>
              <td class="text-end text-nowrap">
                <button type="button" class="btn btn-sm btn-light client-tag-save-btn d-none" title="Guardar"><i class="ph ph-check"></i></button>
                <button type="button" class="btn btn-sm btn-light client-tag-edit-btn" title="Editar nome"><i class="ph ph-pencil-simple"></i></button>
                <button type="button" class="btn btn-sm btn-light text-danger client-tag-delete-btn" title="Eliminar" @disabled(($tag->clients_count ?? 0) > 0)><i class="ph ph-trash"></i></button>
              </td>
            </tr>
          @empty
            <tr id="clientTagsEmptyRow">
              <td colspan="3" class="text-center text-muted py-4">Ainda não existem etiquetas.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  var storeUrl = @json(route('client-tags.store'));
  var updateUrlTemplate = @json(url('client-tags/__ID__'));

  function toast(msg, type) {
    if (typeof window.showToast === 'function') window.showToast(msg, type || 'success');
    else if (type === 'error') alert(msg);
  }

  function apiError(data, fallback) {
    if (data && data.message) return data.message;
    if (data && data.errors) {
      var vals = Object.values(data.errors);
      if (vals.length && Array.isArray(vals[0])) return vals[0][0];
    }
    return fallback;
  }

  function updateUrl(id) { return updateUrlTemplate.replace('__ID__', String(id)); }
  function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }

  function updateNameChip(tr, tag) {
    var chip = tr.querySelector('.client-tag-name-display');
    if (!chip) return;
    chip.textContent = tag.name;
  }

  function bindRow(tr) {
    var id = tr.getAttribute('data-tag-id');
    var nameDisplay = tr.querySelector('.client-tag-name-display');
    var nameInput = tr.querySelector('.client-tag-name-input');
    var saveBtn = tr.querySelector('.client-tag-save-btn');
    var editBtn = tr.querySelector('.client-tag-edit-btn');
    var deleteBtn = tr.querySelector('.client-tag-delete-btn');

    function setEditing(on) {
      nameDisplay.classList.toggle('d-none', on);
      nameInput.classList.toggle('d-none', !on);
      saveBtn.classList.toggle('d-none', !on);
    }

    editBtn.addEventListener('click', function () { setEditing(true); nameInput.focus(); nameInput.select(); });
    saveBtn.addEventListener('click', function () { saveTag(tr, id); });
    nameInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); saveTag(tr, id); }
      if (e.key === 'Escape') { nameInput.value = nameDisplay.textContent; setEditing(false); }
    });
    deleteBtn.addEventListener('click', function () {
      if (!confirm('Eliminar esta etiqueta?')) return;
      fetch(updateUrl(id), { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(apiError(d, 'Erro.')); }); })
        .then(function () {
          tr.remove();
          if (!document.querySelector('#clientTagsTable tbody tr[data-tag-id]')) {
            document.querySelector('#clientTagsTable tbody').innerHTML = '<tr id="clientTagsEmptyRow"><td colspan="3" class="text-center text-muted py-4">Ainda não existem etiquetas.</td></tr>';
          }
          if (window.ClientTags) window.ClientTags.invalidateCatalog();
          toast('Etiqueta eliminada.', 'success');
        })
        .catch(function (err) { toast(err.message, 'error'); });
    });
  }

  function saveTag(tr, id) {
    var nameInput = tr.querySelector('.client-tag-name-input');
    var nameDisplay = tr.querySelector('.client-tag-name-display');
    var saveBtn = tr.querySelector('.client-tag-save-btn');
    fetch(updateUrl(id), {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ name: nameInput.value.trim() })
    }).then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(apiError(d, 'Erro.')); return d; }); })
      .then(function (data) {
        updateNameChip(tr, data.tag);
        nameInput.value = data.tag.name;
        nameDisplay.classList.remove('d-none');
        nameInput.classList.add('d-none');
        saveBtn.classList.add('d-none');
        if (window.ClientTags) window.ClientTags.invalidateCatalog();
        toast('Etiqueta atualizada.', 'success');
      })
      .catch(function (err) { toast(err.message, 'error'); });
  }

  function createRow(tag) {
    var tr = document.createElement('tr');
    tr.setAttribute('data-tag-id', String(tag.id));
    tr.innerHTML =
      '<td><span class="client-tag-settings-chip client-tag-name-display">' + escapeHtml(tag.name) + '</span>' +
      '<input type="text" class="form-control form-control-sm d-none client-tag-name-input" value="' + escapeHtml(tag.name) + '" maxlength="80"></td>' +
      '<td class="text-center text-muted">0</td>' +
      '<td class="text-end text-nowrap">' +
      '<button type="button" class="btn btn-sm btn-light client-tag-save-btn d-none"><i class="ph ph-check"></i></button>' +
      '<button type="button" class="btn btn-sm btn-light client-tag-edit-btn"><i class="ph ph-pencil-simple"></i></button>' +
      '<button type="button" class="btn btn-sm btn-light text-danger client-tag-delete-btn"><i class="ph ph-trash"></i></button></td>';
    bindRow(tr);
    return tr;
  }

  document.querySelectorAll('#clientTagsTable tbody tr[data-tag-id]').forEach(bindRow);

  var createForm = document.getElementById('clientTagCreateForm');
  var createSubmit = document.getElementById('clientTagCreateSubmit');
  var newTagNameInput = document.getElementById('newTagName');

  function submitNewTag() {
    var name = newTagNameInput ? newTagNameInput.value.trim() : '';
    if (!name) return;
    fetch(storeUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ name: name })
    }).then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(apiError(d, 'Erro.')); return d; }); })
      .then(function (data) {
        document.getElementById('clientTagsEmptyRow')?.remove();
        document.querySelector('#clientTagsTable tbody').appendChild(createRow(data.tag));
        if (newTagNameInput) newTagNameInput.value = '';
        if (window.ClientTags) window.ClientTags.invalidateCatalog();
        toast('Etiqueta criada.', 'success');
      })
      .catch(function (err) { toast(err.message, 'error'); });
  }

  if (createSubmit) {
    createSubmit.addEventListener('click', submitNewTag);
  }
  if (createForm && newTagNameInput) {
    newTagNameInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitNewTag();
      }
    });
  }
})();
</script>
