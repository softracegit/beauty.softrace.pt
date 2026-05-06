<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
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
            'services.vendus.document_type' => 'FT',
            'services.vendus.tax_id' => 'NOR',
            'services.vendus.category_id' => null,
            'services.vendus.category_title' => 'Serviços',
            'services.vendus.simulate' => false,
        ]);

        Http::fake([
            'https://www.vendus.pt/ws/v1.1/products/categories*' => Http::response([
                ['id' => 555, 'title' => 'Serviços', 'status' => 'on'],
            ], 200),
            'https://www.vendus.pt/ws/v1.1/documents/' => Http::response(['id' => 987], 201),
        ]);

        $sale = new Sale();
        $sale->id = 10;
        $sale->data_emissao = Carbon::parse('2026-05-05');
        $sale->desconto = 0;
        $sale->vendus_document_id = null;
        $sale->setRelation('client', new Client([
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.test',
            'phone' => '+351912345678',
            'nif' => '123456789',
        ]));
        $sale->setRelation('items', new Collection([
            new SaleItem([
                'id' => 100,
                'tipo' => 'servico',
                'service_id' => 77,
                'descricao' => 'Servico X',
                'quantidade' => 1,
                'preco_unitario' => 20,
                'desconto' => 0,
            ]),
        ]));

        $result = app(VendusInvoiceService::class)->syncSale($sale);

        $this->assertTrue($result['ok']);
        $this->assertSame(201, $result['status']);
        $this->assertSame(987, $result['document_id']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://www.vendus.pt/ws/v1.1/documents/'
                && isset($data['type'])
                && $data['type'] === 'FT'
                && isset($data['external_reference'])
                && $data['external_reference'] === 'SALE-10'
                && isset($data['items'][0]['reference'])
                && $data['items'][0]['reference'] === 'SRV-77'
                && isset($data['items'][0]['category_id'])
                && (int) $data['items'][0]['category_id'] === 555;
        });
    }
}
