<figure class="ajuda-figure">
    <div class="ajuda-visual-toolbar" role="img" aria-label="Barra superior da agenda com controlos principais">
        <div class="ajuda-visual-toolbar__group">
            <span class="ajuda-visual-btn">Hoje</span>
            <span class="ajuda-visual-btn ajuda-visual-btn--icon" aria-hidden="true"><i class="ph ph-caret-left"></i></span>
            <span class="ajuda-visual-btn">30 Jun 2026</span>
            <span class="ajuda-visual-btn ajuda-visual-btn--icon" aria-hidden="true"><i class="ph ph-caret-right"></i></span>
            <span class="ajuda-visual-btn ajuda-visual-btn--icon" aria-hidden="true"><i class="ph ph-arrows-clockwise"></i></span>
        </div>
        <div class="ajuda-visual-toolbar__group">
            <span class="ajuda-visual-btn">Toda a equipa <i class="ph ph-caret-down"></i></span>
            <span class="ajuda-visual-btn">Dia <i class="ph ph-caret-down"></i></span>
            <span class="ajuda-visual-btn ajuda-visual-btn--primary">Adicionar <i class="ph ph-caret-down"></i></span>
        </div>
    </div>
    <div class="ajuda-visual-toolbar__legend">
        <div class="ajuda-visual-callout">
            <span class="ajuda-visual-callout__num">1</span>
            <span><strong>Hoje / data / setas</strong> — navegar entre dias</span>
        </div>
        <div class="ajuda-visual-callout">
            <span class="ajuda-visual-callout__num">2</span>
            <span><strong>Atualizar</strong> — recarregar eventos</span>
        </div>
        <div class="ajuda-visual-callout">
            <span class="ajuda-visual-callout__num">3</span>
            <span><strong>Toda a equipa</strong> — filtrar colaboradores</span>
        </div>
        <div class="ajuda-visual-callout">
            <span class="ajuda-visual-callout__num">4</span>
            <span><strong>Vista</strong> — Dia, Semana, 3 dias ou Mês</span>
        </div>
        <div class="ajuda-visual-callout">
            <span class="ajuda-visual-callout__num">5</span>
            <span><strong>Adicionar</strong> — nova marcação ou tempo pessoal</span>
        </div>
    </div>
    @if (!empty($caption))
        <figcaption class="ajuda-figure__caption">{{ $caption }}</figcaption>
    @endif
</figure>
