<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\User;
use App\Services\ClientWalletService;
use App\Support\CurrentStore;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingAccountWalletController extends Controller
{
    public function __construct(
        private ClientWalletService $walletService,
    ) {}

    public function index(Request $request): View
    {
        $client = $this->resolveBookingClient($request);
        $client->refresh();

        $transactions = ClientWalletTransaction::query()
            ->where('client_id', $client->id)
            ->where('store_id', $this->bookingStoreId())
            ->with([
                'calendarEvent:id,store_id,start_at',
                'booking:id',
                'sale:id,numero_fatura',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('booking.conta.carteira', [
            'businessName' => $this->bookingBusinessName(),
            'client' => $client,
            'balanceCents' => $this->walletService->getBalanceCents($client),
            'transactions' => $transactions,
            'bookingStoreSlug' => $this->bookingStoreSlug(),
        ]);
    }

    private function resolveBookingClient(Request $request): Client
    {
        $user = $request->user();
        if (! ($user instanceof User) || ! $user->isBookingClient()) {
            abort(403);
        }

        $client = $user->loadMissing('client')->client;
        if (! $client instanceof Client) {
            abort(404);
        }

        if ((int) $client->store_id !== $this->bookingStoreId()) {
            abort(404);
        }

        return $client;
    }

    private function bookingStoreId(): int
    {
        return app(CurrentStore::class)->id();
    }

    private function bookingStoreSlug(): string
    {
        return (string) app(CurrentStore::class)->get()->slug;
    }

    private function bookingBusinessName(): string
    {
        return (string) app(CurrentStore::class)->get()->name;
    }
}
