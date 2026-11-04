@extends('layouts.app')

@php
    $editing = (bool) $product;
@endphp
@section('title', $editing ? __('operations.edit_product') : __('operations.create_product'))

@section('content')
<form method="POST" action="{{ $editing ? route('catalog.update', $product) : route('catalog.store') }}" class="space-y-6">
    @csrf @if($editing) @method('PUT') @endif
    <div class="page-head"><div><p class="text-slate-500">{{ __('operations.catalog_subtitle') }}</p></div><div class="flex gap-2"><a class="btn" href="{{ route('catalog.index') }}">{{ __('operations.cancel') }}</a><button class="btn btn-primary" type="submit">{{ __('operations.save') }}</button></div></div>

    <section class="panel p-6"><h2 class="mb-5 text-lg font-semibold">{{ __('operations.main_info') }}</h2><div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <label class="field"><span>{{ __('operations.name_az') }}</span><input name="name_az" value="{{ old('name_az', $product?->name_az) }}" required></label>
        <label class="field"><span>{{ __('operations.name_ru') }}</span><input name="name_ru" value="{{ old('name_ru', $product?->name_ru) }}"></label>
        <label class="field"><span>{{ __('operations.name_en') }}</span><input name="name_en" value="{{ old('name_en', $product?->name_en) }}"></label>
        <label class="field"><span>{{ __('operations.sku') }}</span><input name="sku" value="{{ old('sku', $product?->sku) }}" required></label>
        <label class="field"><span>{{ __('operations.barcode') }}</span><input name="barcode" value="{{ old('barcode', $product?->barcode) }}"></label>
        <label class="field"><span>{{ __('operations.type') }}</span><select name="type" required>@foreach(['standard','electronic','drug','food','service'] as $type)<option value="{{ $type }}" @selected(old('type', $product?->type ?? 'standard') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></label>
        <label class="field"><span>{{ __('operations.category') }}</span><select name="category_id"><option value="">—</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $category->id)>{{ $category->localizedName() }}</option>@endforeach</select></label>
        <label class="field"><span>{{ __('operations.brand') }}</span><select name="brand_id"><option value="">—</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string) old('brand_id', $product?->brand_id) === (string) $brand->id)>{{ $brand->name }}</option>@endforeach</select></label>
        <label class="field"><span>{{ __('operations.unit') }}</span><select name="unit">@foreach(['pcs','pack','box','kg','g','l','ml','portion'] as $unit)<option value="{{ $unit }}" @selected(old('unit', $product?->unit ?? 'pcs') === $unit)>{{ $unit }}</option>@endforeach</select></label>
        <label class="field md:col-span-2 xl:col-span-3"><span>{{ __('operations.description') }}</span><textarea name="description" rows="3">{{ old('description', $product?->description_az) }}</textarea></label>
    </div></section>

    <section class="grid gap-6 xl:grid-cols-2">
        <div class="panel p-6"><h2 class="mb-5 text-lg font-semibold">{{ __('operations.pricing') }}</h2><div class="grid gap-4 sm:grid-cols-2">
            <label class="field"><span>{{ __('operations.purchase_price') }}</span><input type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price', $product?->purchase_price ?? 0) }}" required></label>
            <label class="field"><span>{{ __('operations.sale_price') }}</span><input type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price', $product?->sale_price ?? 0) }}" required></label>
            <label class="field"><span>{{ __('operations.tax_rate') }}</span><input type="number" step="0.01" min="0" max="100" name="tax_rate" value="{{ old('tax_rate', $product?->tax_rate ?? 0) }}"></label>
            <label class="field"><span>{{ __('operations.min_stock') }}</span><input type="number" step="0.001" min="0" name="min_stock" value="{{ old('min_stock', $product?->min_stock ?? 0) }}"></label>
            <label class="field"><span>{{ __('operations.tracking') }}</span><select name="tracking_mode" required>@foreach(['none','serial','batch'] as $mode)<option value="{{ $mode }}" @selected(old('tracking_mode', $product?->tracking_mode ?? 'none') === $mode)>{{ $mode }}</option>@endforeach</select></label>
            <label class="flex items-center gap-3 pt-7"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $product?->is_active ?? true))><span>{{ __('operations.active') }}</span></label>
        </div></div>
        <div class="panel p-6"><h2 class="mb-5 text-lg font-semibold">{{ __('operations.industry_info') }}</h2><div class="grid gap-4 sm:grid-cols-2">
            @php
                $meta = $product?->metadata ?? [];
            @endphp
            <label class="field"><span>{{ __('operations.model') }}</span><input name="model" value="{{ old('model', $meta['model'] ?? null) }}"></label>
            <label class="field"><span>{{ __('operations.color') }}</span><input name="color" value="{{ old('color', $meta['color'] ?? null) }}"></label>
            <label class="field"><span>{{ __('operations.memory') }}</span><input name="memory_capacity" value="{{ old('memory_capacity', $meta['memory_capacity'] ?? null) }}"></label>
            <label class="field"><span>{{ __('operations.warranty') }}</span><input type="number" min="0" max="240" name="warranty_months" value="{{ old('warranty_months', $meta['warranty_months'] ?? 0) }}"></label>
            <label class="field"><span>{{ __('operations.active_ingredient') }}</span><input name="active_ingredient" value="{{ old('active_ingredient', $meta['active_ingredient'] ?? null) }}"></label>
            <label class="field"><span>{{ __('operations.dosage') }}</span><input name="dosage" value="{{ old('dosage', $meta['dosage'] ?? null) }}"></label>
            <label class="field"><span>{{ __('operations.dosage_form') }}</span><input name="dosage_form" value="{{ old('dosage_form', $meta['dosage_form'] ?? null) }}"></label>
            <label class="field"><span>{{ __('operations.manufacturer') }}</span><input name="manufacturer" value="{{ old('manufacturer', $meta['manufacturer'] ?? null) }}"></label>
            <label class="flex items-center gap-3"><input type="hidden" name="prescription_required" value="0"><input type="checkbox" name="prescription_required" value="1" @checked(old('prescription_required', $meta['prescription_required'] ?? false))><span>{{ __('operations.prescription_required') }}</span></label>
        </div></div>
    </section>

    @php
        $variantRows = old('variants');
        if ($variantRows === null) {
            $variantRows = $product?->variants?->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'model' => $variant->model,
                'color' => $variant->color,
                'memory_capacity' => $variant->memory_capacity,
                'purchase_price' => $variant->purchase_price,
                'sale_price' => $variant->sale_price,
                'warranty_months' => $variant->warranty_months,
                'active' => $variant->is_active,
                'remove' => false,
            ])->values()->all() ?? [];
        }
    @endphp
    <section class="panel p-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="text-lg font-semibold">{{ __('variants.title') }}</h2><p class="mt-1 text-sm text-slate-500">{{ __('variants.help') }}</p></div>
            <button class="btn" type="button" data-add-variant>+ {{ __('variants.add') }}</button>
        </div>
        <div class="space-y-4" data-variant-list>
            @foreach($variantRows as $index => $variant)
                <article class="rounded-2xl border border-slate-200 p-4" data-variant-row data-existing="{{ !empty($variant['id']) ? '1' : '0' }}">
                    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] ?? '' }}">
                    <input type="hidden" name="variants[{{ $index }}][remove]" value="{{ (int)($variant['remove'] ?? false) }}" data-variant-remove>
                    <div class="mb-4 flex items-center justify-between"><strong>{{ __('variants.variant') }} #{{ $index + 1 }}</strong><button class="text-sm font-semibold text-rose-600" type="button" data-remove-variant>{{ __('variants.remove') }}</button></div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label class="field"><span>{{ __('operations.sku') }}</span><input name="variants[{{ $index }}][sku]" value="{{ $variant['sku'] ?? '' }}" required></label>
                        <label class="field"><span>{{ __('operations.barcode') }}</span><input name="variants[{{ $index }}][barcode]" value="{{ $variant['barcode'] ?? '' }}"></label>
                        <label class="field"><span>{{ __('operations.model') }}</span><input name="variants[{{ $index }}][model]" value="{{ $variant['model'] ?? '' }}"></label>
                        <label class="field"><span>{{ __('operations.color') }}</span><input name="variants[{{ $index }}][color]" value="{{ $variant['color'] ?? '' }}"></label>
                        <label class="field"><span>{{ __('operations.memory') }}</span><input name="variants[{{ $index }}][memory_capacity]" value="{{ $variant['memory_capacity'] ?? '' }}"></label>
                        <label class="field"><span>{{ __('operations.purchase_price') }}</span><input type="number" min="0" step="0.01" name="variants[{{ $index }}][purchase_price]" value="{{ $variant['purchase_price'] ?? '' }}"></label>
                        <label class="field"><span>{{ __('operations.sale_price') }}</span><input type="number" min="0" step="0.01" name="variants[{{ $index }}][sale_price]" value="{{ $variant['sale_price'] ?? '' }}"></label>
                        <label class="field"><span>{{ __('operations.warranty') }}</span><input type="number" min="0" max="240" name="variants[{{ $index }}][warranty_months]" value="{{ $variant['warranty_months'] ?? 0 }}"></label>
                        <label class="flex items-center gap-3"><input type="hidden" name="variants[{{ $index }}][active]" value="0"><input type="checkbox" name="variants[{{ $index }}][active]" value="1" @checked($variant['active'] ?? true)><span>{{ __('operations.active') }}</span></label>
                    </div>
                </article>
            @endforeach
        </div>
        <p class="mt-4 text-sm text-slate-500" data-variant-empty @if(count($variantRows)) hidden @endif>{{ __('variants.empty') }}</p>
    </section>
