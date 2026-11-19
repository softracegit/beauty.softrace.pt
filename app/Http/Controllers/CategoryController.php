<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\CategoryClinicalField;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Fee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(Request $request)
    {
        $organizationId = current_organization_id();

        $archivedServicesCount = Service::forOrganization($organizationId)->onlyTrashed()->count();

        // Only return JSON if explicitly requested via AJAX (lista + counts para badges)
        if ($request->ajax() && $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $categories = Category::forOrganization($organizationId)->orderBy('sort_order')->withCount('services')->get();

            return response()->json([
                'categories' => $categories,
                'archived_services_count' => $archivedServicesCount,
            ]);
        }

        $selectedCategory = null; // por defeito: "Todas as categorias"
        $categories = Category::forOrganization($organizationId)->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q
                ->with([
                    'agents' => fn ($aq) => $aq->with(['store:id,name'])->orderBy('agents.name'),
                    'extras',
                    'fees',
                    'options',
                ])
                ->withCount(['extras', 'fees', 'agents'])
                ->orderBy('sort_order')])
            ->withCount('services')
            ->get();
        $extras = Extra::query()
            ->whereHas('extraCategory', fn ($q) => $q->where('organization_id', $organizationId))
            ->with('extraCategory')
            ->orderBy('extra_category_id')
            ->orderBy('sort_order')
            ->get();
        $extraCategories = ExtraCategory::forOrganization($organizationId)->orderBy('sort_order')
            ->with(['extras' => fn ($q) => $q->orderBy('sort_order')])
            ->get();
        $fees = Fee::forOrganization($organizationId)->orderBy('sort_order')->orderBy('name')->get();

        return view('services.index', [
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'extras' => $extras,
            'extraCategories' => $extraCategories,
            'fees' => $fees,
            'archivedServicesCount' => $archivedServicesCount,
        ]);
    }

    /**
     * Display the specified category (for AJAX)
     */
    public function show(Request $request, Category $category)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $category->load(['clinicalFields' => fn ($q) => $q->ordered()]);

            return response()->json(array_merge($category->toArray(), [
                'clinical_fields' => $category->clinicalFields->map(fn (CategoryClinicalField $f) => $f->toSchemaArray())->values()->all(),
            ]));
        }

        return redirect()->route('services.index');
    }

    /**
     * Store a newly created category
     */
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $data['hidden_from_booking'] = $request->boolean('hidden_from_booking');
        $organizationId = current_organization_id();

        // Set sort_order if not provided
        if (! isset($data['sort_order'])) {
            $maxOrder = Category::forOrganization($organizationId)->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
        }

        $data['organization_id'] = $organizationId;
        $category = Category::create($data);

        // Check if it's an AJAX request by checking headers
        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoria criada com sucesso.',
                'category' => $category,
            ]);
        }

        return redirect()->route('services.index')
            ->with('success', 'Categoria criada com sucesso.');
    }

    /**
     * Update the specified category
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();
        $data['hidden_from_booking'] = $request->boolean('hidden_from_booking');
        $category->update($data);

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso.',
                'category' => $category->fresh(),
            ]);
        }

        return redirect()->route('services.index')
            ->with('success', 'Categoria atualizada com sucesso.');
    }

    /**
     * Remove the specified category
     */
    public function destroy(Request $request, Category $category)
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->canDeleteCatalogServicesAndCategories()) {
            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sem permissão para eliminar categorias.',
                ], 403);
            }

            return redirect()->route('services.index')
                ->with('error', 'Sem permissão para eliminar categorias.');
        }

        // Inclui serviços no Arquivo: apagar a categoria faria cascade e apagava-os da BD.
        if ($category->services()->withTrashed()->count() > 0) {
            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível eliminar uma categoria que possui serviços (incluindo no Arquivo).',
                ], 422);
            }

            return redirect()->route('services.index')
                ->with('error', 'Não é possível eliminar uma categoria que possui serviços (incluindo no Arquivo).');
        }

        $category->delete();

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoria eliminada com sucesso.',
            ]);
        }

        return redirect()->route('services.index')
            ->with('success', 'Categoria eliminada com sucesso.');
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $organizationId = current_organization_id();
        $idsInOrg = Category::forOrganization($organizationId)
            ->whereIn('id', $request->order)
            ->pluck('id')
            ->count();
        if ($idsInOrg !== count($request->order)) {
            return response()->json([
                'success' => false,
                'message' => 'Ordem inválida para esta organização.',
            ], 422);
        }

        foreach ($request->order as $index => $categoryId) {
            Category::forOrganization($organizationId)->whereKey($categoryId)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordem das categorias atualizada com sucesso.',
        ]);
    }

    /**
     * List clinical fields for a category.
     */
    public function clinicalFieldsIndex(Category $category): JsonResponse
    {
        $fields = $category->clinicalFields()->ordered()->get()
            ->map(fn (CategoryClinicalField $f) => $f->toSchemaArray())
            ->values();

        return response()->json(['clinical_fields' => $fields]);
    }

    /**
     * Create a clinical field on a category.
     */
    public function clinicalFieldsStore(Request $request, Category $category): JsonResponse
    {
        $data = $this->validateClinicalField($request, $category);
        $maxOrder = (int) $category->clinicalFields()->max('sort_order');
        $field = $category->clinicalFields()->create([
            'organization_id' => (int) ($category->organization_id ?: current_organization_id()),
            'key' => $data['key'],
            'label' => $data['label'],
            'input_type' => $data['input_type'],
            'options' => $data['options'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'is_active' => $data['is_active'] ?? true,
            'is_required' => $data['is_required'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Campo criado.',
            'field' => $field->toSchemaArray(),
        ], 201);
    }

    /**
     * Update a clinical field.
     */
    public function clinicalFieldsUpdate(Request $request, Category $category, CategoryClinicalField $clinicalField): JsonResponse
    {
        $this->assertFieldBelongsToCategory($category, $clinicalField);
        $data = $this->validateClinicalField($request, $category, $clinicalField);
        // A chave é imutável após criar — preserva histórico nas fichas das marcações.
        $clinicalField->update([
            'label' => $data['label'],
            'input_type' => $data['input_type'],
            'options' => $data['options'] ?? null,
            'sort_order' => $data['sort_order'] ?? $clinicalField->sort_order,
            'is_active' => $data['is_active'] ?? $clinicalField->is_active,
            'is_required' => $data['is_required'] ?? $clinicalField->is_required,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Campo atualizado.',
            'field' => $clinicalField->fresh()->toSchemaArray(),
        ]);
    }

    /**
     * Soft-deactivate / delete clinical field (deactivate by default to preserve history keys).
     */
    public function clinicalFieldsDestroy(Category $category, CategoryClinicalField $clinicalField): JsonResponse
    {
        $this->assertFieldBelongsToCategory($category, $clinicalField);
        $clinicalField->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Campo desactivado.',
        ]);
    }

    /**
     * Reorder clinical fields.
     */
    public function clinicalFieldsReorder(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer'],
        ]);
        $ids = $category->clinicalFields()->whereIn('id', $request->order)->pluck('id')->all();
        if (count($ids) !== count($request->order)) {
            return response()->json(['success' => false, 'message' => 'Ordem inválida.'], 422);
        }
        foreach ($request->order as $index => $fieldId) {
            CategoryClinicalField::query()
                ->where('category_id', $category->id)
                ->whereKey($fieldId)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true, 'message' => 'Ordem atualizada.']);
    }

    private function assertFieldBelongsToCategory(Category $category, CategoryClinicalField $field): void
    {
        abort_unless((int) $field->category_id === (int) $category->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateClinicalField(Request $request, Category $category, ?CategoryClinicalField $existing = null): array
    {
        $keyRule = Rule::unique('category_clinical_fields', 'key')
            ->where(fn ($q) => $q->where('category_id', $category->id));
        if ($existing) {
            $keyRule = $keyRule->ignore($existing->id);
        }

        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', $keyRule],
            'label' => ['required', 'string', 'max:255'],
            'input_type' => ['required', Rule::in(CategoryClinicalField::inputTypes())],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
        ]);

        $label = trim((string) $data['label']);
        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            $key = Str::slug($label, '_');
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key)) ?: 'campo';
            if (! preg_match('/^[a-z]/', $key)) {
                $key = 'c_'.$key;
            }
            $base = $key;
            $i = 2;
            while (CategoryClinicalField::query()
                ->where('category_id', $category->id)
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->where('key', $key)
                ->exists()) {
                $key = $base.'_'.$i;
                $i++;
            }
            $data['key'] = $key;
        }

        $needsOptions = in_array($data['input_type'], [
            CategoryClinicalField::TYPE_SELECT,
            CategoryClinicalField::TYPE_MULTISELECT,
        ], true);
        $options = array_values(array_filter(array_map('trim', $data['options'] ?? []), fn ($o) => $o !== ''));
        if ($needsOptions && $options === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'options' => ['Indique pelo menos uma opção para este tipo de campo.'],
            ]);
        }
        $data['options'] = $needsOptions ? $options : null;
        $data['label'] = $label;
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : ($existing?->is_active ?? true);
        $data['is_required'] = $request->has('is_required') ? $request->boolean('is_required') : ($existing?->is_required ?? false);

        return $data;
    }
}
