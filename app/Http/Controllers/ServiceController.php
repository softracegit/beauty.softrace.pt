<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Category;
use App\Models\Agent;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    /**
     * All services grouped by category (AJAX) – for "Todas as categorias"
     */
    public function allGrouped(Request $request): JsonResponse
    {
        $groups = Category::orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->with('agents')->withCount('extras')->orderBy('sort_order')])
            ->get()
            ->map(fn (Category $cat) => [
                'category' => $cat,
                'services' => $cat->services,
            ]);
        return response()->json(['groups' => $groups]);
    }

    /**
     * Display services for a specific category (AJAX)
     */
    public function index(Request $request, Category $category = null): JsonResponse
    {
        if (!$category) {
            return response()->json(['services' => [], 'category' => null]);
        }
        
        $services = $category->services()->with('agents')->withCount('extras')->orderBy('sort_order')->get();
        
        return response()->json([
            'services' => $services,
            'category' => $category,
        ]);
    }

    /**
     * Display the specified service (for AJAX)
     */
    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'service' => $service->load(['category', 'agents', 'extras']),
        ]);
    }

    /**
     * Store a newly created service
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // Set sort_order if not provided
        if (!isset($data['sort_order'])) {
            $maxOrder = Service::where('category_id', $data['category_id'])->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
        }
        
        $service = Service::create($data);
        
        // Sync agents if provided
        if ($request->has('agent_ids')) {
            $service->agents()->sync($request->agent_ids);
        }
        if ($request->has('extra_ids')) {
            $service->extras()->sync($request->extra_ids);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Serviço criado com sucesso.',
            'service' => $service->load(['category', 'agents', 'extras']),
        ]);
    }

    /**
     * Update the specified service
     */
    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $data = $request->validated();
        
        $service->update($data);
        
        // Sync agents if provided
        if ($request->has('agent_ids')) {
            $service->agents()->sync($request->agent_ids);
        } else {
            $service->agents()->sync([]);
        }
        if ($request->has('extra_ids')) {
            $service->extras()->sync($request->extra_ids);
        } else {
            $service->extras()->sync([]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Serviço atualizado com sucesso.',
            'service' => $service->fresh()->load(['category', 'agents', 'extras']),
        ]);
    }

    /**
     * Remove the specified service
     */
    public function destroy(Service $service): JsonResponse
    {
        $service->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Serviço eliminado com sucesso.',
        ]);
    }

    /**
     * Reorder services within a category
     */
    public function reorder(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:services,id'],
        ]);
        
        // Verify all services belong to the category
        $serviceIds = Service::whereIn('id', $request->order)
            ->where('category_id', $category->id)
            ->pluck('id')
            ->toArray();
        
        if (count($serviceIds) !== count($request->order)) {
            return response()->json([
                'success' => false,
                'message' => 'Alguns serviços não pertencem a esta categoria.',
            ], 422);
        }
        
        foreach ($request->order as $index => $serviceId) {
            Service::where('id', $serviceId)->update(['sort_order' => $index + 1]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Ordem dos serviços atualizada com sucesso.',
        ]);
    }
}
