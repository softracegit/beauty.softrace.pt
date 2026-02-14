<?php

namespace App\Http\Requests;

use App\Models\Opportunity;
use App\Models\Client;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
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
            'status' => ['required', Rule::in(array_keys(Opportunity::statuses()))],
            'priority' => ['required', Rule::in(array_keys(Opportunity::priorities()))],
            'type' => ['required', Rule::in(array_keys(Opportunity::types()))],
            'client_id' => ['required', 'exists:clients,id'],
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

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'O estado é obrigatório.',
            'priority.required' => 'A prioridade é obrigatória.',
            'type.required' => 'O tipo é obrigatório.',
            'client_id.required' => 'O cliente é obrigatório.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Gerar referência automática se não fornecida
        if (!$this->has('reference') || empty($this->reference)) {
            $this->merge([
                'reference' => Opportunity::generateReference(),
            ]);
        }
    }
}
