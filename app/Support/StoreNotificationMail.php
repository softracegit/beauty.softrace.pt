<?php

namespace App\Support;

use App\Models\CalendarEvent;
use App\Models\Store;
use Illuminate\Notifications\Messages\MailMessage;

final class StoreNotificationMail
{
    /**
     * CC do email configurado em Definições → Negócio (campo email da loja).
     */
    public static function applyStoreCc(
        MailMessage $mail,
        CalendarEvent $event,
        ?string $primaryRecipientEmail = null,
    ): MailMessage {
        $storeEmail = self::resolveStoreEmail($event);
        if ($storeEmail === null) {
            return $mail;
        }

        $primary = trim((string) ($primaryRecipientEmail ?? ''));
        if ($primary !== '' && strcasecmp($primary, $storeEmail) === 0) {
            return $mail;
        }

        return $mail->cc($storeEmail);
    }

    public static function resolveStoreEmail(CalendarEvent $event): ?string
    {
        $event->loadMissing('store');

        return self::normalizeEmail($event->store);
    }

    public static function normalizeEmail(?Store $store): ?string
    {
        if ($store === null) {
            return null;
        }

        $email = trim((string) ($store->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }
}
