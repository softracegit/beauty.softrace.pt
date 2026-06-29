<?php

namespace App\Support;

use Illuminate\Http\Request;

class ComissoesReportPdfColumns
{
    /** @var array<string, string> */
    public const LABELS = [
        'data_venda' => 'Data venda',
        'numero_fatura' => 'N.º fatura',
        'colaborador' => 'Colaborador(a)',
        'cliente' => 'Cliente',
        'servico' => 'Serviço',
        'valor_servico' => 'Valor serviço',
        'comissao_taxa' => 'Comissão (%)',
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
        return ReportPdfPrintOptions::resolveColumns($request, self::LABELS, 'comissoes_pdf_cols');
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::LABELS;
    }

    public static function resolveOrientationFromRequest(Request $request): string
    {
        return ReportPdfPrintOptions::resolveOrientation($request, 'comissoes_pdf_orientation');
    }
}
