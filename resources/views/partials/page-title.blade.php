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
            $lead = isset($lead) ? $lead : (isset($agente) ? null : null);
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
    } elseif (str_contains($currentRoute, 'agentes')) {
        $breadcrumbs[] = [
            'label' => 'Agentes',
            'url' => route('agentes.index'),
            'active' => in_array($currentRoute, ['agentes.index', 'agentes.create'])
        ];
        
        if (in_array($currentRoute, ['agentes.show', 'agentes.edit'])) {
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
    } else {
        $pageTitle = view()->yieldContent('page-heading-title');
        $breadcrumbs[] = [
            'label' => $pageTitle ?: 'Dashboard',
            'url' => null,
            'active' => true
        ];
    }
@endphp

@if(!request()->routeIs('agenda.*'))
<div class="page-header">
  <div>
    <h1 class="page-title">@yield('page-heading-title')</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        @foreach($breadcrumbs as $index => $breadcrumb)
          @if($index === 0)
            <li class="breadcrumb-item"><a href="{{ $breadcrumb['url'] }}">Home</a></li>
          @elseif(isset($breadcrumb['active']) && $breadcrumb['active'])
            <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb['label'] }}</li>
          @else
            <li class="breadcrumb-item"><a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a></li>
          @endif
        @endforeach
      </ol>
    </nav>
  </div>
</div>
@endif