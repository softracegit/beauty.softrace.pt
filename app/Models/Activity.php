<?php

namespace App\Models;

use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::creating(function (self $activity) {
            if ($activity->store_id !== null) {
                return;
            }
            [$storeId, $organizationId] = self::resolveStoreAndOrganizationIds($activity);
            if ($storeId !== null) {
                $activity->store_id = $storeId;
            }
            if ($organizationId !== null) {
                $activity->organization_id = $organizationId;
            }
        });
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    protected static function resolveStoreAndOrganizationIds(self $activity): array
    {
        $current = app(CurrentStore::class);
        $tryStoreId = $current->tryId();
        if ($tryStoreId !== null) {
            try {
                $store = $current->get();

                return [(int) $store->getKey(), (int) $store->organization_id];
            } catch (\Throwable) {
            }
        }

        $subject = $activity->subject;
        if ($subject instanceof Model) {
            return self::inferFromSubject($subject);
        }

        return [null, null];
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    protected static function inferFromSubject(Model $subject): array
    {
        $rawStoreId = $subject->getAttribute('store_id');
        if ($rawStoreId !== null && $rawStoreId !== '') {
            return self::pairForStoreId((int) $rawStoreId);
        }

        if ($subject instanceof ServiceOption) {
            $service = $subject->relationLoaded('service')
                ? $subject->getRelation('service')
                : $subject->service()->select(['id', 'store_id'])->first();
            if ($service instanceof Service && $service->store_id) {
                return self::pairForStoreId((int) $service->store_id);
            }
        }

        if ($subject instanceof Extra) {
            $category = $subject->relationLoaded('extraCategory')
                ? $subject->getRelation('extraCategory')
                : $subject->extraCategory()->select(['id', 'store_id'])->first();
            if ($category instanceof ExtraCategory && $category->store_id) {
                return self::pairForStoreId((int) $category->store_id);
            }
        }

        return [null, null];
    }

    /**
     * @return array{0: int, 1: ?int}
     */
    protected static function pairForStoreId(int $storeId): array
    {
        $organizationId = Store::query()->whereKey($storeId)->value('organization_id');

        return [$storeId, $organizationId !== null ? (int) $organizationId : null];
    }
}
