<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Hosts públicos de marcação (booking.fadastudio.pt, e no futuro o subdomínio de um cliente).
 * O caminho público é /{loja}/{tecnica}; por dentro continua a ser /booking/{loja}/{tecnica}.
 */
final class BookingPublicUrl
{
    /**
     * Domínios gravados na ficha da loja (separador Booking).
     *
     * @return list<string>
     */
    public static function hosts(): array
    {
        $hosts = Cache::remember('booking.public_hosts', 300, function (): array {
            if (! Schema::hasTable('stores') || ! Schema::hasColumn('stores', 'booking_domain')) {
                return [];
            }

            return \App\Models\Store::query()
                ->whereNotNull('booking_domain')
                ->pluck('booking_domain')
                ->map(fn ($host) => self::normalizeHost((string) $host))
                ->filter(fn (string $host) => $host !== '')
                ->unique()
                ->values()
                ->all();
        });

        return is_array($hosts) ? array_values($hosts) : [];
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host, 2)[0];

        return explode(':', $host, 2)[0];
    }

    public static function requestUsesPublicHost(Request $request): bool
    {
        return in_array(self::normalizeHost($request->getHost()), self::hosts(), true);
    }

    /**
     * Ficheiros e rotas que não pertencem ao fluxo /booking e têm de abrir tal como estão.
     */
    public static function isPassThroughPath(string $path): bool
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '/') {
            return false;
        }

        $first = explode('/', trim($path, '/'), 2)[0] ?? '';

        return in_array($first, [
            'booking-assets',
            'template',
            'storage',
            'build',
            'vendor',
            'js',
            'css',
            'img',
            'images',
            'fonts',
            'favicon.ico',
            'robots.txt',
            'up',
            'livewire',
            'stripe',
            'legal',
            'r',
        ], true);
    }

    public static function stripBookingPrefix(string $path): string
    {
        $query = '';
        $queryPos = strpos($path, '?');
        if ($queryPos !== false) {
            $query = substr($path, $queryPos);
            $path = substr($path, 0, $queryPos);
        }

        $path = '/'.ltrim($path, '/');
        if ($path === '/booking') {
            return '/'.$query;
        }

        if (str_starts_with($path, '/booking/')) {
            $stripped = substr($path, strlen('/booking'));

            return ($stripped !== '' ? $stripped : '/').$query;
        }

        return $path.$query;
    }

    public static function domainForSlug(string $storeSlug): ?string
    {
        $storeSlug = trim($storeSlug);
        if ($storeSlug === '' || ! Schema::hasTable('stores') || ! Schema::hasColumn('stores', 'booking_domain')) {
            return null;
        }

        $domain = \App\Models\Store::query()->where('slug', $storeSlug)->value('booking_domain');
        if (! is_string($domain)) {
            return null;
        }

        $normalized = self::normalizeHost($domain);

        return $normalized !== '' ? $normalized : null;
    }

    public static function storeBase(string $storeSlug, ?string $domain = null): string
    {
        $storeSlug = trim($storeSlug, '/');
        $domain = $domain !== null ? self::normalizeHost($domain) : self::domainForSlug($storeSlug);
        if ($domain !== null && $domain !== '') {
            return 'https://'.$domain.'/'.$storeSlug;
        }

        return url('/booking/'.$storeSlug);
    }

    public static function agentLink(string $storeSlug, string $agentSlug, ?string $domain = null): string
    {
        return self::storeBase($storeSlug, $domain).'/'.trim($agentSlug, '/');
    }
}
