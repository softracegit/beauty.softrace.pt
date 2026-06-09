<div class="modal fade" id="caixaOpenModal" tabindex="-1" aria-labelledby="caixaOpenModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="{{ route('caixa.open') }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="caixaOpenModalLabel">Abrir caixa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">Indique o dinheiro inicial na gaveta (fundo de maneio).</p>
          <div class="mb-0">
            <label for="caixaOpeningFloat" class="form-label">Dinheiro na caixa (€)</label>
            <input
              type="number"
              step="0.01"
              min="0"
              class="form-control @error('opening_float') is-invalid @enderror"
              id="caixaOpeningFloat"
              name="opening_float"
              value="{{ old('opening_float', '0.00') }}"
              required
              autofocus
            >
            @error('opening_float')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">Abrir caixa</button>
        </div>
      </form>
    </div>
  </div>
</div>
