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
