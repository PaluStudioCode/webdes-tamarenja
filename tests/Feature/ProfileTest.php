<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_akun_page_is_displayed_for_operator_and_kepala_desa(): void
    {
        $this->seed(EssentialDesaSeeder::class);

        $operator = User::factory()->create();
        $kades = User::factory()->kepalaDesa()->create();

        $this->actingAs($operator)
            ->get('/panel/akun')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Akun/Edit')
                ->where('user.email', $operator->email)
            );

        $this->actingAs($kades)
            ->get('/panel/akun')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Akun/Edit')
                ->where('user.email', $kades->email)
            );
    }

    public function test_profile_information_can_be_updated_by_operator_and_kepala_desa(): void
    {
        $user = User::factory()->kepalaDesa()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/panel/akun', [
                'name' => 'H. Darwis Kepala Desa',
                'email' => 'darwis@tamarenja.desa.id',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/panel/akun');

        $user->refresh();

        $this->assertSame('H. Darwis Kepala Desa', $user->name);
        $this->assertSame('darwis@tamarenja.desa.id', $user->email);
    }

    public function test_self_account_deletion_route_is_disabled(): void
    {
        $user = User::factory()->create();

        // Rute hapus akun mandiri (/profile maupun DELETE /panel/akun) sudah dihapus sesuai PRD
        $this->actingAs($user)->get('/profile')->assertStatus(404);
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertStatus(404);

        $deleteAkun = $this->actingAs($user)->delete('/panel/akun', ['password' => 'password']);
        $this->assertTrue(in_array($deleteAkun->status(), [404, 405], true));
        $this->assertNotNull($user->fresh());
    }
}
