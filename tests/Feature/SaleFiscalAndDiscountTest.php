<?php

namespace Tests\Feature;

use App\Contracts\FiscalProvider;
use App\Models\FiscalReceipt;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Services\InventoryService;
use App\Services\Money;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class SaleFiscalAndDiscountTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_provider_issue_and_refund_create_separate_receipts_and_balanced_payments(): void
    {
        $provider = new class implements FiscalProvider
        {
            /** @var list<array{operation: string, status: string}> */
            public array $calls = [];

            public function name(): string
            {
                return 'test-provider';
            }

            public function issue(Sale $sale): array
            {
                $this->calls[] = ['operation' => 'issue', 'status' => $sale->status];

                return [
                    'status' => 'accepted',
                    'external_id' => 'test-issue-1',
                    'payload' => ['receipt_number' => 'FP-ISSUE-1'],
                ];
            }

            public function refund(Sale $sale): array
            {
                $this->calls[] = ['operation' => 'refund', 'status' => $sale->status];

                return [
                    'status' => 'accepted',
                    'external_id' => 'test-refund-1',
                    'payload' => ['receipt_number' => 'FP-REFUND-1'],
                ];
            }
        };
        $this->app->instance(FiscalProvider::class, $provider);

        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization'], ['sale_price' => 80]);
        $this->receive($workspace, $product->id, 2, 40);

        $sales = app(SaleService::class);
        $sale = $sales->checkout(
            $workspace['organization']->id,
            $workspace['branch'],
            $workspace['warehouse'],
            $workspace['user'],
            [
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 80,
                ]],
                'payments' => [['method' => 'card', 'amount' => 80, 'reference' => 'AUTH-1']],
            ],
            'owner',
        );
        $originalPayment = $sale->payments->firstOrFail();

        $refunded = $sales->refund(
            $workspace['organization']->id,
            $sale,
            $workspace['user'],
            'Customer return',
        );

        $this->assertSame([
            ['operation' => 'issue', 'status' => 'completed'],
            ['operation' => 'refund', 'status' => 'refunded'],
        ], $provider->calls);
        $this->assertSame('refunded', $refunded->status);

        $receipts = FiscalReceipt::query()->where('sale_id', $sale->id)->orderBy('id')->get();
        $this->assertCount(2, $receipts);
        $this->assertSame(['issue', 'refund'], $receipts->pluck('operation')->all());
        $this->assertSame(['FP-ISSUE-1', 'FP-REFUND-1'], $receipts->pluck('fiscal_number')->all());
        $this->assertSame('refund', $receipts->last()->request_payload['operation']);

        $payments = $refunded->payments()->orderBy('id')->get();
        $this->assertSame(['80.00', '-80.00'], $payments->pluck('amount')->all());
        $this->assertSame(0, $payments->sum(fn ($payment): int => Money::toCents($payment->amount)));
        $this->assertSame('refund', $payments->last()->metadata['type']);
        $this->assertSame($originalPayment->id, $payments->last()->metadata['reverses_payment_id']);
        $this->assertSame('2.000', Stock::query()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_cashier_and_pharmacist_cannot_apply_global_or_line_discounts(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization'], ['sale_price' => 80]);
        $this->receive($workspace, $product->id, 5, 40);
        $sales = app(SaleService::class);

        $attempts = [
            ['role' => 'cashier', 'global' => 1, 'line' => 0, 'field' => 'discount'],
            ['role' => 'pharmacist', 'global' => 0, 'line' => 1, 'field' => 'items.0.discount'],
        ];

        foreach ($attempts as $attempt) {
            try {
                $sales->checkout(
                    $workspace['organization']->id,
                    $workspace['branch'],
                    $workspace['warehouse'],
                    $workspace['user'],
                    [
                        'discount' => $attempt['global'],
                        'items' => [[
                            'product_id' => $product->id,
                            'quantity' => 1,
                            'unit_price' => 80,
                            'discount' => $attempt['line'],
                        ]],
                        'payments' => [['method' => 'cash', 'amount' => 80]],
                    ],
                    $attempt['role'],
                );
                $this->fail('Unauthorized discount was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($attempt['field'], $exception->errors());
            }
        }

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame('5.000', Stock::query()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_global_discount_is_distributed_before_tax_and_item_totals_reconcile(): void
    {
        $workspace = $this->createWorkspace();
        $first = $this->createProduct($workspace['organization'], [
            'sale_price' => 10,
            'tax_rate' => 10,
        ]);
        $second = $this->createProduct($workspace['organization'], [
            'sale_price' => 20,
            'tax_rate' => 20,
        ]);
        $this->receive($workspace, $first->id, 2, 4);
        $this->receive($workspace, $second->id, 2, 8);

        $sale = app(SaleService::class)->checkout(
            $workspace['organization']->id,
            $workspace['branch'],
            $workspace['warehouse'],
            $workspace['user'],
            [
                'discount' => 2.99,
                'items' => [
                    [
                        'product_id' => $first->id,
                        'quantity' => 1,
                        'unit_price' => 10,
                        'discount' => 1,
                    ],
                    [
                        'product_id' => $second->id,
                        'quantity' => 1,
                        'unit_price' => 20,
                        'discount' => 0,
                    ],
                ],
                'payments' => [['method' => 'card', 'amount' => 30.41]],
            ],
            'owner',
        );

        $items = SaleItem::query()->where('sale_id', $sale->id)->orderBy('id')->get();
        $this->assertSame('30.00', $sale->subtotal);
        $this->assertSame('3.99', $sale->discount);
        $this->assertSame('4.40', $sale->tax);
        $this->assertSame('30.41', $sale->total);
        $this->assertSame(399, $items->sum(fn (SaleItem $item): int => Money::toCents($item->discount)));
        $this->assertSame(440, $items->sum(fn (SaleItem $item): int => Money::toCents($item->tax)));
        $this->assertSame(3041, $items->sum(fn (SaleItem $item): int => Money::toCents($item->total)));
        $this->assertSame(['1.92', '2.07'], $items->pluck('discount')->all());
        $this->assertDatabaseHas('fiscal_receipts', [
            'sale_id' => $sale->id,
            'operation' => 'issue',
            'provider' => 'internal',
        ]);
    }

    /** @param array{organization: mixed, branch: mixed, warehouse: mixed, user: mixed} $workspace */
    private function receive(array $workspace, int $productId, int $quantity, int $unitCost): void
    {
        app(InventoryService::class)->receive(
            $workspace['organization']->id,
            $workspace['branch']->id,
            $workspace['warehouse'],
            $workspace['user'],
            ['items' => [[
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ]]],
        );
    }
}
