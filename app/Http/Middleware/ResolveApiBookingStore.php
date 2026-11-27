<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API pública de marcação (app móvel): resolve a loja pelo slug do URL,
 * com as mesmas regras do BookingContext do booking web, mas sem partilhar
 * dados com as views. Loja inexistente ou organização inativa → 404 JSON.
 */
class ResolveApiBookingStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $param = $request->route('store');
        $store = $param instanceof Store ? $param : null;

        if ($store === null && (is_string($param) || is_numeric($param))) {
            $slug = trim((string) $param);
            if ($slug !== '') {
                $store = Store::query()->where('slug', $slug)->first();
            }
        }

        if (! $store instanceof Store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        $store->loadMissing('organization');
        if ($store->organization && strtolower((string) $store->organization->status) !== 'active') {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        $request->route()?->setParameter('store', $store);
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

        return $next($request);
    }
}
