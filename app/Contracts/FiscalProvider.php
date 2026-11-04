<?php

namespace App\Contracts;

use App\Models\Sale;

interface FiscalProvider
{
    public function name(): string;

    /**
     * @return array{status: string, external_id: string|null, payload: array<string, mixed>}
     */
    public function issue(Sale $sale): array;

    /**
     * @return array{status: string, external_id: string|null, payload: array<string, mixed>}
     */
    public function refund(Sale $sale): array;
}
