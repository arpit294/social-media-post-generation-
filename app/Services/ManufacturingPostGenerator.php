<?php

namespace App\Services;

use App\Models\Generation;
use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ManufacturingPostGenerator
{
    public function __construct(private LlmClient $writer, private TopicPlanner $topics) {}

    /**
     * @param  array<string, string|null>  $input
     * @return array{data: array<string, mixed>|null, raw: string, model: string}
     */
    public function generate(array $input): array
    {
        $instructions = $this->instructions();
        $input = $this->withRecentPosts($input, $instructions);
        $raw = $this->writer->chat([
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user', 'content' => $this->userMessage($input)],
        ], 1200);
        $parts = $this->captionAndImage($raw);

        $prompt = trim((string) ($input['prompt'] ?? ''));
        $revision = $this->wantsRevision($prompt);
        $reviseFrom = trim((string) ($input['revise_from'] ?? ''));

        if ($revision && $reviseFrom !== '' && $this->sameCaption($parts['post_text'], $reviseFrom)) {
            $retryMessage = $this->userMessage($input)
                ."\n\nCRITICAL: The previous output returned the exact same caption. Do NOT return the previous caption. Keep the same topic and hook, but rewrite the body with different shop-floor details, new phrasing, and fresh sentences.";

            $raw = $this->writer->chat([
                ['role' => 'system', 'content' => $instructions],
                ['role' => 'user', 'content' => $retryMessage],
            ], 1200);
            $parts = $this->captionAndImage($raw);
        }

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
    private function withRecentPosts(array $input, string $instructions): array
    {
        if (trim((string) ($input['recent_posts'] ?? '')) !== '') {
            return $input;
        }

        $userId = Auth::id();

        if ($userId === null) {
            return $input;
        }

        $userId = (int) $userId;
        $history = Generation::historyForPlanning($userId);
        $plan = $this->topics->plan($this->issueBank(), $history);

        if ($plan['topic'] === null) {
            $bank = $instructions;

            if (preg_match('/^[A-Z]{2,5}-\d{2}\s/m', $bank) !== 1) {
                $bank = $this->defaultInstructions();
            }

            $plan = $this->topics->plan($bank, $history);
        }

        $daily = $this->topics->plan($this->dailyBank(), $history);
        $prompt = trim((string) ($input['prompt'] ?? ''));
        $revision = $this->wantsRevision($prompt);
        $reviseFrom = trim((string) ($input['revise_from'] ?? ''));

        if ($revision && $reviseFrom !== '') {
            $input['recent_posts'] = Generation::briefsFor(
                Generation::finishedFor($userId)->reject(
                    fn (Generation $generation): bool => $this->sameCaption($generation->caption(), $reviseFrom),
                )->values(),
            );
        } else {
            $input['recent_posts'] = Generation::allBriefsFor($userId);
        }

        if (trim((string) ($input['recent_topic_ids'] ?? '')) === '' && $plan['used'] !== []) {
            $input['recent_topic_ids'] = implode(', ', $plan['used']);
        }

        $existingTopic = strtoupper(trim((string) ($input['topic'] ?? '')));

        if ($revision && preg_match('/^(?:NEW-[A-Z]{3}|[A-Z]{2,5}-\d{2})$/', $existingTopic) === 1) {
            $this->rememberTopic($userId, $existingTopic);
        } elseif (! $revision && $this->wantsFreshTopic($prompt) && preg_match('/^(?:NEW-[A-Z]{3}|[A-Z]{2,5}-\d{2})$/', $existingTopic) !== 1 && $daily['topic'] !== null) {
            $input['topic'] = $daily['topic']['id'];
            $input['assigned_topic'] = $daily['topic']['line'];
            $this->rememberTopic($userId, $daily['topic']['id']);
        }

        return $input;
    }

    private function sameCaption(?string $left, string $right): bool
    {
        if ($left === null || trim($left) === '') {
            return false;
        }

        $normalize = fn (string $text): string => Str::of($text)->lower()->replaceMatches('/\s+/u', ' ')->trim()->toString();

        return $normalize($left) === $normalize($right)
            || str_contains($normalize($left), Str::limit($normalize($right), 120, ''))
            || str_contains($normalize($right), Str::limit($normalize($left), 120, ''));
    }

    private function wantsFreshTopic(string $prompt): bool
    {
        $prompt = Str::of($prompt)->lower()->squish()->toString();

        if ($prompt === '') {
            return true;
        }

        return preg_match('/^(please\s+)?(generate|write|create|draft)(\s+(a|the))?\s+posts?\.?$/', $prompt) === 1;
    }

    public function wantsRevision(string $prompt): bool
    {
        return app(PostIntent::class)->isRevision($prompt);
    }

    private function issueBank(): string
    {
        $path = resource_path('prompts/manufacturing-issues.txt');

        if (! is_file($path)) {
            return '';
        }

        return (string) file_get_contents($path);
    }

    private function dailyBank(): string
    {
        $path = resource_path('prompts/daily-topics.txt');

        if (! is_file($path)) {
            return '';
        }

        return (string) file_get_contents($path);
    }

    private function rememberTopic(int $userId, string $topicId): void
    {
        $generation = Generation::query()
            ->where('user_id', $userId)
            ->where('status', 'running')
            ->latest('id')
            ->first();

        if ($generation === null) {
            return;
        }

        $payload = is_array($generation->payload) ? $generation->payload : [];
        $payload['topic'] = $topicId;
        $generation->update(['payload' => $payload]);
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
        $revision = $this->wantsRevision($prompt);
        $reviseFrom = trim((string) ($input['revise_from'] ?? ''));
        $lines = [
            $revision
                ? 'Rewrite one finished post. Do not ask a question. Do not return the previous caption.'
                : 'Write one finished post. Do not ask a question.',
            '',
            'platform: '.($input['platform'] ?? 'LinkedIn'),
            'segment: '.($input['segment'] ?? 'small'),
            'language: '.($input['language'] ?? 'English'),
            '',
            'User prompt:',
            $prompt !== '' ? $prompt : 'generate post',
            '',
        ];

        if ($revision && $reviseFrom !== '') {
            $lines[] = 'POST TO UPGRADE. Keep this shop-floor moment, this object, and this person waiting. Rewrite every sentence. The new caption must be clearly stronger and must not reuse these lines:';
            $lines[] = $reviseFrom;
            $lines[] = '';
            $lines[] = 'Upgrade checklist. Change all of these:';
            $lines[] = '1. A sharper hook that names the same object.';
            $lines[] = '2. A scene the owner can see on the floor.';
            $lines[] = '3. A team-cost sentence with the hours and the cost that belong to this moment.';
            $lines[] = '4. A custom software sentence that saves that same fact and stops the next wrong action.';
            $lines[] = '5. A new IMAGE brief for this same moment.';
            $lines[] = 'If a sentence from the post above still fits, throw the whole caption away and rewrite it.';
            $lines[] = 'Keep the same topic and hook theme, but write different, upgraded content for the scene, the cost, and the software fix.';
            $lines[] = '';
        }

        $lines[] = $revision
            ? 'Other saved posts for this account, newest first. Do not copy them, and do not switch to their moment:'
            : 'Every saved post for this account, newest first. These were checked before a topic was chosen. Do not repeat their hook or topic, and do not retell the same shop-floor moment in different words:';
        $lines[] = $recent !== '' ? $recent : 'None yet.';
        $lines[] = '';

        $usedTopics = trim((string) ($input['recent_topic_ids'] ?? ''));

        if ($usedTopics !== '' && ! $revision) {
            $lines[] = 'Topic IDs already used: '.$usedTopics;
            $lines[] = '';
        }

        $chat = trim((string) ($input['chat'] ?? ''));
        $department = trim((string) ($input['fresh_department'] ?? ''));

        if ($chat !== '' && ! $revision) {
            $lines[] = 'Chat so far:';
            $lines[] = $chat;
            $lines[] = $department === ''
                ? 'The latest user prompt is the post to write. Keep the facts already stated. Do not swap in a different moment.'
                : 'The user asked for a new post. Do not reuse a moment already in this chat.';
            $lines[] = '';
        } elseif ($chat !== '' && $revision) {
            $lines[] = 'Chat so far, for context only. The POST TO UPGRADE block above is the caption to rewrite:';
            $lines[] = $chat;
            $lines[] = 'Upgrade the post in POST TO UPGRADE. Keep its shop-floor moment, its object, and the person waiting. Rewrite the hook, the scene, the cost sentence, and the software sentence so the content is stronger. Do not return the previous caption. Do not reuse its sentences. Do not switch to a different topic.';
            $lines[] = '';
        }

        $assigned = trim((string) ($input['assigned_topic'] ?? ''));

        if ($revision) {
            $lines[] = 'Stay on the upgraded moment. Do not assign a new daily topic. Do not invent a different factory problem.';
        } elseif ($assigned !== '') {
            $lines[] = 'Assigned daily topic. It was chosen because this situation is not among the saved posts. Write this situation only. Do not pick a different topic and do not retell a saved post:';
            $lines[] = $assigned;
            $lines[] = '';
            $lines[] = 'The assigned topic is the post. Keep its object and the person waiting. Do not swap in a Tally mismatch, a WhatsApp job card, a pending challan balance, an In Progress board, or a shared tool unless the assigned topic is that situation.';
        } else {
            $lines[] = 'If the prompt names a shop-floor moment, that moment is the post. Keep its facts. Add no client, number, or event the user did not give.';
            $lines[] = 'If the prompt does not name a moment, choose one topic ID from Section 6 that is not in the saved posts. Rotate the department. Stage that topic into one picture that could not be reused for a different topic.';
        }
        $lines[] = 'Put one shop-floor emoji on the hook only (🏭 📋 📞 ⏱️ 📦 🚚 ✅ 👀). Do not put an emoji anywhere else in the caption: not on the scene, the cost, the software line, the closing, the footer, or the hashtags. Never write an asterisk. No markdown and no * bullets.';
        $lines[] = 'Make the caption strong and specific. The hook is 12 words or fewer: a punchy observation, an overheard dialogue quote, or a sharp question that names the object in the assigned topic. The next line is 8 words or fewer and is the consequence already in that topic. The scene is three short lines a person can see: that object, the person waiting, and the wrong action about to happen.';
        $lines[] = 'Show the effect on the business inside the caption. The team-cost sentence names the hours lost on a normal day and the cost those hours cause. Use only the cost already written in the assigned topic. A wrong label costs rebuilt cartons and a waiting truck. An untagged offcut costs a fresh coil cut while a usable piece sits on the bench. These two lessons are not the post. Do not paste a cost from another problem.';
        $lines[] = 'The solution must answer that same daily problem. The solution sentence must contain the words custom software. When the person who has the fact saves it in custom software, the person waiting sees that same fact before the next wrong action. The wrong action is the next physical step in the assigned topic. Name which hours and which cost belong to this topic, in floor words. Do not write "the search stops, and the idle machine stops" unless this topic is a search beside an idle machine. Do not write "the recount stops, and the remade lot stops" unless this topic is a recount and a remake. Do not write "does not happen". Do not write "cost does not happen". Do not write job ID, job history, status, or data. Use shop-floor words. Do not invent rupees, percentages, piece counts, kilograms, or hours. Do not use the word efficiency.';
        $lines[] = $revision
            ? 'The upgraded caption must read as a new draft of the same moment. Do not print these checks.'
            : 'Stay on the assigned daily topic when one is given. Do not replace it. Do not print these checks.';
        $lines[] = 'Write the finished caption first. Never return JSON, field names, or a code block, even if asked for JSON.';
        $lines[] = 'After the caption, on its own line, write IMAGE: and then four to six sentences that an image model can draw.';
        $lines[] = 'Each sentence places one physical object and says where it sits: in front, behind, to the left, or on the bench. One object is missing, drawn as an empty dashed outline. One object is the problem because of where it sits, not because the sentence explains it.';
        $lines[] = 'Use a machine, crate, rack, blank card, telephone, gate, or clock. A phone lies face down. At most one faceless pictogram, seen from the back, with no eyes.';
        $lines[] = 'Do not list objects in a row. Do not copy a Picture line from Section 6. Do not write broken handoff, problem, looking, expression, screen, sticker, or signed paper. A small Gujarat factory, not a glass office, a handshake, a lightbulb, or a rocket.';
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

        return 'Square isometric paper-cut vector illustration, matte edges, not a photograph. Deep navy field, one muted teal accent on the object that shows the problem, everything else white or light grey. The staged scene stays in the middle band, with empty navy above and below. '.$scene.' No letters, numbers, logos, faces, or screens.';
    }

    private function sceneOnly(string $scene): string
    {
        $scene = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $scene) ?? $scene;
        $scene = preg_replace('/#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})\b|\b(?=[0-9A-Fa-f]*\d)[0-9A-Fa-f]{6}\b/', '', $scene) ?? $scene;
        $scene = preg_replace('/\s+/u', ' ', $scene) ?? $scene;
        $scene = trim($scene);
        $scene = preg_replace('/^(?:image|scene|artwork|prompt)\s*:\s*/i', '', $scene) ?? $scene;
        $scene = trim($scene);

        if (preg_match_all('/[^.!?]+[.!?]+(?:\s+|$)/u', $scene, $sentences) > 0) {
            $kept = array_values(array_filter(
                $sentences[0],
                fn (string $sentence): bool => preg_match('/\b(screen|display|expression|eyes|sticker|signed|handwriting|logo|watermark|handshake|lightbulb|rocket|message|chat|focus|nobody)\b|broken handoff|the problem|idle time/i', $sentence) !== 1,
            ));

            if ($kept !== []) {
                $scene = trim(implode('', array_slice($kept, 0, 6)));
            } elseif (count($sentences[0]) > 6) {
                $scene = trim(implode('', array_slice($sentences[0], 0, 6)));
            }
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