</form>

<template data-variant-template>
    <article class="rounded-2xl border border-slate-200 p-4" data-variant-row data-existing="0">
        <input type="hidden" name="variants[__INDEX__][remove]" value="0" data-variant-remove>
        <div class="mb-4 flex items-center justify-between"><strong>{{ __('variants.variant') }}</strong><button class="text-sm font-semibold text-rose-600" type="button" data-remove-variant>{{ __('variants.remove') }}</button></div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="field"><span>{{ __('operations.sku') }}</span><input name="variants[__INDEX__][sku]" required></label>
            <label class="field"><span>{{ __('operations.barcode') }}</span><input name="variants[__INDEX__][barcode]"></label>
            <label class="field"><span>{{ __('operations.model') }}</span><input name="variants[__INDEX__][model]"></label>
            <label class="field"><span>{{ __('operations.color') }}</span><input name="variants[__INDEX__][color]"></label>
            <label class="field"><span>{{ __('operations.memory') }}</span><input name="variants[__INDEX__][memory_capacity]"></label>
            <label class="field"><span>{{ __('operations.purchase_price') }}</span><input type="number" min="0" step="0.01" name="variants[__INDEX__][purchase_price]"></label>
            <label class="field"><span>{{ __('operations.sale_price') }}</span><input type="number" min="0" step="0.01" name="variants[__INDEX__][sale_price]"></label>
            <label class="field"><span>{{ __('operations.warranty') }}</span><input type="number" min="0" max="240" name="variants[__INDEX__][warranty_months]" value="0"></label>
            <label class="flex items-center gap-3"><input type="hidden" name="variants[__INDEX__][active]" value="0"><input type="checkbox" name="variants[__INDEX__][active]" value="1" checked><span>{{ __('operations.active') }}</span></label>
        </div>
    </article>
