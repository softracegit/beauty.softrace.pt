<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use App\Services\AgendaSameDayPayableService;
use App\Services\CashRegisterService;
use App\Support\DateTimeDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    public function __construct(
        private readonly CashRegisterService $cashRegisterService,
        private readonly AgendaSameDayPayableService $agendaSameDayPayableService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeCashRegister($request);

        $storeId = (int) current_store_id();
        $session = $this->cashRegisterService->getOpenSession($storeId);
        $history = CashRegisterSession::query()
            ->forStore($storeId)
            ->where('status', CashRegisterSession::STATUS_CLOSED)
            ->with(['openedBy', 'closedBy'])
            ->orderByDesc('closed_at')
            ->limit(30)
            ->get();

        return view('caixa.index', [
            'pageTitle' => 'Relatórios — Caixa',
            'session' => $session,
            'history' => $history,
        ]);
    }

    public function openPreview(Request $request): JsonResponse
    {
        $this->authorizeCashRegister($request);

        if ($this->cashRegisterService->getOpenSession((int) current_store_id()) !== null) {
            return response()->json(['error' => 'Já existe uma sessão de caixa aberta.'], 422);
        }

        return response()->json([
            'pending_booking' => $this->cashRegisterService->pendingBookingOrphansPreview((int) current_store_id()),
        ]);
    }

    public function closeSummary(Request $request): JsonResponse
    {
        $this->authorizeCashRegister($request);

        $session = $this->cashRegisterService->getOpenSession((int) current_store_id());
        if ($session === null) {
            return response()->json(['error' => 'Não há sessão de caixa aberta.'], 422);
        }

        $summary = $this->cashRegisterService->buildExpectedSummary($session);

        $storeId = (int) current_store_id();

        return response()->json([
            'session' => [
                'id' => $session->id,
                'opened_at_label' => DateTimeDisplay::business($session->opened_at),
                'opening_float' => $session->openingFloatEur(),
            ],
            'summary' => $summary,
            'unpaid_marcacoes' => $this->agendaSameDayPayableService->unpaidMarcacoesTodayForStore($storeId),
        ]);
    }

    public function open(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeCashRegister($request);

        $validated = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        try {
            $this->cashRegisterService->openSession(
                $request->user(),
                (int) current_store_id(),
                (float) $validated['opening_float'],
            );
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return back()->withErrors(['opening_float' => $e->getMessage()])->withInput();
        }

        $session = $this->cashRegisterService->getOpenSession((int) current_store_id());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Caixa aberta.',
                'assigned_booking_sales_count' => $session
                    ? $this->cashRegisterService->countBookingSalesAssignedToSession($session)
                    : 0,
            ]);
        }

        return redirect()
            ->route('relatorios.caixa')
            ->with('success', 'Caixa aberta.');
    }

    public function close(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeCashRegister($request);

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = $this->cashRegisterService->getOpenSession((int) current_store_id());
        if ($session === null) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Não há sessão de caixa aberta.'], 422);
            }

            return redirect()
                ->route('relatorios.caixa')
                ->withErrors(['counted_cash' => 'Não há sessão de caixa aberta.']);
        }

        $storeId = (int) current_store_id();
        $unpaidMarcacoes = $this->agendaSameDayPayableService->unpaidMarcacoesTodayForStore($storeId);
        if (($unpaidMarcacoes['count'] ?? 0) > 0) {
            $count = (int) $unpaidMarcacoes['count'];
            $message = $count === 1
                ? 'Não é possível fechar a caixa: existe 1 marcação por liquidar ou faturar (últimos 30 dias).'
                : 'Não é possível fechar a caixa: existem '.$count.' marcações por liquidar ou faturar (últimos 30 dias).';

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 422);
            }

            return back()->withErrors(['counted_cash' => $message])->withInput();
        }

        try {
            $closed = $this->cashRegisterService->closeSession(
                $session,
                $request->user(),
                (float) $validated['counted_cash'],
                $validated['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['counted_cash' => [$e->getMessage()]]], 422);
            }

            return back()->withErrors(['counted_cash' => $e->getMessage()])->withInput();
        }

        $diff = (float) (($closed->closing_summary['cash_difference'] ?? 0));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Caixa fechada.',
                'cash_difference' => $diff,
            ]);
        }

        return redirect()
            ->route('relatorios.caixa')
            ->with('success', 'Caixa fechada.')
            ->with('cash_difference', $diff);
    }

    private function authorizeCashRegister(Request $request): void
    {
        $user = $request->user();
        if ($user === null || ! $this->cashRegisterService->userCanManageCashRegister($user)) {
            abort(403, 'Sem permissão para gerir a caixa.');
        }
    }
}
