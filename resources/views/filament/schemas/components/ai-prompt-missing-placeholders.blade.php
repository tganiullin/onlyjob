@php
    $record = $getRecord();
    $placeholders = $record?->available_placeholders ?? [];
    $content = (string) $get('content');

    $missingKeys = [];
    $wrapKey = static fn (string $key): string => '{{' . $key . '}}';

    foreach ($placeholders as $placeholder) {
        $key = is_array($placeholder) ? ($placeholder['key'] ?? '') : (string) $placeholder;
        if ($key !== '' && ! str_contains($content, $wrapKey($key))) {
            $missingKeys[] = $key;
        }
    }
@endphp

@if ($missingKeys !== [])
    <div class="flex gap-2 rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-600 dark:bg-warning-400/10 dark:text-warning-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
        </svg>
        <div>
            Missing placeholders in prompt content:
            @foreach ($missingKeys as $key)
                <code class="rounded bg-warning-50 px-1.5 py-0.5 font-mono text-xs text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">{{ $wrapKey($key) }}</code>
            @endforeach
        </div>
    </div>
@endif
