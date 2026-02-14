<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyTypology extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'bedrooms',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'bedrooms' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

}
