<?php

namespace App\Services\Fiscal;

use App\Contracts\FiscalProvider;
use App\Models\Sale;

final class InternalReceiptProvider implements FiscalProvider
{
    public function name(): string
    {
        return 'internal';
    }

    public function issue(Sale $sale): array
    {
        return [
            'status' => 'internal',
            'external_id' => null,
            'payload' => [
                'receipt_number' => $sale->number,
                'issued_at' => now()->toIso8601String(),
                'notice' => 'Internal receipt. No external fiscal provider is configured.',
            ],
        ];
    }

    public function refund(Sale $sale): array
    {
        return [
            'status' => 'internal',
            'external_id' => null,
            'payload' => [
                'receipt_number' => $sale->number.'-R',
                'original_receipt_number' => $sale->number,
                'refunded_at' => now()->toIso8601String(),
                'notice' => 'Internal refund. No external fiscal provider is configured.',
            ],
        ];
    }
}
