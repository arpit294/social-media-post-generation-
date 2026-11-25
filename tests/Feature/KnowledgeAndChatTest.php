<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ReplyText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeAndChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_save_a_knowledge_base(): void
    {
        $user = User::factory()->create(['name' => 'Mahendra']);

        $this->actingAs($user)
            ->put('/knowledge-base', [
                'body' => 'Custom rule: always mention the challan.',
            ])
            ->assertRedirect(route('knowledge.edit'));

        $this->get('/knowledge-base')
            ->assertOk()
            ->assertSee('Custom rule: always mention the challan.');
    }

    public function test_chat_saves_the_user_message_and_the_agent_reply(): void
    {
        Http::fake(function ($request) {
            $body = $request->data();

            $this->assertStringContainsString(
                'Saved rule for Mahendra.',
                $body['messages'][0]['content'],
            );
            $this->assertStringContainsString(
                'Put one shop-floor emoji on the hook only',
                $body['messages'][0]['content'],
            );
            $this->assertStringContainsString(
                'Never write an asterisk',
                $body['messages'][0]['content'],
            );
            $this->assertStringContainsString(
                'the default reply is the readable post itself',
                $body['messages'][0]['content'],
            );
            $this->assertStringContainsString(
                'Never send JSON',
                $body['messages'][0]['content'],
            );
            $this->assertStringContainsString(
                'If the user asks for JSON, still write the caption.',
                $body['messages'][0]['content'],
            );
            $this->assertStringNotContainsString(
                'explicitly asks for JSON',
                $body['messages'][0]['content'],
            );
            $this->assertStringContainsString(
                'When you write the finished caption',
                $body['messages'][0]['content'],
            );
            $this->assertStringContainsString(
                'A clarifying question stays plain text, with no IMAGE line.',
                $body['messages'][0]['content'],
            );
            $this->assertSame('user', $body['messages'][1]['role']);
            $this->assertArrayNotHasKey('format', $body);

            return Http::response([
                'message' => ['content' => 'Which order should the post follow?'],
            ]);
        });

        $user = User::factory()->create(['name' => 'Mahendra']);
        $user->knowledgeBase()->create([
            'body' => 'Saved rule for Mahendra.',
        ]);

        $this->actingAs($user)
            ->post('/chat', [
                'message' => 'What should the operator follow for this post?',
            ])
            ->assertRedirect(route('chat.show'));

        $this->get('/chat')
            ->assertOk()
            ->assertSee('What should the operator follow for this post?')
            ->assertSee('Which order should the post follow?')
            ->assertDontSee('Generate post from this chat');
    }

    public function test_a_post_is_generated_from_the_chat(): void
    {
        Http::fake(function ($request) {
            $this->assertStringContainsString('old job card', $request->data()['messages'][1]['content']);
            $this->assertArrayNotHasKey('format', $request->data());

            return Http::response([
                'message' => [
                    'content' => "Party changed the size.\n\nThe card did not.\n\nWebexpert Infotech | Custom software for manufacturers.\nIMAGE: An old job card on a steel desk in production, the operator still holding the previous size.",
                ],
            ]);
        });

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Post chat',
        ]);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'The operator still has the old job card.',
        ]);

        $this->actingAs($user)
            ->post('/chat/post', [
                'platform' => 'LinkedIn',
                'language' => 'English',
            ])
            ->assertRedirect(route('chat.show'));

        $page = $this->get('/chat');
        $page->assertSee('Party changed the size.')
            ->assertSee('An old job card on a steel desk in production, the operator still holding the previous size.')
            ->assertSee('Square isometric paper-cut vector illustration')
            ->assertDontSee('IMAGE:');

        $html = $page->getContent();
        $this->assertMatchesRegularExpression(
            '/Party changed the size\..*?class="image-brief".*?An old job card on a steel desk/s',
            $html,
        );

        $saved = Message::query()->where('role', 'assistant')->first();
        $this->assertNotNull($saved);
        $this->assertStringContainsString('Party changed the size.', $saved->content);
        $this->assertStringNotContainsString('Isometric paper-cut', $saved->content);
        $this->assertStringContainsString('An old job card on a steel desk', (string) $saved->image_prompt);
    }

    public function test_a_finished_chat_reply_shows_the_image_brief_under_the_caption(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => [
                    'content' => "Party changed the size.\n\nThe card did not.\n\nWebexpert Infotech | Custom software for manufacturers.\nIMAGE: An old job card on a steel desk in production, the operator still holding the previous size.",
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/chat', [
                'message' => 'The operator still has the old job card.',
            ])
            ->assertRedirect(route('chat.show'));

        $page = $this->get('/chat');
        $page->assertSee('Party changed the size.')
            ->assertSee('An old job card on a steel desk in production')
            ->assertSee('Square isometric paper-cut vector illustration')
            ->assertDontSee('IMAGE:');

        $this->assertMatchesRegularExpression(
            '/Party changed the size\..*?class="image-brief".*?An old job card on a steel desk/s',
            $page->getContent(),
        );

        $saved = Message::query()->where('role', 'assistant')->first();
        $this->assertNotNull($saved);
        $this->assertStringNotContainsString('IMAGE:', $saved->content);
        $this->assertStringContainsString('Square isometric paper-cut', (string) $saved->image_prompt);
    }

    public function test_chat_sessions_keep_their_own_messages_and_take_the_first_prompt_as_the_name(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => ['content' => 'Which order should the post follow?'],
            ]),
        ]);

        $user = User::factory()->create();
        $other = User::factory()->create();
        $first = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Post chat',
        ]);
        $first->messages()->create([
            'role' => 'user',
            'content' => 'The party changed the size on WhatsApp.',
        ]);
        $foreign = Conversation::query()->create([
            'user_id' => $other->id,
            'title' => 'Someone else',
        ]);

        $this->actingAs($user)
            ->get('/chat')
            ->assertOk()
            ->assertSee('The party changed the size on WhatsApp.')
            ->assertSee('New chat');

        $this->assertSame('The party changed the size on WhatsApp.', $first->fresh()->title);

        $this->post('/chat/sessions')
            ->assertRedirect();

        $second = Conversation::query()->where('user_id', $user->id)->where('title', 'New chat')->first();
        $this->assertNotNull($second);

        $this->get(route('chat.show', $second))
            ->assertOk()
            ->assertSee('A question still gets an answer.');

        $prompt = 'Too many excel sheets for one order and the quantities never match on the floor.';

        $this->post(route('chat.session.store', $second), [
            'message' => $prompt,
        ])->assertRedirect(route('chat.show', $second));

        $this->assertSame(Str::limit($prompt, 48, '…'), $second->fresh()->title);
        $this->assertSame('The party changed the size on WhatsApp.', $first->fresh()->title);
        $this->assertTrue($second->messages()->where('content', $prompt)->exists());
        $this->assertFalse($first->messages()->where('role', 'assistant')->exists());

        $this->get(route('chat.show', $first))
            ->assertOk()
            ->assertSee('The party changed the size on WhatsApp.')
            ->assertSee($second->fresh()->title)
            ->assertDontSee('class="bubble assistant"', false);

        $this->get('/chat/'.$foreign->id)->assertNotFound();
    }

    public function test_agent_replies_use_emojis_instead_of_asterisks(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => ['content' => "**Option A: The file**\n*   **The Object:** A job card.\n*   **The Person Waiting:** The owner."],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/chat', [
                'message' => 'What happens when only one employee knows the machine setting?',
            ])
            ->assertRedirect(route('chat.show'));

        $reply = Message::query()->where('role', 'assistant')->first();

        $this->assertNotNull($reply);
        $this->assertStringNotContainsString('*', $reply->content);
        $this->assertStringContainsString('The Object: A job card.', $reply->content);
        $this->assertStringContainsString('The Person Waiting: The owner.', $reply->content);
        $this->assertStringNotContainsString('🏭', $reply->content);
        $this->assertStringNotContainsString('📋', $reply->content);

        $this->get('/chat')
            ->assertOk()
            ->assertSee('The Object: A job card.')
            ->assertDontSee('**The Object:**');
    }

    public function test_chat_shows_a_readable_post_when_the_model_returns_json(): void
    {
        $json = json_encode([
            'specific_moment' => 'The owner is waiting on one clerk.',
            'hook_candidates' => [['line_1' => 'hidden field']],
            'post_text' => "🏭 One clerk is on leave.\n\n📋 The file stays in his drawer.\n\n✅ Webexpert Infotech | Custom software for manufacturers.",
        ]);

        Http::fake([
            '*' => Http::response([
                'message' => ['content' => $json],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/chat', [
                'message' => 'Write the post about the clerk on leave.',
            ])
            ->assertRedirect(route('chat.show'));

        $this->get('/chat')
            ->assertOk()
            ->assertSee('🏭 One clerk is on leave.')
            ->assertSee('The file stays in his drawer.')
            ->assertDontSee('📋')
            ->assertDontSee('✅')
            ->assertDontSee('hook_candidates')
            ->assertDontSee('specific_moment');

        $saved = Message::query()->where('role', 'assistant')->value('content');
        $this->assertIsString($saved);
        $this->assertStringNotContainsString('hook_candidates', $saved);
    }

    public function test_chat_stays_a_caption_when_the_user_asks_for_json(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => ['content' => '{"specific_moment":"The owner is waiting."}'],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/chat', [
                'message' => 'Send that as json.',
            ])
            ->assertRedirect(route('chat.show'));

        $this->get('/chat')
            ->assertOk()
            ->assertSee('The owner is waiting.')
            ->assertDontSee('specific_moment');

        $saved = Message::query()->where('role', 'assistant')->value('content');
        $this->assertIsString($saved);
        $this->assertStringNotContainsString('specific_moment', $saved);
        $this->assertStringNotContainsString('{', $saved);
    }

    public function test_a_json_reply_without_a_caption_becomes_readable_sentences(): void
    {
        $text = ReplyText::forChat(json_encode([
            'specific_moment' => 'The owner waits for one file.',
            'no_blame_turn' => "The real issue isn't effort. It's that the file never leaves one drawer.",
            'custom_software_line' => 'Custom software records the file once and shows it to the owner.',
            'hook_candidates' => [['line_1' => 'hidden field']],
        ]));

        $this->assertStringContainsString('The owner waits for one file.', $text);
        $this->assertStringContainsString('Webexpert Infotech | Custom software for manufacturers.', $text);
        $this->assertStringNotContainsString('hook_candidates', $text);
    }

    public function test_a_cut_off_json_reply_still_shows_the_readable_lines(): void
    {
        $text = ReplyText::forChat(<<<'TXT'
Here is the plan for the post.

```json
{
  "hook_candidates": [ { "line_1": "A weaker opening." } ],
  "hit_line": "Does your factory stop when your key clerk takes a leave?",
  "specific_moment": "The owner needs a past challan and the clerk is on leave.",
  "no_blame_turn": "The real issue isn't the clerk's memory. It's that the information
TXT);

        $this->assertStringContainsString('Does your factory stop when your key clerk takes a leave?', $text);
        $this->assertStringContainsString('The owner needs a past challan and the clerk is on leave.', $text);
        $this->assertStringNotContainsString('hook_candidates', $text);
        $this->assertStringNotContainsString('```', $text);
        $this->assertStringNotContainsString('A weaker opening.', $text);
    }
}
