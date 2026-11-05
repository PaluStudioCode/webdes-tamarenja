<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated_via_panel_akun(): void
    {
        $user = User::factory()->kepalaDesa()->create();

        $response = $this
            ->actingAs($user)
            ->from('/panel/akun')
            ->put('/panel/akun/password', [
                'current_password' => 'password',
                'password' => 'sandi-baru-2026',
                'password_confirmation' => 'sandi-baru-2026',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/panel/akun');

        $this->assertTrue(Hash::check('sandi-baru-2026', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/panel/akun')
            ->put('/panel/akun/password', [
                'current_password' => 'wrong-password',
                'password' => 'sandi-baru-2026',
                'password_confirmation' => 'sandi-baru-2026',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/panel/akun');
    }
}
