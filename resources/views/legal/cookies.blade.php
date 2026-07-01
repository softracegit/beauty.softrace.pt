@extends('legal.layout')

@section('content')
    <h1>Política de Cookies</h1>
    @include('legal.partials.entity')
    <p class="legal-meta mb-0">Versão {{ $privacyVersion }}</p>

    <h2>1. O que são cookies</h2>
    <p>
        Cookies são pequenos ficheiros armazenados no seu dispositivo quando visita um website.
        São usados para permitir funcionalidades essenciais, manter sessões seguras e, em alguns casos,
        melhorar a experiência de utilização.
    </p>

    <h2>2. Como utilizamos cookies</h2>
    <p>Na Plataforma de marcação online e na área de cliente, utilizamos principalmente:</p>

    <h2>2.1. Cookies estritamente necessários</h2>
    <p>Estes cookies são indispensáveis ao funcionamento do site e não requerem consentimento:</p>
    <ul>
        <li><strong>Sessão da aplicação</strong> — mantém o utilizador autenticado e o estado da marcação em curso;</li>
        <li><strong>Proteção CSRF</strong> — token de segurança em formulários e pedidos;</li>
        <li><strong>Preferências essenciais</strong> — dados técnicos necessários ao fluxo de checkout.</li>
    </ul>

    <h2>2.2. Cookies de análise</h2>
    <p>Com o seu consentimento (aviso no site), utilizamos:</p>
    <ul>
        <li><strong>Google Analytics</strong> — estatísticas de utilização da marcação online (páginas visitadas, origem do tráfego). Pode revogar o consentimento apagando os dados do site no seu browser ou bloqueando cookies de análise nas definições do browser.</li>
    </ul>

    <h2>2.3. Cookies de terceiros (funcionais)</h2>
    <p>Alguns serviços integrados podem definir cookies ou tecnologias semelhantes:</p>
    <ul>
        <li><strong>Stripe</strong> — processamento seguro de pagamentos online;</li>
        <li><strong>CDN de bibliotecas</strong> — carregamento de componentes de interface (ex.: seleção internacional de telefone).</li>
    </ul>
    <p>
        Recomendamos consultar as políticas de privacidade desses prestadores para mais detalhe.
    </p>

    <h2>3. Duração</h2>
    <p>
        Os cookies de sessão expiram quando fecha o browser ou termina a sessão.
        Cookies persistentes, quando utilizados, têm duração limitada ao necessário para a finalidade
        (ex.: manter sessão autenticada durante um período configurado).
    </p>

    <h2>4. Gestão de cookies</h2>
    <p>
        Pode configurar o seu browser para bloquear ou apagar cookies. Note que bloquear cookies necessários
        pode impedir o funcionamento da marcação online, do login por código ou do pagamento.
    </p>

    <h2>5. Mais informação</h2>
    <p>
        Para informação sobre o tratamento de dados pessoais, consulte a
        <a href="{{ route('legal.privacy') }}">Política de Privacidade</a>.
        Para questões: <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
    </p>
@endsection
