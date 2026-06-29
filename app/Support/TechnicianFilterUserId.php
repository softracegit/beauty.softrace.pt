<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\User;

class TechnicianFilterUserId
{
    /**
     * Converte o valor do filtro de técnico (users.id ou agents.id) para users.id.
     */
    public static function resolve(mixed $tecnicoFilter): ?int
    {
        if ($tecnicoFilter === null || $tecnicoFilter === '') {
            return null;
        }

        $id = (int) $tecnicoFilter;
        if ($id <= 0) {
            return null;
        }

        if (User::query()->whereKey($id)->exists()) {
            return $id;
        }

        $userId = Agent::query()->whereKey($id)->value('user_id');

        return $userId ? (int) $userId : null;
    }
}
