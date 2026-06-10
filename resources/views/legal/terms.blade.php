@extends('legal.layout')

@section('content')
    <h1>Termos e Condições</h1>
    @include('legal.partials.entity')
    <p class="legal-meta mb-0">Última atualização: {{ $privacyVersion }}</p>

    <h2>1. Objeto</h2>
    <p>
        Os presentes Termos e Condições regulam a utilização da plataforma de marcação online e da área de cliente
        disponibilizada por <strong>{{ $companyName }}</strong> (doravante, «Plataforma»), através da qual os utilizadores
        podem consultar serviços, efetuar marcações e gerir a sua conta junto dos estabelecimentos parceiros.
    </p>

    <h2>2. Prestador da Plataforma e prestador do serviço</h2>
    <p>
        A Plataforma é operada por <strong>{{ $companyName }}</strong>.
        O serviço concreto (tratamento, consulta, etc.) é prestado pelo estabelecimento escolhido no momento da marcação,
        que atua como prestador do serviço face ao cliente final.
    </p>

    <h2>3. Conta de utilizador</h2>
    <p>
        O acesso à área de cliente é feito sem palavra-passe, mediante código de verificação enviado por email ou SMS.
        O utilizador é responsável pela exatidão dos dados que fornece e pela confidencialidade do acesso ao seu dispositivo
        e às mensagens que recebe.
    </p>

    <h2>4. Processo de marcação</h2>
    <p>A marcação online segue, em geral, os seguintes passos:</p>
    <ul>
        <li>seleção do serviço e, quando aplicável, do colaborador;</li>
        <li>escolha de data e hora disponíveis;</li>
        <li>confirmação dos dados de contacto;</li>
        <li>eventual pré-pagamento ou depósito online, quando exigido pelo estabelecimento.</li>
    </ul>
    <p>
        Durante o processo, pode ser reservado temporariamente um horário («slot») até à conclusão ou expiração da reserva.
    </p>

    <h2>5. Pagamentos</h2>
    <p>
        Quando aplicável, os pagamentos online são processados por prestadores de serviços de pagamento certificados
        (ex.: Stripe). A Plataforma não armazena o número completo do cartão bancário.
        O valor em falta, quando existir, poderá ser liquidado no estabelecimento no dia da marcação.
    </p>

    <h2>6. Cancelamento e reagendamento</h2>
    <p>
        As regras de cancelamento, incluindo prazos de aviso e eventual perda ou crédito de pré-pagamentos, são as
        definidas na política de cancelamento apresentada no fluxo de marcação
        e na área de cliente, e podem variar consoante as definições de cada estabelecimento.
        O cancelamento dentro do prazo aplicável pode gerar crédito na carteira digital do cliente, para utilização
        em futuras marcações no mesmo estabelecimento, salvo indicação em contrário.
    </p>

    <h2>7. Comunicações</h2>
    <p>
        A Plataforma pode enviar comunicações relacionadas com a marcação (confirmações, alterações, lembretes,
        códigos de acesso) por email e/ou SMS, conforme as preferências do utilizador e a necessidade do serviço.
        Comunicações de marketing apenas serão enviadas com consentimento prévio e específico do utilizador.
    </p>

    <h2>8. Faturação</h2>
    <p>
        Quando solicitada e aplicável, a faturação pode ser emitida através de sistemas de faturação integrados,
        com base nos dados fiscais fornecidos pelo cliente (ex.: NIF).
    </p>

    <h2>9. Conduta do utilizador</h2>
    <p>O utilizador compromete-se a:</p>
    <ul>
        <li>fornecer informações verdadeiras e atualizadas;</li>
        <li>não utilizar a Plataforma para fins ilícitos ou abusivos;</li>
        <li>comparecer às marcações confirmadas ou cancelá-las atempadamente.</li>
    </ul>

    <h2>10. Disponibilidade e limitação de responsabilidade</h2>
    <p>
        A Plataforma procura manter o serviço disponível de forma contínua, mas não garante ausência de interrupções
        por manutenção, falhas técnicas ou causas fora do seu controlo.
        A responsabilidade pela execução do serviço agendado cabe ao estabelecimento prestador.
    </p>

    <h2>11. Proteção de dados</h2>
    <p>
        O tratamento de dados pessoais rege-se pela
        <a href="{{ route('legal.privacy') }}">Política de Privacidade</a> e pela
        <a href="{{ route('legal.cookies') }}">Política de Cookies</a>.
    </p>

    <h2>12. Alterações</h2>
    <p>
        A Plataforma pode atualizar estes Termos. A versão em vigor é publicada nesta página.
        Em caso de alterações relevantes, poderá ser solicitada nova aceitação no registo ou no acesso à conta.
    </p>

    <h2>13. Lei aplicável e foro</h2>
    <p>
        Estes Termos regem-se pela lei portuguesa. Para litígios de consumo, aplicam-se as regras legais imperativas,
        incluindo os mecanismos de resolução alternativa de litígios.
    </p>

    <h2>14. Contactos</h2>
    <p>
        Para questões sobre estes Termos: <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
    </p>
@endsection
