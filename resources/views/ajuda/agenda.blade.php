@extends('ajuda.layout')

@section('ajuda_content')
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h5 class="card-title mb-1">Agenda</h5>
            <p class="text-muted small mb-0">Guia de utilização da agenda e marcações.</p>
        </div>
        <a href="{{ route('agenda.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="ph ph-arrow-square-out me-1"></i> Abrir agenda
        </a>
    </div>
    <div class="card-body">
        <nav class="ajuda-toc mb-4" aria-label="Nesta página">
            <p class="fw-semibold small mb-2">Nesta página</p>
            <ul class="small">
                <li><a href="#visao-geral">Visão geral</a></li>
                <li><a href="#navegacao">Navegação e vistas</a></li>
                <li><a href="#criar-marcacao">Criar marcação</a></li>
                <li><a href="#tempo-pessoal">Tempo pessoal</a></li>
                <li><a href="#editar-mover">Editar e mover</a></li>
                <li><a href="#estados">Estados da marcação</a></li>
                <li><a href="#pagamento">Pagamento e checkout</a></li>
                <li><a href="#cancelamento">Cancelamento</a></li>
                <li><a href="#faqs">Perguntas frequentes</a></li>
            </ul>
        </nav>

        <section id="visao-geral" class="ajuda-section">
            <h2>Visão geral</h2>
            <p>
                A agenda mostra as marcações da equipa organizadas por colaborador (colunas) e por hora (linhas).
                Marcações feitas online aparecem automaticamente; também pode criar marcações manualmente a partir do CRM.
            </p>
            <ul>
                <li><strong>Receção e administradores</strong> — veem toda a equipa activa e visível na agenda.</li>
                <li><strong>Prestadores</strong> — veem apenas a própria coluna e as respectivas marcações.</li>
            </ul>
            @include('ajuda.partials.visuals.grid')
        </section>

        <section id="navegacao" class="ajuda-section">
            <h2>Navegação e vistas</h2>
            <p>Na barra superior da agenda encontra os controlos principais:</p>
            @include('ajuda.partials.visuals.toolbar')
            <p class="mt-3 mb-0">
                A vista <strong>Dia</strong> é a principal para o dia-a-dia: cada coluna corresponde a um membro da equipa,
                ordenado conforme definido em <em>Definições → Equipa</em>. Use o selector de vista para alternar para Semana, 3 dias ou Mês.
            </p>
        </section>

        <section id="criar-marcacao" class="ajuda-section">
            <h2>Criar marcação</h2>
            <p>Pode criar uma marcação de três formas:</p>
            @include('ajuda.partials.visuals.step-cards', ['steps' => [
                [
                    'icon' => 'ph ph-plus-circle',
                    'title' => 'Botão Adicionar',
                    'text' => 'Na barra superior, escolha Adicionar → Nova marcação.',
                ],
                [
                    'icon' => 'ph ph-cursor-click',
                    'title' => 'Clique no slot',
                    'text' => 'Clique num horário vazio na grelha e seleccione Nova marcação.',
                ],
                [
                    'icon' => 'ph ph-sidebar-simple',
                    'title' => 'Menu lateral',
                    'text' => 'Agenda → Nova marcação (excepto para prestadores).',
                ],
            ]])
            <p class="mb-0">No formulário, seleccione o cliente (existente ou novo), o(s) serviço(s), o colaborador e a data/hora. A duração é calculada com base nos serviços escolhidos.</p>
        </section>

        <section id="tempo-pessoal" class="ajuda-section">
            <h2>Tempo pessoal</h2>
            <p>
                Use o tempo pessoal para bloquear horários que não estão disponíveis para clientes — almoço, formação,
                reuniões ou outros compromissos.
            </p>
            @include('ajuda.partials.visuals.event-types')
            <p class="mb-0">
                Crie via <strong>Adicionar → Tempo pessoal</strong> ou clique num slot vazio e escolha essa opção.
            </p>
        </section>

        <section id="editar-mover" class="ajuda-section">
            <h2>Editar e mover</h2>
            @include('ajuda.partials.visuals.drag-demo')
            <p class="mb-0 text-muted small mt-3">
                Nem todas as acções estão disponíveis para todos os perfis ou tipos de evento (por exemplo, marcações online já pagas).
            </p>
        </section>

        <section id="estados" class="ajuda-section">
            <h2>Estados da marcação</h2>
            <p>Cada marcação passa por estados que reflectem o progresso do atendimento:</p>
            @include('ajuda.partials.visuals.status-badges')
            <p class="mb-0">Estados finais que removem a marcação da vista activa da agenda: <strong>Faltou</strong>, <strong>Cancelado</strong> e <strong>Anulado</strong>.</p>
        </section>

        <section id="pagamento" class="ajuda-section">
            <h2>Pagamento e checkout</h2>
            <p>No detalhe da marcação pode registar o pagamento através do <strong>Checkout</strong>:</p>
            <ul>
                <li>Dinheiro, MB WAY, transferência, cartão ou outros métodos configurados.</li>
                <li><strong>Sinal / depósito</strong> — quando a loja exige pagamento antecipado (online ou manual).</li>
                <li><strong>Carteira do cliente</strong> — crédito disponível pode ser usado no pagamento.</li>
            </ul>
            <p class="mb-0 text-muted small">
                Alguns pagamentos podem exigir caixa aberta. Se vir um aviso nesse sentido, abra a caixa antes de continuar.
            </p>
        </section>

        <section id="cancelamento" class="ajuda-section">
            <h2>Cancelamento</h2>
            <p>
                Pode cancelar ou anular uma marcação a partir do painel de detalhe. As regras de reembolso,
                sinal e carteira dependem das definições em <em>Definições → Marcações</em> e da origem da marcação
                (online ou manual).
            </p>
            <p class="mb-0">
                Antes de confirmar, o sistema mostra uma pré-visualização das consequências do cancelamento
                (reembolso, retenção de sinal, etc.), quando aplicável.
            </p>
        </section>

        <section id="faqs" class="ajuda-section">
            <h2>Perguntas frequentes</h2>
            @include('ajuda.partials.faq', ['items' => [
                [
                    'id' => 'faq-colaboradores',
                    'question' => 'Porque não vejo todos os colaboradores na agenda?',
                    'answer' => 'Só aparecem membros da equipa com estado activo e marcados como visíveis na agenda. Verifique em Definições → Equipa o estado e a visibilidade de cada colaborador.',
                ],
                [
                    'id' => 'faq-horario',
                    'question' => 'Posso marcar fora do horário de funcionamento?',
                    'answer' => 'A grelha da agenda reflecte o horário configurado em Definições → Negócio. Slots fora desse horário podem não estar disponíveis ou aparecer desactivados consoante a configuração da loja.',
                ],
                [
                    'id' => 'faq-online',
                    'question' => 'Uma marcação online não aparece. O que fazer?',
                    'answer' => 'Confirme que não foi cancelada ou anulada. Use o botão Atualizar na barra da agenda. Se persistir, verifique em Definições → Marcações se o booking online está activo e se o colaborador/serviço estão disponíveis.',
                ],
                [
                    'id' => 'faq-chegou',
                    'question' => 'Como registo que o cliente chegou?',
                    'answer' => 'Abra a marcação e altere o estado para Chegou. Depois pode avançar para Iniciado, Terminado e finalmente Pago no checkout.',
                ],
                [
                    'id' => 'faq-prestador',
                    'question' => 'Sou prestador — o que posso fazer na agenda?',
                    'answer' => 'Vê apenas a sua coluna. Pode consultar e actualizar as suas marcações, registar estados e efectuar checkout conforme as permissões da loja. Não pode criar marcações para outros colaboradores.',
                ],
            ]])
        </section>
    </div>
</div>
@endsection
