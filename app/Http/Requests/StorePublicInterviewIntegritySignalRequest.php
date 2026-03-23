<?php

namespace App\Http\Requests;

use App\Enums\InterviewIntegrityEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicInterviewIntegritySignalRequest extends FormRequest
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
            'event_type' => ['required', 'string', Rule::in(InterviewIntegrityEventType::values())],
            'occurred_at' => ['required', 'date'],
            'interview_question_id' => ['nullable', 'integer', 'exists:interview_questions,id'],
            'payload' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('payload');

        if (is_string($payload) && $payload !== '') {
            $decodedPayload = json_decode($payload, true);

            if (is_array($decodedPayload)) {
                $payload = $decodedPayload;
            }
        }

        $interviewQuestionId = $this->input('interview_question_id');

        if ($interviewQuestionId === '' || $interviewQuestionId === 'null') {
            $interviewQuestionId = null;
        }

        $this->merge([
            'payload' => $payload,
            'interview_question_id' => $interviewQuestionId,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_type.required' => 'Event type is required.',
            'event_type.in' => 'Provided event type is not supported.',
            'occurred_at.required' => 'Event timestamp is required.',
            'occurred_at.date' => 'Event timestamp is invalid.',
            'interview_question_id.exists' => 'Interview question was not found.',
            'payload.array' => 'Payload must be a valid JSON object.',
        ];
    }
}
