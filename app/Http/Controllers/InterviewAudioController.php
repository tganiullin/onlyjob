<?php

namespace App\Http\Controllers;

use App\Models\InterviewQuestion;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
            return $this->serveBinaryFile($disk->path($path), $mimeType);
        }

        $tempPath = $this->downloadToTemp($disk, $path);

        return $this->serveBinaryFile($tempPath, $mimeType, deleteAfterSend: true);
    }

    /**
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $disk
     */
    private function downloadToTemp(mixed $disk, string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'webm';
        $tempPath = sys_get_temp_dir().'/audio_'.md5($path).'.'.$extension;

        if (file_exists($tempPath) && filemtime($tempPath) > time() - 300) {
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

    private function serveBinaryFile(string $localPath, string $mimeType, bool $deleteAfterSend = false): BinaryFileResponse
    {
        $response = new BinaryFileResponse($localPath, 200, [
            'Content-Type' => $mimeType,
        ]);

        $response->headers->set('Accept-Ranges', 'bytes');
        $response->setAutoEtag();

        if ($deleteAfterSend) {
            $response->deleteFileAfterSend();
        }

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
