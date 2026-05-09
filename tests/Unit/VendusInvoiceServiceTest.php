<?php

namespace Tests\Unit;

use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Services\VendusInvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendusInvoiceServiceTest extends TestCase
{
    public function test_sync_sale_posts_document_to_vendus(): void
    {
        config([
            'services.vendus.api_key' => 'test-key',
            'services.vendus.base_url' => 'https://www.vendus.pt/ws/v1.1',
            'services.vendus.auth_mode' => 'basic',
            'services.vendus.documents_endpoint' => '/documents/',
            'services.vendus.document_type' => 'FR',
            'services.vendus.tax_id' => 'NOR',
            'services.vendus.category_id' => null,
            'services.vendus.category_title' => 'Serviços',
            'services.vendus.simulate' => false,
        ]);

        Http::fake([
            'https://www.vendus.pt/ws/v1.1/products/categories*' => Http::response([
                ['id' => 555, 'title' => 'Serviços', 'status' => 'on'],
            ], 200),
            'https://www.vendus.pt/ws/v1.1/documents/paymentmethods*' => Http::response([
                ['id' => 42, 'title' => 'MB Way', 'type' => 'MBWAY', 'status' => 'on'],
            ], 200),
            'https://www.vendus.pt/ws/v1.1/documents/' => Http::response(['id' => 987], 201),
        ]);

        $sale = new Sale();
        $sale->id = 10;
        $sale->scope = Sale::SCOPE_CAIXA_LIQUIDACAO;
        $sale->data_emissao = Carbon::parse('2026-05-05');
        $sale->desconto = 0;
        $sale->total = 20;
        $sale->payment_method = Sale::PAYMENT_MBWAY;
        $sale->vendus_document_id = null;
        $sale->setRelation('client', new Client([
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.test',
            'phone' => '+351912345678',
            'nif' => '123456789',
        ]));
        $line = new SaleItem([
            'id' => 100,
            'tipo' => 'servico',
            'service_id' => 77,
            'descricao' => 'Texto interno longo — nunca para Vendus',
            'quantidade' => 1,
            'preco_unitario' => 20,
            'desconto' => 0,
        ]);
        $line->setRelation('service', new Service(['id' => 77, 'name' => 'Corte unissex']));
        $sale->setRelation('items', new Collection([$line]));

        $result = app(VendusInvoiceService::class)->syncSale($sale);

        $this->assertTrue($result['ok']);
        $this->assertSame(201, $result['status']);
        $this->assertSame(987, $result['document_id']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://www.vendus.pt/ws/v1.1/documents/'
                && isset($data['type'])
                && $data['type'] === 'FR'
                && isset($data['external_reference'])
                && $data['external_reference'] === 'SALE-10'
                && isset($data['items'][0]['reference'])
                && $data['items'][0]['reference'] === 'SRV-77'
                && isset($data['items'][0]['category_id'])
                && (int) $data['items'][0]['category_id'] === 555
                && (string) $data['items'][0]['title'] === 'Corte unissex'
                && (string) $data['items'][0]['text'] === 'Pagamento final em loja'
                && isset($data['payments'][0]['id'])
                && (string) $data['payments'][0]['id'] === '42'
                && isset($data['payments'][0]['amount'])
                && (float) $data['payments'][0]['amount'] === 20.0;
        });
    }

    public function test_sync_sale_fr_uses_single_liquidation_line_when_remainder_after_reserva(): void
    {
        config([
            'services.vendus.api_key' => 'test-key',
            'services.vendus.base_url' => 'https://www.vendus.pt/ws/v1.1',
            'services.vendus.auth_mode' => 'basic',
            'services.vendus.documents_endpoint' => '/documents/',
            'services.vendus.document_type' => 'FR',
            'services.vendus.tax_id' => 'NOR',
            'services.vendus.category_id' => null,
            'services.vendus.category_title' => 'Serviços',
            'services.vendus.simulate' => false,
        ]);

        Http::fake([
            'https://www.vendus.pt/ws/v1.1/products/categories*' => Http::response([
                ['id' => 555, 'title' => 'Serviços', 'status' => 'on'],
            ], 200),
            'https://www.vendus.pt/ws/v1.1/documents/paymentmethods*' => Http::response([
                ['id' => 42, 'title' => 'Numerário', 'type' => 'NU', 'status' => 'on'],
            ], 200),
            'https://www.vendus.pt/ws/v1.1/documents/' => Http::response(['id' => 988], 201),
        ]);

        $sale = new Sale();
        $sale->id = 11;
        $sale->scope = Sale::SCOPE_CAIXA_LIQUIDACAO;
        $sale->calendar_event_id = 50;
        $svc = new Service(['id' => 3, 'name' => 'Manicure Tradicional']);
        $ces = new CalendarEventService([
            'calendar_event_id' => 50,
            'service_id' => 3,
            'option_name' => null,
        ]);
        $ces->setRelation('service', $svc);
        $sale->setRelation('calendarEvent', tap(new CalendarEvent(), function (CalendarEvent $e) use ($ces): void {
            $e->id = 50;
            $e->title = 'Título agenda (não vai para Vendus)';
            $e->setRelation('eventServiceItems', collect([$ces]));
        }));
        $sale->data_emissao = Carbon::parse('2026-05-06');
        $sale->desconto = null;
        $sale->gorjeta = null;
        $sale->total = 12.0;
        $sale->valor_pago = 12.0;
        $sale->payment_method = Sale::PAYMENT_DINHEIRO;
        $sale->vendus_document_id = null;
        $sale->setRelation('client', new Client([
            'name' => 'Cliente',
            'email' => 'c@example.test',
            'phone' => '+351900000000',
            'nif' => '123456789',
        ]));
        $liqLine = new SaleItem([
            'tipo' => 'servico',
            'service_id' => 3,
            'descricao' => 'Qualquer texto interno',
            'quantidade' => 1,
            'preco_unitario' => 15.0,
            'desconto' => null,
        ]);
        $liqLine->setRelation('service', $svc);
        $sale->setRelation('items', new Collection([$liqLine]));

        $result = app(VendusInvoiceService::class)->syncSale($sale);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://www.vendus.pt/ws/v1.1/documents/'
                && ! isset($data['discount_amount'])
                && count($data['items']) === 1
                && $data['items'][0]['reference'] === 'SRV-3'
                && (float) $data['items'][0]['gross_price'] === 12.0
                && (string) $data['items'][0]['title'] === 'Manicure Tradicional'
                && (string) $data['items'][0]['text'] === 'Pagamento final em loja'
                && (float) $data['payments'][0]['amount'] === 12.0;
        });
    }

    public function test_sync_sale_fr_keeps_itemized_lines_when_document_discount_matches_payment(): void
    {
        config([
            'services.vendus.api_key' => 'test-key',
            'services.vendus.base_url' => 'https://www.vendus.pt/ws/v1.1',
            'services.vendus.auth_mode' => 'basic',
            'services.vendus.documents_endpoint' => '/documents/',
            'services.vendus.document_type' => 'FR',
            'services.vendus.tax_id' => 'NOR',
            'services.vendus.category_id' => null,
            'services.vendus.category_title' => 'Serviços',
            'services.vendus.simulate' => false,
        ]);

        Http::fake([
            'https://www.vendus.pt/ws/v1.1/products/categories*' => Http::response([
                ['id' => 555, 'title' => 'Serviços', 'status' => 'on'],
            ], 200),
            'https://www.vendus.pt/ws/v1.1/documents/paymentmethods*' => Http::response([
                ['id' => 42, 'title' => 'Numerário', 'type' => 'NU', 'status' => 'on'],
            ], 200),
            'https://www.vendus.pt/ws/v1.1/documents/' => Http::response(['id' => 989], 201),
        ]);

        $sale = new Sale();
        $sale->id = 12;
        $sale->scope = Sale::SCOPE_CAIXA_LIQUIDACAO;
        $sale->data_emissao = Carbon::parse('2026-05-07');
        $sale->desconto = 3.0;
        $sale->gorjeta = null;
        $sale->total = 12.0;
        $sale->valor_pago = 12.0;
        $sale->payment_method = Sale::PAYMENT_DINHEIRO;
        $sale->vendus_document_id = null;
        $sale->setRelation('client', new Client([
            'name' => 'Cliente',
            'email' => 'c@example.test',
            'phone' => '+351900000000',
            'nif' => '123456789',
        ]));
        $line3 = new SaleItem([
            'tipo' => 'servico',
            'service_id' => 1,
            'descricao' => 'Corte premium — promo',
            'quantidade' => 1,
            'preco_unitario' => 15.0,
            'desconto' => null,
        ]);
        $line3->setRelation('service', new Service(['id' => 1, 'name' => 'Corte premium']));
        $sale->setRelation('items', new Collection([$line3]));

        $result = app(VendusInvoiceService::class)->syncSale($sale);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://www.vendus.pt/ws/v1.1/documents/'
                && (float) $data['discount_amount'] === 3.0
                && $data['items'][0]['reference'] === 'SRV-1'
                && (string) $data['items'][0]['title'] === 'Corte premium'
                && (string) $data['items'][0]['text'] === 'Pagamento final em loja'
                && (float) $data['payments'][0]['amount'] === 12.0;
        });
    }

    public function test_sync_sale_option_uses_option_id_as_srv_reference_with_option_title(): void
    {
        config([
            'services.vendus.api_key' => 'test-key',
            'services.vendus.base_url' => 'https://www.vendus.pt/ws/v1.1',
            'services.vendus.auth_mode' => 'basic',
            'services.vendus.documents_endpoint' => '/documents/',
            'services.vendus.document_type' => 'FR',
            'services.vendus.tax_id' => 'NOR',
            'services.vendus.category_id' => null,
            'services.vendus.category_title' => 'Serviços',
            'services.vendus.simulate' => false,
        ]);

        Http::fake([
            'https://www.vendus.pt/ws/v1.1/products/categories*' => Http::response([
                ['id' => 555, 'title' => 'Serviços', 'status' => 'on'],
            ], 200),
            'https://www.vendus.pt/ws/v1.1/documents/paymentmethods*' => Http::response([
                ['id' => 42, 'title' => 'Cartão de Crédito', 'type' => 'CC', 'status' => 'on'],
            ], 200),
            'https://www.vendus.pt/ws/v1.1/documents/' => Http::response(['id' => 990], 201),
        ]);

        $sale = new Sale();
        $sale->id = 13;
        $sale->scope = Sale::SCOPE_BOOKING_RESERVA;
        $sale->data_emissao = Carbon::parse('2026-05-07');
        $sale->desconto = 0;
        $sale->total = 10;
        $sale->valor_pago = 10;
        $sale->payment_method = Sale::PAYMENT_CARTAO;
        $sale->vendus_document_id = null;
        $sale->setRelation('client', new Client([
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.test',
            'phone' => '+351912345678',
            'nif' => '123456789',
        ]));

        $line = new SaleItem([
            'id' => 101,
            'tipo' => 'servico',
            'service_id' => 101,
            'descricao' => 'Manicure Tradicional 1 — adiantamento',
            'quantidade' => 1,
            'preco_unitario' => 10,
            'desconto' => 0,
        ]);
        $line->setRelation('service', new Service(['id' => 101, 'name' => 'Manicure Tradicional']));
        $ces = new CalendarEventService([
            'id' => 501,
            'service_id' => 101,
            'service_option_id' => 9001,
            'option_name' => 'Manicure Tradicional 1',
        ]);
        $ces->setRelation('service', new Service(['id' => 101, 'name' => 'Manicure Tradicional']));
        $line->setRelation('calendarEventService', $ces);
        $sale->setRelation('items', new Collection([$line]));

        $result = app(VendusInvoiceService::class)->syncSale($sale);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://www.vendus.pt/ws/v1.1/documents/'
                && (string) $data['items'][0]['reference'] === 'SRV-9001'
                && (string) $data['items'][0]['title'] === 'Manicure Tradicional 1';
        });
    }
}
