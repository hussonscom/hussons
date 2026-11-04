@extends('layouts.app')

@php
    $catalog = collect();
    foreach ($products as $product) {
        $activeVariants = $product->variants->where('is_active', true)->values();
        $sellableVariants = $activeVariants->isEmpty() ? collect([null]) : $activeVariants;
        foreach ($sellableVariants as $variant) {
            $parts = array_filter([$variant?->model, $variant?->color, $variant?->memory_capacity]);
            $variantId = $variant?->id;
            $catalog->push([
                'key' => $product->id.':'.($variantId ?? 0),
                'id' => $product->id,
                'variant_id' => $variantId,
                'name' => $product->localizedName().($parts ? ' · '.implode(' · ', $parts) : ''),
                'sku' => $variant?->sku ?? $product->sku,
                'barcode' => $variant?->barcode ?? $product->barcode,
                'price' => (float) ($variant?->sale_price ?? $product->sale_price),
                'tax_rate' => (float) $product->tax_rate,
                'tracking' => $product->tracking_mode,
                'stock' => (float) $product->stocks->where('product_variant_id', $variantId)->sum('available_quantity'),
                'units' => ($serialUnits[$product->id] ?? collect())
                    ->where('product_variant_id', $variantId)
                    ->map(fn ($unit) => ['id' => $unit->id, 'warehouse_id' => $unit->warehouse_id, 'label' => $unit->serial_number ?: $unit->imei1])
                    ->values(),
                'lots' => ($lots[$product->id] ?? collect())
                    ->where('product_variant_id', $variantId)
                    ->map(fn ($lot) => ['id' => $lot->id, 'warehouse_id' => $lot->warehouse_id, 'label' => $lot->batch_number.' · '.optional($lot->expiry_date)->format('d.m.Y'), 'quantity' => (float) $lot->quantity_available])
                    ->values(),
            ]);
        }
    }
@endphp

@section('title', __('operations.pos_title'))

