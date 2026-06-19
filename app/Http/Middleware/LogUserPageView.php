<?php

namespace App\Http\Middleware;

use App\Jobs\RecordUserPageViewJob;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('user_navigation_log.enabled', true)) {
            return;
        }

        if (! $request->isMethod('GET')) {
            return;
        }

        if ($response->getStatusCode() >= 400) {
            return;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return;
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return;
        }

        $routeName = $request->route()?->getName();
        if ($routeName === null || $this->isExcludedRoute($routeName)) {
            return;
        }

        $storeId = function_exists('current_store_id') ? current_store_id() : null;
        [$routeParams, $subjectType, $subjectId] = $this->extractRouteContext($request);
        $debounceSeconds = max(1, (int) config('user_navigation_log.debounce_seconds', 60));
        $debounceKey = $this->debounceKey($user->id, $storeId, $routeName, $routeParams);

        if (! \Illuminate\Support\Facades\Cache::add($debounceKey, 1, $debounceSeconds)) {
            return;
        }

        $job = new RecordUserPageViewJob(
            userId: (int) $user->id,
            storeId: $storeId !== null ? (int) $storeId : null,
            routeName: $routeName,
            path: '/'.ltrim($request->path(), '/'),
            subjectType: $subjectType,
            subjectId: $subjectId,
            routeParams: $routeParams,
            ipAddress: $request->ip(),
            userAgent: substr((string) $request->userAgent(), 0, 500) ?: null,
        );

        dispatch($job)->afterResponse();
    }

    protected function isExcludedRoute(string $routeName): bool
    {
        $excluded = config('user_navigation_log.excluded_route_names', []);
        if (in_array($routeName, $excluded, true)) {
            return true;
        }

        foreach (config('user_navigation_log.excluded_route_prefixes', []) as $prefix) {
            if (str_starts_with($routeName, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: array<string, scalar|null>, 1: ?string, 2: ?int}
     */
    protected function extractRouteContext(Request $request): array
    {
        $routeParams = [];
        $subjectType = null;
        $subjectId = null;

        foreach ($request->route()?->parameters() ?? [] as $key => $value) {
            if ($value instanceof Model) {
                $routeParams[(string) $key] = $value->getKey();
                if ($subjectType === null) {
                    $subjectType = $value->getMorphClass();
                    $subjectId = (int) $value->getKey();
                }

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $routeParams[(string) $key] = $value;
            }
        }

        ksort($routeParams);

        return [$routeParams, $subjectType, $subjectId];
    }

    /**
     * @param  array<string, scalar|null>  $routeParams
     */
    protected function debounceKey(int $userId, ?int $storeId, string $routeName, array $routeParams): string
    {
        $paramsHash = md5(json_encode($routeParams) ?: '');

        return sprintf(
            'user_nav:%d:%s:%s:%s',
            $userId,
            $storeId ?? '0',
            $routeName,
            $paramsHash
        );
    }
}
