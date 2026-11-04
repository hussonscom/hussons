<label class="field"><span>{{ __('directory.name') }}</span><input name="name" value="{{ old('name', $supplier?->name) }}" required maxlength="180"></label>
<label class="field"><span>{{ __('directory.tax_id') }}</span><input name="tax_id" value="{{ old('tax_id', $supplier?->tax_id) }}" maxlength="64"></label>
<label class="field"><span>{{ __('directory.contact_name') }}</span><input name="contact_name" value="{{ old('contact_name', $supplier?->contact_name) }}" maxlength="180"></label>
<label class="field"><span>{{ __('directory.phone') }}</span><input name="phone" value="{{ old('phone', $supplier?->phone) }}" maxlength="32"></label>
<label class="field"><span>{{ __('directory.email') }}</span><input type="email" name="email" value="{{ old('email', $supplier?->email) }}"></label>
<label class="field"><span>{{ __('directory.address') }}</span><input name="address" value="{{ old('address', $supplier?->address) }}" maxlength="255"></label>
<label class="field md:col-span-2 xl:col-span-3"><span>{{ __('directory.notes') }}</span><textarea name="notes" rows="2">{{ old('notes', $supplier?->notes) }}</textarea></label>
