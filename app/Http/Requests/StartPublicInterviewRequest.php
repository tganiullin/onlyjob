<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartPublicInterviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $firstName = $this->input('first_name');
        $lastName = $this->input('last_name');
        $email = $this->input('email');
        $telegram = $this->input('telegram');

        if (is_string($telegram)) {
            $telegram = trim($telegram);

            if (str_starts_with($telegram, '@')) {
                $telegram = substr($telegram, 1);
            }

            $telegram = strtolower($telegram);
        }

        $normalizedEmail = is_string($email) ? trim($email) : $email;

        if ($normalizedEmail === '') {
            $normalizedEmail = null;
        }

        $this->merge([
            'first_name' => is_string($firstName) ? trim($firstName) : $firstName,
            'last_name' => is_string($lastName) ? trim($lastName) : $lastName,
            'email' => $normalizedEmail,
            'telegram' => $telegram,
            'client_request_id' => $this->input('client_request_id'),
        ]);
    }

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
            'telegram' => ['required', 'string', 'min:5', 'max:32', 'regex:/^[a-z0-9_]+$/'],
            'client_request_id' => ['required', 'uuid'],
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
            'telegram.min' => 'Telegram аккаунт должен быть не короче 5 символов.',
            'telegram.max' => 'Telegram аккаунт должен быть не длиннее 32 символов.',
            'telegram.regex' => 'Укажите корректный Telegram аккаунт.',
            'client_request_id.required' => 'Повторите отправку формы.',
            'client_request_id.uuid' => 'Повторите отправку формы.',
            'consent.accepted' => 'Необходимо дать согласие на обработку персональных данных.',
        ];
    }
}
