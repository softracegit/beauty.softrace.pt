@extends('legal.layout')

@section('content')
    <h1>Política de Privacidade</h1>
    @include('legal.partials.entity')
    <p class="legal-meta mb-0">Versão {{ $privacyVersion }}</p>

    <h2>1. Responsável pelo tratamento</h2>
    <p>
        O responsável pelo tratamento dos dados pessoais tratados através da Plataforma é
        <strong>{{ $companyName }}</strong>
        @if ($companyNif !== '')
            (NIF {{ $companyNif }})
        @endif
        @if ($companyAddress !== '')
            , com sede em {{ $companyAddress }},
        @endif
        contactável em <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
    </p>
    <p>
        Os estabelecimentos onde o cliente efetua marcações podem tratar dados adicionais no âmbito da prestação
        do serviço no local, como responsáveis autónomos ou co-responsáveis, conforme o caso.
    </p>

    <h2>2. Dados que recolhemos</h2>
    <p>Podemos tratar as seguintes categorias de dados:</p>
    <ul>
        <li><strong>Identificação e contacto:</strong> nome, email, telemóvel, NIF (quando fornecido);</li>
        <li><strong>Conta e autenticação:</strong> códigos de verificação (OTP), data de verificação, endereço IP e user-agent no pedido de código;</li>
        <li><strong>Marcações:</strong> serviços, data/hora, colaborador, observações, estado da marcação;</li>
        <li><strong>Pagamentos:</strong> montantes, estado de pagamento, identificadores de transação; dados de cartão são tratados pelo prestador de pagamentos (tokenização);</li>
        <li><strong>Carteira digital:</strong> saldo e movimentos de crédito/débito associados a cancelamentos ou pagamentos;</li>
        <li><strong>Preferências:</strong> opções de notificação por email e SMS;</li>
        <li><strong>Faturação:</strong> dados necessários à emissão de documentos fiscais, quando aplicável.</li>
    </ul>

    <h2>3. Finalidades e bases legais</h2>
    <ul>
        <li><strong>Gestão de conta e autenticação</strong> — execução de contrato e medidas pré-contratuais;</li>
        <li><strong>Processamento de marcações</strong> — execução de contrato;</li>
        <li><strong>Pagamentos online</strong> — execução de contrato;</li>
        <li><strong>Comunicações transacionais</strong> (confirmações, alterações, lembretes, códigos OTP) — execução de contrato;</li>
        <li><strong>Faturação</strong> — cumprimento de obrigação legal;</li>
        <li><strong>Comunicações de marketing</strong> — consentimento (apenas com opt-in explícito);</li>
        <li><strong>Segurança e prevenção de abuso</strong> (ex.: limites de reenvio de OTP) — interesse legítimo.</li>
    </ul>

    <h2>4. Destinatários e subcontratantes</h2>
    <p>Os dados podem ser comunicados a:</p>
    <ul>
        <li>estabelecimentos parceiros onde o cliente marca serviços;</li>
        <li>prestadores de serviços de pagamento (ex.: Stripe);</li>
        <li>prestadores de envio de SMS (ex.: Twilio);</li>
        <li>prestadores de email;</li>
        <li>sistemas de faturação (ex.: Vendus), quando aplicável;</li>
        <li>fornecedores de infraestrutura e alojamento.</li>
    </ul>
    <p>
        Alguns subcontratantes podem estar localizados fora do Espaço Económico Europeu. Nesses casos, são aplicadas
        salvaguardas adequadas, como cláusulas contratuais-tipo aprovadas pela Comissão Europeia.
    </p>

    <h2>5. Prazos de conservação</h2>
    <p>
        Os dados são conservados enquanto existir relação com o cliente e pelo tempo necessário ao cumprimento de
        obrigações legais (ex.: fiscais e contabilísticas). Registos técnicos de segurança são conservados por períodos
        limitados, proporcionais à finalidade.
    </p>

    <h2>6. Direitos do titular</h2>
    <p>Nos termos do RGPD, o titular dos dados pode exercer os direitos de:</p>
    <ul>
        <li>acesso, retificação e apagamento;</li>
        <li>limitação e oposição ao tratamento;</li>
        <li>portabilidade dos dados;</li>
        <li>retirar o consentimento, quando o tratamento se baseie nele, sem comprometer a licitude anterior.</li>
    </ul>
    <p>
        Para exercer estes direitos: <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
        Tem também o direito de apresentar reclamação à Comissão Nacional de Proteção de Dados (CNPD).
    </p>

    <h2>7. Segurança</h2>
    <p>
        Implementamos medidas técnicas e organizativas adequadas para proteger os dados pessoais, incluindo
        comunicação encriptada (HTTPS), controlo de acessos e registo de atividades relevantes.
    </p>

    <h2>8. Cookies e tecnologias semelhantes</h2>
    <p>
        A utilização de cookies e tecnologias semelhantes está descrita na
        <a href="{{ route('legal.cookies') }}">Política de Cookies</a>.
    </p>

    <h2>9. Alterações</h2>
    <p>
        Esta política pode ser atualizada. A versão em vigor é identificada no topo desta página.
        Alterações relevantes podem exigir nova aceitação no registo ou no acesso à conta.
    </p>
@endsection
