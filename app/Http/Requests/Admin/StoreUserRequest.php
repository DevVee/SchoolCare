<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255'],
            'email'                => ['required', 'email', 'max:255', 'unique:users,email'],
            // Same policy as profile change / password reset (AppServiceProvider).
            'password'             => ['required', 'confirmed', Password::defaults()],
            'role'                 => ['required', 'string', 'exists:roles,name'],
            'is_active'            => ['boolean'],
            'must_change_password' => ['boolean'],
        ];
    }
}
