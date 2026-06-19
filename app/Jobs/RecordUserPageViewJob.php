<?php

namespace App\Jobs;

use App\Models\UserPageViewLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordUserPageViewJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 15;

    /**
     * @param  array<string, scalar|null>  $routeParams
     */
    public function __construct(
        public int $userId,
        public ?int $storeId,
        public ?string $routeName,
        public string $path,
        public ?string $subjectType,
        public ?int $subjectId,
        public array $routeParams,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}

    public function handle(): void
    {
        UserPageViewLog::query()->create([
            'store_id' => $this->storeId,
            'user_id' => $this->userId,
            'route_name' => $this->routeName,
            'path' => $this->path,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'route_params' => $this->routeParams !== [] ? $this->routeParams : null,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'created_at' => now(),
        ]);
    }
}
