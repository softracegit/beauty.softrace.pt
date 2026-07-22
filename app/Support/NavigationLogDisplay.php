<?php

namespace App\Support;

class NavigationLogDisplay
{
    /** @var array<string, string> */
    private const ROUTE_LABELS = [
        'dashboard' => 'Resumo',
        'dashboard.marcacoes' => 'Dashboard → Marcações',
        'dashboard.ocupacao' => 'Dashboard → Ocupação',
        'dashboard.financeiro' => 'Dashboard → Financeiro',
        'dashboard.clientes' => 'Dashboard → Clientes',
        'dashboard.equipa' => 'Dashboard → Equipa',
        'dashboard.imoveis' => 'Dashboard → Imóveis',
        'dashboard.negocios' => 'Dashboard → Negócios',
        'agenda.index' => 'Agenda',
        'activity.index' => 'Activity Log',
        'activity.navigation' => 'Activity Log → Navegação',
        'definicoes.index' => 'Definições',
        'definicoes.negocio' => 'Definições → Negócio',
        'definicoes.marcacoes' => 'Definições → Marcações',
        'definicoes.equipa' => 'Definições → Equipa',
        'definicoes.emails' => 'Definições → Emails',
        'definicoes.etiquetas' => 'Definições → Etiquetas',
        'definicoes.notificacoes' => 'Definições → Notificações',
        'definicoes.pagamentos' => 'Definições → Pagamentos',
        'notifications.index' => 'Notificações',
        'clientes.index' => 'Clientes → Lista',
        'clientes.create' => 'Clientes → Novo cliente',
        'clientes.show' => 'Clientes → Ficha',
        'clientes.edit' => 'Clientes → Editar',
        'equipa.index' => 'Equipa → Membros',
        'equipa.create' => 'Equipa → Novo membro',
        'equipa.show' => 'Equipa → Ficha',
        'equipa.edit' => 'Equipa → Editar',
        'services.index' => 'Catálogo → Serviços',
        'services.tecnicos' => 'Catálogo → Serviços por equipa',
        'services.show' => 'Catálogo → Serviço',
        'services.byCategory' => 'Catálogo → Serviços',
        'categories.show' => 'Catálogo → Categoria',
        'extras.index' => 'Catálogo → Extras',
        'extras.create' => 'Catálogo → Novo extra',
        'extras.show' => 'Catálogo → Extra',
        'extras.edit' => 'Catálogo → Editar extra',
        'fees.index' => 'Catálogo → Taxas',
        'fees.show' => 'Catálogo → Taxa',
        'relatorios.vendas' => 'Relatórios → Vendas',
        'relatorios.marcacoes' => 'Relatórios → Marcações',
        'relatorios.comissoes' => 'Relatórios → Comissões',
        'relatorios.caixa' => 'Relatórios → Caixa',
        'relatorios.sms' => 'Relatórios → SMS',
        'marketing.campanhas-sms' => 'Marketing → Campanhas SMS',
        'leads.kanban' => 'Leads → Kanban',
        'leads.index' => 'Leads → Lista',
        'leads.create' => 'Leads → Novo lead',
        'leads.show' => 'Leads → Ficha',
        'leads.edit' => 'Leads → Editar',
        'opportunities.kanban' => 'Oportunidades → Kanban',
        'opportunities.index' => 'Oportunidades → Lista',
        'opportunities.create' => 'Oportunidades → Nova oportunidade',
        'opportunities.show' => 'Oportunidades → Ficha',
        'opportunities.edit' => 'Oportunidades → Editar',
        'properties.index' => 'Imóveis → Lista',
        'properties.create' => 'Imóveis → Novo imóvel',
        'properties.show' => 'Imóveis → Ficha',
        'properties.edit' => 'Imóveis → Editar',
        'deals.index' => 'Negócios → Lista',
        'deals.show' => 'Negócios → Ficha',
        'caixa.open.preview' => 'Caixa → Abrir dia',
        'caixa.close.summary' => 'Caixa → Fechar dia',
    ];

