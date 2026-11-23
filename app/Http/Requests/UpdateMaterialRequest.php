<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $orgId = current_organization_id();
        /** @var \App\Models\Material|null $material */
        $material = $this->route('material');

        return [
            'material_category_id' => [
                'required',
                'integer',
                Rule::exists('material_categories', 'id')->where(fn ($q) => $q->where('organization_id', $orgId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('materials', 'code')
                    ->where(fn ($q) => $q->where('organization_id', $orgId)->whereNull('deleted_at'))
                    ->ignore($material?->id),
            ],
            'brand' => ['nullable', 'string', 'max:128'],
            'finish' => ['nullable', 'string', Rule::in(Material::finishOptions())],
            'hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        if (is_string($code) && trim($code) === '') {
            $this->merge(['code' => null]);
        }
        $brand = $this->input('brand');
        if (is_string($brand) && trim($brand) === '') {
            $this->merge(['brand' => null]);
        }
        $finish = $this->input('finish');
        if (is_string($finish) && trim($finish) === '') {
            $this->merge(['finish' => null]);
        }
        $hex = $this->input('hex');
        if (is_string($hex) && trim($hex) === '') {
            $this->merge(['hex' => null]);
        }
        if ($this->has('stock') && (string) $this->input('stock') === '') {
            $this->merge(['stock' => null]);
        }
    }
}
