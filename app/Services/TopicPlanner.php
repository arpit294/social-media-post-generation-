<?php

namespace App\Services;

use App\Models\Generation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TopicPlanner
{
    /**
     * @param  Collection<int, Generation>  $posts
     * @return array{topic: array{id: string, line: string, department: string}|null, used: list<string>}
     */
    public function plan(string $instructions, Collection $posts): array
    {
        $topics = $this->catalog($instructions);

        if ($topics === []) {
            return ['topic' => null, 'used' => []];
        }

        $matched = [];

        foreach ($posts->values() as $index => $post) {
            $id = $this->matchId($topics, $post);

            if ($id === null) {
                continue;
            }

            $matched[$id][] = $index;
        }

        $used = [];

        foreach ($topics as $topic) {
            if (isset($matched[$topic['id']])) {
                $used[] = $topic['id'];
            }
        }

        $unused = array_values(array_filter(
            $topics,
            fn (array $topic): bool => ! isset($matched[$topic['id']]),
        ));

        $latestDepartment = $this->latestDepartment($topics, $matched);
        $pool = $unused !== [] ? $unused : $this->oldestFirst($topics, $matched);
        $topic = $this->preferOtherDepartment($pool, $latestDepartment);

        return [
            'topic' => $topic,
            'used' => $used,
        ];
    }

    /**
     * A department for a new situation that is not one of the reference issues.
     *
     * @param  Collection<int, Generation>  $posts
     * @return array{code: string, name: string}
     */
    public function openDepartment(string $instructions, Collection $posts): array
    {
        $names = [
            'PLN' => 'production planning',
            'MAT' => 'raw material and stores',
            'QLT' => 'quality control',
            'MNT' => 'machine maintenance',
            'SHF' => 'shift handover',
            'CUS' => 'customer changes',
            'WIP' => 'material flow',
            'SHP' => 'dispatch',
            'PUR' => 'purchasing',
            'REC' => 'records and compliance',
        ];
        $topics = $this->catalog($instructions);
        $counts = array_fill_keys(array_keys($names), 0);
        $latest = null;

        foreach ($posts->values() as $post) {
            $payloadTopic = strtoupper(trim((string) data_get($post->payload, 'topic')));

            if (preg_match('/^NEW-([A-Z]{3})$/', $payloadTopic, $match) === 1 && isset($counts[$match[1]])) {
                $counts[$match[1]]++;
                $latest ??= $match[1];

                continue;
            }

            if ($topics === []) {
                continue;
            }

            $id = $this->matchId($topics, $post);

            if ($id === null) {
                continue;
            }

            $code = Str::before($id, '-');

            if (isset($counts[$code])) {
                $counts[$code]++;
                $latest ??= $code;
            }
        }

        $bestCode = null;
        $bestCount = null;

        foreach ($names as $code => $name) {
            if ($code === $latest) {
                continue;
            }

            $count = $counts[$code];

            if ($bestCount === null || $count < $bestCount) {
                $bestCount = $count;
                $bestCode = $code;
            }
        }

        $bestCode ??= array_key_first($names);

        return [
            'code' => $bestCode,
            'name' => $names[$bestCode],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $topics
     * @param  array<string, list<int>>  $matched
     */
    private function latestDepartment(array $topics, array $matched): ?string
    {
        $newestIndex = null;
        $department = null;

        foreach ($topics as $topic) {
            $index = $matched[$topic['id']][0] ?? null;

            if ($index === null) {
                continue;
            }

            if ($newestIndex === null || $index < $newestIndex) {
                $newestIndex = $index;
                $department = $topic['department'];
            }
        }

        return is_string($department) ? $department : null;
    }

    /**
     * @param  list<array<string, mixed>>  $topics
     * @param  array<string, list<int>>  $matched
     * @return list<array<string, mixed>>
     */
    private function oldestFirst(array $topics, array $matched): array
    {
        $pool = $topics;

        usort($pool, function (array $left, array $right) use ($matched): int {
            $leftIndex = $matched[$left['id']][0] ?? -1;
            $rightIndex = $matched[$right['id']][0] ?? -1;

            return $rightIndex <=> $leftIndex;
        });

        return $pool;
    }

    /**
     * @param  list<array<string, mixed>>  $pool
     * @return array{id: string, line: string, department: string}|null
     */
    private function preferOtherDepartment(array $pool, ?string $department): ?array
    {
        if ($pool === []) {
            return null;
        }

        $chosen = $pool[0];

        if ($department !== null) {
            foreach ($pool as $topic) {
                if ($topic['department'] !== $department) {
                    $chosen = $topic;
                    break;
                }
            }
        }

        return [
            'id' => $chosen['id'],
            'line' => $chosen['line'],
            'department' => $chosen['department'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(string $instructions): array
    {
        if (preg_match_all('/^([A-Z]{2,5}-\d{2})\s+(.+)$/m', $instructions, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $topics = [];

        foreach ($matches as $match) {
            $rest = trim($match[2]);
            $title = trim(Str::before($rest, ':'));
            $problem = trim(Str::before(Str::after($rest, ':'), ' Fix:'));
            $topics[] = [
                'id' => $match[1],
                'line' => $match[1].' '.$rest,
                'department' => Str::before($match[1], '-'),
                'title' => $title,
                'tokens' => $this->tokens($title.' '.$problem),
                'title_tokens' => $this->tokens($title),
            ];
        }

        $documentFrequency = [];

        foreach ($topics as $topic) {
            foreach ($topic['tokens'] as $token) {
                $documentFrequency[$token] = ($documentFrequency[$token] ?? 0) + 1;
            }
        }

        foreach ($topics as $index => $topic) {
            $weights = [];

            $boostTitle = count($topic['title_tokens']) <= 8;

            foreach ($topic['tokens'] as $token) {
                $weight = 1 / max(1, $documentFrequency[$token]);

                if ($boostTitle && in_array($token, $topic['title_tokens'], true)) {
                    $weight *= 3;
                }

                $weights[$token] = $weight;
            }

            $topics[$index]['weights'] = $weights;
        }

        return $topics;
    }

    /**
     * @param  list<array<string, mixed>>  $topics
     */
    private function matchId(array $topics, Generation $generation): ?string
    {
        $text = Str::lower(implode("\n", array_filter([
            $generation->caption(),
            (string) data_get($generation->payload, 'prompt'),
            (string) data_get($generation->payload, 'real_detail'),
        ])));
        $payloadTopic = strtoupper(trim((string) data_get($generation->payload, 'topic')));

        foreach ($topics as $topic) {
            if ($payloadTopic === $topic['id'] || preg_match('/\b'.preg_quote(strtolower($topic['id']), '/').'\b/', $text) === 1) {
                return $topic['id'];
            }
        }

        $postTokens = array_flip($this->tokens($text));
        $bestId = null;
        $best = 0.0;
        $second = 0.0;

        foreach ($topics as $topic) {
            $score = 0.0;

            foreach ($topic['weights'] as $token => $weight) {
                if (isset($postTokens[$token])) {
                    $score += $weight;
                }
            }

            $title = Str::lower((string) $topic['title']);

            if ($title !== '' && str_contains($text, $title)) {
                $score += 4;
            }

            if ($score > $best) {
                $second = $best;
                $best = $score;
                $bestId = $topic['id'];
            } elseif ($score > $second) {
                $second = $score;
            }
        }

        if (! is_string($bestId) || $best < 1.8) {
            return null;
        }

        if ($second > 0 && $best < ($second * 1.15)) {
            return null;
        }

        return $bestId;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $text = Str::lower(Str::ascii($text));
        preg_match_all('/[a-z]{4,}|\d{3,}/', $text, $matches);

        $stop = [
            'this', 'that', 'with', 'from', 'your', 'they', 'them', 'then', 'than', 'into', 'only',
            'when', 'what', 'where', 'which', 'have', 'been', 'were', 'will', 'each', 'same', 'before',
            'after', 'many', 'other', 'their', 'about', 'there', 'these', 'those', 'does', 'still',
            'keep', 'make', 'made', 'also', 'just', 'over', 'under', 'such', 'more', 'most', 'some',
            'once', 'onto', 'already', 'every', 'because', 'without', 'inside', 'across', 'another',
            'should', 'could', 'would', 'being', 'using', 'used',
        ];

        $tokens = [];

        foreach ($matches[0] as $token) {
            if (in_array($token, $stop, true)) {
                continue;
            }

            if (str_ends_with($token, 's') && strlen($token) > 5 && ! str_ends_with($token, 'ss')) {
                $token = substr($token, 0, -1);
            }

            $tokens[$token] = true;
        }

        return array_keys($tokens);
    }
}
