<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManufacturingPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_the_signed_in_user_and_the_model(): void
    {
        $user = User::factory()->create([
            'name' => 'Mahendra',
            'email' => 'mahendra@webexpertinfotech.com',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Mahendra')
            ->assertSee('mahendra@webexpertinfotech.com')
            ->assertSee('gemma4:12b')
            ->assertSee('manufacturing knowledge base')
            ->assertSee('Prompt')
            ->assertSee('One prompt')
            ->assertDontSee('Verified detail')
            ->assertDontSee('Topic ID or description')
            ->assertSee('Time so far')
            ->assertSee('0:00');
    }

    public function test_a_post_is_generated_from_the_knowledge_base_instructions(): void
    {
        Http::fake(function ($request) {
            $body = $request->data();

            $this->assertSame('gemma4:12b', $body['model']);
            $this->assertFalse($body['think']);
            $this->assertArrayNotHasKey('format', $body);
            $this->assertSame(1200, $body['options']['num_predict']);
            $this->assertStringContainsString('Do not ask a question', $body['messages'][1]['content']);
            $this->assertStringContainsString('Write the finished caption first', $body['messages'][1]['content']);
            $this->assertStringContainsString('Never return JSON', $body['messages'][1]['content']);
            $this->assertStringContainsString('four to six sentences', $body['messages'][1]['content']);
            $this->assertStringContainsString('Do not list objects', $body['messages'][1]['content']);
            $this->assertStringContainsString('IMAGE:', $body['messages'][1]['content']);
            $this->assertStringContainsString('Never return JSON', $body['messages'][0]['content']);
            $this->assertStringNotContainsString('Return exactly one valid JSON object', $body['messages'][0]['content']);
            $this->assertStringContainsString(
                'CUSTOM SOFTWARE LINE is mandatory',
                $body['messages'][0]['content'],
            );
            $this->assertStringContainsString('platform: LinkedIn', $body['messages'][1]['content']);
            $this->assertStringContainsString('The stock register and Tally show different quantities for the same lot.', $body['messages'][1]['content']);
            $this->assertStringContainsString('Picture:', $body['messages'][0]['content']);
            $this->assertStringContainsString('None yet.', $body['messages'][1]['content']);
            $this->assertStringNotContainsString('Assigned topic', $body['messages'][1]['content']);
            $this->assertStringNotContainsString('Reference list', $body['messages'][1]['content']);
            $this->assertStringContainsString('Put one shop-floor emoji on the hook only', $body['messages'][1]['content']);

            return Http::response([
                'message' => [
                    'content' => "Register says one number. Tally says another.\n\nUsually, both are late.\n\nWebexpert Infotech | Custom software for manufacturers.\n\n#GujaratManufacturing #InventoryManagement #SMEManufacturing\nIMAGE: A stock register beside a Tally book on a navy grid, teal marks, no text.",
                ],
            ]);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/posts', [
            'platform' => 'LinkedIn',
            'language' => 'English',
            'prompt' => 'The stock register and Tally show different quantities for the same lot.',
        ]);

        $response->assertRedirect('/');

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Register says one number. Tally says another.')
            ->assertSee('A stock register beside a Tally book on a navy grid, teal marks, no text.')
            ->assertSee('Square isometric paper-cut vector illustration')
            ->assertSee('No letters, numbers, logos, faces, or screens.')
            ->assertDontSee('IMAGE:')
            ->assertDontSee('Full JSON');
    }

    public function test_a_blank_prompt_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/')
            ->post('/posts', [
                'platform' => 'LinkedIn',
                'language' => 'English',
                'prompt' => '   ',
            ])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['prompt']);
    }

    public function test_a_new_post_is_told_which_hooks_were_already_written(): void
    {
        Http::fake(function ($request) {
            $this->assertStringContainsString(
                'Party ordered 1,000 pieces.',
                $request->data()['messages'][1]['content'],
            );
            $content = $request->data()['messages'][1]['content'];
            $this->assertStringContainsString(
                'Do not repeat their hook or topic',
                $content,
            );
            $this->assertStringContainsString('Reference list', $content);
            $this->assertStringContainsString('must not be mentioned in the reference list', $content);
            $this->assertStringContainsString('production planning', $content);
            $this->assertStringNotContainsString('Write this situation only', $content);

            return Http::response([
                'message' => [
                    'content' => "Register says one number. Tally says another.\nIMAGE: An open stock register beside a blank account book.",
                ],
            ]);
        });

        $user = User::factory()->create();
        Generation::query()->create([
            'user_id' => $user->id,
            'kind' => 'post',
            'status' => 'done',
            'payload' => ['topic' => 'DSP-02', 'prompt' => 'partial dispatch'],
            'result' => [
                'data' => ['post_text' => "Party ordered 1,000 pieces.\n\nThree challans went out."],
            ],
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/posts', [
                'platform' => 'LinkedIn',
                'language' => 'English',
                'prompt' => 'generate post',
            ])
            ->assertRedirect('/');

        $written = Generation::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertSame('NEW-PLN', $written->payload['topic']);
    }

    public function test_generate_post_skips_topics_already_used_by_any_saved_post(): void
    {
        Http::fake(function ($request) {
            $content = $request->data()['messages'][1]['content'];

            $this->assertStringContainsString('Reference list', $content);
            $this->assertStringContainsString('must not be mentioned in the reference list', $content);
            $this->assertStringContainsString('Topic IDs already used:', $content);
            $this->assertStringContainsString('CUS-01', $content);
            $this->assertDoesNotMatchRegularExpression('/Invent one new shop-floor situation in customer changes/u', $content);

            return Http::response([
                'message' => [
                    'content' => "The owner is the status desk.\nIMAGE: Three pictograms and a desk phone.",
                ],
            ]);
        });

        $user = User::factory()->create();
        $captions = [
            'STR-01' => "Is your stock register different from your Tally balance?\n\nThe storekeeper writes a slip. Tally stays blank for days.",
            'STR-02' => "Does your storekeeper know which stock is actually free?\n\nThe rack shows 500 kg. 400 kg is earmarked.",
            'ORD-01' => "Party changed the size after the order was confirmed?\n\nSales posts the change on WhatsApp. The operator runs the old printed job card.",
            'PRD-01' => "Is your production status stuck on In Progress?\n\nFive days of no movement.",
            'DSP-02' => "Party ordered 1,000 pieces. How many are still pending?\n\nThree challans went out. Nobody can say the balance.",
            'TEM-01' => "Does your factory stop moving when your key clerk is away?\n\nOne person holds every detail.",
        ];

        $minutes = 1;

        foreach ($captions as $caption) {
            Generation::query()->create([
                'user_id' => $user->id,
                'kind' => 'post',
                'status' => 'done',
                'payload' => ['prompt' => 'generate the post'],
                'result' => ['data' => ['post_text' => $caption]],
                'finished_at' => now()->subMinutes($minutes),
            ]);
            $minutes++;
        }

        $this->actingAs($user)
            ->post('/posts', [
                'platform' => 'LinkedIn',
                'language' => 'English',
                'prompt' => 'generate the post',
            ])
            ->assertRedirect('/');

        $written = Generation::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertStringStartsWith('NEW-', $written->payload['topic']);
    }

    public function test_a_new_post_is_checked_against_every_saved_post(): void
    {
        Http::fake(function ($request) {
            $content = $request->data()['messages'][1]['content'];

            $this->assertStringContainsString('Newest saved post.', $content);
            $this->assertStringContainsString('Saved post 1.', $content);
            $this->assertStringContainsString('Saved post 19.', $content);
            $this->assertStringContainsString('Oldest post that must stay out.', $content);
            $this->assertStringContainsString('Reference list', $content);
            $this->assertStringContainsString('must not be mentioned in the reference list', $content);
            $this->assertStringContainsString('production planning', $content);
            $this->assertStringNotContainsString('This post belongs to someone else.', $content);

            return Http::response([
                'message' => [
                    'content' => "A different hook.\nIMAGE: A crate with a missing label.",
                ],
            ]);
        });

        $user = User::factory()->create();
        $other = User::factory()->create();

        Generation::query()->create([
            'user_id' => $user->id,
            'kind' => 'post',
            'status' => 'done',
            'payload' => ['topic' => 'OLD'],
            'result' => ['data' => ['post_text' => 'Oldest post that must stay out.']],
            'finished_at' => now()->subDays(3),
        ]);

        foreach (range(1, 19) as $index) {
            Generation::query()->create([
                'user_id' => $user->id,
                'kind' => 'post',
                'status' => 'done',
                'payload' => ['topic' => 'T-'.$index],
                'result' => ['data' => ['post_text' => "Saved post {$index}."]],
                'finished_at' => now()->subMinutes(40 - $index),
            ]);
        }

        Generation::query()->create([
            'user_id' => $user->id,
            'kind' => 'chat_post',
            'status' => 'done',
            'payload' => ['prompt' => 'generate post'],
            'result' => ['data' => ['post_text' => 'Newest saved post.']],
            'finished_at' => now(),
        ]);

        Generation::query()->create([
            'user_id' => $other->id,
            'kind' => 'post',
            'status' => 'done',
            'payload' => ['topic' => 'OTHER'],
            'result' => ['data' => ['post_text' => 'This post belongs to someone else.']],
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/posts', [
                'platform' => 'LinkedIn',
                'language' => 'English',
                'prompt' => 'generate post',
            ])
            ->assertRedirect('/');
    }

    public function test_finished_posts_are_listed_with_the_date_and_the_time_they_took(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Generation::query()->create([
            'user_id' => $user->id,
            'kind' => 'post',
            'status' => 'done',
            'payload' => ['topic' => 'old'],
            'result' => [
                'data' => ['post_text' => 'Older hook that stays off the sidebar.'],
                'elapsed_seconds' => 20,
            ],
            'started_at' => '2026-11-25 05:00:00',
            'finished_at' => '2026-11-25 05:00:20',
        ]);

        $generation = Generation::query()->create([
            'user_id' => $user->id,
            'kind' => 'post',
            'status' => 'done',
            'payload' => ['topic' => 'ORD-02'],
            'result' => [
                'data' => ['post_text' => "Party ordered 1,000 pieces.\n\nThree challans went out."],
                'elapsed_seconds' => 93,
            ],
            'started_at' => '2026-11-25 06:49:58',
            'finished_at' => '2026-11-25 06:51:32',
        ]);

        foreach (range(1, 4) as $index) {
            Generation::query()->create([
                'user_id' => $user->id,
                'kind' => 'post',
                'status' => 'done',
                'payload' => ['topic' => 'recent-'.$index],
                'result' => [
                    'data' => ['post_text' => "Recent hook {$index}."],
                    'elapsed_seconds' => 30,
                ],
                'started_at' => '2026-11-25 06:40:00',
                'finished_at' => '2026-11-25 06:4'.$index.':00',
            ]);
        }

        Generation::query()->create([
            'user_id' => $other->id,
            'kind' => 'post',
            'status' => 'done',
            'payload' => ['topic' => 'hidden'],
            'result' => [
                'data' => ['post_text' => 'This post belongs to someone else.'],
                'elapsed_seconds' => 10,
            ],
            'started_at' => '2026-11-25 06:00:00',
            'finished_at' => '2026-11-25 06:00:10',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Posts')
            ->assertSee('Party ordered 1,000 pieces.')
            ->assertSee('25 Nov 2026, 12:21 PM')
            ->assertSee('took 1 min 33 sec')
            ->assertSee('Recent hook 4.')
            ->assertDontSee('Older hook that stays off the sidebar.')
            ->assertDontSee('This post belongs to someone else.');

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Older hook that stays off the sidebar.')
            ->assertSee('Party ordered 1,000 pieces.')
            ->assertSee('Recent hook 1.')
            ->assertSee(route('posts.show', $generation), false)
            ->assertDontSee('This post belongs to someone else.');

        $this->actingAs($user)
            ->get(route('posts.show', $generation))
            ->assertOk()
            ->assertSee('Party ordered 1,000 pieces.')
            ->assertSee('Three challans went out.')
            ->assertSee('25 Nov 2026, 12:21 PM')
            ->assertSee('took 1 min 33 sec');

        $this->actingAs($other)
            ->get(route('posts.show', $generation))
            ->assertNotFound();
    }
}
