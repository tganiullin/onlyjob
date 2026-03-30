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

        $mimeType = $this->resolveAudioMimeType($path);

        if (method_exists($disk, 'path') && file_exists($disk->path($path))) {
            return response()->file($disk->path($path), [
                'Content-Type' => $mimeType,
            ]);
        }

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);

            if ($stream !== null) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => $disk->size($path),
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
