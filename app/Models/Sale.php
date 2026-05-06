<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public const SCOPE_REGULAR = 'regular';

    public const SCOPE_BOOKING_RESERVA = 'booking_reserva';

    public const SCOPE_CAIXA_LIQUIDACAO = 'caixa_liquidacao';

    protected $fillable = [
        'store_id',
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
        'vendus_sync_status',
        'vendus_document_id',
        'vendus_synced_at',
        'vendus_sync_error',
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
    ];

    protected $attributes = [
        'status' => self::STATUS_PAGO,
        'scope' => self::SCOPE_REGULAR,
    ];

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
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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
