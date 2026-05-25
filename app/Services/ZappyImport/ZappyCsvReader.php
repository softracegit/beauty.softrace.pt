<?php

namespace App\Services\ZappyImport;

use RuntimeException;

class ZappyCsvReader
{
    /**
     * @return list<array<string, string>>
     */
    public function read(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Ficheiro CSV não encontrado ou ilegível: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Não foi possível ler: {$path}");
        }

        $content = $this->toUtf8($content);

        $handle = fopen('php://memory', 'r+b');
        if ($handle === false) {
            throw new RuntimeException("Não foi possível abrir buffer para: {$path}");
        }
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(fn (string $col) => trim($col), $header);
        $rows = [];

        while (($record = fgetcsv($handle, 0, ';')) !== false) {
            if ($record === [null] || $record === false) {
                continue;
            }
            $padded = array_pad($record, count($header), '');
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = trim((string) ($padded[$i] ?? ''));
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function toUtf8(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        $converted = @mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        $converted = @mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');

        return $converted !== false ? $converted : $content;
    }
}
