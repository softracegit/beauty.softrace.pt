<?php

namespace Tests\Feature;

use App\Http\Middleware\RewriteBookingPublicHost;
use App\Support\BookingPublicUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BookingPublicHostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::put('booking.public_hosts', ['booking.test'], 300);
    }

    public function test_public_host_rewrites_store_and_agent_paths(): void
    {
        $request = Request::create('http://booking.test/fada-host/vanessa?lang=en', 'GET');
        $this->bindRequest($request);

        $seen = null;
        $generated = null;
        $lang = null;
        $response = app(RewriteBookingPublicHost::class)->handle($request, function (Request $rewritten) use (&$seen, &$generated, &$lang) {
            $seen = $rewritten->path();
            $lang = $rewritten->query('lang');
            $generated = route('booking.agent', ['store' => 'fada-host', 'agent' => 'vanessa'], false);

            return response('ok');
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('booking/fada-host/vanessa', $seen);
        $this->assertSame('en', $lang);
        $this->assertSame('/fada-host/vanessa', $generated);
    }

    public function test_public_host_redirects_legacy_booking_prefix(): void
    {
        $this->get('http://booking.test/booking/fada-host?lang=en')
            ->assertRedirect('http://booking.test/fada-host?lang=en');
    }

    public function test_crm_host_keeps_booking_prefix(): void
    {
        $request = Request::create('http://localhost/booking/fada-host', 'GET');
        $this->bindRequest($request);

        $seen = null;
        $generated = null;
        app(RewriteBookingPublicHost::class)->handle($request, function (Request $passed) use (&$seen, &$generated) {
            $seen = $passed->path();
            $generated = route('booking.index', ['store' => 'fada-host'], false);

            return response('ok');
        });

        $this->assertSame('booking/fada-host', $seen);
        $this->assertSame('/booking/fada-host', $generated);
    }

    public function test_public_host_does_not_rewrite_health_or_assets(): void
    {
        $request = Request::create('http://booking.test/up', 'GET');
        $seen = null;
        app(RewriteBookingPublicHost::class)->handle($request, function (Request $passed) use (&$seen) {
            $seen = $passed->path();

            return response('ok');
        });

        $this->assertSame('up', $seen);
        $this->assertTrue(BookingPublicUrl::isPassThroughPath('/c/abc123'));
        $this->assertTrue(BookingPublicUrl::isPassThroughPath('/booking-assets/css/app.css'));
    }

    public function test_sms_confirm_url_is_short_on_the_store_domain(): void
    {
        $this->assertSame(
            'https://booking.fadastudio.pt/c/abc123',
            BookingPublicUrl::smsConfirmUrl('abc123', 'fada', 'booking.fadastudio.pt')
        );
    }

    public function test_client_account_url_uses_the_store_domain(): void
    {
        $this->assertSame(
            'https://booking.fadastudio.pt/fada/conta/marcacoes',
            BookingPublicUrl::clientUrl('booking.conta.marcacoes', 'fada', domain: 'booking.fadastudio.pt')
        );
    }

    public function test_agent_link_uses_the_store_domain(): void
    {
        $this->assertSame(
            'https://booking.empresa.pt/fada-host/vanessa',
            BookingPublicUrl::agentLink('fada-host', 'vanessa', 'https://booking.empresa.pt/extra')
        );
    }

    public function test_agent_link_keeps_crm_path_without_store_domain(): void
    {
        $this->assertSame(
            url('/booking/fada-host/vanessa'),
            BookingPublicUrl::agentLink('fada-host', 'vanessa', '')
        );
    }

    private function bindRequest(Request $request): void
    {
        $this->app->instance('request', $request);
        URL::setRequest($request);
    }
}
