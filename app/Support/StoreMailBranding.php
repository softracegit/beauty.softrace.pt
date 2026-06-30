<?php

namespace App\Support;

use App\Models\CrmSetting;
use App\Models\Store;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;

final class StoreMailBranding
{
    /** @var array<string, mixed>|null */
    private static ?array $current = null;

    /**
     * @return array{use_business: bool, name: string, logo_url: string, from_name: string, footer_name: string}
     */
    public static function using(?Store $store): array
    {
        self::$current = self::resolve($store);

        return self::$current;
    }

    /**
     * @return array{use_business: bool, name: string, logo_url: string, from_name: string, footer_name: string}
     */
    public static function current(): array
    {
        return self::$current ?? self::resolve(null);
    }

    public static function clear(): void
    {
        self::$current = null;
    }

    /**
     * @return array{use_business: bool, name: string, logo_url: string, from_name: string, footer_name: string}
     */
    public static function resolve(?Store $store): array
    {
        $appName = (string) config('app.name');
        $appLogo = asset('template/img/logo-color-black.png');
        $fromName = (string) config('mail.from.name', $appName);

        if ($store === null || ! CrmSetting::emailUseBusinessBranding((int) $store->id)) {
            return [
                'use_business' => false,
                'name' => $appName,
                'logo_url' => $appLogo,
                'from_name' => $fromName !== '' ? $fromName : $appName,
                'footer_name' => $appName,
            ];
        }

        $name = trim((string) $store->name);
        if ($name === '') {
            $name = $appName;
        }

        return [
            'use_business' => true,
            'name' => $name,
            'logo_url' => $store->logoEmailUrl(),
            'from_name' => $name,
            'footer_name' => $name,
        ];
    }

    public static function applyToMailMessage(MailMessage $mail, ?Store $store): MailMessage
    {
        $branding = self::using($store);

        return $mail
            ->from((string) config('mail.from.address'), $branding['from_name'])
            ->salutation($branding['footer_name']);
    }

    public static function envelopeForStore(?Store $store, string $subject): Envelope
    {
        $branding = self::using($store);

        return new Envelope(
            subject: $subject,
            from: new Address((string) config('mail.from.address'), $branding['from_name']),
        );
    }
}
