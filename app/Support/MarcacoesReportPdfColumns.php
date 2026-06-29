<?php

namespace App\Support;

use Illuminate\Http\Request;

class MarcacoesReportPdfColumns
{
    /** @var array<string, string> */
    public const LABELS = [
        'data_hora' => 'Data/Hora',
        'estado' => 'Estado',
        'cliente' => 'Cliente',
        'tecnico' => 'Técnico',
        'servicos' => 'Serviços',
        'categoria' => 'Categoria',
        'preco' => 'Preço',
        'notas' => 'Notas',
    ];

    /**
     * @return list<string>
     */
    public static function resolveFromRequest(Request $request): array
    {
        return ReportPdfPrintOptions::resolveColumns($request, self::LABELS, 'marcacoes_pdf_cols');
    }

    public static function resolveOrientationFromRequest(Request $request): string
    {
        return ReportPdfPrintOptions::resolveOrientation($request, 'marcacoes_pdf_orientation');
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::LABELS;
    }
}
