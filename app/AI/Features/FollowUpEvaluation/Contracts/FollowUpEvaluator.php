<?php

namespace App\AI\Features\FollowUpEvaluation\Contracts;

use App\AI\Features\FollowUpEvaluation\Data\FollowUpEvaluationResult;
use App\Models\InterviewQuestion;
use App\Models\Position;

interface FollowUpEvaluator
{
    public function evaluate(InterviewQuestion $interviewQuestion, Position $position): FollowUpEvaluationResult;
}
