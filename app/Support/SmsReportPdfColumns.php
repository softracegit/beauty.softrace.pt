<?php

namespace App\Support;

use Illuminate\Http\Request;

class SmsReportPdfColumns
{
    /** @var array<string, string> */
    public const LABELS = [
        'data' => 'Data',
        'tipo' => 'Tipo',
        'remetente' => 'Remetente',
        'cliente' => 'Cliente',
        'telemovel' => 'Telemóvel',
        'mensagem' => 'Mensagem',
    ];

    /**
     * @return list<string>
     */
    public static function resolveFromRequest(Request $request): array
    {
        return ReportPdfPrintOptions::resolveColumns($request, self::LABELS, 'sms_pdf_cols');
    }

    public static function resolveOrientationFromRequest(Request $request): string
    {
        return ReportPdfPrintOptions::resolveOrientation($request, 'sms_pdf_orientation');
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::LABELS;
    }
}
