<?php

namespace Tests\Feature\Api;

use App\Actions\SyncServiceOptionsAction;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\CrmSetting;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Support\PortugueseNationalHolidays;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Loja "aveiro" com uma técnica, Unhas de Gel (variantes + adicional + extra) e serviços ocultos.
 */
trait BookingApiFixture
{
    /** @return array{org: Organization, store: Store, agent: Agent, gel: Service, hidden: Service} */
    private function fixture(): array
    {
        $org = Organization::query()->create(['name' => 'Fada Studio', 'slug' => 'fada', 'status' => 'active']);
        $store = Store::query()->create(['organization_id' => $org->id, 'name' => 'Fada Studio Aveiro', 'slug' => 'aveiro']);
        Store::query()->create(['organization_id' => $org->id, 'name' => 'Fada Studio Porto', 'slug' => 'porto']);

        $user = User::query()->create([
            'name' => 'Laissa', 'email' => 'laissa@api.test', 'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR, 'organization_id' => $org->id,
        ]);
        $agent = Agent::query()->create([
            'user_id' => $user->id, 'store_id' => $store->id, 'name' => 'Laissa',
            'status' => Agent::STATUS_ACTIVE, 'visible_in_booking' => true, 'visible_in_agenda' => true,
        ]);

        $manicure = Category::query()->create(['store_id' => $store->id, 'organization_id' => $org->id, 'name' => 'Manicure', 'sort_order' => 1]);
        $oculta = Category::query()->create(['store_id' => $store->id, 'organization_id' => $org->id, 'name' => 'Interna', 'sort_order' => 2, 'hidden_from_booking' => true]);

        $gel = Service::query()->create([
            'store_id' => $store->id, 'organization_id' => $org->id, 'category_id' => $manicure->id,
            'name' => 'Unhas de Gel', 'duration' => 70, 'price' => 26, 'online_price' => 24, 'sort_order' => 1,
        ]);
        $gel->agents()->attach($agent->id);
        app(SyncServiceOptionsAction::class)->execute($gel, true, [
            ['name' => 'Sobre unha natural', 'duration' => 70, 'price' => 24, 'online_price' => 24, 'sort_order' => 0, 'is_baseline' => true],
            ['name' => 'Com extensão', 'duration' => 110, 'price' => 35, 'online_price' => 35, 'sort_order' => 1],
            ['name' => 'Reparação', 'duration' => 15, 'price' => 4, 'online_price' => 4, 'sort_order' => 2, 'type' => 'addon'],
        ]);

        $hidden = Service::query()->create([
            'store_id' => $store->id, 'organization_id' => $org->id, 'category_id' => $manicure->id,
            'name' => 'Serviço escondido', 'duration' => 30, 'price' => 10, 'sort_order' => 2, 'hidden_from_booking' => true,
        ]);
        $interno = Service::query()->create([
            'store_id' => $store->id, 'organization_id' => $org->id, 'category_id' => $oculta->id,
            'name' => 'Serviço interno', 'duration' => 30, 'price' => 10, 'sort_order' => 1,
        ]);
        $interno->agents()->attach($agent->id);

        $extraCat = ExtraCategory::query()->create(['organization_id' => $org->id, 'store_id' => $store->id, 'name' => 'Decoração', 'sort_order' => 1]);
        $francesinha = Extra::query()->create(['extra_category_id' => $extraCat->id, 'name' => 'Francesinha', 'price' => 4, 'duration' => 15, 'sort_order' => 1]);
        $semTecnica = Extra::query()->create(['extra_category_id' => $extraCat->id, 'name' => 'Ninguém faz', 'price' => 9, 'duration' => 15, 'sort_order' => 2]);
        $gel->extras()->attach([$francesinha->id, $semTecnica->id]);
        DB::table('agent_service_extra')->insert([
            'agent_id' => $agent->id, 'service_id' => $gel->id, 'extra_id' => $francesinha->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['org' => $org, 'store' => $store, 'agent' => $agent, 'gel' => $gel, 'hidden' => $hidden];
    }

    private function nextWorkingDay(): Carbon
    {
        $day = now('Europe/Lisbon')->addDays(2)->startOfDay();
        while ($day->isSunday() || PortugueseNationalHolidays::isHoliday($day)) {
            $day->addDay();
        }

        return $day;
    }

}
