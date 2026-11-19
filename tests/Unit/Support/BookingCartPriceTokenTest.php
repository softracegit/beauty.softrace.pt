<?php

namespace Tests\Unit\Support;

use App\Support\BookingCartPriceToken;
use Tests\TestCase;

class BookingCartPriceTokenTest extends TestCase
{
    public function test_issue_and_verify_round_trip(): void
    {
        $token = BookingCartPriceToken::issue(
            BookingCartPriceToken::KIND_SERVICE,
            42,
            30.0,
            time(),
            7,
        );

        $parsed = BookingCartPriceToken::verify($token, BookingCartPriceToken::KIND_SERVICE, 42, 7);

        $this->assertNotNull($parsed);
        $this->assertSame(30.0, $parsed['price']);
        $this->assertSame(42, $parsed['id']);
    }

    public function test_verify_rejects_wrong_kind_or_id(): void
    {
        $token = BookingCartPriceToken::issue(BookingCartPriceToken::KIND_SERVICE, 42, 30.0);

        $this->assertNull(BookingCartPriceToken::verify($token, BookingCartPriceToken::KIND_OPTION, 42));
        $this->assertNull(BookingCartPriceToken::verify($token, BookingCartPriceToken::KIND_SERVICE, 99));
    }

    public function test_verify_rejects_tampered_price_in_token_body(): void
    {
        $token = BookingCartPriceToken::issue(BookingCartPriceToken::KIND_SERVICE, 42, 30.0);
        [$body] = explode('.', $token, 2);
        $json = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        $json['p'] = 1.0;
        $tamperedBody = rtrim(strtr(base64_encode(json_encode($json)), '+/', '-_'), '=');
        $tampered = $tamperedBody.'.'.explode('.', $token, 2)[1];

        $this->assertNull(BookingCartPriceToken::verify($tampered, BookingCartPriceToken::KIND_SERVICE, 42));
    }

    public function test_verify_rejects_expired_token(): void
    {
        $issuedAt = time() - (BookingCartPriceToken::ttlSeconds() + 60);
        $token = BookingCartPriceToken::issue(
            BookingCartPriceToken::KIND_SERVICE,
            42,
            30.0,
            $issuedAt,
        );

        $this->assertNull(BookingCartPriceToken::verify($token, BookingCartPriceToken::KIND_SERVICE, 42));
    }
}
