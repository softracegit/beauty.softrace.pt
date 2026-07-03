<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;
use App\Models\ZappyImportRef;
use App\Support\ClientNameNormalizer;
use App\Support\PhoneDisplay;
use Illuminate\Support\Collection;

class ClientDuplicateAuditService
{
    /**
     * @return Collection<int, object{
     *   client_a_id: int,
     *   client_b_id: int,
     *   name: string,
     *   client_a_phone: ?string,
     *   client_b_phone: ?string,
     *   phone_distance: ?int,
     *   reason: string,
     *   confidence: string,
     *   client_a_appointments: int,
     *   client_b_appointments: int,
     *   client_a_sales: int,
     *   client_b_sales: int,
     *   from_zappy: bool,
     * }>
     */
    public function findSuspects(
        ?int $storeId = null,
        int $maxPhoneDistance = 1,
        bool $includeMissingPhone = true,
    ): Collection {
        $storeId ??= current_store_id();

        $clients = Client::query()
            ->forStore($storeId)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->get(['id', 'name', 'phone', 'email', 'created_at']);

        if ($clients->count() < 2) {
            return collect();
        }

        $zappyClientIds = $this->zappyClientIds($storeId);
        $appointmentCounts = $this->appointmentCountsByClient($storeId);
        $saleCounts = $this->saleCountsByClient($storeId);

        $byMatchKey = $clients->groupBy(fn (Client $c) => ClientNameNormalizer::matchKey($c->name));

        $pairs = collect();
        $seenPairKeys = [];

        foreach ($byMatchKey as $matchKey => $group) {
            if ($group->count() < 2 || $matchKey === '') {
                continue;
            }

            $items = $group->values();
            for ($i = 0; $i < $items->count(); $i++) {
                for ($j = $i + 1; $j < $items->count(); $j++) {
                    /** @var Client $a */
                    $a = $items[$i];
                    /** @var Client $b */
                    $b = $items[$j];

                    $evaluation = $this->evaluatePair($a, $b, $maxPhoneDistance, $includeMissingPhone);
                    if ($evaluation === null) {
                        continue;
                    }

                    $pairKey = $this->pairKey((int) $a->id, (int) $b->id);
                    if (isset($seenPairKeys[$pairKey])) {
                        continue;
                    }
                    $seenPairKeys[$pairKey] = true;

                    $fromZappy = $zappyClientIds->contains((int) $a->id)
                        || $zappyClientIds->contains((int) $b->id);

                    $pairs->push((object) [
                        'client_a_id' => (int) $a->id,
                        'client_b_id' => (int) $b->id,
                        'name' => $a->name,
                        'client_a_phone' => $this->formatPhone($a->phone),
                        'client_b_phone' => $this->formatPhone($b->phone),
                        'phone_distance' => $evaluation['phone_distance'],
                        'reason' => $evaluation['reason'],
                        'confidence' => $evaluation['confidence'],
                        'client_a_appointments' => (int) ($appointmentCounts[(int) $a->id] ?? 0),
                        'client_b_appointments' => (int) ($appointmentCounts[(int) $b->id] ?? 0),
                        'client_a_sales' => (int) ($saleCounts[(int) $a->id] ?? 0),
                        'client_b_sales' => (int) ($saleCounts[(int) $b->id] ?? 0),
                        'from_zappy' => $fromZappy,
                    ]);
                }
            }
        }

        return $pairs
            ->sort(function (object $a, object $b): int {
                $rank = ['alta' => 0, 'media' => 1, 'baixa' => 2];
                $aRank = $rank[$a->confidence] ?? 3;
                $bRank = $rank[$b->confidence] ?? 3;
                if ($aRank !== $bRank) {
                    return $aRank <=> $bRank;
                }

                return strcasecmp($a->name, $b->name);
            })
            ->values();
    }

    /**
     * @return array{phone_distance: ?int, reason: string, confidence: string}|null
     */
    private function evaluatePair(
        Client $a,
        Client $b,
        int $maxPhoneDistance,
        bool $includeMissingPhone,
    ): ?array {
        $digitsA = $this->phoneDigits($a->phone);
        $digitsB = $this->phoneDigits($b->phone);

        if ($digitsA !== '' && $digitsB !== '') {
            $distance = levenshtein($digitsA, $digitsB);
            if ($distance <= $maxPhoneDistance) {
                return [
                    'phone_distance' => $distance,
                    'reason' => $distance === 0
                        ? 'Mesmo nome e telefone igual'
                        : 'Mesmo nome e telefone com '.$distance.' dígito(s) de diferença',
                    'confidence' => $distance === 0 ? 'alta' : ($distance === 1 ? 'alta' : 'media'),
                ];
            }
        }

        if ($includeMissingPhone && ($digitsA === '') !== ($digitsB === '')) {
            return [
                'phone_distance' => null,
                'reason' => 'Mesmo nome; um registo sem telemóvel (possível import Zappy)',
                'confidence' => 'media',
            ];
        }

        $emailA = $this->normalizeEmail($a->email);
        $emailB = $this->normalizeEmail($b->email);
        if ($emailA !== '' && $emailA === $emailB) {
            return [
                'phone_distance' => null,
                'reason' => 'Mesmo nome e email igual',
                'confidence' => 'alta',
            ];
        }

        return null;
    }

    private function phoneDigits(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        $e164 = PhoneDisplay::toE164($phone);

        return preg_replace('/\D+/', '', $e164 ?? $phone) ?? '';
    }

    private function formatPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        return PhoneDisplay::formatInternational($phone) ?? trim($phone);
    }

    private function normalizeEmail(?string $email): string
    {
        if ($email === null || trim($email) === '') {
            return '';
        }

        return mb_strtolower(trim($email), 'UTF-8');
    }

    private function pairKey(int $aId, int $bId): string
    {
        $min = min($aId, $bId);
        $max = max($aId, $bId);

        return $min.':'.$max;
    }

    /**
     * @return Collection<int, int>
     */
    private function zappyClientIds(int $storeId): Collection
    {
        return ZappyImportRef::query()
            ->where('store_id', $storeId)
            ->where('entity_type', ZappyImportRef::TYPE_CLIENT)
            ->pluck('local_id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * @return array<int, int>
     */
    private function appointmentCountsByClient(int $storeId): array
    {
        return CalendarEvent::query()
            ->forStore($storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNotNull('client_id')
            ->selectRaw('client_id, COUNT(*) as total')
            ->groupBy('client_id')
            ->pluck('total', 'client_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function saleCountsByClient(int $storeId): array
    {
        return Sale::query()
            ->where('store_id', $storeId)
            ->whereNotNull('client_id')
            ->selectRaw('client_id, COUNT(*) as total')
            ->groupBy('client_id')
            ->pluck('total', 'client_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
