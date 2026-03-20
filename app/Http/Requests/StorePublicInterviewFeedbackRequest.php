<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicInterviewFeedbackRequest extends FormRequest
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
            'candidate_feedback_rating' => ['required', 'integer', 'between:1,5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'candidate_feedback_rating.required' => 'Поставьте оценку от 1 до 5.',
            'candidate_feedback_rating.integer' => 'Оценка должна быть целым числом.',
            'candidate_feedback_rating.between' => 'Оценка должна быть от 1 до 5.',
        ];
    }
}
