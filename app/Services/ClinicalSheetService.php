<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\CategoryClinicalField;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ClinicalSheetService
{
    /**
     * @param  list<int>|Collection<int, int>  $categoryIds
     * @return Collection<int, Category>
     */
    public function loadCategoriesWithFields(iterable $categoryIds): Collection
    {
        $ids = collect($categoryIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $query = Category::query()
            ->whereIn('id', $ids)
            ->with(['clinicalFields' => fn ($q) => $q->active()->ordered()]);

        return $query->get()->keyBy('id');
    }

    /**
     * @param  list<int>  $serviceIds
     * @return list<int>
     */
    public function categoryIdsForServiceIds(iterable $serviceIds): array
    {
        $ids = collect($serviceIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return Service::query()
            ->whereIn('id', $ids)
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function categoryIdsForEvent(CalendarEvent $event): array
    {
        $event->loadMissing(['eventServices', 'service']);
        $fromPivot = $event->eventServices
            ->map(fn ($s) => (int) ($s->category_id ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        if ($fromPivot !== []) {
            return array_values(array_unique($fromPivot));
        }
        if ($event->service_id && $event->service?->category_id) {
            return [(int) $event->service->category_id];
        }

        return [];
    }

    /**
     * Schema payload for API / UI: keyed by category id.
     *
     * @param  list<int>  $categoryIds
     * @return array{categories: list<array<string, mixed>>, has_session_fields: bool}
     */
    public function schemaPayload(iterable $categoryIds): array
    {
        $categories = $this->loadCategoriesWithFields($categoryIds);
        $out = [];
        $hasSession = false;
        foreach ($categories as $cat) {
            /** @var Category $cat */
            $fields = $cat->clinicalFields->values();
            if ($fields->isEmpty()) {
                continue;
            }
            $hasSession = true;
            $out[] = [
                'category_id' => $cat->id,
                'category_name' => $cat->name,
                'fields' => $fields->map(fn (CategoryClinicalField $f) => $f->toSchemaArray())->values()->all(),
            ];
        }

        return [
            'categories' => $out,
            'has_session_fields' => $hasSession,
        ];
    }

    /**
     * Build snapshot `{ by_category: { "12": { "key": { label, type, value } } } }` from submitted values.
     *
     * @param  array<string, mixed>  $submitted  clinical_sheet.by_category or flat by category id
     * @return array{by_category: array<string, array<string, array{label: string, type: string, value: mixed}>>}
     */
    public function buildSnapshot(iterable $categoryIds, array $submitted): array
    {
        $byCategoryInput = $submitted['by_category'] ?? $submitted;
        if (! is_array($byCategoryInput)) {
            $byCategoryInput = [];
        }

        $categories = $this->loadCategoriesWithFields($categoryIds);
        $byCategory = [];

        foreach ($categories as $catId => $cat) {
            /** @var Category $cat */
            $fields = $cat->clinicalFields;
            if ($fields->isEmpty()) {
                continue;
            }
            $raw = $byCategoryInput[(string) $catId] ?? $byCategoryInput[$catId] ?? [];
            if (! is_array($raw)) {
                $raw = [];
            }
            $block = [];
            foreach ($fields as $field) {
                $value = array_key_exists($field->key, $raw) ? $raw[$field->key] : null;
                // Allow nested { value: ... } from UI
                if (is_array($value) && array_key_exists('value', $value) && count($value) <= 3) {
                    $value = $value['value'];
                }
                $normalized = $this->normalizeValue($field, $value);
                if ($normalized === null && ! $field->is_required) {
                    continue;
                }
                $block[$field->key] = [
                    'label' => $field->label,
                    'type' => $field->input_type,
                    'value' => $normalized,
                ];
            }
            if ($block !== []) {
                $byCategory[(string) $catId] = $block;
            }
        }

        return ['by_category' => $byCategory];
    }

    /**
     * @param  array<string, mixed>  $submitted
     *
     * @throws ValidationException
     */
    public function validateSubmitted(iterable $categoryIds, array $submitted): void
    {
        $byCategoryInput = $submitted['by_category'] ?? $submitted;
        if (! is_array($byCategoryInput)) {
            $byCategoryInput = [];
        }
        $categories = $this->loadCategoriesWithFields($categoryIds);
        $errors = [];

        foreach ($categories as $catId => $cat) {
            /** @var Category $cat */
            $raw = $byCategoryInput[(string) $catId] ?? $byCategoryInput[$catId] ?? [];
            if (! is_array($raw)) {
                $raw = [];
            }
            // Obrigatórios só quando a categoria já tem algum valor (a ficha está a ser preenchida).
            $categoryHasAnyValue = false;
            foreach ($cat->clinicalFields as $probeField) {
                $probe = $raw[$probeField->key] ?? null;
                if (is_array($probe) && array_key_exists('value', $probe)) {
                    $probe = $probe['value'];
                }
                if (! ($probe === null || $probe === '' || $probe === [])) {
                    $categoryHasAnyValue = true;
                    break;
                }
            }
            foreach ($cat->clinicalFields as $field) {
                $value = $raw[$field->key] ?? null;
                if (is_array($value) && array_key_exists('value', $value)) {
                    $value = $value['value'];
                }
                $attr = "clinical_sheet.by_category.{$catId}.{$field->key}";
                try {
                    $this->assertValidValue($field, $value, $categoryHasAnyValue);
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $msgs) {
                        foreach ($msgs as $msg) {
                            $errors[$attr][] = $msg;
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertValidValue(CategoryClinicalField $field, mixed $value, bool $enforceRequired = true): void
    {
        $empty = $value === null || $value === '' || $value === [];
        if ($empty) {
            if ($enforceRequired && $field->is_required) {
                throw ValidationException::withMessages([
                    $field->key => ['O campo '.$field->label.' é obrigatório.'],
                ]);
            }

            return;
        }

        $rules = match ($field->input_type) {
            CategoryClinicalField::TYPE_TEXT => ['string', 'max:500'],
            CategoryClinicalField::TYPE_TEXTAREA => ['string', 'max:5000'],
            CategoryClinicalField::TYPE_NUMBER => ['numeric'],
            CategoryClinicalField::TYPE_BOOL => ['boolean'],
            CategoryClinicalField::TYPE_SELECT => ['string', 'max:255'],
            CategoryClinicalField::TYPE_MULTISELECT => ['array'],
            default => ['nullable'],
        };

        $validator = Validator::make(['v' => $value], ['v' => $rules]);
        if ($validator->fails()) {
            throw ValidationException::withMessages([
                $field->key => $validator->errors()->get('v'),
            ]);
        }

        $options = array_map('strval', $field->options ?? []);
        if ($field->input_type === CategoryClinicalField::TYPE_SELECT && $options !== [] && ! in_array((string) $value, $options, true)) {
            throw ValidationException::withMessages([
                $field->key => ['Valor inválido para '.$field->label.'.'],
            ]);
        }
        if ($field->input_type === CategoryClinicalField::TYPE_MULTISELECT) {
            foreach ((array) $value as $item) {
                if ($options !== [] && ! in_array((string) $item, $options, true)) {
                    throw ValidationException::withMessages([
                        $field->key => ['Valor inválido para '.$field->label.'.'],
                    ]);
                }
            }
        }
    }

    public function normalizeValue(CategoryClinicalField $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($field->input_type === CategoryClinicalField::TYPE_BOOL) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
        }
        if ($field->input_type === CategoryClinicalField::TYPE_NUMBER) {
            return is_numeric($value) ? $value + 0 : $value;
        }
        if ($field->input_type === CategoryClinicalField::TYPE_MULTISELECT) {
            if (! is_array($value)) {
                $value = $value === '' ? [] : [($value)];
            }

            return array_values(array_map('strval', $value));
        }

        return is_scalar($value) ? (string) $value : $value;
    }

    /**
     * Preferências do cliente calculadas a partir das fichas das marcações:
     * por campo, o valor que mais se repete (empate → o mais recente).
     *
     * @return array{by_category: array<string, array<string, mixed>>}
     */
    public function preferencesPayload(Client $client, iterable $categoryIds): array
    {
        $ids = collect($categoryIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return ['by_category' => []];
        }

        $wanted = [];
        foreach ($this->loadCategoriesWithFields($ids) as $cat) {
            /** @var Category $cat */
            foreach ($cat->clinicalFields as $field) {
                $wanted[(string) $cat->id][$field->key] = true;
            }
        }
        if ($wanted === []) {
            return ['by_category' => []];
        }

        $events = CalendarEvent::query()
            ->where('client_id', $client->id)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('clinical_sheet')
            ->orderByDesc('start_at')
            ->get(['id', 'clinical_sheet', 'start_at']);

        /** @var array<string, array<string, array<string, array{value: mixed, count: int, first_seen: int}>>> $tally */
        $tally = [];
        foreach ($events as $order => $event) {
            $sheetByCat = $this->normalizeSheetColumn($event->clinical_sheet)['by_category'] ?? null;
            if (! is_array($sheetByCat)) {
                continue;
            }
            foreach ($wanted as $cid => $keys) {
                $fields = $sheetByCat[$cid] ?? $sheetByCat[(int) $cid] ?? null;
                if (! is_array($fields)) {
                    continue;
                }
                foreach (array_keys($keys) as $key) {
                    $entry = $fields[$key] ?? null;
                    $value = is_array($entry) && array_key_exists('value', $entry) ? $entry['value'] : $entry;
                    if ($value === null || $value === '' || $value === []) {
                        continue;
                    }
                    $hash = json_encode($value, JSON_UNESCAPED_UNICODE);
                    if (! isset($tally[$cid][$key][$hash])) {
                        $tally[$cid][$key][$hash] = ['value' => $value, 'count' => 0, 'first_seen' => $order];
                    }
                    $tally[$cid][$key][$hash]['count']++;
                }
            }
        }

        $byCategory = [];
        foreach ($tally as $cid => $fields) {
            foreach ($fields as $key => $candidates) {
                usort($candidates, fn ($a, $b) => [$b['count'], $a['first_seen']] <=> [$a['count'], $b['first_seen']]);
                $byCategory[$cid][$key] = $candidates[0]['value'];
            }
        }

        return ['by_category' => $byCategory];
    }

    /**
     * Categorias da organização com campos de ficha clínica activos.
     *
     * @return list<int>
     */
    public function categoryIdsWithFields(int $organizationId): array
    {
        return Category::forOrganization($organizationId)
            ->whereHas('clinicalFields', fn ($q) => $q->active())
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Flatten snapshot for display lines.
     *
     * @param  array<string, mixed>|null  $sheet
     * @return list<array{category_id: ?int, label: string, value: string}>
     */
    public function snapshotDisplayLines(?array $sheet): array
    {
        if (! is_array($sheet)) {
            return [];
        }
        $byCategory = $sheet['by_category'] ?? null;
        if (! is_array($byCategory)) {
            // Legacy flat shape without by_category
            $byCategory = ['0' => $sheet];
        }
        $lines = [];
        foreach ($byCategory as $catId => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            foreach ($fields as $key => $entry) {
                if (! is_array($entry)) {
                    $lines[] = [
                        'category_id' => (int) $catId ?: null,
                        'label' => (string) $key,
                        'value' => $this->formatDisplayValue($entry),
                    ];

                    continue;
                }
                $label = (string) ($entry['label'] ?? $key);
                $value = $entry['value'] ?? null;
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }
                $lines[] = [
                    'category_id' => (int) $catId ?: null,
                    'label' => $label,
                    'value' => $this->formatDisplayValue($value),
                ];
            }
        }

        return $lines;
    }

    /**
     * Snapshot agrupado por categoria (secções do modal de detalhe).
     *
     * @param  array<string, mixed>|null  $sheet
     * @param  bool  $includeEmpty  Se true, lista todos os campos activos da categoria; vazios → "---"
     * @param  list<int>|null  $categoryIds  Categorias extra (ex.: serviços da marcação) a incluir quando $includeEmpty
     * @return list<array{category_id: ?int, category_name: ?string, fields: list<array{label: string, type: string, value: mixed, display: string, empty?: bool}>}>
     */
    public function snapshotSections(?array $sheet, bool $includeEmpty = false, ?iterable $categoryIds = null): array
    {
        $byCategory = [];
        if (is_array($sheet)) {
            $byCategory = $sheet['by_category'] ?? null;
            if (! is_array($byCategory)) {
                $byCategory = ['0' => $sheet];
            }
        }

        if ($includeEmpty && $categoryIds !== null) {
            foreach (collect($categoryIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique() as $extraId) {
                $key = (string) $extraId;
                if (! array_key_exists($key, $byCategory) && ! array_key_exists($extraId, $byCategory)) {
                    $byCategory[$key] = [];
                }
            }
        }

        if ($byCategory === []) {
            return [];
        }

        $catIds = collect(array_keys($byCategory))->map(fn ($id) => (int) $id)->filter()->values()->all();
        $names = Category::query()
            ->whereIn('id', $catIds)
            ->pluck('name', 'id');
        $categories = $includeEmpty ? $this->loadCategoriesWithFields($catIds) : collect();

        $sections = [];
        foreach ($byCategory as $catId => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            $cid = (int) $catId ?: null;
            $rows = [];

            if ($includeEmpty && $cid && ($cat = $categories->get($cid)) && $cat->clinicalFields->isNotEmpty()) {
                $seenKeys = [];
                foreach ($cat->clinicalFields as $field) {
                    /** @var CategoryClinicalField $field */
                    $seenKeys[$field->key] = true;
                    $entry = $fields[$field->key] ?? null;
                    $isEntry = is_array($entry) && array_key_exists('value', $entry);
                    $value = $isEntry ? $entry['value'] : (is_array($entry) ? null : $entry);
                    $empty = $value === null || $value === '' || $value === [];
                    $rows[] = [
                        'label' => $field->label,
                        'type' => $field->input_type,
                        'value' => $empty ? null : $value,
                        'display' => $empty ? '---' : $this->formatDisplayValue($value),
                        'empty' => $empty,
                    ];
                }
                // Campos guardados no snapshot que já não existem no schema actual
                foreach ($fields as $key => $entry) {
                    if (isset($seenKeys[$key])) {
                        continue;
                    }
                    $isEntry = is_array($entry) && array_key_exists('value', $entry);
                    $value = $isEntry ? $entry['value'] : $entry;
                    $empty = $value === null || $value === '' || $value === [];
                    $rows[] = [
                        'label' => (string) ($isEntry ? ($entry['label'] ?? $key) : $key),
                        'type' => (string) ($isEntry ? ($entry['type'] ?? 'text') : 'text'),
                        'value' => $empty ? null : $value,
                        'display' => $empty ? '---' : $this->formatDisplayValue($value),
                        'empty' => $empty,
                    ];
                }
            } else {
                foreach ($fields as $key => $entry) {
                    $isEntry = is_array($entry) && array_key_exists('value', $entry);
                    $value = $isEntry ? $entry['value'] : $entry;
                    $empty = $value === null || $value === '' || $value === [];
                    if (! $includeEmpty && $empty) {
                        continue;
                    }
                    $rows[] = [
                        'label' => (string) ($isEntry ? ($entry['label'] ?? $key) : $key),
                        'type' => (string) ($isEntry ? ($entry['type'] ?? 'text') : 'text'),
                        'value' => $empty ? null : $value,
                        'display' => $empty ? '---' : $this->formatDisplayValue($value),
                        'empty' => $empty,
                    ];
                }
            }

            if ($rows === []) {
                continue;
            }
            $sections[] = [
                'category_id' => $cid,
                'category_name' => $cid ? ($names[$cid] ?? null) : null,
                'fields' => $rows,
            ];
        }

        return $sections;
    }

    public function formatDisplayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return (string) $value;
    }

    public function normalizeSheetColumn(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($raw) ? $raw : null;
    }
}
