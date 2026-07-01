<figure class="ajuda-figure">
    <div class="ajuda-visual-drag" role="img" aria-label="Demonstração de arrastar uma marcação para outro horário">
        <div class="ajuda-visual-drag__slot">
            <div class="ajuda-visual-drag__event">
                <div class="ajuda-visual-event ajuda-visual-event--marcacao">
                    <i class="ri-time-fill" aria-hidden="true"></i>
                    10:00 · Manicure
                </div>
            </div>
            <i class="ph ph-arrows-left-right ajuda-visual-drag__arrow" aria-hidden="true"></i>
            <div class="ajuda-visual-drag__ghost">
                <div class="ajuda-visual-event ajuda-visual-event--marcacao">
                    <i class="ri-time-fill" aria-hidden="true"></i>
                    11:00 · Manicure
                </div>
            </div>
        </div>
        <div class="ajuda-visual-drag__hints">
            <span class="ajuda-visual-drag__hint">
                <i class="ph ph-hand-grabbing"></i> Arrastar para mudar hora ou colaborador
            </span>
            <span class="ajuda-visual-drag__hint">
                <i class="ph ph-arrows-out-line-vertical"></i> Redimensionar para alterar duração
            </span>
            <span class="ajuda-visual-drag__hint">
                <i class="ph ph-cursor-click"></i> Clicar para abrir detalhe
            </span>
        </div>
    </div>
    @if (!empty($caption))
        <figcaption class="ajuda-figure__caption">{{ $caption }}</figcaption>
    @endif
</figure>
