<?php

namespace App\Http\Controllers;

use App\Models\InterviewQuestion;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class InterviewAudioController extends Controller
{
    private const TEMP_CACHE_SECONDS = 600;

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
            return $this->serveBinaryFile($disk->path($path), $mimeType);
        }

        $tempPath = $this->downloadToTemp($disk, $path);

        return $this->serveBinaryFile($tempPath, $mimeType);
    }

    /**
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $disk
     */
    private function downloadToTemp(mixed $disk, string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'webm';
        $tempDir = storage_path('app/private/audio-cache');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $this->cleanupExpiredCache($tempDir);

        $tempPath = $tempDir.'/'.md5($path).'.'.$extension;

        if (file_exists($tempPath) && filemtime($tempPath) > time() - self::TEMP_CACHE_SECONDS) {
            return $tempPath;
        }

        $stream = $disk->readStream($path);

        if ($stream === null) {
            abort(404);
        }

        $tempFile = fopen($tempPath, 'wb');
        stream_copy_to_stream($stream, $tempFile);
        fclose($tempFile);
        fclose($stream);

        return $tempPath;
    }

    private function cleanupExpiredCache(string $dir): void
    {
        $cutoff = time() - self::TEMP_CACHE_SECONDS;

        foreach (glob($dir.'/*') as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    private function serveBinaryFile(string $localPath, string $mimeType): BinaryFileResponse
    {
        $response = new BinaryFileResponse($localPath, 200, [
            'Content-Type' => $mimeType,
        ]);

        $response->headers->set('Accept-Ranges', 'bytes');
        $response->setAutoEtag();

        return $response;
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
