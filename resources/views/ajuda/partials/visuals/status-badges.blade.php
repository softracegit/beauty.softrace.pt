<figure class="ajuda-figure">
    <div class="ajuda-visual-statuses">
        <div class="ajuda-visual-status-row">
            <span class="ajuda-visual-status-row__label">Fluxo normal</span>
            <div class="ajuda-visual-status-flow">
                <span class="ajuda-visual-status ajuda-visual-status--agendado"><i class="ri-time-fill"></i> Agendado</span>
                <i class="ph ph-caret-right" aria-hidden="true"></i>
                <span class="ajuda-visual-status ajuda-visual-status--notificado"><i class="ri-notification-3-fill"></i> Notificado</span>
                <i class="ph ph-caret-right" aria-hidden="true"></i>
                <span class="ajuda-visual-status ajuda-visual-status--confirmado"><i class="ri-notification-3-fill"></i> Confirmado</span>
                <i class="ph ph-caret-right" aria-hidden="true"></i>
                <span class="ajuda-visual-status ajuda-visual-status--chegou"><i class="ri-map-pin-fill"></i> Chegou</span>
                <i class="ph ph-caret-right" aria-hidden="true"></i>
                <span class="ajuda-visual-status ajuda-visual-status--iniciado"><i class="ri-play-fill"></i> Iniciado</span>
                <i class="ph ph-caret-right" aria-hidden="true"></i>
                <span class="ajuda-visual-status ajuda-visual-status--terminado"><i class="ri-checkbox-circle-fill"></i> Terminado</span>
                <i class="ph ph-caret-right" aria-hidden="true"></i>
                <span class="ajuda-visual-status ajuda-visual-status--pago"><i class="ri-checkbox-circle-fill"></i> Pago</span>
            </div>
        </div>
        <div class="ajuda-visual-status-row">
            <span class="ajuda-visual-status-row__label">Estados finais</span>
            <span class="ajuda-visual-status ajuda-visual-status--faltou"><i class="ri-forbid-fill"></i> Faltou</span>
            <span class="ajuda-visual-status ajuda-visual-status--cancelado"><i class="ri-close-circle-fill"></i> Cancelado</span>
            <span class="ajuda-visual-status ajuda-visual-status--anulado"><i class="ri-close-circle-fill"></i> Anulado</span>
        </div>
    </div>
    <figcaption class="ajuda-figure__caption">
        {{ $caption ?? 'Cores e ícones semelhantes aos usados na agenda real ao alterar o estado de uma marcação.' }}
    </figcaption>
</figure>
