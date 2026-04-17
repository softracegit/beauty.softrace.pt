<?php

namespace App\Actions;

use App\Models\Service;
use App\Models\ServiceOption;
use Illuminate\Support\Facades\DB;

final class SyncServiceOptionsAction
{
    /**
     * @param  list<array{name: string, duration: int, price: float|string, online_price: float|string, sort_order?: int, is_baseline?: bool}>  $options
     */
    public function execute(Service $service, bool $hasOptions, array $options): void
    {
        DB::transaction(function () use ($service, $hasOptions, $options): void {
            $service->options()->delete();

            if (! $hasOptions || $options === []) {
                return;
            }

            usort($options, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

            foreach (array_values($options) as $idx => $row) {
                $isBaseline = ! empty($row['is_baseline']);
                $name = (string) $row['name'];

                ServiceOption::create([
                    'service_id' => $service->id,
                    'name' => $name,
                    'duration' => (int) $row['duration'],
                    'price' => (float) $row['price'],
                    'online_price' => (float) $row['online_price'],
                    'sort_order' => (int) ($row['sort_order'] ?? $idx),
                    'is_baseline' => $isBaseline,
                ]);
            }

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
