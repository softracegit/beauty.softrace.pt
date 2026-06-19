<?php

namespace Tests\Unit;

use App\Support\NavigationLogDisplay;
use PHPUnit\Framework\TestCase;

class NavigationLogDisplayTest extends TestCase
{
    public function test_known_routes_use_friendly_labels(): void
    {
        $this->assertSame('Agenda', NavigationLogDisplay::routeLabel('agenda.index'));
        $this->assertSame('Definições → Negócio', NavigationLogDisplay::routeLabel('definicoes.negocio'));
        $this->assertSame('Activity Log', NavigationLogDisplay::routeLabel('activity.index'));
        $this->assertSame('Activity Log → Navegação', NavigationLogDisplay::routeLabel('activity.navigation'));
        $this->assertSame('Resumo', NavigationLogDisplay::routeLabel('dashboard'));
    }

    public function test_unknown_routes_are_inferred(): void
    {
        $this->assertSame('Clientes → Ficha', NavigationLogDisplay::routeLabel('clientes.show'));
        $this->assertSame('Relatórios → Vendas', NavigationLogDisplay::routeLabel('relatorios.vendas'));
    }

    public function test_route_params_summary_uses_friendly_labels(): void
    {
        $this->assertSame(
            'Cliente #12 · Membro #3',
            NavigationLogDisplay::routeParamsSummary(['cliente' => 12, 'agente' => 3])
        );
        $this->assertNull(NavigationLogDisplay::routeParamsSummary([]));
    }
}
