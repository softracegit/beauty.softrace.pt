<?php

namespace App\Actions;

use App\Models\Service;
use App\Models\ServiceOption;
use Illuminate\Support\Facades\DB;

final class SyncServiceOptionsAction
{
    /**
     * @param  list<array{name: string, description?: ?string, short_description?: ?string, duration: int, price: float|string, online_price: float|string, sort_order?: int, is_baseline?: bool, type?: string}>  $options
     */
    public function execute(Service $service, bool $hasOptions, array $options): void
    {
        DB::transaction(function () use ($service, $hasOptions, $options): void {
            if (! $hasOptions || $options === []) {
                $service->options()->delete();

                return;
            }

            usort($options, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

            // As variantes que já existem mantêm o id: carrinhos do Booking em curso e marcações
            // antigas continuam a apontar para a mesma variante depois de editar o serviço.
            $existing = $service->options()->get()->keyBy('id');
            $keptIds = [];

            foreach (array_values($options) as $idx => $row) {
                $isBaseline = ! empty($row['is_baseline']);
                $name = (string) $row['name'];
                $description = isset($row['description']) ? trim((string) $row['description']) : '';
                $shortDescription = isset($row['short_description']) ? trim((string) $row['short_description']) : '';

                $attributes = [
                    'service_id' => $service->id,
                    'name' => $name,
                    'description' => $description !== '' ? $description : null,
                    'short_description' => $shortDescription !== '' ? $shortDescription : null,
                    'duration' => (int) $row['duration'],
                    'price' => (float) $row['price'],
                    'online_price' => (float) $row['online_price'],
                    'sort_order' => (int) ($row['sort_order'] ?? $idx),
                    'is_baseline' => $isBaseline,
                    'type' => $isBaseline ? ServiceOption::TYPE_CHOICE : ServiceOption::normalizeType($row['type'] ?? null),
                ];

                $rowId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
                $current = $rowId > 0 && ! in_array($rowId, $keptIds, true) ? $existing->get($rowId) : null;

                if ($current instanceof ServiceOption) {
                    $current->update($attributes);
                    $keptIds[] = $current->id;

                    continue;
                }

                $keptIds[] = ServiceOption::create($attributes)->id;
            }

            // Só desaparecem as variantes que foram mesmo retiradas do formulário
            $service->options()->whereNotIn('id', $keptIds)->delete();

            $baseline = $service->options()->where('is_baseline', true)->first();
            if ($baseline) {
                $service->update([
                    'duration' => $baseline->duration,
                    'price' => $baseline->price,
                    'online_price' => $baseline->online_price,
                ]);
            }
        });
    }
}
