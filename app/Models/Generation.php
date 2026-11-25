<?php

namespace App\Models;

use App\Services\ReplyText;
use App\Traits\Relationships\GenerationRelationship;
use App\Traits\Scopes\GenerationScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'kind',
    'status',
    'payload',
    'result',
    'error',
    'started_at',
    'finished_at',
])]
class Generation extends Model
{
    use GenerationRelationship, GenerationScope;

    public function caption(): ?string
    {
        $result = $this->result;
        $text = is_array($result) ? data_get($result, 'data.post_text') : null;

        if (! is_string($text) || trim($text) === '') {
            $text = is_array($result) ? ($result['raw'] ?? $result['reply'] ?? null) : null;
        }

        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        $caption = trim(ReplyText::forChat($text));

        return $caption === '' ? null : $caption;
    }

    public function imagePrompt(): ?string
    {
        $prompt = data_get($this->result, 'data.image_prompt');

        return is_string($prompt) && trim($prompt) !== '' ? trim($prompt) : null;
    }

    public function listTitle(): string
    {
        $line = Str::of((string) $this->caption())
            ->before("\n")
            ->replaceMatches('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '')
            ->squish()
            ->toString();

        return $line === '' ? 'Post' : $line;
    }

    public function preview(): string
    {
        return Str::limit(Str::of((string) $this->caption())->squish()->toString(), 220);
    }

    /**
     * @return Collection<int, self>
     */
    public static function finishedFor(int $userId): Collection
    {
        return static::query()
            ->finished($userId)
            ->get()
            ->filter(fn (self $generation): bool => $generation->caption() !== null)
            ->values();
    }

    /**
     * @return Collection<int, self>
     */
    public static function recentPosts(int $userId, int $limit = 20): Collection
    {
        return static::query()
            ->finished($userId)
            ->limit($limit * 2)
            ->get()
            ->filter(fn (self $generation): bool => $generation->caption() !== null)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, self>
     */
    public static function historyForPlanning(int $userId): Collection
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('status', 'done')
            ->whereIn('kind', ['post', 'chat_post', 'chat'])
            ->latest('finished_at')
            ->latest('id')
            ->get()
            ->filter(fn (self $generation): bool => $generation->caption() !== null)
            ->values();
    }

    /**
     * @param  Collection<int, self>  $posts
     */
    public static function briefsFor(Collection $posts): string
    {
        return $posts
            ->values()
            ->map(function (self $generation, int $index): string {
                $topic = trim((string) data_get($generation->payload, 'topic'));

                if (preg_match('/^[A-Z]{2,5}-\d{2}$/', $topic) !== 1) {
                    $topic = '';
                }

                $moment = Str::of((string) $generation->caption())
                    ->explode("\n")
                    ->map(fn (string $line): string => trim($line))
                    ->filter(fn (string $line): bool => $line !== ''
                        && ! str_starts_with($line, '#')
                        && ! str_contains($line, 'Webexpert Infotech'))
                    ->take(4)
                    ->implode(' ');
                $moment = Str::limit($moment, 280);
                $label = $topic !== '' ? $topic.': '.$moment : $moment;

                return ($index + 1).'. '.$label;
            })
            ->implode("\n");
    }

    public static function recentBriefsFor(int $userId, int $limit = 20): string
    {
        return static::briefsFor(static::recentPosts($userId, $limit));
    }

    public static function allBriefsFor(int $userId): string
    {
        return static::briefsFor(static::finishedFor($userId));
    }

    public static function recentTopicIdsFor(int $userId, int $limit = 20): string
    {
        return static::recentPosts($userId, $limit)
            ->map(fn (self $generation): string => trim((string) data_get($generation->payload, 'topic')))
            ->filter(fn (string $topic): bool => $topic !== '')
            ->unique()
            ->implode(', ');
    }

    public function finishedLabel(): string
    {
        return ($this->finished_at ?? $this->created_at)
            ->timezone('Asia/Kolkata')
            ->format('d M Y, h:i A');
    }

    public function durationSeconds(): ?int
    {
        $elapsed = is_array($this->result) ? ($this->result['elapsed_seconds'] ?? null) : null;

        if (is_numeric($elapsed)) {
            return max(0, (int) $elapsed);
        }

        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return max(0, (int) $this->started_at->diffInSeconds($this->finished_at));
    }

    public function durationLabel(): ?string
    {
        $seconds = $this->durationSeconds();

        if ($seconds === null) {
            return null;
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        if ($minutes === 0) {
            return $rest.' sec';
        }

        if ($rest === 0) {
            return $minutes.' min';
        }

        return $minutes.' min '.$rest.' sec';
    }

    public function clock(): string
    {
        $seconds = $this->started_at === null
            ? 0
            : (int) now()->diffInSeconds($this->started_at, true);

        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->newQuery()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->where('user_id', auth()->id())
            ->first();
    }

    public function failIfStale(): void
    {
        if ($this->status !== 'running' || $this->started_at === null) {
            return;
        }

        $limit = (int) config('ollama.timeout') + 60;

        if ($this->started_at->gt(now()->subSeconds($limit))) {
            return;
        }

        $this->update([
            'status' => 'failed',
            'error' => 'The writer stopped before it finished. Start it again.',
            'finished_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
