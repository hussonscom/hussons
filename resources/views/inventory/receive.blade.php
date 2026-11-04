@extends('layouts.app')

@section('title', __('operations.receive_title'))

@section('content')
<form method="POST" action="{{ route('inventory.store') }}" data-receive-form>
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="page-head"><div><p class="text-slate-500">{{ __('operations.receive_subtitle') }}</p></div><div class="flex gap-2"><a class="btn" href="{{ route('inventory.index') }}">{{ __('operations.cancel') }}</a><button class="btn btn-primary" type="submit">{{ __('operations.complete_receipt') }}</button></div></div>
    <section class="panel mb-5 grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
        <label class="field"><span>{{ __('operations.warehouse') }}</span><select name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)old('warehouse_id') === (string)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></label>
        <label class="field"><span>{{ __('operations.supplier') }}</span><select name="supplier_id"><option value="">—</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label>
        <label class="field"><span>{{ __('operations.reference') }}</span><input name="reference" value="{{ old('reference') }}"></label>
        <label class="field"><span>{{ __('operations.received_at') }}</span><input type="datetime-local" name="received_at" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"></label>
        <label class="field md:col-span-2 xl:col-span-4"><span>{{ __('operations.notes') }}</span><textarea name="notes" rows="2">{{ old('notes') }}</textarea></label>
    </section>

    <section class="space-y-3" data-receive-lines></section>
    <button class="btn mt-4" type="button" data-add-receive>+ {{ __('operations.add_line') }}</button>
</form>

<template data-receive-template>
    <article class="panel p-5" data-receive-line>
        <div class="mb-4 flex items-center justify-between"><strong>#<span data-line-number></span></strong><button class="text-sm font-semibold text-rose-600" type="button" data-remove-line>× {{ __('operations.archive') }}</button></div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="field md:col-span-2"><span>{{ __('operations.product') }}</span><select name="items[__INDEX__][product_id]" data-product required><option value="">—</option>@foreach($products as $product)<option value="{{ $product->id }}" data-tracking="{{ $product->tracking_mode }}" data-cost="{{ $product->purchase_price }}" data-price="{{ $product->sale_price }}" data-warranty="{{ data_get($product->metadata, 'warranty_months', 0) }}">{{ $product->localizedName() }} · {{ $product->sku }} · {{ $product->tracking_mode }}</option>@endforeach</select></label>
            <label class="field"><span>{{ __('variants.select') }}</span><select name="items[__INDEX__][product_variant_id]" data-variant disabled><option value="">{{ __('variants.base_product') }}</option></select></label>
            <label class="field"><span>{{ __('operations.quantity') }}</span><input type="number" min="0.001" step="0.001" name="items[__INDEX__][quantity]" value="1" required></label>
            <label class="field"><span>{{ __('operations.unit_cost') }}</span><input type="number" min="0" step="0.01" name="items[__INDEX__][unit_cost]" value="0" data-cost required></label>
            <label class="field"><span>{{ __('operations.sale_price') }}</span><input type="number" min="0" step="0.01" name="items[__INDEX__][sale_price]" value="0" data-price></label>
            <label class="field"><span>{{ __('variants.warranty_override') }}</span><input type="number" min="0" max="240" name="items[__INDEX__][warranty_months]" value="0" data-warranty></label>
            <label class="field"><span>{{ __('operations.batch_number') }}</span><input name="items[__INDEX__][batch_number]" data-batch></label>
            <label class="field"><span>{{ __('operations.expiry_date') }}</span><input type="date" name="items[__INDEX__][expiry_date]" data-expiry></label>
            <label class="field md:col-span-2 xl:col-span-4"><span>{{ __('operations.serials_imeis') }}</span><textarea rows="3" placeholder="Serial | IMEI1 | IMEI2" data-serial-input></textarea><small class="text-slate-400">Serial | IMEI1 | IMEI2</small></label>
        </div>
    </article>
