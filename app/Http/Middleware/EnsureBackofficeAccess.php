<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackofficeAccess
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $user->isAdmin()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';

        if ($user->isPrestador()) {
            if ($this->prestadorCanAccessRoute($routeName)) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                abort(403, 'Sem permissão para aceder a este recurso.');
            }

            return redirect()->route('dashboard');
        }

        if ($user->isRececao() && ! $user->canAccessRoute($routeName)) {
            if ($request->expectsJson()) {
                abort(403, 'Sem permissão para aceder a este recurso.');
            }

            return redirect()->route('dashboard');
        }

        return $next($request);
    }

    private function prestadorCanAccessRoute(string $routeName): bool
    {
        if ($routeName === '') {
            return true;
        }

        $allowedPrefixes = [
            'agenda.',
            'notifications.',
        ];

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return $routeName === 'dashboard';
    }
}
