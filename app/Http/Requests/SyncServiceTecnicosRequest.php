<?php

namespace App\Http\Requests;

use App\Models\Agent;
use App\Models\User;
use App\Support\StoreContextPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncServiceTecnicosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function resolvedStoreId(): int
    {
        $user = $this->user();
        if ($user instanceof User) {
            $accessibleIds = $user->accessibleStores()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $fromRequest = StoreContextPreference::parseStoreIdParam(
                $this->input(StoreContextPreference::QUERY_STORE)
            );
            if ($fromRequest !== null && in_array($fromRequest, $accessibleIds, true)) {
                return $fromRequest;
            }
        }

        return (int) current_store_id();
    }

    /**
     * @return list<int>
     */
    protected function allowedTechnicianAgentIds(): array
    {
        return Agent::query()
            ->forStore($this->resolvedStoreId())
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
            StoreContextPreference::QUERY_STORE => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'assignments.*.*.in' => 'Seleção de técnico inválida.',
        ];
    }
}
