<?php

namespace App\Http\Controllers;

use App\Models\InterviewQuestion;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class InterviewAudioController extends Controller
{
    public function stream(InterviewQuestion $interviewQuestion): Response
    {
        $path = $interviewQuestion->candidate_answer_audio_path;

        if (! is_string($path) || $path === '') {
            abort(404);
        }

        $disk = Storage::disk();

        if (! $disk->exists($path)) {
            abort(404);
        }

        $driver = (string) config('filesystems.disks.'.config('filesystems.default').'.driver');

        if ($driver === 's3') {
            return redirect()->to(
                $disk->temporaryUrl($path, now()->addMinutes(5)),
            );
        }

        return response()->file($disk->path($path), [
            'Content-Type' => $this->resolveAudioMimeType($path),
        ]);
    }

    private function resolveAudioMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'mp3' => 'audio/mpeg',
            default => 'audio/webm',
        };
    }
}
