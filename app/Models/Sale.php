<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sale extends Model
{
    use BelongsToStore;

    public const STATUS_PAGO = 'pago';

    public const STATUS_ANULADO = 'anulado';

    public const PAYMENT_MULTIBANCO = 'multibanco';

    public const PAYMENT_MBWAY = 'mbway';

    public const PAYMENT_DINHEIRO = 'dinheiro';

    public const PAYMENT_TRANSFERENCIA = 'transferencia';

    public const PAYMENT_CARTAO = 'cartao';

    public const PAYMENT_OUTRO = 'outro';

    public const PAYMENT_CREDITOS_CARTEIRA = 'creditos_carteira';

    public const SCOPE_REGULAR = 'regular';

    public const SCOPE_BOOKING_RESERVA = 'booking_reserva';

    public const SCOPE_CAIXA_LIQUIDACAO = 'caixa_liquidacao';

    public const INVOICE_STATUS_FATURADO = 'faturado';

    public const INVOICE_STATUS_RASCUNHO = 'rascunho';

    protected $fillable = [
        'store_id',
        'cash_register_session_id',
        'calendar_event_id',
        'client_id',
        'numero_fatura',
        'data_emissao',
        'total',
        'gorjeta',
        'desconto',
        'valor_pago',
        'iva_total',
        'payment_method',
        'scope',
        'status',
        'invoice_status',
        'vendus_sync_status',
        'vendus_document_id',
        'vendus_credit_note_id',
        'vendus_synced_at',
        'vendus_sync_error',
        'issue_without_fiscal_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'data_emissao' => 'date',
        'total' => 'decimal:2',
        'gorjeta' => 'decimal:2',
        'desconto' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'iva_total' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'vendus_synced_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'issue_without_fiscal_id' => 'boolean',
    ];

    protected $attributes = [
        'status' => self::STATUS_PAGO,
        'scope' => self::SCOPE_REGULAR,
        'invoice_status' => self::INVOICE_STATUS_FATURADO,
    ];

    public function isInvoiceDraft(): bool
    {
        return ($this->invoice_status ?? self::INVOICE_STATUS_FATURADO) === self::INVOICE_STATUS_RASCUNHO;
    }

    public function isAnulado(): bool
    {
        return ($this->status ?? self::STATUS_PAGO) === self::STATUS_ANULADO;
    }

    /**
     * Tem nota de crédito emitida na Vendus (permite ver o PDF da NC em vez da fatura anulada).
     */
    public function hasCreditNote(): bool
    {
        return $this->vendus_credit_note_id !== null && (int) $this->vendus_credit_note_id > 0;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PAGO => 'Pago',
            self::STATUS_ANULADO => 'Anulado',
        ];
    }

    public static function paymentMethods(): array
    {
        return [
            self::PAYMENT_MULTIBANCO => 'Multibanco',
            self::PAYMENT_MBWAY => 'MB Way',
            self::PAYMENT_DINHEIRO => 'Dinheiro',
            self::PAYMENT_TRANSFERENCIA => 'Transferência',
            self::PAYMENT_CARTAO => 'Cartão',
            self::PAYMENT_OUTRO => 'Outro',
            self::PAYMENT_CREDITOS_CARTEIRA => 'Créditos (carteira)',
        ];
    }

    /**
     * Rótulo curto para botões PDF / email (pré-pagamento vs loja).
     */
    public function invoiceListLabel(): string
    {
        $num = trim((string) ($this->numero_fatura ?? ''));

        return match ($this->scope) {
            self::SCOPE_BOOKING_RESERVA => $num !== '' ? 'Pré-pagamento · '.$num : 'Pré-pagamento',
            self::SCOPE_CAIXA_LIQUIDACAO => $num !== '' ? 'Pagamento em loja · '.$num : 'Pagamento em loja',
            default => $num !== '' ? 'Fatura · '.$num : 'Fatura',
        };
    }

    /**
     * Descrição da linha na fatura PDF (inclui normalização de textos antigos com «reserva»).
     */
    public function invoiceDisplayDescription(?string $storedDescription = null): string
    {
        $stored = trim((string) ($storedDescription ?? ''));
        if ($stored === '') {
            return match ($this->scope) {
                self::SCOPE_BOOKING_RESERVA => 'Pré-pagamento (marcação online)',
                default => 'Serviço',
            };
        }

        if ($this->scope !== self::SCOPE_BOOKING_RESERVA) {
            return $stored;
        }

        $exact = [
            'Adiantamento de reserva (marcação online)' => 'Pré-pagamento (marcação online)',
            'Adiantamento de reserva (receção)' => 'Pré-pagamento (receção)',
            'Pré-pagamento (marcação online)' => 'Pré-pagamento (marcação online)',
            'Pré-pagamento (receção)' => 'Pré-pagamento (receção)',
        ];
        if (isset($exact[$stored])) {
            return $exact[$stored];
        }

        $normalized = str_replace(
            [
                'Adiantamento de reserva',
                'adiantamento (reserva online)',
                'adiantamento (receção)',
                'sinal de reserva',
                'reserva online',
            ],
            [
                'Pré-pagamento',
                'pré-pagamento (marcação online)',
                'pré-pagamento (receção)',
                'pré-pagamento',
                'pré-pagamento online',
            ],
            $stored
        );

        $normalized = preg_replace(
            '/\s*—\s*adiantamento\s*\(\s*reserva\s+online\s*\)/iu',
            ' — pré-pagamento (marcação online)',
            $normalized
        ) ?? $normalized;

        return preg_replace(
            '/\s*—\s*adiantamento\s*\(\s*rece[cç][aã]o\s*\)/iu',
            ' — pré-pagamento (receção)',
            $normalized
        ) ?? $normalized;
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('sort_order');
    }

    /**
     * Marcações liquidadas por esta venda (consolidado/single).
     */
    public function settledEvents(): BelongsToMany
    {
        return $this->belongsToMany(CalendarEvent::class, 'sale_calendar_events')
            ->withPivot(['amount_settled_cents', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Valor efetivamente pago até ao momento (null = legado, assume fatura paga na totalidade).
     */
    public function effectiveAmountPaid(): float
    {
        if ($this->valor_pago === null) {
            return (float) $this->total;
        }

        return (float) $this->valor_pago;
    }

    /**
     * Montante ainda em dívida face ao total da fatura.
     */
    public function amountDue(): float
    {
        return max(0, round((float) $this->total - $this->effectiveAmountPaid(), 2));
    }

    /**
     * Generate next invoice number for the given year/month (e.g. 2026/03-001), por loja.
     */
    public static function nextNumeroFatura(int $year, int $month, int $storeId): string
    {
        $prefix = sprintf('%04d/%02d-', $year, $month);
        $maxSeq = static::query()
            ->forStore($storeId)
            ->where('numero_fatura', 'like', $prefix.'%')
            ->pluck('numero_fatura')
            ->map(fn (string $n) => (int) substr($n, strlen($prefix)))
            ->max();

        $next = ($maxSeq ?? 0) + 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
