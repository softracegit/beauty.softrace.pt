<style>
  .relatorio-print-dropdown { min-width: 15rem; }
  .relatorio-print-dropdown .form-check-label { cursor: pointer; }
  .relatorio-pdf-orientation-group {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.85rem;
  }
  .relatorio-pdf-orientation-option {
    flex: 1 1 0;
    min-width: 0;
    min-height: 3.25rem;
    margin: 0;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md, 0.375rem);
    padding: 0.35rem 0.25rem;
    text-align: center;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: border-color 0.15s ease, background-color 0.15s ease;
  }
  .relatorio-pdf-orientation-option:hover {
    border-color: color-mix(in srgb, var(--border-color), var(--accent-color) 35%);
  }
  .relatorio-pdf-orientation-option:has(.js-relatorio-pdf-orientation:checked) {
    border-color: var(--accent-color, #0d6efd);
    background: color-mix(in srgb, var(--accent-color, #0d6efd) 8%, transparent);
  }
  .relatorio-pdf-orientation-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    line-height: 1;
    color: var(--heading-color, #333);
  }
  .relatorio-pdf-orientation-icon--landscape {
    transform: rotate(90deg);
  }
  .relatorio-pdf-orientation-label {
    display: block;
    margin-top: 0.2rem;
    font-size: 0.625rem;
    line-height: 1.2;
    color: var(--muted-color, #666);
  }
</style>
