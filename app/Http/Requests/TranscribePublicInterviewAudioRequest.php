<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranscribePublicInterviewAudioRequest extends FormRequest
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
            'audio' => [
                'required',
                'file',
                'extensions:webm,wav,ogg,m4a,mp3',
                'max:25600',
            ],
            'language' => ['required', 'string', 'in:auto,en-US,en-GB,ru-RU,browser-default'],
            'interview_question_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio.required' => 'Please record audio before transcription.',
            'audio.max' => 'Audio file is too large.',
            'language.required' => 'Recognition language is required.',
            'language.in' => 'Recognition language is invalid.',
        ];
    }
}
