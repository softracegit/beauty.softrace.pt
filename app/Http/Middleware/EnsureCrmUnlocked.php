<?php

namespace App\Http\Middleware;

use App\Support\CrmPrivacyLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCrmUnlocked
{
    public function __construct(
        private readonly CrmPrivacyLock $privacyLock,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->privacyLock->isActive()) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($this->isAllowedWhenLocked($routeName)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'crm_locked',
                'message' => 'O CRM está bloqueado neste posto.',
            ], 403);
        }

        return redirect()->route('agenda.index')->with('status', 'CRM bloqueado neste posto.');
    }

    private function isAllowedWhenLocked(string $routeName): bool
    {
        if ($routeName === 'logout') {
            return true;
        }

        $allowedPrefixes = [
            'agenda.',
            'notifications.',
            'crm-privacy-lock.',
            'dashboard.',
        ];

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        if ($routeName === 'dashboard') {
            return true;
        }

        return false;
    }
}
