<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Material extends Model
{
    use BelongsToOrganization, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['material_category_id', 'name', 'code', 'brand', 'finish', 'hex', 'stock', 'photo_path', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Material criado',
                'updated' => 'Material atualizado',
                'deleted' => 'Material arquivado',
                default => 'Material alterado',
            });
    }

    /** @return list<string> */
    public static function finishOptions(): array
    {
        return [
            'creme',
            'shimmer',
            'glitter',
            'chrome',
            'matte',
            'pearl',
            'metallic',
            'transparente',
        ];
    }

    public static function finishLabel(?string $finish): ?string
    {
        if ($finish === null || $finish === '') {
            return null;
        }

        return match ($finish) {
            'creme' => 'Creme',
            'shimmer' => 'Shimmer',
            'glitter' => 'Glitter',
            'chrome' => 'Chrome',
            'matte' => 'Matte',
            'pearl' => 'Pearl',
            'metallic' => 'Metallic',
            'transparente' => 'Transparente',
            default => $finish,
        };
    }

    protected $fillable = [
        'organization_id',
        'material_category_id',
        'name',
        'code',
        'brand',
        'finish',
        'hex',
        'stock',
        'photo_path',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'stock' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function photoUrl(): ?string
    {
        if ($this->photo_path === null || $this->photo_path === '') {
            return null;
        }

        return asset('storage/'.$this->photo_path);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<MaterialCategory, $this>
     */
    public function materialCategory(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    /**
     * Campos de ficha técnica (tipo Material) onde este material entra —
     * options vazias = todos os activos; senão lista explícita de IDs.
     *
     * @return list<array{category_id: int, category_name: string, field_id: int, field_key: string, field_label: string}>
     */
    public function clinicalFieldUsages(): array
    {
        $orgId = (int) $this->organization_id;
        if ($orgId <= 0 || ! $this->id) {
            return [];
        }

        $fields = CategoryClinicalField::query()
            ->where('organization_id', $orgId)
            ->where('input_type', CategoryClinicalField::TYPE_MATERIAL)
            ->with(['category:id,name'])
            ->ordered()
            ->get();

        $out = [];
        foreach ($fields as $field) {
            $opts = array_values(array_filter(array_map('intval', $field->options ?? []), fn ($id) => $id > 0));
            if ($opts !== [] && ! in_array((int) $this->id, $opts, true)) {
                continue;
            }
            $out[] = [
                'category_id' => (int) $field->category_id,
                'category_name' => (string) ($field->category?->name ?? 'Categoria'),
                'field_id' => (int) $field->id,
                'field_key' => (string) $field->key,
                'field_label' => (string) $field->label,
            ];
        }

        return $out;
    }

    /**
     * Catálogo / choices live na agenda (inclui stock e URL de foto).
     *
     * @return array{id: int, name: string, code: ?string, brand: ?string, finish: ?string, finish_label: ?string, hex: ?string, photo_path: ?string, stock: ?int, photo_url: ?string}
     */
    public function toChoiceArray(): array
    {
        return $this->toSnapshotArray() + [
            'stock' => $this->stock !== null ? (int) $this->stock : null,
            'photo_url' => $this->photoUrl(),
        ];
    }

    /**
     * Snapshot estável para ficha técnica (sem inventário nem URL absoluta).
     *
     * @return array{id: int, name: string, code: ?string, brand: ?string, finish: ?string, finish_label: ?string, hex: ?string, photo_path: ?string}
     */
    public function toSnapshotArray(): array
    {
        $finish = $this->finish !== null && $this->finish !== '' ? (string) $this->finish : null;

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'code' => $this->code !== null && $this->code !== '' ? (string) $this->code : null,
            'brand' => $this->brand !== null && $this->brand !== '' ? (string) $this->brand : null,
            'finish' => $finish,
            'finish_label' => self::finishLabel($finish),
            'hex' => $this->hex !== null && $this->hex !== '' ? (string) $this->hex : null,
            'photo_path' => $this->photo_path !== null && $this->photo_path !== '' ? (string) $this->photo_path : null,
        ];
    }
}
