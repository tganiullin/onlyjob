<?php

namespace App\AI\Features\InterviewReview\Contracts;

use App\AI\Features\InterviewReview\Data\InterviewReviewResult;
use App\Models\Interview;

interface InterviewReviewer
{
    public function review(Interview $interview): InterviewReviewResult;
}
