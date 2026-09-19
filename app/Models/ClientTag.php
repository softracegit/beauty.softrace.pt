<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\ClientTagStyle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClientTag extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'store_id',
        'name',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_client_tag');
    }

    public function normalizedColor(): string
    {
        return ClientTagStyle::defaultColor();
    }

    /**
     * @return array{id: int, name: string}
     */
    public function toPickerArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
        ];
    }
}
