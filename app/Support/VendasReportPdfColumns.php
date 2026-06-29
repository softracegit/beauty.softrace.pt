<?php

namespace App\Support;

use Illuminate\Http\Request;

class VendasReportPdfColumns
{
    /** @var array<string, string> */
    public const LABELS = [
        'data' => 'Data',
        'cliente' => 'Cliente',
        'nif' => 'NIF',
        'tecnico' => 'Técnico',
        'servico' => 'Serviço',
        'total' => 'Total',
        'taxas' => 'Taxas',
        'gorjeta' => 'Gorjeta',
        'estado_fatura' => 'Estado fatura',
    ];

    /**
     * @return list<string>
     */
    public static function resolveFromRequest(Request $request): array
    {
        return ReportPdfPrintOptions::resolveColumns($request, self::LABELS, 'vendas_pdf_cols');
    }

    public static function resolveOrientationFromRequest(Request $request): string
    {
        return ReportPdfPrintOptions::resolveOrientation($request, 'vendas_pdf_orientation');
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::LABELS;
    }
}
