<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicInterviewCustomQuestionRequest extends FormRequest
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
            'candidate_custom_question' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'candidate_custom_question.required' => 'Введите ваш вопрос.',
            'candidate_custom_question.string' => 'Вопрос должен быть строкой.',
            'candidate_custom_question.max' => 'Вопрос не может быть длиннее 1000 символов.',
        ];
    }
}
