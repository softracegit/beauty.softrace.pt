<?php

namespace App\Support;

use App\Models\CalendarEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

final class ReceptionNotificationMail
{
    /**
     * Recepção em CC nos emails de marcação, excepto quando a acção na agenda é da própria receção.
     */
    public static function shouldCcReceptionForActor(bool $fromPublicBooking = false, ?int $actorUserId = null): bool
    {
        if ($fromPublicBooking) {
            return true;
        }

        if ($actorUserId === null) {
            $actor = auth()->user();
            $actorUserId = $actor?->id;
        }

        if ($actorUserId === null) {
            return true;
        }

        $user = User::query()->find($actorUserId);

        return ! ($user instanceof User && $user->role === User::ROLE_RECECAO);
    }

    /**
     * CC nos emails de marcação para todas as receções com acesso à loja do evento.
     *
     * @param  list<string>  $excludeEmails
     */
    public static function applyReceptionCc(
        MailMessage $mail,
        CalendarEvent $event,
        array $excludeEmails = [],
    ): MailMessage {
        $exclude = self::normalizeEmailList($excludeEmails);

        foreach (self::receptionEmailsForStore((int) ($event->store_id ?? 0)) as $email) {
            if (in_array(strtolower($email), $exclude, true)) {
                continue;
            }
            $mail->cc($email);
        }

        return $mail;
    }

    /**
     * @param  list<string>  $excludeEmails
     */
    public static function applyReceptionCcWhenAllowed(
        MailMessage $mail,
        CalendarEvent $event,
        array $excludeEmails = [],
        bool $fromPublicBooking = false,
        ?int $actorUserId = null,
    ): MailMessage {
        if (! self::shouldCcReceptionForActor($fromPublicBooking, $actorUserId)) {
            return $mail;
        }

        return self::applyReceptionCc($mail, $event, $excludeEmails);
    }

    /**
     * @return Collection<int, User>
     */
    public static function receptionUsersForStore(int $storeId): Collection
    {
        if ($storeId <= 0) {
            return collect();
        }

        $store = Store::query()->find($storeId);
        if ($store === null) {
            return collect();
        }

        /** @var EloquentCollection<int, User> $users */
        $users = User::query()
            ->where('role', User::ROLE_RECECAO)
            ->orderBy('name')
            ->get();

        return $users
            ->filter(function (User $user) use ($storeId, $store): bool {
                if ($user->accessibleStores()->contains('id', $storeId)) {
                    return true;
                }

                // Recepção da organização sem lojas explícitas no pivot (acesso à org inteira).
                return (int) ($user->organization_id ?? 0) === (int) $store->organization_id
                    && $user->stores()->count() === 0;
            })
            ->values();
    }

    /**
     * @return list<string>
     */
    public static function receptionEmailsForStore(int $storeId): array
    {
        return self::receptionUsersForStore($storeId)
            ->map(fn (User $user): string => trim((string) ($user->email ?? '')))
            ->filter(fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    private static function normalizeEmailList(array $emails): array
    {
        $out = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '') {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }
}
