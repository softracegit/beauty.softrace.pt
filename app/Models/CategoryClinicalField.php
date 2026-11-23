<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryClinicalField extends Model
{
    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_SELECT = 'select';

    public const TYPE_MULTISELECT = 'multiselect';

    public const TYPE_NUMBER = 'number';

    public const TYPE_BOOL = 'bool';

    public const TYPE_MATERIAL = 'material';

    protected $fillable = [
        'category_id',
        'organization_id',
        'key',
        'label',
        'input_type',
        'options',
        'sort_order',
        'is_active',
        'is_required',
    ];

    protected $casts = [
        'options' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_required' => 'boolean',
    ];

    /**
     * @return list<string>
     */
    public static function inputTypes(): array
    {
        return [
            self::TYPE_TEXT,
            self::TYPE_TEXTAREA,
            self::TYPE_SELECT,
            self::TYPE_MULTISELECT,
            self::TYPE_NUMBER,
            self::TYPE_BOOL,
            self::TYPE_MATERIAL,
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSchemaArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'input_type' => $this->input_type,
            'options' => $this->options ?? [],
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_required' => $this->is_required,
        ];
    }
}
