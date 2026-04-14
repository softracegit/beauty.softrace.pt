<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Stripe → servidor: confirma estado final do PaymentIntent (assinatura verificada).
     */
    public function handle(Request $request): Response
    {
        $secret = config('stripe.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            Log::warning('Stripe webhook: STRIPE_WEBHOOK_SECRET não está definido.');

            return response('Webhook not configured', 503);
        }

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException|SignatureVerificationException $e) {
            return response('Invalid payload', 400);
        }

        $object = $event->data->object ?? null;

        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded(is_object($object) ? $object : null),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed(is_object($object) ? $object : null),
            default => null,
        };

        return response('', 200);
    }

    private function handlePaymentIntentSucceeded(?object $intent): void
    {
        if ($intent === null) {
            return;
        }

        $id = $intent->id ?? null;
        if (! is_string($id) || $id === '') {
            return;
        }

        Payment::query()->where('stripe_payment_intent_id', $id)->update([
            'status' => Payment::STATUS_SUCCEEDED,
            'failure_message' => null,
        ]);

        $booking = Booking::query()->where('stripe_payment_intent_id', $id)->first();
        if ($booking && $booking->payment_status !== Booking::PAYMENT_PAID) {
            $booking->payment_status = Booking::PAYMENT_PAID;
            $booking->save();
        }
    }

    private function handlePaymentIntentFailed(?object $intent): void
    {
        if ($intent === null) {
            return;
        }

        $id = $intent->id ?? null;
        if (! is_string($id) || $id === '') {
            return;
        }

        $lastErr = $intent->last_payment_error ?? null;
        $msg = is_object($lastErr) && isset($lastErr->message) && is_string($lastErr->message)
            ? $lastErr->message
            : 'payment_failed';

        Payment::query()->where('stripe_payment_intent_id', $id)->update([
            'status' => Payment::STATUS_FAILED,
            'failure_message' => $msg,
        ]);

        $booking = Booking::query()->where('stripe_payment_intent_id', $id)->first();
        if ($booking && $booking->calendar_event_id === null) {
            $booking->payment_status = Booking::PAYMENT_FAILED;
            $booking->save();
        }
    }
}
