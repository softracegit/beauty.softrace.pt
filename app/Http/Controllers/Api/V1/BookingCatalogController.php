<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Category;
use App\Models\CrmSetting;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Store;
use App\Support\AgentServiceExtras;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API v1 (só leitura) para a app móvel de marcações.
 *
 * Segue as mesmas regras do booking web: categorias/serviços ocultos não aparecem,
 * preço online quando existe, extras só os que alguma técnica visível faz, e a
 * disponibilidade é calculada pelo mesmo código (BookingController::availability).
 */
class BookingCatalogController extends Controller
{
    /** GET /api/v1/stores/{store} */
    public function store(Store $store): JsonResponse
    {
        $profile = $store->publicBookingProfile();
        $storeId = (int) $store->id;

        $otherStores = Store::query()
            ->where('organization_id', $store->organization_id)
            ->whereKeyNot($store->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Store $s): array => [
                'id' => (int) $s->id,
                'slug' => (string) $s->slug,
                'name' => (string) $s->name,
                'address' => $s->formattedAddress(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'id' => $storeId,
                'slug' => (string) $store->slug,
                'name' => (string) $store->name,
                'address' => (string) $profile['address'],
                'phone' => $profile['phone'] !== '' ? $profile['phone'] : null,
                'email' => $profile['email'] !== '' ? $profile['email'] : null,
                'maps_url' => ($profile['maps_url'] ?? '#') !== '#' && $profile['maps_url'] !== '' ? $profile['maps_url'] : null,
                'website_url' => $profile['website_url'] !== '#' ? $profile['website_url'] : null,
                'instagram_url' => $profile['instagram_url'] !== '#' ? $profile['instagram_url'] : null,
                'logo_url' => $profile['photo'] ?: null,
                'timezone' => (string) config('booking.business_timezone'),
                'weekly_schedule' => $store->normalizedWeeklySchedule(),
                'booking' => [
                    'prepayment_required' => CrmSetting::onlineBookingPaymentRequired($storeId),
                    'cancellation_notice_hours' => CrmSetting::bookingCancellationNoticeHours($storeId),
                    'cancellation_policy_text' => CrmSetting::bookingCancellationPolicyText($storeId),
                    'slot_interval_minutes' => CrmSetting::bookingSlotIntervalMinutes($storeId),
                ],
                'organization' => [
                    'name' => (string) ($store->organization->name ?? ''),
                ],
                'other_stores' => $otherStores,
            ],
        ]);
    }

    /** GET /api/v1/stores/{store}/catalog */
    public function catalog(Store $store): JsonResponse
    {
        $storeId = (int) $store->id;

        $categories = Category::query()
            ->forOrganization((int) $store->organization_id)
            ->visibleInBooking()
            ->with([
                'services' => function ($q) use ($storeId) {
                    $q->activeInStore($storeId)
                        ->visibleInBooking()
                        ->orderBy('sort_order')
                        ->with([
                            'options' => fn ($oq) => $oq->orderBy('sort_order'),
                            'extras' => fn ($eq) => $eq->orderBy('sort_order'),
                            'fees' => fn ($fq) => $fq->orderBy('sort_order')->orderBy('name'),
                        ]);
                },
            ])
            ->whereHas('services', fn ($q) => $q->activeInStore($storeId)->visibleInBooking())
            ->orderBy('sort_order')
            ->get();

        $bookableExtras = AgentServiceExtras::bookableExtraIdsByService($storeId);

        $data = $categories->map(fn (Category $category): array => [
            'id' => (int) $category->id,
            'name' => (string) $category->name,
            'services' => $category->services
                ->map(fn (Service $service): array => $this->servicePayload($service, $bookableExtras[(int) $service->id] ?? []))
                ->values(),
        ])->values();

        return response()->json(['data' => ['categories' => $data]]);
    }

    /** GET /api/v1/stores/{store}/technicians */
    public function technicians(Store $store): JsonResponse
    {
        $storeId = (int) $store->id;

        $technicians = Agent::query()
            ->where('store_id', $storeId)
            ->where('status', Agent::STATUS_ACTIVE)
            ->where('visible_in_booking', true)
            ->whereHas('user', fn ($q) => $q->eligibleForPublicBooking())
            ->withServicesForStore($storeId)
            ->orderBy('agenda_order')
            ->orderBy('name')
            ->get();

        $pairsByAgent = AgentServiceExtras::pairsGroupedByAgent(
            $technicians->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        $data = $technicians->map(fn (Agent $agent): array => [
            'id' => (int) $agent->id,
            'name' => (string) $agent->name,
            'specialization' => Agent::specializationLabel($agent->specialization),
            'avatar_url' => $agent->avatar ? asset('storage/'.$agent->avatar) : null,
            'service_ids' => $agent->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'extra_pairs' => collect($pairsByAgent[(int) $agent->id] ?? [])
                ->map(fn (array $p): array => ['service_id' => $p['serviceId'], 'extra_id' => $p['extraId']])
                ->values()
                ->all(),
        ])->values();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/stores/{store}/availability
     *
     * Query: from=AAAA-MM-DD (hoje por omissão), days=1..14 (7), agent_id=any|<id>,
     * duration=minutos, service_ids=1,2, extra_pairs=serviço:extra,...
     * Cada dia usa exatamente o cálculo do booking web.
     */
    public function availability(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:14'],
            'agent_id' => ['nullable', 'string', 'max:20'],
            'duration' => ['required', 'integer', 'min:5', 'max:1440'],
            'service_ids' => ['nullable', 'string', 'max:500'],
            'extra_pairs' => ['nullable', 'string', 'max:1000'],
        ]);

        $tz = (string) config('booking.business_timezone');
        $from = isset($validated['from'])
            ? Carbon::createFromFormat('Y-m-d', $validated['from'], $tz)->startOfDay()
            : now($tz)->startOfDay();
        $days = (int) ($validated['days'] ?? 7);

        $web = app(BookingController::class);
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->format('Y-m-d');
            $dayRequest = Request::create('/', 'GET', [
                'date' => $date,
                'agent_id' => $validated['agent_id'] ?? 'any',
                'duration' => (int) $validated['duration'],
                'service_ids' => $validated['service_ids'] ?? '',
                'extra_pairs' => $validated['extra_pairs'] ?? '',
            ]);
            $slots = $web->availability($dayRequest)->getData(true)['slots'] ?? [];
            $out[] = ['date' => $date, 'slots' => array_values($slots)];
        }

        return response()->json(['data' => $out]);
    }

    /**
     * @param  list<int>  $allowedExtraIds
     */
    private function servicePayload(Service $service, array $allowedExtraIds): array
    {
        return [
            'id' => (int) $service->id,
            'name' => (string) $service->name,
            'description' => $service->description ? (string) $service->description : null,
            'duration' => (int) ($service->duration ?? 0),
            'price' => round((float) ($service->online_price ?? $service->price), 2),
            'options' => $service->options->map(fn (ServiceOption $o): array => [
                'id' => (int) $o->id,
                'name' => (string) $o->name,
                'description' => $o->description ? (string) $o->description : null,
                'type' => ServiceOption::normalizeType($o->type),
                'is_baseline' => (bool) $o->is_baseline,
                'duration' => (int) $o->duration,
                'price' => round((float) ($o->online_price ?? $o->price), 2),
            ])->values(),
            'extras' => $service->extras
                ->filter(fn ($e): bool => in_array((int) $e->id, $allowedExtraIds, true))
                ->map(fn ($e): array => [
                    'id' => (int) $e->id,
                    'name' => (string) $e->name,
                    'duration' => (int) ($e->duration ?? 0),
                    'price' => round((float) $e->price, 2),
                ])->values(),
            'fees' => $service->fees->map(fn ($f): array => [
                'id' => (int) $f->id,
                'name' => (string) $f->name,
                'price' => round((float) $f->price, 2),
            ])->values(),
        ];
    }
}
