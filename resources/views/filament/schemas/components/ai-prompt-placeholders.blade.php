@php
    $record = $getRecord();
    $placeholders = $record?->available_placeholders ?? [];
    $isRichFormat = isset($placeholders[0]) && is_array($placeholders[0]);

    $wrapKey = static fn (string $key): string => '{{' . $key . '}}';
@endphp

<div>
    <p class="text-sm font-medium text-gray-950 dark:text-white">Available placeholders</p>

    @if ($placeholders === [])
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No placeholders available for this prompt.</p>
    @elseif (! $isRichFormat)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            @foreach ($placeholders as $p)
                <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-white/5">{{ $wrapKey($p) }}</code>@if (! $loop->last),@endif
            @endforeach
        </p>
    @else
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach ($placeholders as $placeholder)
                <div class="flex flex-col gap-0.5 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <code class="font-mono text-sm font-semibold text-primary-600 dark:text-primary-400">{{ $wrapKey($placeholder['key']) }}</code>
                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ $placeholder['description'] }}</span>
                    @if (! empty($placeholder['source']))
                        <span class="text-xs text-gray-500 dark:text-gray-500">
                            Source: <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-xs dark:bg-white/5">{{ $placeholder['source'] }}</code>
                        </span>
                    @endif
                    @if (! empty($placeholder['example']))
                        <span class="text-xs text-gray-500 dark:text-gray-500">
                            Example: <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-xs dark:bg-white/5">{{ $placeholder['example'] }}</code>
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
