<figure class="ajuda-figure">
    <div class="ajuda-visual-grid" role="img" aria-label="Vista dia da agenda com colunas por colaborador">
        <table class="ajuda-visual-grid__table">
            <thead>
                <tr>
                    <th class="ajuda-visual-grid__time"></th>
                    <th class="ajuda-visual-grid__head">Ana</th>
                    <th class="ajuda-visual-grid__head">Sofia</th>
                    <th class="ajuda-visual-grid__head">Maria</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="ajuda-visual-grid__time">10:00</td>
                    <td class="ajuda-visual-grid__cell">
                        <div class="ajuda-visual-event ajuda-visual-event--marcacao">
                            <i class="ri-time-fill" aria-hidden="true"></i>
                            Manicure
                        </div>
                    </td>
                    <td class="ajuda-visual-grid__cell"></td>
                    <td class="ajuda-visual-grid__cell">
                        <div class="ajuda-visual-event ajuda-visual-event--online">
                            <i class="ri-global-line" aria-hidden="true"></i>
                            Online
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="ajuda-visual-grid__time">10:30</td>
                    <td class="ajuda-visual-grid__cell ajuda-visual-grid__now"></td>
                    <td class="ajuda-visual-grid__cell">
                        <div class="ajuda-visual-event ajuda-visual-event--pessoal">Almoço</div>
                    </td>
                    <td class="ajuda-visual-grid__cell"></td>
                </tr>
                <tr>
                    <td class="ajuda-visual-grid__time">11:00</td>
                    <td class="ajuda-visual-grid__cell"></td>
                    <td class="ajuda-visual-grid__cell">
                        <div class="ajuda-visual-event ajuda-visual-event--marcacao ajuda-visual-event--tall">
                            <i class="ri-time-fill" aria-hidden="true"></i>
                            Coloração
                        </div>
                    </td>
                    <td class="ajuda-visual-grid__cell"></td>
                </tr>
            </tbody>
        </table>
    </div>
    <figcaption class="ajuda-figure__caption">
        {{ $caption ?? 'Cada coluna é um colaborador; as linhas são horários. A linha vermelha indica a hora actual.' }}
    </figcaption>
</figure>
