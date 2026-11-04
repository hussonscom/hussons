<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
            'organization_name' => ['required', 'string', 'max:160'],
            'business_types' => ['required', 'array', 'min:1'],
            'business_types.*' => ['string', 'in:retail,electronics,pharmacy,restaurant,cafe,services'],
            'locale' => ['required', 'string', 'in:az,ru,en'],
            'terms' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'business_types' => array_values(array_unique((array) $this->input('business_types', []))),
        ]);
    }
}
