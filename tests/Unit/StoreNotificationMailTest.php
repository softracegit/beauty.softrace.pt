<?php

namespace Tests\Unit;

use App\Models\CalendarEvent;
use App\Models\Store;
use App\Support\StoreNotificationMail;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class StoreNotificationMailTest extends TestCase
{
    public function test_applies_cc_from_store_email(): void
    {
        $store = new Store(['email' => 'info@fadastudio.pt']);
        $event = new CalendarEvent(['store_id' => 1]);
        $event->setRelation('store', $store);

        $mail = StoreNotificationMail::applyStoreCc(
            new MailMessage,
            $event,
            'cliente@example.com',
        );

        $this->assertNotEmpty($mail->cc);
        $this->assertSame('info@fadastudio.pt', $mail->cc[0][0] ?? $mail->cc[0]);
    }

    public function test_skips_cc_when_same_as_primary_recipient(): void
    {
        $store = new Store(['email' => 'info@fadastudio.pt']);
        $event = new CalendarEvent(['store_id' => 1]);
        $event->setRelation('store', $store);

        $mail = StoreNotificationMail::applyStoreCc(
            new MailMessage,
            $event,
            'info@fadastudio.pt',
        );

        $this->assertSame([], $mail->cc);
    }

    public function test_ignores_invalid_store_email(): void
    {
        $store = new Store(['email' => '']);
        $event = new CalendarEvent(['store_id' => 1]);
        $event->setRelation('store', $store);

        $mail = StoreNotificationMail::applyStoreCc(new MailMessage, $event, 'cliente@example.com');

        $this->assertSame([], $mail->cc);
    }
}
