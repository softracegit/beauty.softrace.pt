<?php

namespace Tests\Unit;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Client;
use App\Models\Organization;
use App\Models\PersonalTimeType;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Services\MarcacaoGlueSuggestionsService;
use App\Support\DateTimeDisplay;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarcacaoGlueSuggestionsServiceTest extends TestCase
{
  use RefreshDatabase;

  private MarcacaoGlueSuggestionsService $service;

  protected function setUp(): void
  {
    parent::setUp();
    $this->service = app(MarcacaoGlueSuggestionsService::class);
  }

  /**
   * @return array{store: Store, prestador: User, clientA: Client, clientB: Client, clientC: Client}
   */
  private function fixture(): array
  {
    $org = Organization::query()->create([
      'name' => 'Org Glue',
      'slug' => 'org-glue',
      'status' => 'active',
    ]);
    $store = Store::query()->create([
      'organization_id' => $org->id,
      'name' => 'Loja Glue',
      'slug' => 'loja-glue',
    ]);
    $clientA = Client::query()->create([
      'store_id' => $store->id,
      'name' => 'Cliente A',
      'type' => Client::TYPE_POTENCIAL_CLIENTE,
    ]);
    $clientB = Client::query()->create([
      'store_id' => $store->id,
      'name' => 'Cliente B',
      'type' => Client::TYPE_POTENCIAL_CLIENTE,
    ]);
    $clientC = Client::query()->create([
      'store_id' => $store->id,
      'name' => 'Cliente C',
      'type' => Client::TYPE_POTENCIAL_CLIENTE,
    ]);
    $category = Category::query()->create([
      'store_id' => $store->id,
      'name' => 'Cat',
      'sort_order' => 1,
    ]);
    $service = Service::query()->create([
      'store_id' => $store->id,
      'category_id' => $category->id,
      'name' => 'Manicure',
      'duration' => 60,
      'price' => 25,
      'online_price' => 25,
      'sort_order' => 1,
    ]);
    $prestador = User::query()->create([
      'name' => 'Técnica Glue',
      'email' => 'tecnica-glue@test.test',
      'password' => Hash::make('password'),
      'role' => User::ROLE_PRESTADOR,
      'organization_id' => $org->id,
    ]);
    Agent::query()->create([
      'user_id' => $prestador->id,
      'store_id' => $store->id,
      'name' => 'Técnica Glue',
      'status' => Agent::STATUS_ACTIVE,
    ]);

    return compact('store', 'prestador', 'clientA', 'clientB', 'clientC', 'service');
  }

  private function createMarcacao(
    Store $store,
    User $prestador,
    Client $client,
    Carbon $start,
    int $durationMinutes,
    string $status = CalendarEvent::STATUS_CONFIRMADO,
  ): CalendarEvent {
    return CalendarEvent::query()->create([
    'store_id' => $store->id,
    'client_id' => $client->id,
    'user_id' => $prestador->id,
    'event_type' => CalendarEvent::TYPE_MARCACAO,
    'status' => $status,
    'title' => 'Marcação',
    'start_at' => $start->copy()->utc(),
      'end_at' => $start->copy()->addMinutes($durationMinutes)->utc(),
    ]);
  }

  public function test_detects_gap_and_suggests_gluing_next_appointment(): void
  {
    $fx = $this->fixture();
    $day = Carbon::today()->setTime(14, 0);

    $first = $this->createMarcacao($fx['store'], $fx['prestador'], $fx['clientA'], $day, 60);
    $second = $this->createMarcacao(
      $fx['store'],
      $fx['prestador'],
      $fx['clientB'],
      $day->copy()->addMinutes(90),
      60,
    );

    $result = $this->service->build($fx['store']->id, 'hoje');

    $this->assertSame(1, $result['summary']['suggestion_count']);
    $this->assertSame(30, $result['summary']['recoverable_minutes']);

    $suggestion = $result['suggestions'][0];
    $this->assertSame($second->id, $suggestion['next_event_id']);
    $this->assertSame($first->id, $suggestion['previous_event_id']);
    $this->assertSame(30, $suggestion['gap_minutes']);
    $this->assertSame('15:00', DateTimeDisplay::marcacao($suggestion['suggested_start_at'], $fx['store']->id, 'H:i'));
  }

  public function test_respects_personal_time_between_appointments(): void
  {
    $fx = $this->fixture();
    $day = Carbon::today()->setTime(14, 0);

    $this->createMarcacao($fx['store'], $fx['prestador'], $fx['clientA'], $day, 60);
    $personalType = PersonalTimeType::query()->create([
      'store_id' => $fx['store']->id,
      'name' => 'Almoço',
      'icon' => 'ph-clock',
      'duration' => 15,
      'sort_order' => 1,
      'is_active' => true,
    ]);
    CalendarEvent::query()->create([
      'store_id' => $fx['store']->id,
      'user_id' => $fx['prestador']->id,
      'event_type' => CalendarEvent::TYPE_TEMPO_PESSOAL,
      'personal_time_type_id' => $personalType->id,
      'status' => CalendarEvent::STATUS_AGENDADO,
      'title' => 'Almoço',
      'start_at' => $day->copy()->addHour()->utc(),
      'end_at' => $day->copy()->addMinutes(75)->utc(),
    ]);
    $this->createMarcacao(
      $fx['store'],
      $fx['prestador'],
      $fx['clientB'],
      $day->copy()->addMinutes(90),
      60,
    );

    $result = $this->service->build($fx['store']->id, 'hoje');

    $this->assertSame(1, $result['summary']['suggestion_count']);
    $this->assertSame(15, $result['summary']['recoverable_minutes']);
    $this->assertSame('15:15', DateTimeDisplay::marcacao($result['suggestions'][0]['suggested_start_at'], $fx['store']->id, 'H:i'));
    $between = $result['suggestions'][0]['between_sequence'] ?? [];
    $this->assertCount(1, $between);
    $this->assertSame('tempo_pessoal', $between[0]['type']);
    $this->assertSame('Almoço', $between[0]['label']);
    $this->assertSame('15:00', DateTimeDisplay::marcacao($between[0]['start_at'], $fx['store']->id, 'H:i'));
    $this->assertSame('15:15', DateTimeDisplay::marcacao($between[0]['end_at'], $fx['store']->id, 'H:i'));
  }

  public function test_ignores_gaps_smaller_than_threshold(): void
  {
    $fx = $this->fixture();
    $day = Carbon::today()->setTime(14, 0);

    $this->createMarcacao($fx['store'], $fx['prestador'], $fx['clientA'], $day, 60);
    $this->createMarcacao($fx['store'], $fx['prestador'], $fx['clientB'], $day->copy()->addMinutes(63), 60);

    $result = $this->service->build($fx['store']->id, 'hoje');

    $this->assertSame(0, $result['summary']['suggestion_count']);
  }

  public function test_snaps_suggested_start_to_fifteen_minute_grid(): void
  {
    $fx = $this->fixture();
    $day = Carbon::today()->setTime(14, 0);

    $this->createMarcacao($fx['store'], $fx['prestador'], $fx['clientA'], $day, 65);
    $this->createMarcacao(
      $fx['store'],
      $fx['prestador'],
      $fx['clientB'],
      $day->copy()->setTime(15, 45),
      60,
    );

    $result = $this->service->build($fx['store']->id, 'hoje');

    $this->assertSame(1, $result['summary']['suggestion_count']);
    $this->assertSame('15:15', DateTimeDisplay::marcacao($result['suggestions'][0]['suggested_start_at'], $fx['store']->id, 'H:i'));
    $this->assertSame('16:15', DateTimeDisplay::marcacao($result['suggestions'][0]['suggested_end_at'], $fx['store']->id, 'H:i'));
  }

  public function test_ignores_shifts_larger_than_one_hour(): void
  {
    $fx = $this->fixture();
    $day = Carbon::today()->setTime(9, 0);

    $this->createMarcacao($fx['store'], $fx['prestador'], $fx['clientA'], $day, 60);
    $this->createMarcacao(
      $fx['store'],
      $fx['prestador'],
      $fx['clientB'],
      $day->copy()->setTime(12, 0),
      60,
    );

    $result = $this->service->build($fx['store']->id, 'hoje');

    $this->assertSame(0, $result['summary']['suggestion_count']);
  }

  public function test_allows_shift_up_to_one_hour(): void
  {
    $fx = $this->fixture();
    $day = Carbon::today()->setTime(10, 0);

    $this->createMarcacao($fx['store'], $fx['prestador'], $fx['clientA'], $day, 60);
    $this->createMarcacao(
      $fx['store'],
      $fx['prestador'],
      $fx['clientB'],
      $day->copy()->setTime(11, 15),
      60,
    );

    $result = $this->service->build($fx['store']->id, 'hoje');

    $this->assertSame(1, $result['summary']['suggestion_count']);
    $this->assertSame(15, $result['suggestions'][0]['gap_minutes']);
  }

  public function test_does_not_suggest_moving_past_or_non_movable_appointments(): void
  {
    $fx = $this->fixture();
    $day = Carbon::today()->setTime(8, 0);

    $this->createMarcacao($fx['store'], $fx['prestador'], $fx['clientA'], $day, 60);
    $this->createMarcacao(
      $fx['store'],
      $fx['prestador'],
      $fx['clientB'],
      $day->copy()->addMinutes(90),
      60,
      CalendarEvent::STATUS_INICIADO,
    );

    $result = $this->service->build($fx['store']->id, 'hoje');

    $this->assertSame(0, $result['summary']['suggestion_count']);
  }
}
