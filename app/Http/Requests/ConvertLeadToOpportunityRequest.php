<?php

namespace App\Http\Requests;

use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertLeadToOpportunityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Dados do cliente
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:255'],
            
            // Dados da oportunidade
            'priority' => ['required', Rule::in(array_keys(Opportunity::priorities()))],
            'agent_id' => ['nullable', 'exists:agents,id'],
            'notes' => ['nullable', 'string'],
            
            // Preferência de imóvel
            'preference_property_type_id' => ['nullable', 'exists:property_types,id'],
            'preference_transaction_type_id' => ['nullable', 'exists:transaction_types,id'],
            'preference_property_condition_id' => ['nullable', 'exists:property_conditions,id'],
            'preference_min_price' => ['nullable', 'numeric', 'min:0'],
            'preference_max_price' => ['nullable', 'numeric', 'min:0'],
            'preference_typologies' => ['nullable', 'array'],
            'preference_typologies.*' => ['exists:property_typologies,id'],
            'preference_locations' => ['nullable', 'array'],
            'preference_locations.*.id_district' => ['nullable', 'integer'],
            'preference_locations.*.id_city' => ['nullable', 'integer'],
            'preference_locations.*.id_parish' => ['nullable', 'integer'],
            'preference_features' => ['nullable', 'array'],
            'preference_features.*' => ['exists:property_features,id'],
            'preference_notes' => ['nullable', 'string'],
        ];
    }
}
