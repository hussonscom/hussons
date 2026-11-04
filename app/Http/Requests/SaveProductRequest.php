<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(['standard', 'electronic', 'drug', 'food', 'service'])],
            'tracking_mode' => ['required', Rule::in(['none', 'serial', 'batch'])],
            'sku' => ['required', 'string', 'max:64'],
            'barcode' => ['nullable', 'string', 'max:80'],
            'name_az' => ['required', 'string', 'max:180'],
            'name_ru' => ['nullable', 'string', 'max:180'],
            'name_en' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'unit' => ['required', Rule::in(['pcs', 'piece', 'pack', 'box', 'kg', 'g', 'l', 'ml', 'portion'])],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'active' => ['sometimes', 'boolean'],
            'model' => ['nullable', 'string', 'max:160'],
            'color' => ['nullable', 'string', 'max:80'],
            'memory_capacity' => ['nullable', 'string', 'max:64'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:240'],
            'active_ingredient' => ['nullable', 'string', 'max:255'],
            'dosage' => ['nullable', 'string', 'max:120'],
            'dosage_form' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:180'],
            'prescription_required' => ['sometimes', 'boolean'],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required', 'string', 'max:64'],
            'variants.*.barcode' => ['nullable', 'string', 'max:80'],
            'variants.*.model' => ['nullable', 'string', 'max:160'],
            'variants.*.color' => ['nullable', 'string', 'max:80'],
            'variants.*.memory_capacity' => ['nullable', 'string', 'max:64'],
            'variants.*.purchase_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'variants.*.warranty_months' => ['nullable', 'integer', 'min:0', 'max:240'],
            'variants.*.active' => ['sometimes', 'boolean'],
            'variants.*.remove' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_az' => __('Product name'),
            'purchase_price' => __('Purchase price'),
            'sale_price' => __('Sale price'),
            'tracking_mode' => __('Tracking mode'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') === 'drug' && $this->input('tracking_mode') !== 'batch') {
                $validator->errors()->add('tracking_mode', __('Drug products must be tracked by batch and expiry date.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $variants = array_map(static function (array $variant): array {
            foreach (['sku', 'barcode', 'model', 'color', 'memory_capacity'] as $field) {
                if (array_key_exists($field, $variant) && is_string($variant[$field])) {
                    $variant[$field] = trim($variant[$field]);
                }
            }

            return $variant;
        }, (array) $this->input('variants', []));

        $merge = [
            'sku' => trim((string) $this->input('sku')),
            'barcode' => filled($this->input('barcode')) ? trim((string) $this->input('barcode')) : null,
        ];
        if ($this->exists('variants')) {
            $merge['variants'] = $variants;
        }

        $this->merge($merge);
    }
}
