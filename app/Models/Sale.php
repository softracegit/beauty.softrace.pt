<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUS_PAGO = 'pago';

    public const STATUS_ANULADO = 'anulado';

    public const PAYMENT_MULTIBANCO = 'multibanco';

    public const PAYMENT_MBWAY = 'mbway';

    public const PAYMENT_DINHEIRO = 'dinheiro';

    public const PAYMENT_TRANSFERENCIA = 'transferencia';

    public const PAYMENT_CARTAO = 'cartao';

    public const PAYMENT_OUTRO = 'outro';

    protected $fillable = [
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
        'status',
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
    ];

    protected $attributes = [
        'status' => self::STATUS_PAGO,
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
     * Generate next invoice number for the given year/month (e.g. 2026/03-001).
     */
    public static function nextNumeroFatura(int $year, int $month): string
    {
        $prefix = sprintf('%04d/%02d-', $year, $month);
        $last = static::where('numero_fatura', 'like', $prefix.'%')
            ->orderByRaw('CAST(SUBSTRING(numero_fatura FROM '.(strlen($prefix) + 1).') AS UNSIGNED) DESC')
            ->value('numero_fatura');

        if (! $last) {
            return $prefix.'001';
        }
        $num = (int) substr($last, strrpos($last, '-') + 1);

        return $prefix.str_pad((string) ($num + 1), 3, '0', STR_PAD_LEFT);
    }
}
