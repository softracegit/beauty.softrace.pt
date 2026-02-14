<?php

namespace App\Http\Requests;

use App\Models\Property;
use App\Models\TransactionType;
use App\Models\PropertyType;
use App\Models\PropertyTypology;
use App\Models\PropertyCondition;
use App\Models\PropertyFeature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Identificação
            'reference' => ['nullable', 'string', 'max:255', 'unique:properties,reference'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(Property::statuses()))],
            'property_condition_id' => ['nullable', 'exists:property_conditions,id'],
            
            // Negócio
            'transaction_type_id' => ['required', 'exists:transaction_types,id'],
            'property_type_id' => ['nullable', 'exists:property_types,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'condominium_fee' => ['nullable', 'numeric', 'min:0'],
            'imi_value' => ['nullable', 'numeric', 'min:0'],
            'commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_value' => ['nullable', 'numeric', 'min:0'],
            'agent_id' => ['nullable', 'exists:agents,id'],
            
            // Localização
            'country' => ['nullable', 'string', 'max:100'],
            'id_district' => ['nullable', 'integer'],
            'id_city' => ['nullable', 'integer'],
            'id_parish' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:255'],
            'door' => ['nullable', 'string', 'max:10'],
            'floor_address' => ['nullable', 'string', 'max:10'],
            'side' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            
            // Características
            'property_typology_id' => ['nullable', 'exists:property_typologies,id'],
            'area_total' => ['nullable', 'numeric', 'min:0'],
            'area_private' => ['nullable', 'numeric', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'garages' => ['nullable', 'integer', 'min:0'],
            'parking_spaces' => ['nullable', 'integer', 'min:0'],
            'floor' => ['nullable', 'integer'],
            'year_built' => ['nullable', 'integer', 'min:1000', 'max:' . (date('Y') + 1)],
            'energy_certificate' => ['nullable', Rule::in(array_keys(Property::energyCertificates()))],
            'features' => ['nullable', 'array'],
            'features.*' => ['exists:property_features,id'],
            
            // Detalhes
            'orientation' => ['nullable', Rule::in(array_keys(Property::orientations()))],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'O título é obrigatório.',
            'status.required' => 'O estado é obrigatório.',
            'transaction_type_id.required' => 'O negócio é obrigatório.',
            'transaction_type_id.exists' => 'O negócio selecionado é inválido.',
            'property_type_id.exists' => 'O tipo de imóvel selecionado é inválido.',
            'property_typology_id.exists' => 'A tipologia selecionada é inválida.',
            'reference.unique' => 'Esta referência já existe.',
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
                'reference' => Property::generateReference(),
            ]);
        }
        
        // Gerar título automático se não fornecido
        if (!$this->has('title') || empty($this->title)) {
            $title = 'Imóvel';
            if ($this->has('property_typology_id') && $this->property_typology_id) {
                $typology = \App\Models\PropertyTypology::find($this->property_typology_id);
                if ($typology) {
                    $title = $typology->name;
                }
            }
            if ($this->has('address') && $this->address) {
                $title .= ' - ' . $this->address;
            }
            $this->merge([
                'title' => $title,
            ]);
        }
    }
}
