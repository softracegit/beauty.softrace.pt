<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Utilizador com acesso à área /super-admin.
 * Defina SUPER_ADMIN_EMAIL e SUPER_ADMIN_PASSWORD no .env e execute:
 * php artisan db:seed --class=SuperAdminUserSeeder
 */
class SuperAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');
        if (! is_string($email) || trim($email) === '' || ! is_string($password) || $password === '') {
            $this->command?->warn('Ignorado: defina SUPER_ADMIN_EMAIL e SUPER_ADMIN_PASSWORD no .env.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => strtolower(trim($email))],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'role' => User::ROLE_SUPER_ADMIN,
                'organization_id' => null,
            ],
        );

        $this->command?->info('Super admin criado ou actualizado.');
    }
}
