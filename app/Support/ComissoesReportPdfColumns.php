<?php

namespace App\Support;

use Illuminate\Http\Request;

class ComissoesReportPdfColumns
{
    /** @var array<string, string> */
    public const LABELS = [
        'data_venda' => 'Data venda',
        'numero_fatura' => 'N.º fatura',
        'colaborador' => 'Colaborador',
        'cliente' => 'Cliente',
        'servico' => 'Serviço',
        'valor_servico' => 'Valor serviço',
        'comissao_taxa' => 'Comissão',
        'valor_comissao' => 'Valor comissão',
    ];

    /**
     * @return list<string>
     */
    public static function defaultKeys(): array
    {
        return array_keys(self::LABELS);
    }

    /**
     * @return list<string>
     */
    public static function resolveFromRequest(Request $request): array
    {
        $param = $request->query('comissoes_pdf_cols');
        if ($param === null || trim((string) $param) === '') {
            return self::defaultKeys();
        }

        $requested = array_values(array_unique(array_filter(array_map(
            'trim',
            explode(',', (string) $param),
        ))));

        $valid = self::defaultKeys();
        $selected = array_values(array_intersect($valid, $requested));

        if ($selected === []) {
            return $valid;
        }

        return array_values(array_filter(
            $valid,
            static fn (string $key): bool => in_array($key, $selected, true),
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::LABELS;
    }
}
