<?php

namespace App\View\Composers;

use App\Models\CrmSetting;
use App\Models\Store;
use App\Support\BookingTheme;
use App\Support\CurrentStore;
use Illuminate\View\View;

/**
 * Dados da loja (BD) para todas as vistas do fluxo /booking.
 */
final class BookingStoreComposer
{
    public function compose(View $view): void
    {
        if ($view->offsetExists('bookingStore') && $view->offsetGet('bookingStore') instanceof Store) {
            $this->apply($view, $view->offsetGet('bookingStore'));

            return;
        }

        $store = app(CurrentStore::class)->tryGet();
        if (! $store instanceof Store) {
            $slug = trim((string) ($view->offsetGet('bookingStoreSlug') ?? ''));
            if ($slug === '') {
                $slug = Store::defaultPublicBookingStoreSlug();
            }
            $store = Store::query()->where('slug', $slug)->first();
        }

        if ($store instanceof Store) {
            $this->apply($view, $store);
        }
    }

    private function apply(View $view, Store $store): void
    {
        $resolvedTheme = BookingTheme::resolve(CrmSetting::getString(
            CrmSetting::KEY_BOOKING_THEME,
            BookingTheme::DEFAULT,
            $store->id,
        ), $store->id);

        $view->with([
            'bookingStore' => $store,
            'bookingStoreSlug' => $store->slug,
            'businessName' => $store->name,
            'bookingStoreProfile' => $store->publicBookingProfile(),
            'bookingWeeklySchedule' => $store->normalizedWeeklySchedule(),
            'bookingTheme' => $resolvedTheme,
            'bookingThemeMeta' => BookingTheme::resolved($store->id),
            'bookingUsesRefinedLayout' => BookingTheme::usesRefinedLayout($resolvedTheme),
            'bookingIsElegantTheme' => BookingTheme::usesRefinedLayout($resolvedTheme),
        ]);
    }
}