</template>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-receive-form]');
    if (!form) return;
    const list = form.querySelector('[data-receive-lines]');
    const template = document.querySelector('[data-receive-template]');
    const variants = @json($products->mapWithKeys(fn ($product) => [$product->id => $product->variants->where('is_active', true)->values()->map(fn ($variant) => [
        'id' => $variant->id,
        'sku' => $variant->sku,
        'model' => $variant->model,
        'color' => $variant->color,
        'memory' => $variant->memory_capacity,
        'cost' => (float) ($variant->purchase_price ?? $product->purchase_price),
        'price' => (float) ($variant->sale_price ?? $product->sale_price),
        'warranty' => (int) $variant->warranty_months,
    ])->all()]));
    let nextIndex = 0;
    const addLine = () => {
        const index = nextIndex++;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', index);
        const line = wrapper.firstElementChild;
        line.dataset.index = index;
        line.querySelector('[data-line-number]').textContent = index + 1;
        line.querySelector('[data-remove-line]').addEventListener('click', () => line.remove());
        line.querySelector('[data-product]').addEventListener('change', event => {
            const option = event.target.selectedOptions[0];
            line.querySelector('[data-cost]').value = option?.dataset.cost || 0;
            line.querySelector('[data-price]').value = option?.dataset.price || 0;
            line.querySelector('[data-warranty]').value = option?.dataset.warranty || 0;
            const variantSelect = line.querySelector('[data-variant]');
            const availableVariants = variants[event.target.value] || [];
            variantSelect.innerHTML = '<option value="">{{ __('variants.base_product') }}</option>';
            availableVariants.forEach(variant => {
                const variantOption = document.createElement('option');
                variantOption.value = variant.id;
                variantOption.dataset.cost = variant.cost;
                variantOption.dataset.price = variant.price;
                variantOption.dataset.warranty = variant.warranty;
                variantOption.textContent = [variant.sku, variant.model, variant.color, variant.memory].filter(Boolean).join(' · ');
                variantSelect.append(variantOption);
            });
            variantSelect.disabled = availableVariants.length === 0;
            variantSelect.required = availableVariants.length > 0;
            const mode = option?.dataset.tracking || 'none';
            line.querySelector('[data-batch]').closest('label').classList.toggle('opacity-40', mode !== 'batch');
            line.querySelector('[data-expiry]').closest('label').classList.toggle('opacity-40', mode !== 'batch');
            line.querySelector('[data-serial-input]').closest('label').classList.toggle('opacity-40', mode !== 'serial');
        });
        line.querySelector('[data-variant]').addEventListener('change', event => {
            const option = event.target.selectedOptions[0];
            if (!option?.value) return;
            line.querySelector('[data-cost]').value = option.dataset.cost || 0;
            line.querySelector('[data-price]').value = option.dataset.price || 0;
            line.querySelector('[data-warranty]').value = option.dataset.warranty || 0;
        });
        list.appendChild(line);
    };
    form.querySelector('[data-add-receive]').addEventListener('click', addLine);
    form.addEventListener('submit', () => {
        list.querySelectorAll('[data-receive-line]').forEach(line => {
            const index = line.dataset.index;
            line.querySelectorAll('[data-generated-unit]').forEach(el => el.remove());
            const entries = line.querySelector('[data-serial-input]').value.split('\n').map(v => v.trim()).filter(Boolean);
            entries.forEach((entry, unitIndex) => {
                const [serial = '', imei1 = '', imei2 = ''] = entry.split('|').map(v => v.trim());
                [['serial_number', serial], ['imei1', imei1], ['imei2', imei2]].forEach(([key, value]) => {
                    const input = document.createElement('input'); input.type = 'hidden'; input.dataset.generatedUnit = '1'; input.name = `items[${index}][units][${unitIndex}][${key}]`; input.value = value; line.appendChild(input);
                });
            });
        });
    });
    addLine();
})();
</script>
@endpush
