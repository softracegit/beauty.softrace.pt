<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Store;
use App\Models\User;
use App\Rules\ClientFullName;
use App\Services\CancellationPolicyService;
use App\Support\Api\ClientApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Conta do cliente na app: dados pessoais e lista de marcações.
 * Email e telemóvel não se mudam aqui (exigem verificação, como no booking web).
 */
class ClientAccountController extends Controller
{
    /** GET /api/v1/stores/{store}/me */
    public function show(Request $request, Store $store): JsonResponse
    {
        return response()->json(['data' => ClientApiPresenter::customer($this->user($request))]);
    }

    /** PATCH /api/v1/stores/{store}/me  { name, birth_date?, gender? } */
    public function update(Request $request, Store $store): JsonResponse
    {
        $user = $this->user($request);
        $client = $this->client($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new ClientFullName(__('booking.validation.name_full_required'))],
            'birth_date' => ['nullable', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'in:M,F,O'],
        ]);

        $client->name = trim($validated['name']);
        if (array_key_exists('birth_date', $validated)) {
            $client->birth_date = $validated['birth_date'];
        }
        if (array_key_exists('gender', $validated)) {
            $client->gender = $validated['gender'];
        }
        $client->save();

        if ($user->name !== $client->name) {
            $user->name = $client->name;
            $user->save();
        }

        return response()->json(['data' => ClientApiPresenter::customer($user->fresh('client'))]);
    }

    /** GET /api/v1/stores/{store}/me/bookings */
    public function bookings(Request $request, Store $store, CancellationPolicyService $policies): JsonResponse
    {
        $client = $this->client($this->user($request));

        $events = CalendarEvent::query()
            ->where('store_id', $store->id)
            ->where('client_id', $client->id)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->with([
                'user:id,name',
                'eventServiceItems.service:id,name,category_id',
                'eventServiceItems.service.category:id,name',
                'eventServiceItems.serviceOption:id,name',
                'eventServiceItems.extras' => fn ($q) => $q->orderBy('sort_order'),
                'eventServiceItems.extras.extra:id,name',
            ])
            ->orderByDesc('start_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $events->map(fn (CalendarEvent $e) => ClientApiPresenter::booking($e, $policies))->values(),
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function client(User $user): Client
    {
        $client = $user->loadMissing('client')->client;
        abort_unless($client instanceof Client, 404);

        return $client;
    }
}
