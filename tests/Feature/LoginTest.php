<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_sign_in_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_a_user_can_sign_in_and_open_the_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Mahendra',
            'email' => 'mahendra@webexpertinfotech.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'mahendra@webexpertinfotech.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get('/')->assertOk()->assertSee('Signed in as Mahendra');
    }

    public function test_bad_credentials_stay_on_the_sign_in_page(): void
    {
        User::factory()->create([
            'email' => 'mahendra@webexpertinfotech.com',
            'password' => 'password',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'mahendra@webexpertinfotech.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login');

        $this->assertGuest();
    }
}
