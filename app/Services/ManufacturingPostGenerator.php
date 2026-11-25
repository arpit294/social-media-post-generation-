<?php

namespace App\Services;

use App\Models\Generation;
use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\Auth;

class ManufacturingPostGenerator
{
    public function __construct(private LlmClient $writer) {}

    /**
     * @param  array<string, string|null>  $input
     * @return array{data: array<string, mixed>|null, raw: string, model: string}
     */
    public function generate(array $input): array
    {
        $input = $this->withRecentPosts($input);
        $raw = $this->writer->chat([
            ['role' => 'system', 'content' => $this->instructions()],
            ['role' => 'user', 'content' => $this->userMessage($input)],
        ], 1200);
        $parts = $this->captionAndImage($raw);

        return [
            'data' => $parts,
            'raw' => $parts['post_text'],
            'model' => $this->writer->model(),
        ];
    }

    /**
     * @return array{post_text: string, image_prompt: string|null}
     */
    public function captionAndImage(string $raw): array
    {
        [$caption, $image] = $this->splitImage($raw);

        if ($image === null) {
            $image = $this->imageFromJson($raw);
        }

        return [
            'post_text' => ReplyText::forChat($caption),
            'image_prompt' => $this->frameImage($image),
        ];
    }

    public function instructions(): string
    {
        $userId = Auth::id();

        if ($userId) {
            $saved = KnowledgeBase::query()->where('user_id', $userId)->value('body');

            if (is_string($saved) && trim($saved) !== '') {
                return $saved;
            }
        }

        return $this->defaultInstructions();
    }

    public function defaultInstructions(): string
    {
        $path = resource_path('prompts/webexpert-manufacturing-content-engine.txt');

        if (! is_file($path)) {
            throw new OllamaException('The manufacturing knowledge base file is missing.');
        }

        return (string) file_get_contents($path);
    }

    /**
     * @param  array<string, string|null>  $input
     * @return array<string, string|null>
     */
    private function withRecentPosts(array $input): array
    {
        if (trim((string) ($input['recent_posts'] ?? '')) !== '') {
            return $input;
        }

        $userId = Auth::id();

        if ($userId === null) {
            return $input;
        }

        $input['recent_posts'] = Generation::recentBriefsFor((int) $userId);

        return $input;
    }

    /**
     * @param  array<string, string|null>  $input
     */
    private function userMessage(array $input): string
    {
        $prompt = trim((string) ($input['prompt'] ?? ''));
        $topic = trim((string) ($input['topic'] ?? ''));
        $detail = trim((string) ($input['real_detail'] ?? ''));

        if ($prompt === '') {
            $prompt = trim($topic."\n".$detail);
        }

        $recent = trim((string) ($input['recent_posts'] ?? ''));
        $lines = [
            'Write one finished post. Do not ask a question.',
            '',
            'platform: '.($input['platform'] ?? 'LinkedIn'),
            'segment: '.($input['segment'] ?? 'small'),
            'language: '.($input['language'] ?? 'English'),
            '',
            'User prompt:',
            $prompt !== '' ? $prompt : 'generate post',
            '',
            'Recent posts already written. Do not repeat their hook or topic:',
            $recent !== '' ? $recent : 'None yet.',
            '',
        ];

        $usedTopics = trim((string) ($input['recent_topic_ids'] ?? ''));

        if ($usedTopics !== '') {
            $lines[] = 'Topic IDs already used: '.$usedTopics;
            $lines[] = '';
        }

        $chat = trim((string) ($input['chat'] ?? ''));

        if ($chat !== '') {
            $lines[] = 'Chat so far:';
            $lines[] = $chat;
            $lines[] = 'The latest user prompt is the post to write. Keep the facts already stated. Do not swap in a different moment.';
            $lines[] = '';
        }

        $lines[] = 'If the prompt names a shop-floor moment, that moment is the post. Keep its facts. Add no client, number, or event the user did not give.';
        $lines[] = 'If the prompt does not name a moment, choose one topic ID from Section 6 that is not in the recent posts. Rotate the department. Stage that topic into one picture that could not be reused for a different topic.';
        $lines[] = 'Put one shop-floor emoji on the hook only (🏭 📋 📞 ⏱️ 📦 🚚 ✅ 👀). Do not put an emoji anywhere else in the caption: not on the scene, the cost, the software line, the closing, the footer, or the hashtags. Never write an asterisk. No markdown and no * bullets.';
        $lines[] = 'Write the finished caption first. Never return JSON, field names, or a code block, even if asked for JSON.';
        $lines[] = 'After the caption, on its own line, write IMAGE: and then four to six sentences that stage one specific picture.';
        $lines[] = 'Sentence 1 is the idea: the broken handoff, visible in one look. Then place each object: what is in front, what is missing, and which single object carries the problem. Name the department.';
        $lines[] = 'Do not list objects in a row. Do not copy a Picture line from Section 6. A small Gujarat factory, not a glass office, a handshake, a lightbulb, or a rocket.';
        $lines[] = 'No style notes, colors, hex codes, or camera words. No company name, caption, hashtags, letters, or numbers inside the picture.';

        return implode("\n", $lines);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitImage(string $raw): array
    {
        if (preg_match('/(?:^|\n)\s*IMAGE:\s*(.*)\z/isu', $raw, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return [trim($raw), null];
        }

        $caption = trim(substr($raw, 0, $matches[0][1]));
        $image = trim($matches[1][0]);

        return [$caption, $image !== '' ? $image : null];
    }

    private function imageFromJson(string $raw): ?string
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            return null;
        }

        foreach (['image_prompt', 'visual_metaphor'] as $key) {
            $value = $decoded[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function frameImage(?string $scene): ?string
    {
        $scene = $this->sceneOnly((string) $scene);

        if ($scene === '') {
            return null;
        }

        return $scene.' Square isometric paper-cut vector illustration, matte edges, not a photograph. Deep navy field, one muted teal accent on the object that shows the problem, everything else white or light grey. The staged scene stays in the middle band, with empty navy above and below. No letters, numbers, logos, faces, or screens.';
    }

    private function sceneOnly(string $scene): string
    {
        $scene = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $scene) ?? $scene;
        $scene = preg_replace('/#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})\b|\b(?=[0-9A-Fa-f]*\d)[0-9A-Fa-f]{6}\b/', '', $scene) ?? $scene;
        $scene = preg_replace('/\s+/u', ' ', $scene) ?? $scene;
        $scene = trim($scene);
        $scene = preg_replace('/^(?:image|scene|artwork|prompt)\s*:\s*/i', '', $scene) ?? $scene;
        $scene = trim($scene);

        if (preg_match_all('/[^.!?]+[.!?]+(?:\s+|$)/u', $scene, $sentences) > 6) {
            $scene = trim(implode('', array_slice($sentences[0], 0, 6)));
        }

        $scene = trim($scene, " \t\n\r\0\x0B,;:-");

        if ($scene === '') {
            return '';
        }

        return str_ends_with($scene, '.') || str_ends_with($scene, '!') || str_ends_with($scene, '?')
            ? $scene
            : $scene.'.';
    }
}
