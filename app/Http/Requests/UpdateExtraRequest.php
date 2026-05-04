<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExtraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extra_category_id' => ['required', Rule::exists('extra_categories', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'integer', 'min:0'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => [Rule::exists('services', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
