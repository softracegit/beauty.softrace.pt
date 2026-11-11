<?php

namespace App\Http\Controllers;

use App\Services\YearRecap\YearRecapBuilder;
use App\Support\CurrentStore;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Year Recap (estilo Spotify Wrapped).
 *
 * - Admin e receção: o ano da loja. Prestadoras: o próprio ano.
 * - O que cada perfil vê está em config/year-recap.php e é validado slide a slide.
 * - ?demo=1 mostra os dados de demonstração (para afinar o design).
 * - ?refresh=1 (só admin) recalcula, ignorando a cache.
 */
class YearRecapController extends Controller
{
    private const TEMPLATES = ['nocturne', 'aurora', 'lava', 'gloss', 'scrap'];

    public function show(Request $request, YearRecapBuilder $builder): View
    {
        $user = $request->user();
        $store = app(CurrentStore::class)->get();
        $storeName = (string) ($store->name ?? 'A vossa loja');
        $currentYear = now()->year;

        $year = (int) $request->integer('year', $currentYear);
        $year = max(2020, min($currentYear, $year));

        $template = (string) $request->query('template', 'gloss');
        if (! in_array($template, self::TEMPLATES, true)) {
            $template = 'gloss';
        }

        if ($request->boolean('demo')) {
            $recap = $this->demo($year, $storeName, $template);

            return view('year-recap.index', compact('recap'));
        }

        $recap = $builder->build(
            viewer: $user,
            storeId: (int) $store->id,
            storeName: $storeName,
            year: $year,
            refresh: $request->boolean('refresh') && $user->isAdmin(),
        );
        $recap['template'] = $template;
        $recap['is_demo'] = false;

        return view('year-recap.index', compact('recap'));
    }

    /** Dados de demonstração (protótipo original). */
    private function demo(int $year, string $storeName, string $template): array
    {
        // Demo — substituir por queries reais (CalendarEvent, Sale, Client, …)
        $recap = [
            'year' => $year,
            'store_name' => $storeName,
            'is_demo' => true,
            'template' => $template,
            'audience' => 'admin',
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

        return $recap;
    }
}
