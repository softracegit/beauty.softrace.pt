<?php

namespace Tests\Feature\Booking;

use App\Actions\SyncServiceOptionsAction;
use App\Models\Agent;
use App\Models\Category;
use App\Models\CrmSetting;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Organization;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Store;
use App\Models\User;
use App\Services\OnlineBookingCheckoutService;
use App\Support\BookingTheme;
use App\Support\CurrentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Variantes adicionais (type = addon): somam-se à variante escolhida em vez de a substituir.
 */
class ServiceOptionAddonTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{store: Store, gel: Service, verniz: Service} */
    private function fixture(): array
    {
        $org = Organization::query()->create(['name' => 'Org Booking', 'slug' => 'org-booking', 'status' => 'active']);
        $store = Store::query()->create(['organization_id' => $org->id, 'name' => 'Fada Studio Aveiro', 'slug' => 'aveiro']);
        Store::query()->create(['organization_id' => $org->id, 'name' => 'Fada Studio Forca', 'slug' => 'forca']);

        $user = User::query()->create([
            'name' => 'Ana Ribeiro', 'email' => 'ana@booking.test', 'password' => Hash::make('password'),
            'role' => User::ROLE_PRESTADOR, 'organization_id' => $org->id,
        ]);
        $agent = Agent::query()->create([
            'user_id' => $user->id, 'store_id' => $store->id, 'name' => 'Ana Ribeiro',
            'status' => Agent::STATUS_ACTIVE, 'visible_in_booking' => true, 'visible_in_agenda' => true,
        ]);

        $manicure = Category::query()->create(['store_id' => $store->id, 'organization_id' => $org->id, 'name' => 'Manicure', 'sort_order' => 1]);
        $pedicure = Category::query()->create(['store_id' => $store->id, 'organization_id' => $org->id, 'name' => 'Pedicure', 'sort_order' => 2]);

        $mk = function (Category $cat, string $name, int $dur, float $price, int $order) use ($store, $org, $agent): Service {
            $service = Service::query()->create([
                'store_id' => $store->id, 'organization_id' => $org->id, 'category_id' => $cat->id, 'name' => $name,
                'duration' => $dur, 'price' => $price, 'online_price' => $price, 'sort_order' => $order,
            ]);
            $service->agents()->attach($agent->id);

            return $service;
        };

        $mk($manicure, 'Tradicional', 45, 12, 1);
        $verniz = $mk($manicure, 'Verniz Gel', 60, 18, 2);
        $gel = $mk($manicure, 'Unhas de Gel', 70, 24, 3);
        $mk($manicure, 'Remoção', 20, 5, 4);
        $mk($pedicure, 'Pedicure Tradicional', 50, 15, 1);
        $mk($pedicure, 'Pedicure com Gel', 75, 22, 2);

        app(SyncServiceOptionsAction::class)->execute($gel, true, [
            ['name' => 'Sobre unha natural', 'duration' => 70, 'price' => 24, 'online_price' => 24, 'sort_order' => 0, 'is_baseline' => true],
            ['name' => 'Com extensão', 'duration' => 110, 'price' => 35, 'online_price' => 35, 'sort_order' => 1],
            ['name' => 'Manutenção Cliente Fada', 'duration' => 90, 'price' => 27, 'online_price' => 27, 'sort_order' => 2],
            ['name' => 'Manutenção de profissional externa', 'duration' => 110, 'price' => 30, 'online_price' => 30, 'sort_order' => 3],
            ['name' => 'Reparação', 'duration' => 15, 'price' => 4, 'online_price' => 4, 'sort_order' => 4, 'type' => 'addon'],
            ['name' => 'Remoção', 'duration' => 20, 'price' => 5, 'online_price' => 5, 'sort_order' => 5, 'type' => 'addon'],
        ]);
        // Serviço só com base + adicional: a base é o próprio serviço
        app(SyncServiceOptionsAction::class)->execute($verniz, true, [
            ['name' => 'Verniz Gel', 'duration' => 60, 'price' => 18, 'online_price' => 18, 'sort_order' => 0, 'is_baseline' => true],
            ['name' => 'Reparação', 'duration' => 15, 'price' => 4, 'online_price' => 4, 'sort_order' => 1, 'type' => 'addon'],
        ]);

        $extraCat = ExtraCategory::query()->create(['organization_id' => $org->id, 'store_id' => $store->id, 'name' => 'Decoração', 'sort_order' => 1]);
        foreach ([['Nail Art 3D', 5, 15], ['Nail Art (Decoração)', 3, 15], ['Francesinha', 4, 15], ['Olho de Gato', 4, 20], ['Baby color Boomer', 3, 15]] as $i => [$name, $price, $dur]) {
            $extra = Extra::query()->create(['extra_category_id' => $extraCat->id, 'name' => $name, 'price' => $price, 'duration' => $dur, 'sort_order' => $i]);
            $gel->extras()->attach($extra->id);
            DB::table('agent_service_extra')->insert([
                'agent_id' => $agent->id, 'service_id' => $gel->id, 'extra_id' => $extra->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ['store' => $store, 'gel' => $gel->fresh('options'), 'verniz' => $verniz->fresh('options')];
    }

    public function test_sync_saves_option_type_and_baseline_is_never_addon(): void
    {
        $fx = $this->fixture();
        $types = $fx['gel']->options->pluck('type', 'name');

        $this->assertSame(ServiceOption::TYPE_CHOICE, $types['Sobre unha natural']);
        $this->assertSame(ServiceOption::TYPE_ADDON, $types['Reparação']);
        $this->assertSame(ServiceOption::TYPE_ADDON, $types['Remoção']);

        app(SyncServiceOptionsAction::class)->execute($fx['verniz'], true, [
            ['name' => 'Base', 'duration' => 60, 'price' => 18, 'online_price' => 18, 'is_baseline' => true, 'type' => 'addon'],
            ['name' => 'Outra', 'duration' => 30, 'price' => 9, 'online_price' => 9],
        ]);
        $this->assertSame(ServiceOption::TYPE_CHOICE, $fx['verniz']->options()->where('is_baseline', true)->value('type'));
    }

    public function test_booking_page_marks_addons_and_ignores_them_in_from_price(): void
    {
        $fx = $this->fixture();
        CrmSetting::setBookingTheme(BookingTheme::MINIMAL, $fx['store']->id);

        $response = $this->get(route('booking.index', ['store' => $fx['store']->slug]));
        $response->assertOk()
            ->assertSee('data-booking-theme="minimal"', false)
            ->assertSee('booking-navbar-store__toggle', false)
            ->assertSee('Org Booking')
            ->assertSee('booking-offcanvas__store-toggle', false)
            ->assertSee('Fada Studio Forca');

        $html = $response->getContent();
        if ($dump = env('BOOKING_DUMP_HTML')) {
            // Só para verificação visual local
            file_put_contents($dump, $html);
            file_put_contents(str_replace('.html', '-tech.html', $dump), $this->get(route('booking.technician', ['store' => $fx['store']->slug]))->getContent());
            $fx['store']->forceFill(['logo' => 'store-logos/demo/logo.png'])->save();
            file_put_contents(str_replace('.html', '-logo.html', $dump), $this->get(route('booking.index', ['store' => $fx['store']->slug]))->getContent());
            $fx['store']->forceFill(['logo' => null])->save();
            $cliente = User::query()->create([
                'name' => 'Daniel Simões', 'email' => 'cliente@booking.test', 'password' => Hash::make('password'),
                'role' => User::ROLE_CLIENTE, 'organization_id' => $fx['store']->organization_id,
            ]);
            $cliente->forceFill(['client_id' => \App\Models\Client::query()->create([
                'organization_id' => $fx['store']->organization_id, 'store_id' => $fx['store']->id,
                'name' => 'Daniel Simões', 'email' => 'cliente@booking.test', 'phone' => '910000000',
            ])->id])->save();
            $this->actingAs($cliente);
            foreach (['narrow', 'wide'] as $w) {
                CrmSetting::setBookingWidth($w, $fx['store']->id);
                file_put_contents(str_replace('.html', '-conta-'.$w.'.html', $dump), $this->get(route('booking.conta.marcacoes', ['store' => $fx['store']->slug]))->getContent());
            }
            file_put_contents(str_replace('.html', '-auth.html', $dump), $this->get(route('booking.index', ['store' => $fx['store']->slug]))->getContent());
            file_put_contents(str_replace('.html', '-conta-flash.html', $dump), $this->withSession(['success' => __('booking.messages.cancel_success')])->get(route('booking.conta.marcacoes', ['store' => $fx['store']->slug]))->getContent());
            file_put_contents(str_replace('.html', '-conta-perfil.html', $dump), $this->get(route('booking.conta.index', ['store' => $fx['store']->slug]))->getContent());
            file_put_contents(str_replace('.html', '-conta-confirmada.html', $dump), $this->get(route('booking.conta.marcacoes', ['store' => $fx['store']->slug, 'marcacao_confirmada' => 1, 'primeira_marcacao' => 1]))->getContent());
            CrmSetting::setBool(CrmSetting::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED, false, $fx['store']->id);
            file_put_contents(str_replace('.html', '-step3.html', $dump), $this->get(route('booking.step3', ['store' => $fx['store']->slug]))->getContent());
        }
        $this->assertStringContainsString('"type":"addon"', $html);
        // "Desde" continua a ser o da variante mais barata de escolha única (24 €), não o da adicional (4 €)
        $this->assertStringContainsString('"catalogMinPrice":24', $html);
        // Serviço só com base + adicional: preço fixo, sem "Desde" nem etiqueta de opções
        $this->assertStringNotContainsString('2 opções', $html);
    }

    public function test_booking_width_setting_is_saved_per_store_and_reaches_the_page(): void
    {
        $fx = $this->fixture();
        $storeId = $fx['store']->id;
        CrmSetting::setBookingTheme(BookingTheme::MINIMAL, $storeId);

        $this->assertSame(CrmSetting::BOOKING_WIDTH_WIDE, CrmSetting::bookingWidth($storeId));
        $this->get(route('booking.index', ['store' => $fx['store']->slug]))
            ->assertSee('data-booking-width="wide"', false);

        CrmSetting::setBookingWidth(CrmSetting::BOOKING_WIDTH_NARROW, $storeId);
        $this->get(route('booking.index', ['store' => $fx['store']->slug]))
            ->assertSee('data-booking-width="narrow"', false);

        CrmSetting::setBookingWidth('qualquer-coisa', $storeId);
        $this->assertSame(CrmSetting::BOOKING_WIDTH_WIDE, CrmSetting::bookingWidth($storeId));

        // Pelo formulário das definições (mesmo serviço que o CRM usa)
        $settings = app(\App\Services\StoreOperationalSettingsService::class);
        $data = $settings->validateBooking(\Illuminate\Http\Request::create('/', 'POST', [
            'booking_slot_hold_minutes' => 15,
            'booking_cart_ttl_hours' => 24,
            'booking_slot_interval_minutes' => CrmSetting::BOOKING_SLOT_INTERVAL_MINUTES_DEFAULT,
            'booking_cancellation_notice_hours' => 24,
            'booking_theme' => BookingTheme::MINIMAL,
            'booking_width' => CrmSetting::BOOKING_WIDTH_NARROW,
            'booking_services_layout' => CrmSetting::BOOKING_SERVICES_LAYOUT_LIST,
            'booking_cancellation_policy_text' => "Pode cancelar até 3 horas antes.\n<b>Sem custos</b>.",
        ]), $storeId);
        $settings->applyBookingToStore($data, $storeId);
        // Texto livre da política de cancelamento: guardado sem HTML e partilhado com o Booking
        $this->assertSame("Pode cancelar até 3 horas antes.\nSem custos.", CrmSetting::bookingCancellationPolicyText($storeId));
        $this->assertSame(
            CrmSetting::bookingCancellationPolicyText($storeId),
            $settings->bookingViewData($storeId)['bookingCancellationPolicyText'],
        );
        $this->assertSame(CrmSetting::BOOKING_WIDTH_NARROW, CrmSetting::bookingWidth($storeId));
        $this->assertSame(CrmSetting::BOOKING_SERVICES_LAYOUT_LIST, CrmSetting::bookingServicesLayout($storeId));
        $this->get(route('booking.index', ['store' => $fx['store']->slug]))
            ->assertSee('data-booking-services="list"', false)
            ->assertSee('booking-legal-footer--minimal', false)
            ->assertDontSee('booking-navbar-lang__toggle', false);
        $this->assertArrayHasKey('bookingWidths', $settings->bookingViewData($storeId));
    }

    public function test_editing_a_service_keeps_existing_option_ids(): void
    {
        $fx = $this->fixture();
        $gel = $fx['gel']->fresh('options');
        $before = $gel->options->pluck('id', 'name');
        $removed = 'Com extensão';

        // Mesmo formulário do CRM: variantes existentes vêm com id, uma muda de tipo, uma sai e entra uma nova
        $rows = $gel->options
            ->reject(fn ($o) => $o->name === $removed)
            ->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'duration' => $o->duration,
                'price' => $o->price,
                'online_price' => $o->online_price,
                'sort_order' => $o->sort_order,
                'is_baseline' => $o->is_baseline,
                'type' => $o->name === 'Remoção' ? \App\Models\ServiceOption::TYPE_CHOICE : $o->type,
            ])
            ->values()
            ->all();
        $rows[] = ['name' => 'Variante nova', 'duration' => 30, 'price' => 10, 'online_price' => 10, 'sort_order' => 99];

        app(SyncServiceOptionsAction::class)->execute($gel, true, $rows);

        $after = $gel->fresh('options')->options->pluck('id', 'name');
        foreach ($before as $name => $id) {
            if ($name === $removed) {
                $this->assertFalse($after->has($name));

                continue;
            }
            $this->assertSame($id, $after[$name], "A variante «{$name}» mudou de id ao guardar o serviço.");
        }
        $this->assertTrue($after->has('Variante nova'));
        $this->assertNotContains($after['Variante nova'], $before->values()->all());
        $this->assertSame(
            \App\Models\ServiceOption::TYPE_CHOICE,
            $gel->fresh('options')->options->firstWhere('name', 'Remoção')->type,
        );
    }

    public function test_checkout_rejects_addon_without_base_variant(): void
    {
        $fx = $this->fixture();
        app(CurrentStore::class)->set($fx['store']);
        $gel = $fx['gel'];
        $base = $gel->options->firstWhere('name', 'Manutenção Cliente Fada');
        $addon = $gel->options->firstWhere('name', 'Reparação');

        $resolve = new \ReflectionMethod(OnlineBookingCheckoutService::class, 'resolveOnlineBookingServiceLines');
        $resolve->setAccessible(true);
        $service = app(OnlineBookingCheckoutService::class);

        $lines = $resolve->invoke($service, [
            ['id' => $gel->id, 'service_option_id' => $base->id],
            ['id' => $gel->id, 'service_option_id' => $addon->id],
        ]);
        $this->assertCount(2, $lines);
        $this->assertSame(105, array_sum(array_column($lines, 'duration')));
        $this->assertEqualsWithDelta(31.0, array_sum(array_column($lines, 'price')), 0.001);

        $this->expectException(ValidationException::class);
        $resolve->invoke($service, [
            ['id' => $gel->id, 'service_option_id' => $addon->id],
        ]);
    }
}
