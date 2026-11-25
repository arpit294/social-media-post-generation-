<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateChatPostRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\Conversation;
use App\Models\Generation;
use App\Models\Message;
use App\Services\AgentChat;
use App\Services\BackgroundWork;
use App\Services\PostIntent;
use App\Services\ReplyText;
use App\Services\GenerationBusyException;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ChatController extends BaseController
{
    public function show(AgentChat $chat, BackgroundWork $work, ?Conversation $conversation = null): View
    {
        $this->breadcrumbs([
            'Chat' => route('chat.show'),
        ]);

        $requestUser = auth()->user();
        $history = $chat->history($requestUser);
        $conversation = $this->current($conversation, $history, $chat, $requestUser);
        $originalId = (int) ($history->sortBy('id')->first()->id ?? $conversation->id);
        $running = $work->runningFor($requestUser);
        $runningHere = $this->forConversation($running, $conversation, $originalId) ? $running : null;
        $latestPost = Generation::query()
            ->where('user_id', $requestUser->id)
            ->where('kind', 'chat_post')
            ->where('status', 'done')
            ->latest('id')
            ->limit(20)
            ->get()
            ->first(fn (Generation $generation) => $this->forConversation($generation, $conversation, $originalId));
        $latest = Generation::query()
            ->where('user_id', $requestUser->id)
            ->whereIn('kind', ['chat', 'chat_post'])
            ->where('status', 'failed')
            ->latest('id')
            ->limit(20)
            ->get()
            ->first(fn (Generation $generation) => $this->forConversation($generation, $conversation, $originalId));

        $messages = $conversation->messages()->orderBy('id')->get()->each(function (Message $message): void {
            if ($message->role === 'assistant') {
                $message->content = ReplyText::forChat($message->content);
            }
        });
        $this->placeImageBriefs($messages, $requestUser, $conversation, $originalId);

        return view('chat.show', [
            'conversation' => $conversation,
            'conversations' => $history,
            'messages' => $messages,
            'user' => $requestUser,
            'generated' => $latestPost?->result,
            'failure' => $latest?->error,
            'running' => $runningHere,
            'busy' => $running !== null,
        ]);
    }

    public function storeSession(AgentChat $chat): RedirectResponse
    {
        return redirect()->route('chat.show', $chat->start(auth()->user()));
    }

    public function store(StoreChatMessageRequest $request, BackgroundWork $work, AgentChat $chat, PostIntent $intent, ?Conversation $conversation = null): RedirectResponse
    {
        $data = $request->validated();

        $conversation ??= $chat->latest($request->user());
        $chat->rememberPrompt($conversation, $data['message']);

        $savedPrompt = null;

        try {
            if ($intent->isPost($data['message'])) {
                $savedPrompt = $conversation->messages()->create([
                    'role' => 'user',
                    'content' => $data['message'],
                ]);
                $work->start($request->user(), 'chat_post', [
                    'platform' => 'LinkedIn',
                    'language' => 'English',
                    'segment' => 'small',
                    'prompt' => $data['message'],
                    'conversation_id' => $conversation->id,
                ]);
            } else {
                $work->start($request->user(), 'chat', [
                    'message' => $data['message'],
                    'conversation_id' => $conversation->id,
                ]);
            }
        } catch (GenerationBusyException $exception) {
            $savedPrompt?->delete();

            return $this->back($conversation)
                ->withInput()
                ->withErrors(['message' => $exception->getMessage()]);
        }

        return $this->back($conversation);
    }

    public function generate(GenerateChatPostRequest $request, BackgroundWork $work, AgentChat $chat, ?Conversation $conversation = null): RedirectResponse
    {
        $data = $request->validated();

        $conversation ??= $chat->latest($request->user());
        $prompt = trim((string) $conversation->messages()->where('role', 'user')->latest('id')->value('content'));

        try {
            $work->start($request->user(), 'chat_post', [
                'platform' => $data['platform'],
                'language' => $data['language'],
                'segment' => 'small',
                'prompt' => $prompt !== '' ? $prompt : 'generate post',
                'conversation_id' => $conversation->id,
            ]);
        } catch (GenerationBusyException $exception) {
            return $this->back($conversation)
                ->withErrors(['message' => $exception->getMessage()]);
        }

        return $this->back($conversation);
    }

    /**
     * @param  Collection<int, Conversation>  $history
     */
    private function current(?Conversation $conversation, Collection $history, AgentChat $chat, User $user): Conversation
    {
        if ($conversation === null) {
            $conversation = $history->first() ?? $chat->start($user);

            if ($history->doesntContain(fn (Conversation $item) => $item->is($conversation))) {
                $history->prepend($conversation);
            }

            return $conversation;
        }

        return $history->firstWhere(fn (Conversation $item) => $item->is($conversation)) ?? $conversation;
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    private function placeImageBriefs(Collection $messages, User $user, Conversation $conversation, int $originalId): void
    {
        $briefs = Generation::query()
            ->where('user_id', $user->id)
            ->where('kind', 'chat_post')
            ->where('status', 'done')
            ->orderBy('id')
            ->get()
            ->filter(fn (Generation $generation): bool => $this->forConversation($generation, $conversation, $originalId))
            ->map(function (Generation $generation): ?array {
                $caption = trim((string) data_get($generation->result, 'data.post_text'));
                $image = $generation->imagePrompt();

                if ($caption === '' || $image === null) {
                    return null;
                }

                return ['caption' => $this->flat($caption), 'image' => $image];
            })
            ->filter()
            ->values();

        $used = [];

        foreach ($messages as $message) {
            if ($message->role !== 'assistant' || filled($message->image_prompt)) {
                continue;
            }

            $caption = $this->flat($message->content);

            foreach ($briefs as $index => $brief) {
                if (isset($used[$index]) || $brief['caption'] !== $caption) {
                    continue;
                }

                $message->image_prompt = $brief['image'];
                $used[$index] = true;
                break;
            }
        }
    }

    private function flat(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function forConversation(?Generation $generation, Conversation $conversation, int $originalId): bool
    {
        if ($generation === null) {
            return false;
        }

        $id = $generation->payload['conversation_id'] ?? $originalId;

        return (int) $id === $conversation->id;
    }

    private function back(Conversation $conversation): RedirectResponse
    {
        if (request()->route('conversation') === null) {
            return redirect()->route('chat.show');
        }

        return redirect()->route('chat.show', $conversation);
    }
}
