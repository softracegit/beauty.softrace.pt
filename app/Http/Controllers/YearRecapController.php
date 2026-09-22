<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Protótipo do Recap anual (estilo Spotify Wrapped).
 * Dados de demonstração — a ligar depois às métricas reais do dashboard.
 */
class YearRecapController extends Controller
{
    public function show(Request $request): View
    {
        $year = (int) $request->integer('year', now()->year);
        $storeName = session('current_store_name')
            ?? auth()->user()?->agent?->store?->name
            ?? 'A vossa loja';

        $template = $request->query('template', 'nocturne');
        if (! in_array($template, ['nocturne', 'aurora', 'lava'], true)) {
            $template = 'nocturne';
        }

        // Demo — substituir por queries reais (CalendarEvent, Sale, Client, …)
        $recap = [
            'year' => $year,
            'store_name' => $storeName,
            'is_demo' => true,
            'template' => $template,
            'slides' => [
                [
                    'id' => 'cover',
                    'theme' => 'cover',
                    'kicker' => 'Year Recap',
                    'title' => (string) $year,
                    'subtitle' => $storeName,
                    'body' => 'Um olhar sobre o ano que a equipa construiu — marcações, clientes e momentos que importaram.',
                    'cta' => 'Começar',
                ],
                [
                    'id' => 'marcacoes',
                    'theme' => 'ink',
                    'kicker' => 'Agenda',
                    'label' => 'Marcações realizadas',
                    'value' => '2 847',
                    'hint' => 'excludes canceladas e faltas',
                    'body' => 'Quase 8 marcações por dia, em média — o ritmo do vosso ano.',
                    'footnote' => '+12% vs '.($year - 1),
                ],
                [
                    'id' => 'clientes',
                    'theme' => 'rose',
                    'kicker' => 'Pessoas',
                    'label' => 'Clientes atendidos',
                    'value' => '1 126',
                    'body' => 'Rostos únicos que passaram pela cadeira este ano.',
                    'stat_secondary' => [
                        'label' => 'Novos clientes',
                        'value' => '384',
                    ],
                ],
                [
                    'id' => 'retorno',
                    'theme' => 'sage',
                    'kicker' => 'Fidelização',
                    'label' => 'Voltaram pelo menos uma vez',
                    'value' => '68%',
                    'body' => 'Quase 7 em cada 10 clientes marcaram de novo. A confiança é o vosso melhor marketing.',
                    'stat_secondary' => [
                        'label' => 'Intervalo médio entre visitas',
                        'value' => '42 dias',
                    ],
                ],
                [
                    'id' => 'servico',
                    'theme' => 'ink',
                    'kicker' => 'Serviço estrela',
                    'label' => 'O mais pedido',
                    'value' => 'Corte + Brushing',
                    'body' => '342 vezes este ano — o ritual que a casa não larga.',
                    'list' => [
                        ['name' => 'Coloração', 'count' => '281'],
                        ['name' => 'Manicure', 'count' => '219'],
                        ['name' => 'Barba', 'count' => '174'],
                    ],
                ],
                [
                    'id' => 'pico',
                    'theme' => 'amber',
                    'kicker' => 'Ritmo',
                    'label' => 'O vosso horário de ouro',
                    'value' => 'Sábados · 11h',
                    'body' => 'É quando a agenda enche mais. Sexta à tarde fica a seguir, a respirar.',
                    'stat_secondary' => [
                        'label' => 'Mês mais intenso',
                        'value' => 'Dezembro',
                    ],
                ],
                [
                    'id' => 'equipa',
                    'theme' => 'rose',
                    'kicker' => 'Equipa',
                    'label' => 'Mais marcações concluídas',
                    'value' => 'Ana Ribeiro',
                    'body' => '612 atendimentos — e a média de satisfação da equipa fala por si.',
                    'list' => [
                        ['name' => 'Miguel Costa', 'count' => '498'],
                        ['name' => 'Sara Mendes', 'count' => '451'],
                        ['name' => 'João Alves', 'count' => '387'],
                    ],
                ],
                [
                    'id' => 'canais',
                    'theme' => 'sage',
                    'kicker' => 'Canais',
                    'label' => 'Como chegaram até vocês',
                    'split' => [
                        ['label' => 'Agenda / balcão', 'pct' => 61, 'value' => '61%'],
                        ['label' => 'Online', 'pct' => 39, 'value' => '39%'],
                    ],
                    'body' => 'O digital já traz quase 4 em cada 10 marcações — e continua a crescer.',
                ],
                [
                    'id' => 'financeiro',
                    'theme' => 'ink',
                    'kicker' => 'Resultado',
                    'label' => 'Faturação do ano',
                    'value' => '148 920 €',
                    'body' => 'Ticket médio de 52 €. Cada visita conta — e o ano soma.',
                    'stat_secondary' => [
                        'label' => 'vs ano anterior',
                        'value' => '+9%',
                    ],
                ],
                [
                    'id' => 'finale',
                    'theme' => 'finale',
                    'kicker' => 'Obrigado',
                    'title' => 'Foi um ano cheio.',
                    'body' => 'Marcações, regressos, picos e calmaria — tudo isto é a história da loja em '.$year.'.',
                    'highlights' => [
                        ['label' => 'Marcações', 'value' => '2 847'],
                        ['label' => 'Clientes', 'value' => '1 126'],
                        ['label' => 'Retorno', 'value' => '68%'],
                        ['label' => 'Faturação', 'value' => '148k €'],
                    ],
                    'cta' => 'Ver de novo',
                    'exit' => 'Voltar ao dashboard',
                ],
            ],
        ];

        return view('year-recap.index', compact('recap'));
    }
}
