<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class AiPrompt extends Model
{
    /** @use HasFactory<\Database\Factories\AiPromptFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'feature',
        'type',
        'content',
        'description',
        'available_placeholders',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_placeholders' => 'array',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AiPromptVersion::class)->orderByDesc('version_number');
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    public static function resolve(string $feature, string $type, array $placeholders = []): ?string
    {
        $prompt = static::query()
            ->where('feature', $feature)
            ->where('type', $type)
            ->first();

        if (! $prompt instanceof static) {
            return null;
        }

        return static::replacePlaceholders($prompt->content, $placeholders);
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    public static function replacePlaceholders(string $content, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $content = str_replace("{{{$key}}}", (string) $value, $content);
        }

        return $content;
    }

    public function createVersion(?string $changeNote = null): AiPromptVersion
    {
        $latestVersion = $this->versions()->max('version_number') ?? 0;

        return $this->versions()->create([
            'content' => $this->content,
            'version_number' => $latestVersion + 1,
            'change_note' => $changeNote,
            'user_id' => Auth::id(),
        ]);
    }

    public function revertToVersion(AiPromptVersion $version): void
    {
        $this->createVersion('Reverted to version #'.$version->version_number);

        $this->update(['content' => $version->content]);
    }

    public function latestVersionNumber(): int
    {
        return (int) ($this->versions()->max('version_number') ?? 0);
    }
}
