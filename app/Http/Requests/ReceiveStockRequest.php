<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['bail', 'required', 'uuid'],
            'warehouse_id' => ['required', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:120'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:9999999.999'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'items.*.sale_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'items.*.warranty_months' => ['nullable', 'integer', 'min:0', 'max:240'],
            'items.*.batch_number' => ['nullable', 'string', 'max:120'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.units' => ['nullable', 'array', 'max:500'],
            'items.*.units.*.serial_number' => ['nullable', 'string', 'max:128', 'distinct:ignore_case'],
            'items.*.units.*.imei1' => ['nullable', 'string', 'regex:/^\d{14,16}$/', 'distinct'],
            'items.*.units.*.imei2' => ['nullable', 'string', 'regex:/^\d{14,16}$/', 'distinct'],
            'items.*.units.*.warranty_months' => ['nullable', 'integer', 'min:0', 'max:240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = array_map(static function (array $item): array {
            if (isset($item['units']) && is_array($item['units'])) {
                $item['units'] = array_map(static function (array $unit): array {
                    if (isset($unit['serial_number'])) {
                        $unit['serial_number'] = trim((string) $unit['serial_number']);
                    }
                    foreach (['imei1', 'imei2'] as $field) {
                        if (filled($unit[$field] ?? null)) {
                            $unit[$field] = preg_replace('/\D+/', '', (string) $unit[$field]);
                        }
                    }

                    return $unit;
                }, $item['units']);
            }

            return $item;
        }, (array) $this->input('items', []));

        $this->merge(['items' => $items]);
    }
}
