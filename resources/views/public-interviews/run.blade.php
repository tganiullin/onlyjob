<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $position?->title }} — интервью</title>
    @vite(['resources/css/app.css', 'resources/js/entry-interview.js'])
</head>
<body class="min-h-screen bg-[#eef1ff] text-[#22263f]">
<div
    id="public-interview-run"
    data-questions='@json($questions, JSON_UNESCAPED_UNICODE)'
    data-company-questions='@json($companyQuestions, JSON_UNESCAPED_UNICODE)'
    data-answer-endpoint-template="{{ route('public-interviews.questions.answer', ['interview' => $interview, 'interviewQuestion' => '__QUESTION_ID__']) }}"
    data-skip-endpoint-template="{{ route('public-interviews.questions.skip', ['interview' => $interview, 'interviewQuestion' => '__QUESTION_ID__']) }}"
    data-transcribe-endpoint="{{ route('public-interviews.transcribe', ['interview' => $interview]) }}"
    data-feedback-endpoint="{{ route('public-interviews.feedback', ['interview' => $interview]) }}"
    data-custom-question-endpoint="{{ route('public-interviews.custom-question', ['interview' => $interview]) }}"
    data-candidate-custom-question="{{ $interview->candidate_custom_question ?? '' }}"
    data-integrity-signal-endpoint="{{ $integritySignalEndpoint }}"
    data-answer-time-seconds="{{ $answerTimeSeconds }}"
    data-interview-completed="{{ $interviewCompleted ? '1' : '0' }}"
    data-completion-message="{{ $completionMessage }}"
    data-candidate-feedback-rating="{{ $candidateFeedbackRating ?? '' }}"
    data-first-name="{{ $interview->first_name }}"
    data-last-name="{{ $interview->last_name }}"
    data-position-title="{{ $position?->title ?? '' }}"
    data-answer-time-label="{{ $position?->answer_time_seconds?->getLabel() ?? '2 минуты' }}"
    data-logo-url="{{ asset('images/logo.svg') }}"
    class="relative min-h-screen overflow-hidden"
></div>
</body>
</html>