</template>
@endsection

@push('scripts')
<script>
(() => {
    const list = document.querySelector('[data-variant-list]');
    const add = document.querySelector('[data-add-variant]');
    const template = document.querySelector('[data-variant-template]');
    const empty = document.querySelector('[data-variant-empty]');
    if (!list || !add || !template) return;
    let nextIndex = {{ count($variantRows) }};
    const refreshEmpty = () => { empty.hidden = list.querySelectorAll('[data-variant-row]:not(.is-removed)').length > 0; };
    const bind = row => {
        row.querySelector('[data-remove-variant]').addEventListener('click', event => {
            if (row.dataset.existing === '1') {
                const removed = row.querySelector('[data-variant-remove]');
                const isRemoved = removed.value === '1';
                removed.value = isRemoved ? '0' : '1';
                row.classList.toggle('is-removed', !isRemoved);
                row.classList.toggle('opacity-40', !isRemoved);
                event.currentTarget.textContent = isRemoved ? '{{ __('variants.remove') }}' : '{{ __('variants.restore') }}';
            } else {
                row.remove();
            }
            refreshEmpty();
        });
    };
    list.querySelectorAll('[data-variant-row]').forEach(bind);
    add.addEventListener('click', () => {
        const holder = document.createElement('div');
        holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', nextIndex++);
        const row = holder.firstElementChild;
        bind(row);
        list.append(row);
        refreshEmpty();
        row.querySelector('input[name$="[sku]"]').focus();
    });
    refreshEmpty();
})();
</script>
@endpush
