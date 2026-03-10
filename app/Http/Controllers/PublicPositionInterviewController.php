<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartPublicInterviewRequest;
use App\Models\Interview;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicPositionInterviewController extends Controller
{
    public function show(string $token): View
    {
        return view('public-interviews.entry', [
            'position' => $this->findPublicPositionByToken($token),
        ]);
    }

    public function start(StartPublicInterviewRequest $request, string $token): RedirectResponse
    {
        $position = $this->findPublicPositionByToken($token);

        if ($position->questions_count < 1) {
            abort(404);
        }

        $interview = Interview::createPendingForCandidate(
            $position,
            $request->validated(),
        );

        $request->session()->put('public_interview_id', $interview->id);

        return redirect()->route('public-interviews.run', [
            'interview' => $interview,
        ]);
    }

    private function findPublicPositionByToken(string $token): Position
    {
        return Position::query()
            ->where('public_token', $token)
            ->where('is_public', true)
            ->withCount('questions')
            ->firstOrFail();
    }
}
