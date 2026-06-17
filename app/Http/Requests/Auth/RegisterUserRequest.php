<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['bail', 'required', 'string', 'min:3', 'max:30', 'alpha_dash', 'unique:booking_users,username'],
            'first_name' => ['bail', 'required', 'string', 'max:100'],
            'last_name' => ['bail', 'required', 'string', 'max:100'],
            'email' => ['bail', 'required', 'email', 'max:255', 'unique:booking_users,email'],
            'phone' => ['bail', 'required', 'string', 'max:20', 'regex:/^[0-9+()\\-\\s]{7,20}$/'],
            'password' => ['bail', 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'first_name' => $this->cleanNamePart($this->input('first_name')),
            'last_name' => $this->cleanNamePart($this->input('last_name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => preg_replace('/\s+/', ' ', trim((string) $this->input('phone'))),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.alpha_dash' => 'The username may only contain letters, numbers, dashes, and underscores.',
            'username.unique' => 'That username is already taken. Please choose another one.',
            'email.unique' => 'That email address is already registered.',
            'phone.regex' => 'Please enter a valid phone number using digits and optional +, -, spaces, or parentheses.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }

    private function cleanNamePart(mixed $value): string
    {
        return preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';
    }
}
