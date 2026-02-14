<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Local extends Model
{
    protected $table = 'local';

    protected $fillable = [
        'id_district',
        'district',
        'id_city',
        'city',
        'id_parish',
        'parish',
        'dicofre',
    ];

    public $timestamps = false;

    /**
     * Get all unique districts
     */
    public static function getDistricts()
    {
        return static::select('id_district', 'district')
            ->whereNotNull('district')
            ->distinct()
            ->orderBy('district')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id_district,
                    'name' => $item->district,
                ];
            });
    }

    /**
     * Get cities (concelhos) by district
     */
    public static function getCitiesByDistrict($districtId)
    {
        return static::select('id_city', 'city')
            ->where('id_district', $districtId)
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id_city,
                    'name' => $item->city,
                ];
            });
    }

    /**
     * Get parishes (freguesias) by city
     */
    public static function getParishesByCity($cityId)
    {
        return static::select('id_parish', 'parish')
            ->where('id_city', $cityId)
            ->whereNotNull('parish')
            ->distinct()
            ->orderBy('parish')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id_parish,
                    'name' => $item->parish,
                ];
            });
    }

    /**
     * Get district by name
     */
    public static function getDistrictByName($districtName)
    {
        return static::where('district', $districtName)
            ->first();
    }

    /**
     * Get city by name and district
     */
    public static function getCityByName($cityName, $districtId = null)
    {
        $query = static::where('city', $cityName);
        
        if ($districtId) {
            $query->where('id_district', $districtId);
        }
        
        return $query->first();
    }

    /**
     * Get parish by name and city
     */
    public static function getParishByName($parishName, $cityId = null)
    {
        $query = static::where('parish', $parishName);
        
        if ($cityId) {
            $query->where('id_city', $cityId);
        }
        
        return $query->first();
    }

    /**
     * Get district name by ID
     */
    public static function getDistrictNameById(int $id): ?string
    {
        $local = static::where('id_district', $id)->first();
        return $local ? $local->district : null;
    }

    /**
     * Get city name by ID
     */
    public static function getCityNameById(int $id): ?string
    {
        $local = static::where('id_city', $id)->first();
        return $local ? $local->city : null;
    }

    /**
     * Get parish name by ID
     */
    public static function getParishNameById(int $id): ?string
    {
        $local = static::where('id_parish', $id)->first();
        return $local ? $local->parish : null;
    }
}