    /** @var array<string, string> */
    private const GROUP_LABELS = [
        'dashboard' => 'Dashboard',
        'agenda' => 'Agenda',
        'activity' => 'Activity Log',
        'definicoes' => 'Definições',
        'clientes' => 'Clientes',
        'equipa' => 'Equipa',
        'services' => 'Catálogo',
        'categories' => 'Catálogo',
        'extras' => 'Catálogo',
        'fees' => 'Catálogo',
        'relatorios' => 'Relatórios',
        'marketing' => 'Marketing',
        'notifications' => 'Notificações',
        'leads' => 'Leads',
        'opportunities' => 'Oportunidades',
        'properties' => 'Imóveis',
        'deals' => 'Negócios',
        'caixa' => 'Caixa',
        'sales' => 'Vendas',
        'visits' => 'Visitas',
        'proposals' => 'Propostas',
    ];

    /** @var array<string, string> */
    private const SEGMENT_LABELS = [
        'negocio' => 'Negócio',
        'marcacoes' => 'Marcações',
        'equipa' => 'Equipa',
        'notificacoes' => 'Notificações',
        'pagamentos' => 'Pagamentos',
        'navigation' => 'Navegação',
        'vendas' => 'Vendas',
        'comissoes' => 'Comissões',
        'caixa' => 'Caixa',
        'campanhas-sms' => 'Campanhas SMS',
        'tecnicos' => 'Serviços por equipa',
        'byCategory' => 'Serviços',
        'open' => 'Abrir dia',
        'close' => 'Fechar dia',
        'summary' => 'Resumo',
        'preview' => 'Pré-visualização',
    ];

    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'index' => 'Lista',
        'show' => 'Ficha',
        'create' => 'Novo',
        'edit' => 'Editar',
        'kanban' => 'Kanban',
    ];

    /** @var array<string, string> */
    private const PARAM_LABELS = [
        'cliente' => 'Cliente',
        'agente' => 'Membro',
        'calendarEvent' => 'Marcação',
        'service' => 'Serviço',
        'category' => 'Categoria',
        'extra' => 'Extra',
        'fee' => 'Taxa',
        'lead' => 'Lead',
        'opportunity' => 'Oportunidade',
        'property' => 'Imóvel',
        'deal' => 'Negócio',
        'sale' => 'Venda',
        'visit' => 'Visita',
        'proposal' => 'Proposta',
        'user' => 'Utilizador',
    ];

    public static function routeLabel(?string $routeName): string
    {
        if ($routeName === null || trim($routeName) === '') {
            return '—';
        }

        if (isset(self::ROUTE_LABELS[$routeName])) {
            return self::ROUTE_LABELS[$routeName];
        }

        return self::inferRouteLabel($routeName);
    }

    /**
     * @param  array<string, mixed>|null  $routeParams
     */
    public static function routeParamsSummary(?array $routeParams): ?string
    {
        if (! is_array($routeParams) || $routeParams === []) {
            return null;
        }

        $parts = [];
        foreach ($routeParams as $key => $value) {
            if (! is_scalar($value) || $value === '') {
                continue;
            }

            $label = self::PARAM_LABELS[(string) $key] ?? self::humanizeSegment((string) $key);
            $parts[] = $label.' #'.$value;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private static function inferRouteLabel(string $routeName): string
    {
        $parts = explode('.', $routeName);
        if ($parts === []) {
            return self::humanizeSegment($routeName);
        }

        if (count($parts) === 1) {
            return self::GROUP_LABELS[$parts[0]] ?? self::humanizeSegment($parts[0]);
        }

        $labels = [];
        foreach ($parts as $index => $part) {
            $isLast = $index === count($parts) - 1;
            $isFirst = $index === 0;

            if ($isFirst) {
                $labels[] = self::GROUP_LABELS[$part] ?? self::humanizeSegment($part);
                continue;
            }

            if ($isLast && $part === 'index' && count($parts) === 2) {
                continue;
            }

            if ($isLast && isset(self::ACTION_LABELS[$part])) {
                $labels[] = self::ACTION_LABELS[$part];
                continue;
            }

            $labels[] = self::SEGMENT_LABELS[$part] ?? self::humanizeSegment($part);
        }

        $labels = array_values(array_filter($labels, fn (string $label) => $label !== ''));

        if ($labels === []) {
            return self::humanizeSegment($routeName);
        }

        return implode(' → ', $labels);
    }

    private static function humanizeSegment(string $segment): string
    {
        $segment = str_replace(['-', '_'], ' ', $segment);

        return mb_convert_case($segment, MB_CASE_TITLE, 'UTF-8');
    }
}
