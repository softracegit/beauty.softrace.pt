<?php

namespace App\Http\Middleware;

use App\Models\CrmSetting;
use App\Models\Store;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contexto do fluxo público de marcação (booking): loja da URL, timezone, geração de rotas.
 */
class BookingContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $storeParam = $request->route('store');

        $store = $storeParam instanceof Store
            ? $storeParam
            : null;

        if ($store === null && (is_string($storeParam) || is_numeric($storeParam))) {
            $slug = trim((string) $storeParam);
            if ($slug !== '') {
                $store = Store::query()->where('slug', $slug)->first();
            }
        }

        if (! $store instanceof Store) {
            abort(404);
        }

        $request->route()?->setParameter('store', $store);

        $store->loadMissing('organization');
        if ($store->organization && strtolower((string) $store->organization->status) !== 'active') {
            abort(404);
        }

        app(CurrentStore::class)->set($store);

        $tz = is_string($store->timezone) ? trim($store->timezone) : '';
        if ($tz !== '') {
            try {
                new \DateTimeZone($tz);
                config(['booking.business_timezone' => $tz]);
            } catch (\Exception) {
                // Mantém config/booking.php se o valor na BD for inválido.
            }
        }

        URL::defaults(['store' => $store->slug]);

        View::share('bookingStoreSlug', $store->slug);
        View::share('bookingCancellationNoticeHours', CrmSetting::bookingCancellationNoticeHours($store->id));
        View::share(
            'bookingCancellationPolicyNotice',
            CrmSetting::bookingCancellationPolicyNoticeText($store->id),
        );

        return $next($request);
    }
}
