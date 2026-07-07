<?php

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\User;
use App\Support\ActivityLogMarcacaoOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_events') || ! Schema::hasColumn('calendar_events', 'marcacao_source')) {
            return;
        }

        $online = ActivityLogMarcacaoOrigin::ONLINE;
        $marcacaoType = CalendarEvent::TYPE_MARCACAO;

        if (Schema::hasTable('bookings')) {
            $this->markOnlineFromSubquery($marcacaoType, $online, function ($query): void {
                $query->select('calendar_event_id')
                    ->from('bookings')
                    ->whereNotNull('calendar_event_id');
            });
        }

        if (Schema::hasTable('sales')) {
            $this->markOnlineFromSubquery($marcacaoType, $online, function ($query): void {
                $query->select('calendar_event_id')
                    ->from('sales')
                    ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
                    ->whereNotNull('calendar_event_id');
            });
        }

        if (Schema::hasTable('booking_sms_action_links')) {
            $this->markOnlineFromSubquery($marcacaoType, $online, function ($query): void {
                $query->select('calendar_event_id')
                    ->from('booking_sms_action_links');
            });
        }

        if (Schema::hasTable('activity_log')) {
            $subjectType = (new CalendarEvent)->getMorphClass();

            $this->markOnlineFromSubquery($marcacaoType, $online, function ($query) use ($subjectType): void {
                $query->select('subject_id')
                    ->from('activity_log')
                    ->where('subject_type', $subjectType)
                    ->where('event', 'created')
                    ->whereNotNull('subject_id')
                    ->where(function ($inner): void {
                        $inner->where('description', 'like', '%marcação online%')
                            ->orWhere('description', 'like', '%marcacao online%');
                        if (DB::getDriverName() === 'mysql') {
                            $inner->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.origem')) = ?", [ActivityLogMarcacaoOrigin::ONLINE]);
                        }
                    });
            });

            if (Schema::hasTable('users')) {
                $userMorphClass = (new User)->getMorphClass();

                // Junho e anteriores: descrição só "Marcação criada", mas o causer é conta de cliente (booking).
                $this->markOnlineFromSubquery($marcacaoType, $online, function ($query) use ($subjectType, $userMorphClass): void {
                    $query->select('al.subject_id')
                        ->from('activity_log as al')
                        ->join('users as u', 'u.id', '=', 'al.causer_id')
                        ->where('al.subject_type', $subjectType)
                        ->where('al.causer_type', $userMorphClass)
                        ->where('al.event', 'created')
                        ->whereNotNull('al.subject_id')
                        ->where('u.role', User::ROLE_CLIENTE)
                        ->whereNotNull('u.client_id');
                });
            }
        }

        // Convidado na 1.ª marcação: user cliente criado no mesmo fluxo (sem causer no log).
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('users')) {
            $this->markOnlineFromSubquery($marcacaoType, $online, function ($query) use ($marcacaoType): void {
                $query->select('ce.id')
                    ->from('calendar_events as ce')
                    ->join('users as u', 'u.client_id', '=', 'ce.client_id')
                    ->where('ce.event_type', $marcacaoType)
                    ->whereNotNull('ce.client_id')
                    ->where('u.role', User::ROLE_CLIENTE)
                    ->whereNotNull('u.client_id')
                    ->whereRaw('ABS(TIMESTAMPDIFF(SECOND, u.created_at, ce.created_at)) <= 120');
            });
        }
    }

    public function down(): void
    {
        // Não é possível distinguir com segurança quais linhas eram agenda antes do backfill.
    }

    /**
     * @param  callable(\Illuminate\Database\Query\Builder): void  $subquery
     */
    private function markOnlineFromSubquery(string $marcacaoType, string $online, callable $subquery): void
    {
        DB::table('calendar_events')
            ->where('event_type', $marcacaoType)
            ->whereIn('id', function ($query) use ($subquery): void {
                $subquery($query);
            })
            ->update(['marcacao_source' => $online]);
    }
};
