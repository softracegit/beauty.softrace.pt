<?php

namespace App\Http\Middleware;

use App\Support\BookingLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetBookingLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = BookingLocale::supported();
        $locale = null;

        if ($request->has('lang')) {
            $requested = BookingLocale::resolve($request->query('lang'));
            if (in_array($requested, $supported, true)) {
                $locale = $requested;
                $request->session()->put('booking_locale', $locale);
            }
        }

        if ($locale === null) {
            $sessionLocale = $request->session()->get('booking_locale');
            if (is_string($sessionLocale) && in_array($sessionLocale, $supported, true)) {
                $locale = $sessionLocale;
            }
        }

        if ($locale === null) {
            $locale = BookingLocale::default();
        }

        BookingLocale::apply($locale);

        return $next($request);
    }
}
