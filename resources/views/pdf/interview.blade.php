@php
    $statusColor = match($interview->status?->value ?? '') {
        'reviewed_passed' => 'success',
        'reviewed_failed', 'review_failed' => 'danger',
        'in_progress', 'reviewing' => 'warning',
        'pending_confirmation' => 'gray',
        default => 'info',
    };

    $scoreColorFn = static function (mixed $value): string {
        if ($value === null) {
            return '#6b7280';
        }
        $v = (float) $value;
        if ($v >= 7) {
            return '#059669';
        }
        return $v >= 4 ? '#d97706' : '#dc2626';
    };

    $scoreBgFn = static function (mixed $value): string {
        if ($value === null) {
            return '#f3f4f6';
        }
        $v = (float) $value;
        if ($v >= 7) {
            return '#d1fae5';
        }
        return $v >= 4 ? '#fef3c7' : '#fee2e2';
    };

    $questionNumber = 0;
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; line-height: 1.55; }

        .header-bar { background: #4f46e5; height: 6px; }
        .page { padding: 24px 36px; }

        .title-row { margin-bottom: 2px; }
        .title-row h1 { font-size: 22px; color: #111827; display: inline; }
        .title-row .id-tag { font-size: 22px; color: #6366f1; font-weight: bold; }
        .title-sub { font-size: 10px; color: #6b7280; margin-bottom: 16px; }

        .metrics-bar { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .metrics-bar td { width: 33.33%; padding: 0; }
        .metric-card { padding: 10px 14px; border: 1px solid #e5e7eb; text-align: center; }
        .metric-card-first { border-radius: 6px 0 0 6px; }
        .metric-card-last { border-radius: 0 6px 6px 0; }
        .metric-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; font-weight: bold; margin-bottom: 4px; }
        .metric-value { font-size: 20px; font-weight: bold; }
        .metric-sub { font-size: 9px; color: #9ca3af; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 9.5px; font-weight: bold; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f3f4f6; color: #374151; }

        .section-title { font-size: 13px; font-weight: bold; color: #111827; margin-top: 16px; margin-bottom: 6px; padding-bottom: 3px; border-bottom: 2px solid #e5e7eb; }

        .two-col { width: 100%; border-collapse: collapse; }
        .two-col > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
        .two-col > tbody > tr > td:first-child { padding-right: 12px; }
        .two-col > tbody > tr > td:last-child { padding-left: 12px; }
        .col-title { font-size: 11px; font-weight: bold; color: #4f46e5; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px; }

        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 3px 0; vertical-align: top; font-size: 10.5px; }
        .info-grid .label { font-weight: bold; color: #6b7280; width: 110px; white-space: nowrap; padding-right: 8px; }
        .info-grid .value { color: #111827; }

        .summary-block { margin-top: 12px; padding: 8px 14px; background: #f0f0ff; border-left: 4px solid #6366f1; border-radius: 0 4px 4px 0; }
        .summary-block-title { font-size: 10px; font-weight: bold; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
        .summary-block p { font-size: 10.5px; color: #1f2937; white-space: pre-wrap; line-height: 1.5; }

        .custom-q-block { margin-top: 8px; padding: 8px 14px; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 0 4px 4px 0; }
        .custom-q-block-title { font-size: 10px; font-weight: bold; color: #b45309; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
        .custom-q-block p { font-size: 10.5px; color: #1f2937; white-space: pre-wrap; }

        .question-block { margin-bottom: 8px; padding: 8px 12px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 5px; }
        .question-block.follow-up { margin-left: 20px; background: #fafafa; border-left: 3px solid #a5b4fc; }
        .question-header { margin-bottom: 4px; }
        .question-number { font-weight: bold; color: #4f46e5; font-size: 10px; }
        .question-text { font-weight: bold; font-size: 11px; color: #111827; margin-bottom: 4px; }
        .answer-text { margin-bottom: 4px; white-space: pre-wrap; color: #374151; }
        .score-pill { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 10px; font-weight: bold; margin-right: 6px; }
        .ai-comment { margin-top: 4px; padding: 5px 10px; background: #eef2ff; border-radius: 4px; font-size: 10px; color: #374151; border: 1px solid #e0e7ff; }
        .ai-comment-label { font-weight: bold; color: #4f46e5; font-size: 9px; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 1px; }
        .no-data { color: #9ca3af; font-style: italic; font-size: 10px; }

        .events-table { width: 100%; border-collapse: collapse; margin-top: 8px; border: 1px solid #e5e7eb; border-radius: 4px; }
        .events-table th { background: #f3f4f6; text-align: left; padding: 7px 10px; font-size: 9.5px; font-weight: bold; color: #374151; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 2px solid #d1d5db; }
        .events-table td { padding: 6px 10px; font-size: 10px; border-bottom: 1px solid #f3f4f6; }
        .events-table tr:nth-child(even) td { background: #f9fafb; }

        .footer { margin-top: 28px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="header-bar"></div>
<div class="page">
    <div class="title-row">
        <span class="id-tag">#{{ $interview->id }}</span>
        <h1>{{ $interview->position?->title ?? 'Interview' }}</h1>
    </div>
    <div class="title-sub">
        {{ $interview->first_name }} {{ $interview->last_name }}
        &middot; {{ now()->format('d.m.Y H:i') }}
    </div>

    {{-- Quick Summary Metrics --}}
    <table class="metrics-bar">
        <tr>
            <td>
                <div class="metric-card metric-card-first" style="background: {{ $scoreBgFn($interview->score) }};">
                    <div class="metric-label">Score</div>
                    <div class="metric-value" style="color: {{ $scoreColorFn($interview->score) }};">
                        {{ $interview->score ?? '—' }}
                    </div>
                    <div class="metric-sub">out of 10</div>
                </div>
            </td>
            <td>
                <div class="metric-card" style="background: {{ $scoreBgFn($interview->adequacy_score) }};">
                    <div class="metric-label">Adequacy</div>
                    <div class="metric-value" style="color: {{ $scoreColorFn($interview->adequacy_score) }};">
                        {{ $interview->adequacy_score ?? '—' }}
                    </div>
                    <div class="metric-sub">out of 10</div>
                </div>
            </td>
            <td>
                <div class="metric-card metric-card-last">
                    <div class="metric-label">Status</div>
                    <div style="margin-top: 4px;">
                        <span class="badge badge-{{ $statusColor }}">{{ $interview->status?->getLabel() ?? '—' }}</span>
                    </div>
                    @if($interview->candidate_feedback_rating)
                        <div class="metric-sub" style="margin-top: 4px;">Feedback: {{ $interview->candidate_feedback_rating }} / 5</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Two-column: Candidate + Details --}}
    <table class="two-col">
        <tr>
            <td>
                <div class="col-title">Candidate</div>
                <table class="info-grid">
                    <tr>
                        <td class="label">Name</td>
                        <td class="value">{{ $interview->first_name }} {{ $interview->last_name }}</td>
                    </tr>
                    @if($interview->email)
                        <tr>
                            <td class="label">Email</td>
                            <td class="value">{{ $interview->email }}</td>
                        </tr>
                    @endif
                    @if($interview->telegram)
                        <tr>
                            <td class="label">Telegram</td>
                            <td class="value">{{ $interview->telegram }}</td>
                        </tr>
                    @endif
                    @if($interview->phone)
                        <tr>
                            <td class="label">Phone</td>
                            <td class="value">{{ $interview->phone }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="label">Position</td>
                        <td class="value">{{ $interview->position?->title ?? '—' }}</td>
                    </tr>
                </table>
            </td>
            <td>
                <div class="col-title">Interview Details</div>
                <table class="info-grid">
                    <tr>
                        <td class="label">Started</td>
                        <td class="value">{{ $interview->started_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Completed</td>
                        <td class="value">{{ $interview->completed_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    </tr>
                    @if($interview->started_at && $interview->completed_at)
                        <tr>
                            <td class="label">Duration</td>
                            <td class="value">{{ $interview->started_at->diffForHumans($interview->completed_at, true) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="label">Questions</td>
                        <td class="value">{{ $interview->interviewQuestions->whereNull('parent_question_id')->count() }}</td>
                    </tr>
                    @if($interview->candidate_feedback_rating)
                        <tr>
                            <td class="label">Feedback</td>
                            <td class="value">
                                @for($i = 1; $i <= 5; $i++)
                                    {{ $i <= $interview->candidate_feedback_rating ? '★' : '☆' }}
                                @endfor
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Summary --}}
    @if($interview->summary)
        <div class="summary-block">
            <div class="summary-block-title">AI Summary</div>
            <p>{{ $interview->summary }}</p>
        </div>
    @endif

    @if($interview->candidate_custom_question)
        <div class="custom-q-block">
            <div class="custom-q-block-title">Candidate Question</div>
            <p>{{ $interview->candidate_custom_question }}</p>
        </div>
    @endif

    {{-- Questions & Answers --}}
    <div class="section-title">Questions &amp; Answers</div>
    @forelse($interview->interviewQuestions as $iq)
        @if(! $iq->parent_question_id)
            @php $questionNumber++ @endphp
        @endif
        <div class="question-block {{ $iq->parent_question_id ? 'follow-up' : '' }}">
            <div class="question-header">
                <span class="question-number">
                    @if($iq->parent_question_id)
                        &#8627; Follow-up
                    @else
                        Q{{ $questionNumber }}
                    @endif
                </span>
                @if($iq->answer_score !== null)
                    <span class="score-pill" style="background: {{ $scoreBgFn($iq->answer_score) }}; color: {{ $scoreColorFn($iq->answer_score) }};">
                        {{ $iq->answer_score }}
                    </span>
                @endif
                @if($iq->adequacy_score !== null)
                    <span class="score-pill" style="background: {{ $scoreBgFn($iq->adequacy_score) }}; color: {{ $scoreColorFn($iq->adequacy_score) }};">
                        A: {{ $iq->adequacy_score }}
                    </span>
                @endif
            </div>
            <div class="question-text">{{ $iq->question_text }}</div>

            @if($iq->candidate_answer)
                <div class="answer-text">{{ $iq->candidate_answer }}</div>
            @else
                <div class="no-data">No answer provided</div>
            @endif

            @if($iq->ai_comment)
                <div class="ai-comment"><div class="ai-comment-label">AI Comment</div>{{ $iq->ai_comment }}</div>
            @endif
        </div>
    @empty
        <p class="no-data">No questions recorded.</p>
    @endforelse

    {{-- Integrity Events --}}
    @if($interview->integrityEvents->isNotEmpty())
        <div class="section-title">Possible Cheating Events</div>
        <table class="events-table">
            <thead>
                <tr>
                    <th style="width: 120px;">Time</th>
                    <th style="width: 130px;">Event</th>
                    <th>Related Question</th>
                </tr>
            </thead>
            <tbody>
                @foreach($interview->integrityEvents as $event)
                    <tr>
                        <td>{{ $event->occurred_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                        <td>{{ $event->event_type?->getLabel() ?? '—' }}</td>
                        <td>
                            @if($event->interviewQuestion)
                                {{ \Illuminate\Support\Str::limit($event->interviewQuestion->question_text ?? '', 80) }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        OnlyJob &middot; Interview Report &middot; {{ now()->format('d.m.Y H:i') }}
    </div>
</div>
</body>
</html>
