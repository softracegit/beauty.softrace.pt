<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CrmSetting;
use App\Models\Store;
use App\Models\User;
use App\Support\BookingTheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Definições operacionais por loja (Booking, flags de pagamento, tempo pessoal)
 * e aplicação em massa a todas as lojas da organização (menus Definições).
 */
class StoreOperationalSettingsService
{
    /**
     * @return list<int>
     */
    public function storeIdsForOrganization(int $organizationId): array
    {
        return Store::query()
            ->where('organization_id', $organizationId)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function representativeStoreId(int $organizationId): ?int
    {
        $ids = $this->storeIdsForOrganization($organizationId);

        return $ids[0] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function bookingViewData(int $storeId): array
    {
        return [
            'bookingSlotHoldMinutes' => CrmSetting::bookingSlotHoldMinutes($storeId),
            'bookingCartTtlHours' => CrmSetting::bookingCartTtlHours($storeId),
            'bookingSlotIntervalMinutes' => CrmSetting::bookingSlotIntervalMinutes($storeId),
            'bookingSlotIntervalOptions' => CrmSetting::BOOKING_SLOT_INTERVAL_MINUTES_OPTIONS,
            'bookingCancellationNoticeHours' => CrmSetting::bookingCancellationNoticeHours($storeId),
            'bookingAnyStaffRules' => CrmSetting::bookingAnyStaffRulesUi(),
            'bookingAnyStaffRule' => CrmSetting::bookingAnyStaffRule($storeId),
            'bookingTheme' => CrmSetting::bookingTheme($storeId),
            'bookingThemes' => BookingTheme::registry(),
        ];
    }

    /**
     * @return array{
     *   booking_slot_hold_minutes: int,
     *   booking_cart_ttl_hours: int,
     *   booking_slot_interval_minutes: int,
     *   booking_cancellation_notice_hours: int,
     *   booking_any_staff_rule: string,
     *   booking_theme: string|null
     * }
     */
    public function validateBooking(Request $request, int $fallbackStoreIdForRule): array
    {
        $validated = $request->validate([
            'booking_slot_hold_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'booking_cart_ttl_hours' => [
                'required',
                'integer',
                'min:'.CrmSetting::BOOKING_CART_TTL_HOURS_MIN,
                'max:'.CrmSetting::BOOKING_CART_TTL_HOURS_MAX,
            ],
            'booking_slot_interval_minutes' => [
                'required',
                'integer',
                'in:'.implode(',', CrmSetting::BOOKING_SLOT_INTERVAL_MINUTES_OPTIONS),
            ],
            'booking_cancellation_notice_hours' => [
                'required',
                'integer',
                'min:'.CrmSetting::BOOKING_CANCELLATION_NOTICE_HOURS_MIN,
                'max:'.CrmSetting::BOOKING_CANCELLATION_NOTICE_HOURS_MAX,
            ],
            'booking_any_staff_rule' => ['nullable', 'string', 'in:'.implode(',', array_keys(CrmSetting::bookingAnyStaffRules()))],
            'booking_any_staff_rule_options' => ['nullable', 'array', 'min:1', 'max:1'],
            'booking_any_staff_rule_options.*' => ['string', 'in:'.implode(',', array_keys(CrmSetting::bookingAnyStaffRules()))],
            'booking_theme' => ['nullable', 'string', 'in:'.implode(',', array_keys(BookingTheme::registry()))],
        ], [
            'booking_slot_hold_minutes.min' => 'O tempo de reserva deve ser pelo menos 1 minuto.',
            'booking_slot_hold_minutes.max' => 'O tempo de reserva não pode exceder 240 minutos.',
            'booking_cart_ttl_hours.min' => 'O tempo de memória do carrinho deve ser pelo menos 1 hora.',
            'booking_cart_ttl_hours.max' => 'O tempo de memória do carrinho não pode exceder 168 horas (7 dias).',
            'booking_slot_interval_minutes.in' => 'Escolha um intervalo de blocos válido.',
            'booking_cancellation_notice_hours.min' => 'O aviso mínimo não pode ser negativo.',
            'booking_cancellation_notice_hours.max' => 'O aviso mínimo não pode exceder 168 horas (7 dias).',
            'booking_any_staff_rule_options.max' => 'Selecione apenas uma regra de atribuição.',
        ]);

        $selectedRule = null;
        if (! empty($validated['booking_any_staff_rule_options']) && is_array($validated['booking_any_staff_rule_options'])) {
            $selectedRule = (string) ($validated['booking_any_staff_rule_options'][0] ?? '');
        } elseif (! empty($validated['booking_any_staff_rule'])) {
            $selectedRule = (string) $validated['booking_any_staff_rule'];
        }
        if ($selectedRule === null || $selectedRule === '') {
            $selectedRule = CrmSetting::bookingAnyStaffRule($fallbackStoreIdForRule);
        }

        return [
            'booking_slot_hold_minutes' => (int) $validated['booking_slot_hold_minutes'],
            'booking_cart_ttl_hours' => (int) $validated['booking_cart_ttl_hours'],
            'booking_slot_interval_minutes' => (int) $validated['booking_slot_interval_minutes'],
            'booking_cancellation_notice_hours' => (int) $validated['booking_cancellation_notice_hours'],
            'booking_any_staff_rule' => $selectedRule,
            'booking_theme' => array_key_exists('booking_theme', $validated)
                ? (string) $validated['booking_theme']
                : null,
        ];
    }

    /**
     * @param  array{
     *   booking_slot_hold_minutes: int,
     *   booking_cart_ttl_hours: int,
     *   booking_slot_interval_minutes: int,
     *   booking_cancellation_notice_hours: int,
     *   booking_any_staff_rule: string,
     *   booking_theme: string|null
     * }  $data
     */
    public function applyBookingToStore(array $data, int $storeId): void
    {
        CrmSetting::setInt(CrmSetting::KEY_BOOKING_SLOT_HOLD_MINUTES, $data['booking_slot_hold_minutes'], $storeId);
        CrmSetting::setBookingCartTtlHours($data['booking_cart_ttl_hours'], $storeId);
        CrmSetting::setInt(CrmSetting::KEY_BOOKING_SLOT_INTERVAL_MINUTES, $data['booking_slot_interval_minutes'], $storeId);
        CrmSetting::setBookingCancellationNoticeHours($data['booking_cancellation_notice_hours'], $storeId);
        CrmSetting::setString(CrmSetting::KEY_BOOKING_ANY_STAFF_RULE, $data['booking_any_staff_rule'], $storeId);
        if ($data['booking_theme'] !== null) {
            CrmSetting::setBookingTheme($data['booking_theme'], $storeId);
        }
    }

    /**
     * @param  array{
     *   booking_slot_hold_minutes: int,
     *   booking_cart_ttl_hours: int,
     *   booking_slot_interval_minutes: int,
     *   booking_cancellation_notice_hours: int,
     *   booking_any_staff_rule: string,
     *   booking_theme: string|null
     * }  $data
     */
    public function applyBookingToOrganization(array $data, int $organizationId): void
    {
        foreach ($this->storeIdsForOrganization($organizationId) as $storeId) {
            $this->applyBookingToStore($data, $storeId);
        }
    }

    /**
     * @return array{onlineBookingPaymentRequired: bool, posGorjetaEnabled: bool}
     */
    public function paymentFlagsViewData(int $storeId): array
    {
        return [
            'onlineBookingPaymentRequired' => CrmSetting::onlineBookingPaymentRequired($storeId),
            'posGorjetaEnabled' => CrmSetting::posGorjetaEnabled($storeId),
        ];
    }

    public function applyPaymentFlagsToStore(bool $posGorjetaEnabled, bool $onlineBookingPaymentRequired, int $storeId): void
    {
        CrmSetting::setBool(CrmSetting::KEY_POS_GORJETA_ENABLED, $posGorjetaEnabled, $storeId);
        CrmSetting::setBool(CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED, $onlineBookingPaymentRequired, $storeId);
    }

    public function applyPaymentFlagsToOrganization(bool $posGorjetaEnabled, bool $onlineBookingPaymentRequired, int $organizationId): void
    {
        foreach ($this->storeIdsForOrganization($organizationId) as $storeId) {
            $this->applyPaymentFlagsToStore($posGorjetaEnabled, $onlineBookingPaymentRequired, $storeId);
        }
    }

    public function applyPersonalTimeLimitToStore(bool $limit, int $storeId): void
    {
        CrmSetting::setPersonalTimeLimitStoreHours($limit, $storeId);
    }

    public function applyPersonalTimeLimitToOrganization(bool $limit, int $organizationId): void
    {
        foreach ($this->storeIdsForOrganization($organizationId) as $storeId) {
            $this->applyPersonalTimeLimitToStore($limit, $storeId);
        }
    }

    /**
     * @return array{agents: \Illuminate\Support\Collection, personalTimeLimitStoreHours: bool, storeHoursLabel: string, roles: array<string, string>, showStoreColumn: bool}
     */
    public function equipaViewDataForStore(Store $store): array
    {
        $storeId = (int) $store->id;
        $agents = Agent::query()
            ->activeTeamMembers($storeId)
            ->with(['user:id,name,role', 'store:id,name'])
            ->orderBy('agenda_order')
            ->orderBy('name')
            ->get();

        return [
            'agents' => $agents,
            'roles' => User::roles(),
            'personalTimeLimitStoreHours' => CrmSetting::personalTimeLimitStoreHours($storeId),
            'storeHoursLabel' => $store->hoursDisplayLabel(),
            'showStoreColumn' => false,
        ];
    }

    /**
     * @return array{agents: \Illuminate\Support\Collection, personalTimeLimitStoreHours: bool, storeHoursLabel: string, roles: array<string, string>, showStoreColumn: bool}
     */
    public function equipaViewDataForOrganization(int $organizationId): array
    {
        $storeIds = $this->storeIdsForOrganization($organizationId);
        $repId = $storeIds[0] ?? 0;
        $repStore = $repId > 0 ? Store::query()->find($repId) : null;

        $agents = Agent::query()
            ->whereIn('store_id', $storeIds)
            ->where('status', '!=', Agent::STATUS_INACTIVE)
            ->with(['user:id,name,role', 'store:id,name'])
            ->orderBy('store_id')
            ->orderBy('agenda_order')
            ->orderBy('name')
            ->get();

        return [
            'agents' => $agents,
            'roles' => User::roles(),
            'personalTimeLimitStoreHours' => $repId > 0
                ? CrmSetting::personalTimeLimitStoreHours($repId)
                : false,
            'storeHoursLabel' => $repStore?->hoursDisplayLabel() ?? '09:00–20:00',
            'showStoreColumn' => true,
        ];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $members
     * @param  list<int>  $allowedStoreIds
     */
    public function applyEquipaMembers(array $members, array $allowedStoreIds): void
    {
        foreach ($members as $agentId => $row) {
            $isVisibleInAgenda = (bool) ($row['visible_in_agenda'] ?? false);
            $agendaOrder = $row['agenda_order'] ?? null;
            if ($isVisibleInAgenda && ($agendaOrder === null || $agendaOrder === '')) {
                throw ValidationException::withMessages([
                    "members.$agentId.agenda_order" => 'Indique a ordem na agenda para membros visíveis na agenda.',
                ]);
            }
        }

        $agentIds = array_map('intval', array_keys($members));
        if ($agentIds === []) {
            return;
        }

        $agents = Agent::query()
            ->whereIn('store_id', $allowedStoreIds)
            ->with('user:id,role')
            ->whereIn('id', $agentIds)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($members, $agents): void {
            foreach ($members as $agentId => $row) {
                $agent = $agents->get((int) $agentId);
                if (! $agent || ! $agent->user) {
                    continue;
                }

                $role = (string) ($row['role'] ?? $agent->user->role);
                if ($agent->user->role !== $role) {
                    $agent->user->role = $role;
                    $agent->user->save();
                }

                $visibleAgenda = (bool) ($row['visible_in_agenda'] ?? false);
                $visibleBooking = (bool) ($row['visible_in_booking'] ?? false);
                $agendaOrder = $visibleAgenda ? (int) ($row['agenda_order'] ?? 0) : 0;

                $agent->update([
                    'visible_in_agenda' => $visibleAgenda,
                    'visible_in_booking' => $visibleBooking,
                    'agenda_order' => $agendaOrder,
                ]);
            }
        });
    }
}
