<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\SaleItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    /**
     * GET agenda/events/{event}/checkout – dados do evento para o checkout.
     */
    public function checkout(CalendarEvent $calendarEvent)
    {
        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json(['error' => 'Apenas marcações podem ir a checkout.'], 422);
        }

        if ($calendarEvent->isMarcacaoStatusLocked()) {
            return response()->json(['error' => 'Esta marcação não pode ser paga.'], 422);
        }

        $calendarEvent->load(['client', 'eventServiceItems.service', 'eventServiceItems.extras.extra']);

        $existingSale = $calendarEvent->sale;
        if ($existingSale && $existingSale->status !== Sale::STATUS_ANULADO) {
            $existingSale->load('items');

            return response()->json([
                'event_id' => $calendarEvent->id,
                'client' => $calendarEvent->client ? [
                    'id' => $calendarEvent->client->id,
                    'name' => $calendarEvent->client->name,
                    'email' => $calendarEvent->client->email,
                ] : null,
                'existing_sale' => [
                    'id' => $existingSale->id,
                    'numero_fatura' => $existingSale->numero_fatura,
                    'total' => (float) $existingSale->total,
                    'pdf_url' => route('sales.pdf', $existingSale),
                ],
                'items' => [],
                'payment_methods' => Sale::paymentMethods(),
            ]);
        }

        $items = [];
        $sortOrder = 0;
        foreach ($calendarEvent->eventServiceItems as $esi) {
            $items[] = [
                'tipo' => SaleItem::TIPO_SERVICO,
                'calendar_event_service_id' => $esi->id,
                'service_id' => $esi->service_id,
                'extra_id' => null,
                'descricao' => $esi->service?->name ?? 'Serviço',
                'quantidade' => 1,
                'preco_unitario' => (float) $esi->price,
                'subtotal' => (float) $esi->price,
                'sort_order' => $sortOrder++,
            ];
            foreach ($esi->extras as $ex) {
                $price = (float) ($ex->price ?? $ex->extra?->price ?? 0);
                $items[] = [
                    'tipo' => SaleItem::TIPO_EXTRA,
                    'calendar_event_service_id' => $esi->id,
                    'service_id' => null,
                    'extra_id' => $ex->extra_id,
                    'descricao' => '+ '.($ex->extra?->name ?? 'Extra'),
                    'quantidade' => 1,
                    'preco_unitario' => $price,
                    'subtotal' => $price,
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        return response()->json([
            'event_id' => $calendarEvent->id,
            'client' => $calendarEvent->client ? [
                'id' => $calendarEvent->client->id,
                'name' => $calendarEvent->client->name,
                'email' => $calendarEvent->client->email,
            ] : null,
            'existing_sale' => null,
            'items' => $items,
            'payment_methods' => Sale::paymentMethods(),
        ]);
    }

    /**
     * POST agenda/checkout – criar venda e devolver pdf_url.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:calendar_events,id'],
            'payment_method' => ['required', 'string', 'in:'.implode(',', array_keys(Sale::paymentMethods()))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.tipo' => ['required', 'in:servico,extra'],
            'items.*.descricao' => ['required', 'string', 'max:255'],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
            'items.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],
            'items.*.desconto' => ['nullable', 'numeric', 'min:0'],
            'items.*.calendar_event_service_id' => ['nullable', 'exists:calendar_event_services,id'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.extra_id' => ['nullable', 'exists:extras,id'],
            'gorjeta' => ['nullable', 'numeric', 'min:0'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'valor_pago' => ['nullable', 'numeric', 'min:0'],
        ]);

        $calendarEvent = CalendarEvent::findOrFail($validated['event_id']);
        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return response()->json(['error' => 'Apenas marcações podem ir a checkout.'], 422);
        }
        if ($calendarEvent->isMarcacaoStatusLocked()) {
            return response()->json(['error' => 'Esta marcação não pode ser paga.'], 422);
        }
        $hasActiveSale = $calendarEvent->sale()
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->exists();
        if ($hasActiveSale) {
            return response()->json(['error' => 'Esta marcação já foi faturada.'], 422);
        }

        $subtotalItens = 0.0;
        foreach ($validated['items'] as $row) {
            $qty = (int) $row['quantidade'];
            $preco = (float) $row['preco_unitario'];
            $bruto = round($qty * $preco, 2);
            $descLinha = isset($row['desconto']) ? (float) $row['desconto'] : 0;
            $descLinha = min(max(0, $descLinha), $bruto);
            $subtotalItens += round($bruto - $descLinha, 2);
        }
        $gorjeta = isset($validated['gorjeta']) ? (float) $validated['gorjeta'] : 0;
        $descontoDoc = isset($validated['desconto']) ? max(0, (float) $validated['desconto']) : 0;
        $maxDocDisc = $subtotalItens + $gorjeta;
        $descontoDoc = min($descontoDoc, $maxDocDisc);
        $total = round(max(0, $subtotalItens + $gorjeta - $descontoDoc), 2);
        $valorPago = isset($validated['valor_pago']) ? max(0, (float) $validated['valor_pago']) : $total;
        $valorPago = min($valorPago, $total);

        $now = now();
        $numeroFatura = Sale::nextNumeroFatura((int) $now->format('Y'), (int) $now->format('m'));

        try {
            DB::beginTransaction();

            $sale = Sale::create([
                'calendar_event_id' => $calendarEvent->id,
                'client_id' => $calendarEvent->client_id,
                'numero_fatura' => $numeroFatura,
                'data_emissao' => $now->toDateString(),
                'total' => $total,
                'gorjeta' => $gorjeta > 0 ? round($gorjeta, 2) : null,
                'desconto' => $descontoDoc > 0 ? round($descontoDoc, 2) : null,
                'valor_pago' => round($valorPago, 2),
                'iva_total' => null,
                'payment_method' => $validated['payment_method'],
                'status' => Sale::STATUS_PAGO,
            ]);

            foreach ($validated['items'] as $idx => $row) {
                $qty = (int) $row['quantidade'];
                $preco = (float) $row['preco_unitario'];
                $bruto = round($qty * $preco, 2);
                $descLinha = isset($row['desconto']) ? (float) $row['desconto'] : 0;
                $descLinha = min(max(0, $descLinha), $bruto);
                $subtotal = round($bruto - $descLinha, 2);
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'tipo' => $row['tipo'],
                    'calendar_event_service_id' => $row['calendar_event_service_id'] ?? null,
                    'service_id' => $row['service_id'] ?? null,
                    'extra_id' => $row['extra_id'] ?? null,
                    'descricao' => $row['descricao'],
                    'quantidade' => $qty,
                    'preco_unitario' => $preco,
                    'subtotal' => $subtotal,
                    'desconto' => $descLinha > 0 ? round($descLinha, 2) : null,
                    'sort_order' => $idx,
                ]);
            }

            // Marcar evento como completo
            $calendarEvent->update(['status' => CalendarEvent::STATUS_COMPLETO]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['error' => 'Erro ao gravar a venda.', 'message' => $e->getMessage()], 500);
        }

        $pdfUrl = route('sales.pdf', $sale);

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'numero_fatura' => $sale->numero_fatura,
            'pdf_url' => $pdfUrl,
        ]);
    }

    /**
     * GET sales/{sale}/pdf – devolver PDF da fatura (stream no browser ou descarga).
     */
    public function pdf(Request $request, Sale $sale)
    {
        $sale->load(['client', 'items', 'calendarEvent']);

        $pdf = Pdf::loadView('pdf.invoice', ['sale' => $sale])
            ->setPaper('a4', 'portrait');

        $safeFilename = str_replace(['/', '\\'], '-', $sale->numero_fatura).'-fatura.pdf';

        if ($request->boolean('download')) {
            return $pdf->download($safeFilename);
        }

        return $pdf->stream($safeFilename);
    }

    /**
     * POST sales/{sale}/revert – anular a venda e desbloquear a marcação para edição.
     */
    public function revert(Sale $sale)
    {
        if ($sale->status === Sale::STATUS_ANULADO) {
            return response()->json(['error' => 'Esta venda já foi anulada.'], 422);
        }

        $calendarEvent = $sale->calendarEvent;
        if (! $calendarEvent) {
            return response()->json(['error' => 'Venda sem marcação associada.'], 422);
        }

        try {
            DB::beginTransaction();
            $sale->update(['status' => Sale::STATUS_ANULADO]);
            $calendarEvent->update(['status' => CalendarEvent::STATUS_INICIADO]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['error' => 'Erro ao reverter a venda.', 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Venda anulada. A marcação pode ser editada novamente.',
            'event_id' => $calendarEvent->id,
        ]);
    }
}
