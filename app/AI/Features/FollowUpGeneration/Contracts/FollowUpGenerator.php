<?php

namespace App\AI\Features\FollowUpGeneration\Contracts;

use App\AI\Features\FollowUpGeneration\Data\FollowUpResult;
use App\Models\InterviewQuestion;

interface FollowUpGenerator
{
    public function generate(InterviewQuestion $question, ?int $minScore = null): FollowUpResult;
}
