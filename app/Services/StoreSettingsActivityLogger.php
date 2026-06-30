<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;

class StoreSettingsActivityLogger
{
    /**
     * @param  list<string>  $changes
     */
    public function logSection(Store $store, string $section, string $description, array $changes, ?User $causer = null): void
    {
        $changes = array_values(array_filter($changes, fn ($line) => is_string($line) && trim($line) !== ''));
        if ($changes === []) {
            return;
        }

        $logger = activity()
            ->performedOn($store)
            ->event('settings_updated')
            ->withProperties([
                'secao' => $section,
                'alteracoes' => $changes,
            ]);

        $causer = $this->resolveCauser($causer);
        if ($causer instanceof User) {
            $logger->causedBy($causer);
        }

        $logger->log($description);
    }

    public function logBoolChange(string $label, bool $before, bool $after): ?string
    {
        if ($before === $after) {
            return null;
        }

        return $label.': '.$this->boolLabel($before).' → '.$this->boolLabel($after);
    }

    public function logScalarChange(string $label, mixed $before, mixed $after, ?callable $formatter = null): ?string
    {
        $beforeStr = $this->scalarForCompare($before);
        $afterStr = $this->scalarForCompare($after);
        if ($beforeStr === $afterStr) {
            return null;
        }

        $beforeDisplay = $formatter ? $formatter($before) : $this->displayScalar($before);
        $afterDisplay = $formatter ? $formatter($after) : $this->displayScalar($after);

        return $label.': '.$beforeDisplay.' → '.$afterDisplay;
    }

    private function resolveCauser(?User $explicit): ?User
    {
        if ($explicit instanceof User) {
            return $explicit;
        }

        $authUser = auth()->user();

        return $authUser instanceof User ? $authUser : null;
    }

    private function boolLabel(bool $value): string
    {
        return $value ? 'Sim' : 'Não';
    }

    private function scalarForCompare(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value) ?: '';
        }

        return trim((string) $value);
    }

    private function displayScalar(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $this->boolLabel($value);
        }

        return (string) $value;
    }
}
