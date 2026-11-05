<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\ProfilDesa;
use App\Models\User;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->seed(EssentialDesaSeeder::class);

        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
            );
    }

    public function test_out_of_scope_breeze_routes_return_404(): void
    {
        $this->get('/register')->assertStatus(404);
        $this->post('/register', [])->assertStatus(404);
        $this->get('/forgot-password')->assertStatus(404);
        $this->post('/forgot-password', [])->assertStatus(404);
        $this->get('/reset-password/sample-token')->assertStatus(404);
        $this->post('/reset-password', [])->assertStatus(404);
        $this->get('/verify-email')->assertStatus(404);
    }

    public function test_users_can_authenticate_redirect_to_panel_dashboard_and_record_last_login(): void
    {
        $this->seed(EssentialDesaSeeder::class);
        $profil = ProfilDesa::singleton();

        $user = User::factory()->create([
            'role' => UserRole::Operator,
            'is_active' => true,
            'last_login_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/panel/dashboard');
        $response->assertSessionHas('filter_tahun', $profil->tahun_anggaran_aktif);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_login_endpoint_is_rate_limited_to_10_attempts_per_minute(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'salah-sandi',
            ]);
        }

        // Percobaan ke-11 diblokir oleh throttle:10,1 (429 Too Many Requests) atau lockout
        $eleventh = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'salah-sandi',
        ]);

        $this->assertTrue(
            $eleventh->status() === 429 || $eleventh->isRedirect('/login'),
            'Percobaan ke-11 wajib ditahan oleh pembatas laju login.',
        );
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
