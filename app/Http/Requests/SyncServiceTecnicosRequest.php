<?php

namespace App\Http\Requests;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncServiceTecnicosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return list<int>
     */
    protected function allowedTechnicianAgentIds(): array
    {
        return Agent::query()
            ->forStore(current_store_id())
            ->whereHas('user', fn ($q) => $q->whereIn('role', User::serviceProviderRoles()))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['nullable', 'array'],
            'assignments.*' => ['array'],
            'assignments.*.*' => ['integer', Rule::in($this->allowedTechnicianAgentIds())],
        ];
    }

    public function messages(): array
    {
        return [
            'assignments.*.*.in' => 'Seleção de técnico inválida.',
        ];
    }
}
