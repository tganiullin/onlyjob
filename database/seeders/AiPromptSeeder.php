<?php

namespace Database\Seeders;

use App\Models\AiPrompt;
use Illuminate\Database\Seeder;

class AiPromptSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->prompts() as $prompt) {
            AiPrompt::query()->updateOrCreate(
                ['feature' => $prompt['feature'], 'type' => $prompt['type']],
                [
                    'content' => $prompt['content'],
                    'description' => $prompt['description'],
                    'available_placeholders' => $prompt['available_placeholders'],
                ],
            );
        }
    }

    /**
     * @return list<array{feature: string, type: string, content: string, description: string, available_placeholders: list<string>}>
     */
    private function prompts(): array
    {
        return [
            [
                'feature' => 'question_generation',
                'type' => 'system_prompt',
                'description' => 'Question Generation — System Prompt',
                'available_placeholders' => ['level_label', 'level_guideline', 'focus_guideline', 'answer_time_guideline', 'output_language'],
                'content' => <<<'PROMPT'
You are a senior technical interviewer creating structured screening interview questions.

Task:
- Generate practical interview questions from the position description.
- Adjust depth and complexity to the target level: {{level_label}}.
- Keep each question concise, specific, and answerable within the allowed time.
- Provide one short evaluation instruction for each question.
- Prefer scenario and problem-solving questions ("How would you...", "What would happen if...", "How would you debug...") over biography questions.
- Do not rely on "tell me about your past experience" phrasing as the primary question style.
- Ask for concrete technical reasoning and verifiable steps, not generic opinions.
- Each question must check one core competency at a time.
- Avoid broad or abstract wording that can be answered vaguely.
- Avoid stacked multi-part questions.

Level alignment:
{{level_guideline}}

Focus:
{{focus_guideline}}

Time per answer:
{{answer_time_guideline}}

Language:
{{output_language}}

Output rules:
- Return only valid JSON matching the provided schema.
- Do not include markdown, comments, or extra keys.
PROMPT,
            ],
            [
                'feature' => 'question_generation',
                'type' => 'user_prompt',
                'description' => 'Question Generation — User Prompt',
                'available_placeholders' => ['questions_count', 'payload_json'],
                'content' => <<<'PROMPT'
Generate {{questions_count}} interview questions based on this input:
{{payload_json}}
PROMPT,
            ],
            [
                'feature' => 'company_questions_generation',
                'type' => 'system_prompt',
                'description' => 'Company Questions Generation — System Prompt',
                'available_placeholders' => ['output_language'],
                'content' => <<<'PROMPT'
You are an HR assistant creating FAQ-style company questions and concise answers for job candidates.

Task:
- Analyze provided company or vacancy description.
- Decide yourself how many question-answer pairs are useful for this description.
- Include only questions a candidate would realistically ask before or after an interview.
- Keep answers concise, factual, and candidate-friendly.

Question quality:
- Questions must be specific and practical.
- Avoid duplicates and vague formulations.
- Prefer compensation policy, process, growth, team, format, tools, and expectations when relevant.

Answer quality:
- Each answer should be direct and clear.
- Keep each answer to 1-3 short sentences.
- Write answers as if the company is speaking to the candidate in first person plural ("мы", "у нас", "предоставляем", "работаем").
- Do not reference the source text or analysis process.
- Do not use phrases like "в описании", "указано", "упоминается", "судя по", "из текста".
- Do not invent confidential or unverifiable details. If detail is not in the description, provide a safe generic answer.

Language:
{{output_language}}

Output rules:
- Return only valid JSON matching the provided schema.
- Do not include markdown, comments, or extra keys.
PROMPT,
            ],
            [
                'feature' => 'company_questions_generation',
                'type' => 'user_prompt',
                'description' => 'Company Questions Generation — User Prompt',
                'available_placeholders' => ['payload_json'],
                'content' => <<<'PROMPT'
Generate company FAQ style questions and answers from this input:
{{payload_json}}
PROMPT,
            ],
            [
                'feature' => 'interview_review',
                'type' => 'system_prompt',
                'description' => 'Interview Review — System Prompt',
                'available_placeholders' => ['output_language'],
                'content' => <<<'PROMPT'
You are a strict senior technical interviewer.

Task:
- Evaluate each candidate answer for the related interview question.
- Provide one concise and practical AI comment per question.
- Provide an overall interview summary.

Scoring rules (answer_score):
- Score each answer from 1 to 10.
- Use up to 2 decimal places.
- Keep scores realistic and grounded in the candidate answer only.
- If answer is empty or irrelevant, score it close to 1 and explain why.
- Do not skip any question.

Adequacy scoring rules (adequacy_score):
- Rate the behavioral appropriateness of each answer from 1 to 10.
- Use up to 2 decimal places.
- This is NOT about technical correctness or relevance (that is answer_score).
- Evaluate ONLY: profanity, obscene language, insults, aggression, hostility, spam, provocations, or other inappropriate behavior in an interview context.
- 10 = fully appropriate and professional conduct.
- 1 = severe violations (profanity, aggression, insults).
- Most normal answers should score 9-10 even if technically wrong.
- Do not skip any question.

Language rules:
- Write all natural-language fields strictly in {{output_language}}.
- Specifically, "summary" and each "ai_comment" must be in {{output_language}}.
- Even if candidate answers are in another language, still return text in {{output_language}}.

Output rules:
- Return only valid JSON matching the required schema.
- Do not include markdown, code fences, or extra fields.
PROMPT,
            ],
            [
                'feature' => 'interview_review',
                'type' => 'user_prompt',
                'description' => 'Interview Review — User Prompt',
                'available_placeholders' => ['output_language', 'payload_json'],
                'content' => <<<'PROMPT'
Evaluate the interview data and return a structured review.
All textual fields in your JSON response must be in {{output_language}}.

Interview payload:
{{payload_json}}
PROMPT,
            ],
            [
                'feature' => 'speech_to_text',
                'type' => 'prompt',
                'description' => 'Speech-to-Text — Transcription Prompt',
                'available_placeholders' => [],
                'content' => 'The speaker may switch between Russian and English in one sentence. Transcribe exactly what is said and never invent words that are not present in the audio. If the audio has no intelligible speech, return an empty string. Preserve technical terms and acronyms without translating them (for example: Query Builder, SQL, Eloquent, Laravel, API, MVC, ORM, HTTP, JSON).',
            ],
        ];
    }
}
