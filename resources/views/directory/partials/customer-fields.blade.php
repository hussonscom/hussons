<label class="field"><span>{{ __('directory.name') }}</span><input name="name" value="{{ old('name', $customer?->name) }}" required maxlength="180"></label>
<label class="field"><span>{{ __('directory.phone') }}</span><input name="phone" value="{{ old('phone', $customer?->phone) }}" maxlength="32"></label>
<label class="field"><span>{{ __('directory.email') }}</span><input type="email" name="email" value="{{ old('email', $customer?->email) }}"></label>
<label class="field"><span>{{ __('directory.tax_id') }}</span><input name="tax_id" value="{{ old('tax_id', $customer?->tax_id) }}" maxlength="64"></label>
<label class="field"><span>{{ __('directory.birth_date') }}</span><input type="date" name="birth_date" value="{{ old('birth_date', $customer?->birth_date?->format('Y-m-d')) }}" max="{{ today()->toDateString() }}"></label>
<label class="field md:col-span-2 xl:col-span-3"><span>{{ __('directory.notes') }}</span><textarea name="notes" rows="2">{{ old('notes', $customer?->notes) }}</textarea></label>
