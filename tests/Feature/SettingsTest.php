<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_save_their_details_and_keep_the_local_model(): void
    {
        $user = User::factory()->create([
            'name' => 'Mahendra',
            'email' => 'mahendra@webexpertinfotech.com',
        ]);

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertSee('Your details')
            ->assertSee('Add an API key')
            ->assertSee('Local model (gemma4:12b)');

        $this->put('/settings', [
            'name' => 'Mahendra Patel',
            'email' => 'mahendra@webexpertinfotech.com',
            'active_api_key_id' => '',
        ])->assertRedirect(route('settings.edit'));

        $this->assertSame('Mahendra Patel', $user->fresh()->name);
        $this->assertNull($user->fresh()->active_api_key_id);
    }

    public function test_the_user_chooses_which_saved_key_writes(): void
    {
        Http::fake(function ($request) {
            $this->assertSame('https://api.openai.com/v1/chat/completions', $request->url());
            $this->assertSame('Bearer sk-test-key', $request->header('Authorization')[0]);
            $this->assertSame('gpt-4o-mini', $request->data()['model']);

            return Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode(['post_text' => 'Party changed the size.'])],
                ]],
            ]);
        });

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/keys', [
                'name' => 'OpenAI',
                'api_key' => 'sk-test-key',
                'model' => 'gpt-4o-mini',
            ])
            ->assertRedirect(route('settings.edit'));

        $key = $user->apiKeys()->first();
        $this->assertNotNull($key);
        $this->assertSame('sk-test-key', $key->api_key);
        $this->assertNull($user->fresh()->active_api_key_id);

        $this->put('/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'active_api_key_id' => $key->id,
        ])->assertRedirect(route('settings.edit'));

        $this->post('/posts', [
            'platform' => 'LinkedIn',
            'language' => 'English',
            'prompt' => 'The stock register and Tally show different quantities for the same lot.',
        ])->assertRedirect('/');

        $this->get('/')->assertSee('OpenAI');
    }
}
