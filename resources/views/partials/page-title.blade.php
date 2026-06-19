@php
    $breadcrumbs = [];
    
    $breadcrumbs[] = [
        'label' => 'Home',
        'url' => route('dashboard'),
        'icon' => 'ph-duotone ph-house'
    ];
    
    $currentRoute = request()->route()->getName();
    
    if (str_contains($currentRoute, 'leads')) {
        $breadcrumbs[] = [
            'label' => 'Leads',
            'url' => route('leads.kanban'),
            'active' => in_array($currentRoute, ['leads.kanban', 'leads.index', 'leads.create'])
        ];
        
        if (in_array($currentRoute, ['leads.show', 'leads.edit'])) {
            $lead = isset($lead) ? $lead : null;
            if (isset($lead) && $lead) {
                $breadcrumbs[] = [
                    'label' => $lead->name,
                    'url' => null,
                    'active' => true
                ];
            }
        }
    } elseif (str_contains($currentRoute, 'clientes')) {
        $breadcrumbs[] = [
            'label' => 'Clientes',
            'url' => route('clientes.index'),
            'active' => in_array($currentRoute, ['clientes.index', 'clientes.create'])
        ];
        
        if (in_array($currentRoute, ['clientes.show', 'clientes.edit'])) {
            $cliente = isset($cliente) ? $cliente : null;
            if ($cliente) {
                $breadcrumbs[] = [
                    'label' => $cliente->name,
                    'url' => null,
                    'active' => true
                ];
            }
        }
    } elseif (str_contains($currentRoute, 'equipa')) {
        $breadcrumbs[] = [
            'label' => 'Equipa',
            'url' => route('equipa.index'),
            'active' => in_array($currentRoute, ['equipa.index', 'equipa.create'])
        ];
        
        if (in_array($currentRoute, ['equipa.show', 'equipa.edit'])) {
            $agente = isset($agente) ? $agente : null;
            if ($agente) {
                $breadcrumbs[] = [
                    'label' => $agente->name,
                    'url' => null,
                    'active' => true
                ];
            }
        }
    } elseif (str_contains($currentRoute, 'opportunities')) {
        $breadcrumbs[] = [
            'label' => 'Oportunidades',
            'url' => route('opportunities.kanban'),
            'active' => in_array($currentRoute, ['opportunities.kanban', 'opportunities.index', 'opportunities.create'])
        ];
        
        if (in_array($currentRoute, ['opportunities.show', 'opportunities.edit'])) {
            $opportunity = isset($opportunity) ? $opportunity : null;
            if ($opportunity) {
                $breadcrumbs[] = [
                    'label' => $opportunity->reference,
                    'url' => null,
                    'active' => true
                ];
            }
        }
    } elseif (str_contains($currentRoute, 'properties')) {
        $breadcrumbs[] = [
            'label' => 'Imóveis',
            'url' => route('properties.index'),
            'active' => in_array($currentRoute, ['properties.index', 'properties.create'])
        ];
        
        if (in_array($currentRoute, ['properties.show', 'properties.edit'])) {
            $property = isset($property) ? $property : null;
            if ($property) {
                $breadcrumbs[] = [
                    'label' => $property->reference,
                    'url' => null,
                    'active' => true
                ];
            }
        }
    } elseif (str_contains($currentRoute, 'deals')) {
        $breadcrumbs[] = [
            'label' => 'Negócios',
            'url' => route('deals.index'),
            'active' => in_array($currentRoute, ['deals.index'])
        ];
        
        if (in_array($currentRoute, ['deals.show'])) {
            $deal = isset($deal) ? $deal : null;
            if ($deal) {
                $breadcrumbs[] = [
                    'label' => $deal->reference,
                    'url' => null,
                    'active' => true
                ];
            }
        }
    } elseif (str_contains($currentRoute, 'definicoes')) {
        $breadcrumbs[] = [
            'label' => 'Definições',
            'url' => route('definicoes.index'),
            'active' => false
        ];
        $sectionLabels = [
            'definicoes.negocio' => 'Negócio',
            'definicoes.marcacoes' => 'Marcações',
            'definicoes.equipa' => 'Equipa',
            'definicoes.notificacoes' => 'Notificações',
            'definicoes.pagamentos' => 'Pagamentos',
        ];
        if (isset($sectionLabels[$currentRoute])) {
            $breadcrumbs[] = [
                'label' => $sectionLabels[$currentRoute],
                'url' => null,
                'active' => true
            ];
        }
    } elseif (str_contains($currentRoute, 'activity')) {
        $breadcrumbs[] = [
            'label' => 'Definições',
            'url' => route('definicoes.index'),
            'active' => false
        ];
        $breadcrumbs[] = [
            'label' => 'Activity Log',
            'url' => route('activity.index'),
            'active' => $currentRoute === 'activity.index',
        ];
        if ($currentRoute === 'activity.navigation') {
            $breadcrumbs[] = [
                'label' => 'Navegação',
                'url' => null,
                'active' => true,
            ];
        }
    } elseif (str_starts_with($currentRoute, 'marketing.')) {
        $breadcrumbs[] = [
            'label' => 'Marketing',
            'url' => route('marketing.campanhas-sms'),
            'active' => in_array($currentRoute, ['marketing.campanhas-sms'], true),
        ];
        if ($currentRoute === 'marketing.campanhas-sms') {
            $breadcrumbs[] = [
                'label' => 'Campanhas SMS',
                'url' => null,
                'active' => true,
            ];
        }
    } elseif (str_starts_with($currentRoute, 'relatorios.')) {
        $breadcrumbs[] = [
            'label' => 'Relatórios',
            'url' => route('relatorios.vendas'),
            'active' => false
        ];
        $sectionLabels = [
            'relatorios.marcacoes' => 'Marcações',
            'relatorios.vendas' => 'Vendas',
            'relatorios.comissoes' => 'Comissões',
            'relatorios.caixa' => 'Caixa',
        ];
        if (isset($sectionLabels[$currentRoute])) {
            $breadcrumbs[] = [
                'label' => $sectionLabels[$currentRoute],
                'url' => null,
                'active' => true
            ];
        }
    } else {
        $breadcrumbs[] = [
            'label' => 'Dashboard',
            'url' => null,
            'active' => true
        ];
    }
@endphp

{{-- Page header removido --}}