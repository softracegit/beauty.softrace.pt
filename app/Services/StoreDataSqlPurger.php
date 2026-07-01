<?php

namespace App\Services;

class StoreDataSqlPurger
{
    public const MODE_DATA = 'data';

    /** Dados + catálogo (serviços, extras, taxas); preserva users e agents. */
    public const MODE_CATALOG = 'catalog';

    /** Tudo incluindo agents (não apaga users). */
    public const MODE_FULL = 'full';

    public function __construct(
        private int $storeId = 1,
        private string $mode = self::MODE_DATA,
    ) {}

    public function purgeSql(): string
    {
        $storeId = $this->storeId;

        $lines = [];
        $lines[] = '-- Limpeza de dados da loja store_id='.$storeId;
        $lines[] = '-- Modo: '.$this->modeLabel();
        $lines[] = '-- Executar no servidor ANTES de importar store_'.$storeId.'_data.sql';
        $lines[] = '-- Gerado em '.now()->toDateTimeString();
        $lines[] = '';
        $lines[] = 'SET @store_id := '.$storeId.';';
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $lines[] = '';

        foreach ($this->deleteStatements() as $label => $sql) {
            $lines[] = '-- '.$label;
            $lines[] = $sql.';';
            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        $lines[] = '-- Limpeza concluída. Pode importar store_'.$storeId.'_data.sql';

        return implode("\n", $lines);
    }

    private function modeLabel(): string
    {
        return match ($this->mode) {
            self::MODE_CATALOG => 'dados + catálogo (categorias, serviços, extras, taxas; preserva users/agents)',
            self::MODE_FULL => 'completo (inclui agents; não apaga users)',
            default => 'só dados (preserva users, agents, serviços)',
        };
    }

    /**
     * @return array<string, string>
     */
    private function deleteStatements(): array
    {
        $statements = $this->dataDeleteStatements();

        if ($this->mode === self::MODE_CATALOG || $this->mode === self::MODE_FULL) {
            $statements += $this->catalogDeleteStatements();
        }

        if ($this->mode === self::MODE_FULL) {
            $statements += $this->teamDeleteStatements();
        }

        return $statements;
    }

    /**
     * @return array<string, string>
     */
    private function dataDeleteStatements(): array
    {
        return [
            'SMS / links de marcação' => <<<'SQL'
DELETE bsl FROM booking_sms_action_links bsl
INNER JOIN calendar_events ce ON ce.id = bsl.calendar_event_id
WHERE ce.store_id = @store_id
SQL,
            'Vendas ↔ eventos (pivot)' => <<<'SQL'
DELETE sce FROM sale_calendar_events sce
LEFT JOIN sales s ON s.id = sce.sale_id
LEFT JOIN calendar_events ce ON ce.id = sce.calendar_event_id
WHERE s.store_id = @store_id OR ce.store_id = @store_id
SQL,
            'Linhas de venda' => <<<'SQL'
DELETE si FROM sale_items si
INNER JOIN sales s ON s.id = si.sale_id
WHERE s.store_id = @store_id
SQL,
            'Extras em serviços de marcação' => <<<'SQL'
DELETE cese FROM calendar_event_service_extras cese
INNER JOIN calendar_event_services ces ON ces.id = cese.calendar_event_service_id
INNER JOIN calendar_events ce ON ce.id = ces.calendar_event_id
WHERE ce.store_id = @store_id
SQL,
            'Pagamentos de reservas' => <<<'SQL'
DELETE p FROM payments p
INNER JOIN bookings b ON b.id = p.booking_id
WHERE b.store_id = @store_id
SQL,
            'Movimentos de carteira' => <<<'SQL'
DELETE FROM client_wallet_transactions WHERE store_id = @store_id
SQL,
            'Cartões guardados (booking)' => <<<'SQL'
DELETE bsc FROM booking_saved_cards bsc
INNER JOIN clients c ON c.id = bsc.client_id
WHERE c.store_id = @store_id
SQL,
            'Reservas online' => <<<'SQL'
DELETE FROM bookings WHERE store_id = @store_id
SQL,
            'Holds de slots' => <<<'SQL'
DELETE FROM booking_slot_holds WHERE store_id = @store_id
SQL,
            'Códigos OTP de booking' => <<<'SQL'
DELETE FROM booking_auth_codes WHERE store_id = @store_id
SQL,
            'Histórico SMS' => <<<'SQL'
DELETE FROM sms_messages WHERE store_id = @store_id
SQL,
            'Vendas' => <<<'SQL'
DELETE FROM sales WHERE store_id = @store_id
SQL,
            'Serviços em marcações' => <<<'SQL'
DELETE ces FROM calendar_event_services ces
INNER JOIN calendar_events ce ON ce.id = ces.calendar_event_id
WHERE ce.store_id = @store_id
SQL,
            'Marcações / agenda' => <<<'SQL'
DELETE FROM calendar_events WHERE store_id = @store_id
SQL,
            'Referências importação Zappy' => <<<'SQL'
DELETE FROM zappy_import_refs WHERE store_id = @store_id
SQL,
            'Desassociar users ↔ clientes da loja (não apaga users)' => <<<'SQL'
UPDATE users u
INNER JOIN clients c ON c.id = u.client_id
SET u.client_id = NULL
WHERE c.store_id = @store_id
SQL,
            'Etiquetas de clientes (pivot)' => <<<'SQL'
DELETE cct FROM client_client_tag cct
INNER JOIN clients c ON c.id = cct.client_id
WHERE c.store_id = @store_id
SQL,
            'Clientes' => <<<'SQL'
DELETE FROM clients WHERE store_id = @store_id
SQL,
            'Sessões de caixa' => <<<'SQL'
DELETE FROM cash_register_sessions WHERE store_id = @store_id
SQL,
        ];
    }

    /**
     * Catálogo da loja (serviços, categorias, extras, taxas); preserva users e agents.
     *
     * @return array<string, string>
     */
    private function catalogDeleteStatements(): array
    {
        return [
            'Serviços por agente (só pivots dos serviços da loja)' => <<<'SQL'
DELETE ags FROM agent_service ags
INNER JOIN services sv ON sv.id = ags.service_id
WHERE sv.store_id = @store_id
SQL,
            'Taxas por serviço' => <<<'SQL'
DELETE sf FROM service_fee sf
INNER JOIN services sv ON sv.id = sf.service_id
WHERE sv.store_id = @store_id
SQL,
            'Opções de serviço' => <<<'SQL'
DELETE so FROM service_options so
INNER JOIN services sv ON sv.id = so.service_id
WHERE sv.store_id = @store_id
SQL,
            'Extras por serviço (catálogo)' => <<<'SQL'
DELETE se FROM service_extra se
INNER JOIN services sv ON sv.id = se.service_id
WHERE sv.store_id = @store_id
SQL,
            'Extras' => <<<'SQL'
DELETE e FROM extras e
INNER JOIN extra_categories ec ON ec.id = e.extra_category_id
WHERE ec.store_id = @store_id
SQL,
            'Categorias de extras' => <<<'SQL'
DELETE FROM extra_categories WHERE store_id = @store_id
SQL,
            'Serviços' => <<<'SQL'
DELETE FROM services WHERE store_id = @store_id
SQL,
            'Categorias de serviços' => <<<'SQL'
DELETE FROM categories WHERE store_id = @store_id
SQL,
            'Etiquetas de clientes' => <<<'SQL'
DELETE FROM client_tags WHERE store_id = @store_id
SQL,
            'Taxas (fees)' => <<<'SQL'
DELETE FROM fees WHERE store_id = @store_id
SQL,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function teamDeleteStatements(): array
    {
        return [
            'Agentes' => <<<'SQL'
DELETE FROM agents WHERE store_id = @store_id
SQL,
            'Tipos de tempo pessoal' => <<<'SQL'
DELETE FROM personal_time_types WHERE store_id = @store_id
SQL,
            'Definições CRM' => <<<'SQL'
DELETE FROM crm_settings WHERE store_id = @store_id
SQL,
        ];
    }
}
