<?php

namespace App\Http\Requests;

use App\Models\ExtraCategory;
use App\Models\Agent;
use App\Models\User;
use App\Support\ServiceOptionValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_options' => $this->boolean('has_options'),
        ]);
        $options = $this->input('options');
        if (! is_array($options)) {
            return;
        }
        foreach ($options as $k => $row) {
            if (! is_array($row)) {
                continue;
            }
            $options[$k]['is_baseline'] = filter_var($row['is_baseline'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        $this->merge(['options' => $options]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasOptions = $this->boolean('has_options');

        $rules = [
            'category_id' => ['required', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'has_options' => ['sometimes', 'boolean'],
            'sync_extras' => ['sometimes', 'boolean'],
            'sync_fees' => ['sometimes', 'boolean'],
            'agent_ids' => ['nullable', 'array'],
            'agent_ids.*' => [
                'integer',
                Rule::exists('agents', 'id')->where(function ($query): void {
                    $query->where('store_id', current_store_id())
                        ->where('status', Agent::STATUS_ACTIVE)
                        ->whereIn('user_id', function ($q): void {
                            $q->select('id')
                                ->from((new User)->getTable())
                                ->whereIn('role', User::serviceProviderRoles());
                        });
                }),
            ],
            'extra_ids' => ['nullable', 'array'],
            'extra_ids.*' => [
                Rule::exists('extras', 'id')->where(fn ($q) => $q->whereIn(
                    'extra_category_id',
                    ExtraCategory::query()->forStore(current_store_id())->select('id')
                )),
            ],
            'fee_ids' => ['nullable', 'array'],
            'fee_ids.*' => [Rule::exists('fees', 'id')->where(fn ($q) => $q->where('store_id', current_store_id()))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'hidden_from_booking' => ['nullable', 'boolean'],
        ];

        if ($hasOptions) {
            $rules['duration'] = ['required', 'integer', 'min:1'];
            $rules['price'] = ['required', 'numeric', 'min:0'];
            $rules['online_price'] = ['required', 'numeric', 'min:0', 'lte:price'];
            $rules['options'] = ['required', 'array', 'min:2'];
            $rules = array_merge($rules, ServiceOptionValidation::optionRowRules('options'));
            $rules['options.*.is_baseline'] = ['sometimes', 'boolean'];
        } else {
            $rules['duration'] = ['required', 'integer', 'min:1'];
            $rules['price'] = ['required', 'numeric', 'min:0'];
            $rules['online_price'] = ['nullable', 'numeric', 'min:0', 'lte:price'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return array_merge([
            'online_price.lte' => 'O preço online não pode ser superior ao preço normal.',
            'online_price.required' => 'Com variantes, o preço online do serviço base é obrigatório.',
            'options.required' => 'Indique as opções do serviço.',
            'options.min' => 'Serviços com variantes precisam de pelo menos duas opções (incluindo a base).',
        ], ServiceOptionValidation::optionRowMessages('options'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('has_options')) {
                return;
            }
            $options = $this->input('options', []);
            if (! is_array($options)) {
                return;
            }
            $baselineCount = 0;
            foreach ($options as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (! empty($row['is_baseline'])) {
                    $baselineCount++;
                }
            }
            if ($baselineCount !== 1) {
                $validator->errors()->add('options', 'Deve existir exatamente uma opção base (duração e preços alinhados ao serviço no catálogo).');
            }
            try {
                ServiceOptionValidation::assertOnlinePriceNotAbovePrice($options);
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceAttributes(): array
    {
        $only = $this->safe()->only([
            'category_id',
            'name',
            'description',
            'duration',
            'price',
            'online_price',
            'sort_order',
        ]);

        $attrs = is_array($only) ? $only : $only->all();
        $attrs['hidden_from_booking'] = $this->boolean('hidden_from_booking');

        return $attrs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function optionRows(): array
    {
        $raw = $this->input('options', []);

        return is_array($raw) ? array_values($raw) : [];
    }
}
