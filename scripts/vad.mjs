import { spawn } from 'node:child_process';
import ffmpegPath from 'ffmpeg-static';

function parseArgs(argv) {
    const parsed = {};

    for (let index = 0; index < argv.length; index += 1) {
        const token = argv[index];

        if (!token.startsWith('--')) {
            continue;
        }

        const key = token.slice(2);
        const next = argv[index + 1];

        if (typeof next === 'undefined' || next.startsWith('--')) {
            parsed[key] = true;
            continue;
        }

        parsed[key] = next;
        index += 1;
    }

    return parsed;
}

function normalizeDuration(value) {
    return Number.isFinite(value) && value > 0 ? value : 0;
}

function extractAudioDurationSeconds(errorOutput) {
    const durationMatch = errorOutput.match(/Duration:\s*(\d{2}):(\d{2}):(\d{2}(?:\.\d+)?)/);
    if (!durationMatch) {
        return 0;
    }

    const hours = Number(durationMatch[1]);
    const minutes = Number(durationMatch[2]);
    const seconds = Number(durationMatch[3]);

    return normalizeDuration((hours * 3600) + (minutes * 60) + seconds);
}

function extractAudioDurationFromProgress(errorOutput) {
    const matches = [...errorOutput.matchAll(/time=(\d{2}):(\d{2}):(\d{2}(?:\.\d+)?)/g)];
    if (matches.length === 0) {
        return 0;
    }

    const lastMatch = matches[matches.length - 1];
    const hours = Number(lastMatch[1]);
    const minutes = Number(lastMatch[2]);
    const seconds = Number(lastMatch[3]);

    return normalizeDuration((hours * 3600) + (minutes * 60) + seconds);
}

function extractAudioDurationFromSilenceEnd(errorOutput) {
    const matches = [...errorOutput.matchAll(/silence_end:\s*([0-9]+(?:\.[0-9]+)?)/g)];
    if (matches.length === 0) {
        return 0;
    }

    const lastSilenceEnd = Number(matches[matches.length - 1][1]);

    return normalizeDuration(lastSilenceEnd);
}

function resolveAudioDurationSeconds(errorOutput) {
    const fromMetadata = extractAudioDurationSeconds(errorOutput);
    if (fromMetadata > 0) {
        return fromMetadata;
    }

    const fromProgress = extractAudioDurationFromProgress(errorOutput);
    if (fromProgress > 0) {
        return fromProgress;
    }

    return extractAudioDurationFromSilenceEnd(errorOutput);
}

function extractSilenceDurationSeconds(errorOutput) {
    const matches = [...errorOutput.matchAll(/silence_duration:\s*([0-9]+(?:\.[0-9]+)?)/g)];
    if (matches.length === 0) {
        return 0;
    }

    return matches.reduce((total, match) => total + Math.max(0, Number(match[1])), 0);
}

function runFfmpeg(command, args, timeoutSeconds) {
    return new Promise((resolve, reject) => {
        let stderr = '';
        let timedOut = false;

        const child = spawn(command, args, { stdio: ['ignore', 'ignore', 'pipe'] });
        const timeoutMs = Math.max(1, Number(timeoutSeconds) || 5) * 1000;
        const timer = setTimeout(() => {
            timedOut = true;
            child.kill('SIGKILL');
        }, timeoutMs);

        child.stderr.on('data', (chunk) => {
            stderr += chunk.toString();
        });

        child.on('error', (error) => {
            clearTimeout(timer);
            reject(error);
        });

        child.on('close', (code) => {
            clearTimeout(timer);

            if (timedOut) {
                reject(new Error('node_vad_timeout'));
                return;
            }

            if (code !== 0) {
                reject(new Error(stderr || 'node_vad_failed'));
                return;
            }

            resolve(stderr);
        });
    });
}

async function main() {
    const args = parseArgs(process.argv.slice(2));
    const audioPath = typeof args['audio-path'] === 'string' ? args['audio-path'] : '';
    const noiseThresholdDb = Number(args['noise-threshold-db'] ?? -45);
    const minSilenceSeconds = Math.max(0, Number(args['min-silence-seconds'] ?? 0.2));
    const minSpeechSeconds = Math.max(0, Number(args['min-speech-seconds'] ?? 0.5));
    const timeoutSeconds = Math.max(1, Number(args['timeout-seconds'] ?? 5));

    if (audioPath === '') {
        throw new Error('audio_path_required');
    }

    if (typeof ffmpegPath !== 'string' || ffmpegPath === '') {
        throw new Error('ffmpeg_static_binary_unavailable');
    }

    const commandArgs = [
        '-hide_banner',
        '-i',
        audioPath,
        '-af',
        `silencedetect=n=${noiseThresholdDb}dB:d=${minSilenceSeconds}`,
        '-f',
        'null',
        '-',
    ];

    const errorOutput = await runFfmpeg(ffmpegPath, commandArgs, timeoutSeconds);
    const audioDurationSeconds = resolveAudioDurationSeconds(errorOutput);

    if (audioDurationSeconds <= 0) {
        throw new Error('audio_duration_unavailable');
    }

    const silenceDurationSeconds = Math.min(audioDurationSeconds, extractSilenceDurationSeconds(errorOutput));
    const speechDurationSeconds = Math.max(0, audioDurationSeconds - silenceDurationSeconds);
    const hasSpeech = speechDurationSeconds >= minSpeechSeconds;

    process.stdout.write(JSON.stringify({
        hasSpeech,
        audioDurationSeconds,
        speechDurationSeconds,
        reason: hasSpeech ? null : 'below_min_speech_threshold',
    }));
}

main().catch((error) => {
    const message = error instanceof Error ? error.message : 'node_vad_failed';
    process.stderr.write(String(message));
    process.exit(1);
});
