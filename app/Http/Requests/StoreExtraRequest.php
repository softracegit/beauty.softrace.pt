<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extra_category_id' => ['required', Rule::exists('extra_categories', 'id')->where(fn ($q) => $q->where('organization_id', current_organization_id()))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'integer', 'min:0'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => [Rule::exists('services', 'id')->where(fn ($q) => $q->where('organization_id', current_organization_id()))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
