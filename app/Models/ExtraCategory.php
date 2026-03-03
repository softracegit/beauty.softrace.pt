<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtraCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function extras(): HasMany
    {
        return $this->hasMany(Extra::class, 'extra_category_id')->orderBy('sort_order');
    }
}
