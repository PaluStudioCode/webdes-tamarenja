<?php

namespace Tests\Feature\Panel;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PenggunaAndResetPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_manage_users_and_reset_other_user_password(): void
    {
        $this->seed(EssentialDesaSeeder::class);

        $operator = User::query()->where('email', 'operator@desa.id')->firstOrFail();

        // 1. Buka halaman daftar pengguna
        $this->actingAs($operator)
            ->get('/panel/pengaturan/pengguna')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pengaturan/Pengguna')
                ->where('activeOperatorCount', 1)
            );

        // 2. Tambah akun Operator ke-2
        $this->actingAs($operator)
            ->from('/panel/pengaturan/pengguna')
            ->post('/panel/pengaturan/pengguna', [
                'name' => 'Staf Pelayanan Loket',
                'email' => 'loket@tamarenja.desa.id',
                'role' => 'operator',
                'password' => 'loket12345',
                'password_confirmation' => 'loket12345',
            ])
            ->assertRedirect('/panel/pengaturan/pengguna')
            ->assertSessionHasNoErrors();

        $staf = User::query()->where('email', 'loket@tamarenja.desa.id')->firstOrFail();
        $this->assertTrue($staf->isOperator());
        $this->assertTrue($staf->is_active);

        // 3. Ubah peran & reset kata sandi akun lain
        $this->actingAs($operator)
            ->from('/panel/pengaturan/pengguna')
            ->put("/panel/pengaturan/pengguna/{$staf->id}", [
                'name' => 'Staf Pelayanan Senior',
                'email' => 'loket@tamarenja.desa.id',
                'role' => 'kepala_desa',
                'is_active' => true,
                'password' => 'sandi-reset-99',
                'password_confirmation' => 'sandi-reset-99',
            ])
            ->assertRedirect('/panel/pengaturan/pengguna')
            ->assertSessionHasNoErrors();

        $staf->refresh();
        $this->assertSame('Staf Pelayanan Senior', $staf->name);
        $this->assertTrue($staf->isKepalaDesa());
        $this->assertTrue(Hash::check('sandi-reset-99', $staf->password));

        // 4. Hapus akun lain yang belum memiliki arsip dokumen
        $this->actingAs($operator)
            ->from('/panel/pengaturan/pengguna')
            ->delete("/panel/pengaturan/pengguna/{$staf->id}")
            ->assertRedirect('/panel/pengaturan/pengguna');

        $this->assertDatabaseMissing('users', ['id' => $staf->id]);
    }

    public function test_kepala_desa_is_forbidden_from_all_pengguna_endpoints(): void
    {
        $this->seed(EssentialDesaSeeder::class);

        $kades = User::query()->where('email', 'kades@desa.id')->firstOrFail();
        $operator = User::query()->where('email', 'operator@desa.id')->firstOrFail();

        $this->actingAs($kades)->get('/panel/pengaturan/pengguna')->assertStatus(403);
        $this->actingAs($kades)->post('/panel/pengaturan/pengguna', [])->assertStatus(403);
        $this->actingAs($kades)->put("/panel/pengaturan/pengguna/{$operator->id}", [])->assertStatus(403);
        $this->actingAs($kades)->patch("/panel/pengaturan/pengguna/{$operator->id}/toggle-status")->assertStatus(403);
        $this->actingAs($kades)->delete("/panel/pengaturan/pengguna/{$operator->id}")->assertStatus(403);
    }

    public function test_anti_self_lockout_prevents_self_deactivation_self_demotion_and_deleting_last_operator(): void
    {
        $operatorUtama = User::factory()->create([
            'name' => 'Operator Utama',
            'email' => 'op1@desa.id',
            'role' => UserRole::Operator,
            'is_active' => true,
        ]);

        // 1. Tolak menonaktifkan akun sendiri via toggleStatus
        $this->actingAs($operatorUtama)
            ->from('/panel/pengaturan/pengguna')
            ->patch("/panel/pengaturan/pengguna/{$operatorUtama->id}/toggle-status")
            ->assertRedirect('/panel/pengaturan/pengguna')
            ->assertSessionHas('notification.type', 'error');
        $this->assertTrue($operatorUtama->fresh()->is_active);

        // 2. Tolak menonaktifkan atau menurunkan role akun sendiri via update
        $this->actingAs($operatorUtama)
            ->from('/panel/pengaturan/pengguna')
            ->put("/panel/pengaturan/pengguna/{$operatorUtama->id}", [
                'name' => 'Operator Utama',
                'email' => 'op1@desa.id',
                'role' => 'operator',
                'is_active' => false,
            ])
            ->assertSessionHasErrors('is_active');

        $this->actingAs($operatorUtama)
            ->from('/panel/pengaturan/pengguna')
            ->put("/panel/pengaturan/pengguna/{$operatorUtama->id}", [
                'name' => 'Operator Utama',
                'email' => 'op1@desa.id',
                'role' => 'kepala_desa',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('role');

        // 3. Tolak menghapus akun sendiri
        $this->actingAs($operatorUtama)
            ->from('/panel/pengaturan/pengguna')
            ->delete("/panel/pengaturan/pengguna/{$operatorUtama->id}")
            ->assertRedirect('/panel/pengaturan/pengguna')
            ->assertSessionHas('notification.type', 'error');
        $this->assertNotNull($operatorUtama->fresh());
    }

    public function test_desa_reset_password_cli_command_resets_password_and_reactivates_user(): void
    {
        $operator = User::factory()->inactive()->create([
            'email' => 'darurat@tamarenja.desa.id',
            'role' => UserRole::Operator,
        ]);

        // Gagal jika email tidak terdaftar
        $this->artisan('desa:reset-password', [
            'email' => 'tidak-ada@tamarenja.desa.id',
            '--password' => 'sandi-aman-123',
        ])->assertExitCode(1);

        // Berhasil menyetel ulang kata sandi & mengaktifkan kembali akun
        $this->artisan('desa:reset-password', [
            'email' => 'darurat@tamarenja.desa.id',
            '--password' => 'pemulihan2026',
        ])
            ->expectsOutputToContain('berhasil disetel ulang')
            ->assertExitCode(0);

        $operator->refresh();
        $this->assertTrue($operator->is_active);
        $this->assertTrue(Hash::check('pemulihan2026', $operator->password));
    }
}
