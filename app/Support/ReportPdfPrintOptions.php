<?php

namespace App\Support;

use Illuminate\Http\Request;

class ReportPdfPrintOptions
{
    /**
     * @param  array<string, string>  $labels
     * @return list<string>
     */
    public static function resolveColumns(Request $request, array $labels, string $queryKey): array
    {
        $valid = array_keys($labels);
        $param = $request->query($queryKey);
        if ($param === null || trim((string) $param) === '') {
            return $valid;
        }

        $requested = array_values(array_unique(array_filter(array_map(
            'trim',
            explode(',', (string) $param),
        ))));

        $selected = array_values(array_intersect($valid, $requested));
        if ($selected === []) {
            return $valid;
        }

        return array_values(array_filter(
            $valid,
            static fn (string $key): bool => in_array($key, $selected, true),
        ));
    }

    public static function resolveOrientation(Request $request, string $queryKey): string
    {
        return $request->query($queryKey) === 'portrait' ? 'portrait' : 'landscape';
    }
}
