<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeeRequest;
use App\Http\Requests\UpdateFeeRequest;
use App\Models\Fee;
use App\Support\ServiceCategoriesForAssociation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeeController extends Controller
{
    public function index(): View
    {
        $fees = Fee::forStore(current_store_id())
            ->withCount('services')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $association = ServiceCategoriesForAssociation::forStore();

        return view('fees.index', [
            'fees' => $fees,
            'serviceCategories' => $association['categories'],
        ]);
    }

    public function store(StoreFeeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $serviceIds = $data['service_ids'] ?? null;
        unset($data['service_ids']);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (Fee::forStore(current_store_id())->max('sort_order') ?? 0) + 1;
        }
        $data['store_id'] = current_store_id();

        $fee = Fee::create($data);
        if ($serviceIds !== null) {
            $fee->services()->sync($serviceIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Taxa criada com sucesso.',
            'fee' => $fee->load('services'),
        ]);
    }

    public function show(Request $request, Fee $fee): JsonResponse
    {
        $fee->load('services');

        return response()->json([
            'id' => $fee->id,
            'name' => $fee->name,
            'price' => (float) $fee->price,
            'sort_order' => (int) $fee->sort_order,
            'service_ids' => $fee->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ]);
    }

    public function update(UpdateFeeRequest $request, Fee $fee): JsonResponse
    {
        $data = $request->validated();
        $serviceIds = $data['service_ids'] ?? null;
        unset($data['service_ids']);

        $fee->update($data);
        if ($serviceIds !== null) {
            $fee->services()->sync($serviceIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Taxa atualizada com sucesso.',
            'fee' => $fee->fresh()->load('services'),
        ]);
    }

    public function destroy(Fee $fee): JsonResponse
    {
        $fee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Taxa eliminada com sucesso.',
        ]);
    }
}