@section('content')
<div class="page-head"><div><p class="text-slate-500">{{ __('operations.pos_subtitle') }}</p></div><a class="btn" href="{{ route('sales.index') }}">← {{ __('operations.sales_title') }}</a></div>
<form method="POST" action="{{ route('sales.store') }}" data-pos-form>
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <input type="hidden" name="branch_id" value="{{ $currentBranch->id }}">
    <div class="grid gap-6 xl:grid-cols-[1fr_430px]">
        <section>
            <div class="panel mb-4 grid gap-3 p-4 sm:grid-cols-[1fr_220px]"><label class="field"><span class="sr-only">{{ __('ui.search') }}</span><input type="search" placeholder="{{ __('operations.search_products') }}" data-product-search></label><label class="field"><span>{{ __('operations.warehouse') }}</span><select name="warehouse_id" required data-warehouse>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></label></div>
            <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3" data-product-grid>
                @foreach($catalog as $item)<button class="panel group p-4 text-left transition hover:border-blue-300 hover:shadow-md" type="button" data-add-product="{{ $item['key'] }}"><div class="flex items-start justify-between gap-3"><span><strong class="block text-slate-950">{{ $item['name'] }}</strong><small class="font-mono text-slate-400">{{ $item['sku'] }} @if($item['barcode'])· {{ $item['barcode'] }}@endif</small></span><span class="badge bg-slate-100 text-slate-700">{{ $item['tracking'] }}</span></div><div class="mt-5 flex items-end justify-between"><strong class="text-xl">{{ number_format($item['price'], 2) }} ₼</strong><small class="text-slate-500">{{ $item['stock'] }} {{ __('operations.stock') }}</small></div></button>@endforeach
            </div>
        </section>
        <aside class="panel h-fit overflow-hidden xl:sticky xl:top-5"><header class="border-b border-slate-100 p-5"><h2 class="text-lg font-semibold">{{ __('operations.cart') }}</h2></header><div class="max-h-[46vh] divide-y divide-slate-100 overflow-y-auto" data-cart><p class="p-10 text-center text-sm text-slate-500" data-cart-empty>{{ __('operations.no_sales') }}</p></div>
            <div class="space-y-4 border-t border-slate-100 p-5">
                <label class="field"><span>{{ __('operations.customer') }}</span><select name="customer_id"><option value="">—</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }} @if($customer->phone)· {{ $customer->phone }}@endif</option>@endforeach</select></label>
                <label class="field"><span>{{ __('operations.discount') }}</span><input type="number" min="0" step="0.01" name="discount" value="0" data-sale-discount></label>
                <details class="rounded-xl border border-slate-200 p-3"><summary class="cursor-pointer text-sm font-semibold">{{ __('operations.prescription_required') }}</summary><div class="mt-3 grid gap-3"><label class="field"><span>{{ __('operations.number') }}</span><input name="prescription[number]"></label><label class="field"><span>{{ __('operations.received_at') }}</span><input type="date" name="prescription[issued_at]"></label><label class="field"><span>{{ __('operations.manufacturer') }}</span><input name="prescription[prescriber]"></label></div></details>
                <div class="grid gap-3 sm:grid-cols-2"><label class="field"><span>{{ __('operations.method') }}</span><select name="payments[0][method]"><option value="cash">Cash</option><option value="card">Card</option><option value="bank">Bank</option><option value="other">Other</option></select></label><label class="field"><span>{{ __('operations.amount') }}</span><input type="number" min="0.01" step="0.01" name="payments[0][amount]" data-payment required></label></div>
                <label class="field"><span>{{ __('operations.notes') }}</span><textarea name="notes" rows="2"></textarea></label>
                <div class="flex items-center justify-between border-t border-slate-100 pt-4"><span class="text-slate-500">{{ __('operations.total') }}</span><strong class="text-2xl" data-cart-total>0.00 ₼</strong></div>
                <button class="btn btn-primary w-full justify-center" type="submit" data-submit-sale disabled>{{ __('operations.complete_sale') }}</button>
            </div>
        </aside>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const catalog = @json($catalog);
    const form = document.querySelector('[data-pos-form]'); if (!form) return;
    const cartNode = form.querySelector('[data-cart]'), totalNode = form.querySelector('[data-cart-total]'), payment = form.querySelector('[data-payment]'), saleDiscount = form.querySelector('[data-sale-discount]'), submit = form.querySelector('[data-submit-sale]');
    const warehouse = form.querySelector('[data-warehouse]'); let cart = [];
    const money = value => Math.round((Number(value) || 0) * 100) / 100;
    const render = () => {
        cartNode.innerHTML = ''; let total = 0;
        cart.forEach((entry, index) => {
            const product = catalog.find(item => item.key === entry.key); const row = document.createElement('div'); row.className = 'p-4';
            const header = document.createElement('div'); header.className = 'mb-3 flex items-start justify-between gap-3'; const label = document.createElement('span'); const name = document.createElement('strong'); name.className='block text-sm'; name.textContent=product.name; const sku=document.createElement('small');sku.className='font-mono text-slate-400';sku.textContent=product.sku;label.append(name,sku); const remove=document.createElement('button');remove.type='button';remove.className='text-rose-600';remove.textContent='×';remove.onclick=()=>{cart.splice(index,1);render();};header.append(label,remove);row.append(header);
            const fields=document.createElement('div');fields.className='grid grid-cols-3 gap-2';fields.innerHTML=`<input type="hidden" name="items[${index}][product_id]" value="${product.id}">${product.variant_id ? `<input type="hidden" name="items[${index}][product_variant_id]" value="${product.variant_id}">` : ''}<label class="field"><span>{{ __('operations.quantity') }}</span><input type="number" min="0.001" max="${product.stock}" step="0.001" name="items[${index}][quantity]" value="${entry.quantity}"></label><label class="field"><span>{{ __('operations.price') }}</span><input type="number" min="0" step="0.01" name="items[${index}][unit_price]" value="${entry.price.toFixed(2)}"></label><label class="field"><span>{{ __('operations.discount') }}</span><input type="number" min="0" step="0.01" name="items[${index}][discount]" value="0"></label>`; row.append(fields);
            const quantityInput=fields.querySelector(`[name="items[${index}][quantity]"]`), priceInput=fields.querySelector(`[name="items[${index}][unit_price]"]`); [quantityInput,priceInput].forEach(input=>input.addEventListener('input',()=>{entry.quantity=Number(quantityInput.value)||0;entry.price=Number(priceInput.value)||0;updateTotal();}));
            if(product.tracking==='serial'){const select=document.createElement('select');select.multiple=true;select.name=`items[${index}][inventory_unit_ids][]`;select.className='mt-3 w-full';product.units.filter(unit=>String(unit.warehouse_id)===warehouse.value).forEach(unit=>{const option=document.createElement('option');option.value=unit.id;option.textContent=unit.label;select.append(option);});select.addEventListener('change',()=>{entry.quantity=select.selectedOptions.length;quantityInput.value=entry.quantity;updateTotal();});const wrap=document.createElement('label');wrap.className='field mt-3';wrap.innerHTML='<span>{{ __('operations.select_serials') }}</span>';wrap.append(select);row.append(wrap);quantityInput.readOnly=true;}
            if(product.tracking==='batch'){const info=document.createElement('p');info.className='mt-3 text-xs text-slate-500';const available=product.lots.filter(lot=>String(lot.warehouse_id)===warehouse.value);info.textContent=available.length ? `FEFO · ${available.map(lot=>`${lot.label} · ${lot.quantity}`).join(' / ')}` : 'FEFO · 0';row.append(info);}
            cartNode.append(row); total += money(entry.quantity*entry.price*(1+product.tax_rate/100));
        });
        if(!cart.length){const empty=document.createElement('p');empty.className='p-10 text-center text-sm text-slate-500';empty.textContent='{{ __('operations.no_sales') }}';cartNode.append(empty);} updateTotal(); submit.disabled=!cart.length;
    };
    const updateTotal=()=>{let total=cart.reduce((sum,entry)=>{const product=catalog.find(item=>item.key===entry.key);return sum+entry.quantity*entry.price*(1+product.tax_rate/100);},0)-Number(saleDiscount.value||0);total=Math.max(0,money(total));totalNode.textContent=total.toFixed(2)+' ₼';payment.value=total.toFixed(2);};
    const addProduct = key => {const existing=cart.find(item=>item.key===key), product=catalog.find(item=>item.key===key);if(!product)return;if(existing&&product.tracking==='none')existing.quantity+=1;else if(!existing)cart.push({key,quantity:product.tracking==='serial'?0:1,price:product.price});render();};
    document.querySelectorAll('[data-add-product]').forEach(button=>button.addEventListener('click',()=>addProduct(button.dataset.addProduct)));
    const productSearch=form.querySelector('[data-product-search]');
    productSearch.addEventListener('input',event=>{const term=event.target.value.trim().toLowerCase();document.querySelectorAll('[data-add-product]').forEach(button=>{const product=catalog.find(item=>item.key===button.dataset.addProduct);button.hidden=!(product.name+' '+product.sku+' '+(product.barcode||'')).toLowerCase().includes(term);});});
    productSearch.addEventListener('keydown',event=>{if(event.key!=='Enter')return;const code=event.currentTarget.value.trim().toLowerCase();const product=catalog.find(item=>String(item.barcode||'').toLowerCase()===code||String(item.sku).toLowerCase()===code);if(!product)return;event.preventDefault();addProduct(product.key);event.currentTarget.value='';event.currentTarget.dispatchEvent(new Event('input'));});
    warehouse.addEventListener('change',()=>{cart=[];render();}); saleDiscount.addEventListener('input',updateTotal); render();
})();
</script>
@endpush
