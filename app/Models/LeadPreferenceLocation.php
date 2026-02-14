<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadPreferenceLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_preference_id',
        'id_district',
        'id_city',
        'id_parish',
    ];

    public function preference()
    {
        return $this->belongsTo(LeadPropertyPreference::class, 'property_preference_id');
    }
}
