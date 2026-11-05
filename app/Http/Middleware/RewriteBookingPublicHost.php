<?php

namespace App\Http\Middleware;

use App\Support\BookingPublicUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * No host público, /{loja}/vanessa é a marcação. O prefixo /booking fica só interno.
 */
class RewriteBookingPublicHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! BookingPublicUrl::requestUsesPublicHost($request)) {
            URL::formatPathUsing(static fn (string $path): string => $path);

            return $next($request);
        }

        URL::formatPathUsing(static fn (string $path): string => BookingPublicUrl::stripBookingPrefix($path));

        $path = '/'.ltrim($request->path(), '/');

        if (BookingPublicUrl::isPassThroughPath($path)) {
            return $next($request);
        }

        if (($request->isMethod('GET') || $request->isMethod('HEAD')) && str_starts_with($path, '/booking/')) {
            $short = BookingPublicUrl::stripBookingPrefix($path);
            $query = $request->getQueryString();

            return redirect()->to($short.($query !== null && $query !== '' ? '?'.$query : ''), 301);
        }

        if ($path === '/booking' || str_starts_with($path, '/booking/')) {
            return $next($request);
        }

        $internal = '/booking'.($path === '/' ? '' : $path);
        $query = $request->getQueryString();
        $relative = $internal.($query !== null && $query !== '' ? '?'.$query : '');

        $rewritten = Request::create(
            $request->getSchemeAndHttpHost().$relative,
            $request->getMethod(),
            $request->isMethod('GET') || $request->isMethod('HEAD') ? [] : $request->request->all(),
            $request->cookies->all(),
            $request->allFiles(),
            $request->server->all(),
            $request->getContent()
        );
        $rewritten->headers->replace($request->headers->all());

        $this->appRequest($rewritten);

        return $next($rewritten);
    }

    private function appRequest(Request $request): void
    {
        app()->instance('request', $request);
    }
}
