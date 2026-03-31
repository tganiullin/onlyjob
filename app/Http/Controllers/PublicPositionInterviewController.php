<?php

namespace App\Http\Controllers;

use App\Enums\InterviewStatus;
use App\Http\Requests\StartPublicInterviewRequest;
use App\Models\Interview;
use App\Models\Position;
use App\Services\TelegramAccountConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PublicPositionInterviewController extends Controller
{
    public function __construct(
        private readonly TelegramAccountConfirmationService $telegramAccountConfirmationService,
    ) {}

    public function show(string $token): View
    {
        return view('public-interviews.entry', [
            'position' => $this->findPublicPositionByToken($token),
        ]);
    }

    public function start(StartPublicInterviewRequest $request, string $token): RedirectResponse|JsonResponse
    {
        $position = $this->findPublicPositionByToken($token);

        if ($position->questions_count < 1) {
            abort(404);
        }

        $validated = $request->validated();

        if (! config('telegram.confirmation_required')) {
            return $this->startWithoutTelegramConfirmation($request, $position, $validated);
        }

        $sessionFingerprint = $this->telegramAccountConfirmationService->resolveSessionFingerprint(
            (string) $request->ip(),
            (string) $request->userAgent(),
        );

        $confirmation = $this->telegramAccountConfirmationService->startOrReusePendingConfirmation(
            $position,
            [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'] ?? null,
                'telegram' => $validated['telegram'],
            ],
            $sessionFingerprint,
            $validated['client_request_id'],
        );

        $request->session()->forget('public_interview_id');
        $request->session()->put('public_pending_confirmation_status_token', $confirmation->status_token);
        $request->session()->put('public_pending_confirmation_fingerprint', $sessionFingerprint);

        $responsePayload = [
            'status' => 'pending_confirmation',
            'status_token' => $confirmation->status_token,
            'status_endpoint' => route('public-positions.confirmation-status', [
                'token' => $position->public_token,
                'statusToken' => $confirmation->status_token,
            ]),
            'telegram_deeplink' => $this->telegramAccountConfirmationService->buildTelegramDeepLink($confirmation->status_token),
            'message' => 'Подтвердите Telegram аккаунт через бота, чтобы продолжить интервью.',
        ];

        if ($request->wantsJson()) {
            return response()->json($responsePayload);
        }

        return redirect()
            ->route('public-positions.show', ['token' => $position->public_token])
            ->with('pending_confirmation', $responsePayload);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function startWithoutTelegramConfirmation(
        StartPublicInterviewRequest $request,
        Position $position,
        array $validated,
    ): RedirectResponse|JsonResponse {
        $interview = Interview::query()->create([
            'position_id' => $position->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'telegram' => $validated['telegram'],
            'status' => InterviewStatus::PendingInterview,
        ]);

        $request->session()->put('public_interview_id', $interview->id);
        $request->session()->forget('public_pending_confirmation_status_token');

        $redirect = URL::temporarySignedRoute(
            'public-interviews.run',
            now()->addMinutes(5),
            ['interview' => $interview],
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'confirmed',
                'redirect' => $redirect,
            ]);
        }

        return redirect()->to($redirect);
    }

    public function confirmationStatus(Request $request, string $token, string $statusToken): JsonResponse
    {
        $position = $this->findPublicPositionByToken($token);

        $sessionFingerprint = (string) $request->session()->get(
            'public_pending_confirmation_fingerprint',
            $this->telegramAccountConfirmationService->resolveSessionFingerprint(
                (string) $request->ip(),
                (string) $request->userAgent(),
            ),
        );

        $statusResult = $this->telegramAccountConfirmationService->resolvePendingConfirmationStatus(
            $position,
            $statusToken,
            $sessionFingerprint,
        );

        if ($statusResult['status'] === 'not_found') {
            abort(404);
        }

        if ($statusResult['status'] === 'confirmed') {
            $interview = Interview::query()->findOrFail($statusResult['interview_id']);

            $request->session()->put('public_interview_id', $interview->id);
            $request->session()->forget('public_pending_confirmation_status_token');

            return response()->json([
                'status' => 'confirmed',
                'redirect' => URL::temporarySignedRoute(
                    'public-interviews.run',
                    now()->addMinutes(5),
                    ['interview' => $interview],
                ),
            ]);
        }

        if ($statusResult['status'] === 'expired') {
            return response()->json([
                'status' => 'expired',
                'message' => 'Время подтверждения истекло. Отправьте форму ещё раз.',
            ], 409);
        }

        if ($statusResult['status'] === 'superseded') {
            return response()->json([
                'status' => 'superseded',
                'message' => 'Форма была отправлена повторно с обновлёнными данными. Используйте последний запрос.',
            ], 409);
        }

        return response()->json([
            'status' => 'pending_confirmation',
            'telegram_deeplink' => $this->telegramAccountConfirmationService->buildTelegramDeepLink($statusToken),
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
