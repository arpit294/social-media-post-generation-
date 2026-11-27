<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Generation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AgentChat
{
    public function __construct(private LlmClient $writer, private ManufacturingPostGenerator $posts) {}

    public function latest(User $user): Conversation
    {
        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->latest('id')
            ->first();

        return $conversation ?? $this->start($user);
    }

    public function start(User $user): Conversation
    {
        $empty = Conversation::query()
            ->where('user_id', $user->id)
            ->where('title', 'New chat')
            ->whereDoesntHave('messages')
            ->latest('id')
            ->first();

        return $empty ?? Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'New chat',
        ]);
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function history(User $user): Collection
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->latest('id')
            ->get()
            ->each(fn (Conversation $conversation) => $this->nameFromSavedPrompt($conversation));
    }

    public function rememberPrompt(Conversation $conversation, string $text): void
    {
        if ($conversation->messages()->where('role', 'user')->exists()) {
            return;
        }

        if (! in_array($conversation->title, ['New chat', 'Post chat'], true)) {
            return;
        }

        $conversation->update(['title' => $this->titleFrom($text)]);
    }

    public function reply(User $user, string $text, ?int $conversationId = null): Message
    {
        $conversation = $this->resolve($user, $conversationId);
        $this->rememberPrompt($conversation, $text);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $text,
        ]);

        $reply = $this->writer->chat(
            $this->transcript($user, $conversation),
            1200,
        );
        $parts = $this->posts->captionAndImage($reply);

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $parts['post_text'],
            'image_prompt' => $parts['image_prompt'],
        ]);
    }

    /**
     * @param  array<string, string|null>  $input
     * @return array{data: array<string, mixed>|null, raw: string, model: string}
     */
    public function generatePost(User $user, array $input): array
    {
        $conversation = $this->resolve($user, isset($input['conversation_id']) ? (int) $input['conversation_id'] : null);
        $messages = $conversation->messages()->orderBy('id')->get();
        $input['chat'] = $messages
            ->map(function (Message $message): string {
                $content = $message->role === 'assistant'
                    ? ReplyText::forChat($message->content)
                    : $message->content;

                return strtoupper($message->role).': '.$content;
            })
            ->implode("\n\n");

        $prompt = trim((string) ($input['prompt'] ?? ''));

        if ($this->posts->wantsRevision($prompt)) {
            $previous = $messages
                ->reverse()
                ->first(fn (Message $message): bool => $message->role === 'assistant' && trim($message->content) !== '');

            if ($previous !== null) {
                $input['revise_from'] = ReplyText::forChat($previous->content);
            }

            $prior = Generation::query()
                ->where('user_id', $user->id)
                ->where('kind', 'chat_post')
                ->where('status', 'done')
                ->latest('id')
                ->limit(20)
                ->get()
                ->first(function (Generation $generation) use ($conversation): bool {
                    return (int) ($generation->payload['conversation_id'] ?? 0) === $conversation->id
                        && $generation->caption() !== null;
                });

            if ($prior !== null) {
                $topic = trim((string) data_get($prior->payload, 'topic'));

                if ($topic !== '') {
                    $input['topic'] = $topic;
                }

                if (trim((string) ($input['revise_from'] ?? '')) === '') {
                    $input['revise_from'] = (string) $prior->caption();
                }
            }
        }

        $generated = $this->posts->generate($input);
        $post = $generated['data']['post_text'] ?? $generated['raw'];
        $image = $generated['data']['image_prompt'] ?? null;

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => ReplyText::forChat(is_string($post) ? $post : $generated['raw']),
            'image_prompt' => is_string($image) && trim($image) !== '' ? trim($image) : null,
        ]);

        return $generated;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function transcript(User $user, Conversation $conversation): array
    {
        $ordered = $conversation->messages()->orderBy('id')->get();
        $latestUser = $ordered->reverse()->first(fn (Message $message): bool => $message->role === 'user');
        $upgrade = $latestUser !== null && $this->posts->wantsRevision($latestUser->content);
        $previousPost = $ordered
            ->reverse()
            ->first(fn (Message $message): bool => $message->role === 'assistant' && trim($message->content) !== '');
        $recent = Generation::allBriefsFor($user->id);
        $savedPosts = $recent === ''
            ? ''
            : "\n\nEvery saved post for this account, newest first. Do not repeat their hook or topic, and do not retell the same shop-floor moment in different words:\n".$recent;
        $upgradeRule = $upgrade && $previousPost !== null
            ? "\n\nUpgrade rule: the user asked to improve the last assistant post. Keep that shop-floor moment, that object, and the person waiting. Rewrite the hook, the scene, the cost sentence, and the custom software sentence. Do not return the previous caption. Do not reuse its sentences. Post to upgrade:\n".ReplyText::forChat($previousPost->content)
            : '';

        $messages = [[
            'role' => 'system',
            'content' => $this->posts->instructions()."\n\nYou are the agent for {$user->name}. Use only this knowledge base. Do not invent clients or numbers. Never write an asterisk. No markdown, no bold, and no * bullets.\n\nChat output rule, which overrides Section 19: the default reply is the readable post itself. Write only the caption, the footer, and the hashtags. Put one shop-floor emoji on the hook only. Do not put an emoji on any other line. Use a problem the owner meets on a normal day, such as a plan changed by voice, a stock figure that does not match the rack, a WhatsApp change missing from the job card, a challan whose balance nobody can say, or a handover that misses the quantity. Do not write a rare audit, a calibration expiry, a serial-number recall, a chemical move, or a once-a-year breakdown. The team-cost sentence names the hours lost on a normal day and the cost those hours cause. The solution sentence must contain the words custom software. When the person saves that fact in custom software, the person waiting sees it before the next physical step. Name which hours and which cost belong to this topic. Do not paste a cost from another problem. Do not write that something does not happen. Do not copy a pattern card or a sample post. Saying it saves time and cost, without naming which hours and which cost, is not enough. No rupees, percentages, piece counts, kilograms, or the word efficiency. Never send JSON, field names, hook_candidates, or self_check. If the user asks for JSON, still write the caption. A clarifying question stays plain text, with no IMAGE line. When you write the finished caption, add a blank line and then IMAGE: followed by four to six sentences an image model can draw. Each sentence places one physical object and says where it sits. One object is an empty dashed outline. A phone lies face down. At most one faceless pictogram, seen from the back, with no eyes. Do not list objects. Do not copy a Picture line. Do not write broken handoff, problem, looking, expression, screen, sticker, or signed paper. No style notes, colors, or hex codes.".$upgradeRule.$savedPosts,
        ]];

        foreach ($ordered as $message) {
            $content = $message->role === 'assistant'
                ? ReplyText::forChat($message->content)
                : $message->content;

            $messages[] = [
                'role' => $message->role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        return $messages;
    }

    private function resolve(User $user, ?int $conversationId): Conversation
    {
        if ($conversationId) {
            $conversation = Conversation::query()
                ->where('user_id', $user->id)
                ->find($conversationId);

            if ($conversation) {
                return $conversation;
            }
        }

        return $this->latest($user);
    }

    private function nameFromSavedPrompt(Conversation $conversation): void
    {
        if (! in_array($conversation->title, ['New chat', 'Post chat'], true)) {
            return;
        }

        $first = $conversation->messages()->where('role', 'user')->orderBy('id')->value('content');

        if (! is_string($first) || trim($first) === '') {
            return;
        }

        $conversation->newQuery()->whereKey($conversation->id)->update([
            'title' => $this->titleFrom($first),
        ]);
        $conversation->title = $this->titleFrom($first);
    }

    private function titleFrom(string $text): string
    {
        $line = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($line === '') {
            return 'New chat';
        }

        return Str::limit($line, 48, '…');
    }
}
