<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartPublicInterviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'telegram' => ['required', 'string', 'max:255'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Укажите имя.',
            'last_name.required' => 'Укажите фамилию.',
            'email.email' => 'Укажите корректный адрес электронной почты.',
            'telegram.required' => 'Укажите Telegram аккаунт.',
            'consent.accepted' => 'Необходимо дать согласие на обработку персональных данных.',
        ];
    }
}
