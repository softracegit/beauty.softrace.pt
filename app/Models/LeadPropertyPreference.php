<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadPropertyPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'property_type_id',
        'transaction_type_id',
        'property_condition_id',
        'max_price',
        'min_price',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'max_price' => 'decimal:2',
        'min_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function propertyType()
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function propertyCondition()
    {
        return $this->belongsTo(PropertyCondition::class);
    }

    public function typologies()
    {
        return $this->belongsToMany(PropertyTypology::class, 'lead_preference_typologies', 'property_preference_id', 'property_typology_id')
                    ->withTimestamps();
    }

    public function features()
    {
        return $this->belongsToMany(PropertyFeature::class, 'lead_preference_features', 'property_preference_id', 'property_feature_id')
                    ->withTimestamps();
    }

    public function preferenceLocations()
    {
        return $this->hasMany(LeadPreferenceLocation::class, 'property_preference_id');
    }
}
