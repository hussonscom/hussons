<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['bail', 'required', 'uuid'],
            'branch_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999.999'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'items.*.discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'items.*.inventory_unit_ids' => ['nullable', 'array', 'max:500'],
            'items.*.inventory_unit_ids.*' => ['integer'],
            'items.*.inventory_lot_id' => ['nullable', 'integer'],
            'prescription' => ['nullable', 'array'],
            'prescription.number' => ['nullable', 'string', 'max:120'],
            'prescription.issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'prescription.prescriber' => ['nullable', 'string', 'max:180'],
            'payments' => ['required', 'array', 'min:1', 'max:10'],
            'payments.*.method' => ['required', Rule::in(['cash', 'card', 'bank', 'mixed', 'other'])],
            'payments.*.amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999.99'],
            'payments.*.reference' => ['nullable', 'string', 'max:160'],
        ];
    }
}
