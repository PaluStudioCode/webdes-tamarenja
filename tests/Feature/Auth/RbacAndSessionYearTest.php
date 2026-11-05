<?php

namespace Tests\Feature\Auth;

use App\Enums\StatusSuratKeluar;
use App\Enums\UserRole;
use App\Models\ProfilDesa;
use App\Models\SuratKeluar;
use App\Models\User;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RbacAndSessionYearTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rute uji representatif untuk halaman form GET & tulis POST ber-middleware role:operator
        Route::middleware(['web', 'auth', 'active', 'role:operator'])->group(function () {
            Route::get('/panel/_test/proyek/tambah', fn () => response('form-tambah'));
            Route::post('/panel/_test/proyek', fn () => response('simpan-proyek'));
            Route::delete('/panel/_test/proyek/1', fn () => response('hapus-proyek'));
        });
    }

    public function test_deactivated_user_is_automatically_logged_out_on_internal_routes(): void
    {
        $this->seed(EssentialDesaSeeder::class);

        $user = User::factory()->create([
            'role' => UserRole::Operator,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/panel/dashboard')->assertOk();

        // Nonaktifkan akun saat sesi sedang berjalan
        $user->update(['is_active' => false]);

        $response = $this->actingAs($user->fresh())->get('/panel/dashboard');

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHas('notification.type', 'error');
    }

    public function test_kepala_desa_is_forbidden_from_write_form_and_settings_routes(): void
    {
        $this->seed(EssentialDesaSeeder::class);

        $kades = User::factory()->kepalaDesa()->create();
        $operator = User::factory()->create(['role' => UserRole::Operator]);

        // Kepala Desa diizinkan membuka dashboard baca
        $this->actingAs($kades)->get('/panel/dashboard')->assertOk();

        // Kepala Desa ditolak 403 Forbidden (merender ErrorPage via Inertia) pada pengaturan, form tambah, dan tulis/hapus
        $this->actingAs($kades)
            ->get('/panel/pengaturan/pengguna')
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page
                ->component('ErrorPage')
                ->where('status', 403)
            );

        $this->actingAs($kades)->get('/panel/_test/proyek/tambah')->assertStatus(403);
        $this->actingAs($kades)->post('/panel/_test/proyek', [])->assertStatus(403);
        $this->actingAs($kades)->delete('/panel/_test/proyek/1')->assertStatus(403);

        // Operator diizinkan penuh
        $this->actingAs($operator)->get('/panel/pengaturan/pengguna')->assertOk();
        $this->actingAs($operator)->get('/panel/_test/proyek/tambah')->assertOk();
        $this->actingAs($operator)->post('/panel/_test/proyek', [])->assertOk();
        $this->actingAs($operator)->delete('/panel/_test/proyek/1')->assertOk();
    }

    public function test_kepala_desa_can_change_session_filter_tahun_without_mutating_profil_desa(): void
    {
        $this->seed(EssentialDesaSeeder::class);

        $profilBefore = ProfilDesa::singleton();
        $tahunAktifPublik = $profilBefore->tahun_anggaran_aktif;
        $tahunSesi = $tahunAktifPublik - 1;

        $kades = User::factory()->kepalaDesa()->create();

        $response = $this->actingAs($kades)
            ->from('/panel/dashboard')
            ->post('/panel/filter-tahun', [
                'tahun' => $tahunSesi,
            ]);

        $response->assertRedirect('/panel/dashboard');
        $response->assertSessionHas('filter_tahun', $tahunSesi);

        // Pastikan profil_desa.tahun_anggaran_aktif publik sama sekali tidak berubah
        $this->assertSame($tahunAktifPublik, $profilBefore->fresh()->tahun_anggaran_aktif);
    }

    public function test_six_special_gates_follow_prd_2_6_matrix(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator, 'is_active' => true]);
        $kades = User::factory()->kepalaDesa()->create(['is_active' => true]);
        $inactiveOp = User::factory()->inactive()->create(['role' => UserRole::Operator]);

        $operatorOnlyGates = [
            'manage-master-penduduk',
            'issue-official-letter',
            'manage-budget-and-projects',
            'manage-village-settings',
            'execute-system-backup',
        ];

        foreach ($operatorOnlyGates as $gate) {
            $this->assertTrue(Gate::forUser($operator)->allows($gate), "Operator wajib diizinkan pada {$gate}");
            $this->assertFalse(Gate::forUser($kades)->allows($gate), "Kepala Desa wajib ditolak pada {$gate}");
            $this->assertFalse(Gate::forUser($inactiveOp)->allows($gate), "Akun nonaktif wajib ditolak pada {$gate}");
        }

        // print-official-reports diizinkan bagi Operator maupun Kepala Desa aktif
        $this->assertTrue(Gate::forUser($operator)->allows('print-official-reports'));
        $this->assertTrue(Gate::forUser($kades)->allows('print-official-reports'));
        $this->assertFalse(Gate::forUser($inactiveOp)->allows('print-official-reports'));
    }

    public function test_handle_inertia_requests_shares_auth_role_profil_antrean_and_filter_tahun(): void
    {
        $this->seed(EssentialDesaSeeder::class);

        SuratKeluar::factory()->count(2)->create([
            'status' => StatusSuratKeluar::MenungguVerifikasi,
        ]);

        $kades = User::factory()->kepalaDesa()->create([
            'name' => 'H. Ahmad Darwis',
        ]);

        $this->actingAs($kades)
            ->withSession(['filter_tahun' => 2025])
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Panel/Dashboard')
                ->where('auth.user.name', 'H. Ahmad Darwis')
                ->where('auth.user.role', 'kepala_desa')
                ->where('profil_desa.nama_desa', 'Tamarenja')
                ->where('antrean_surat_count', 2)
                ->where('filter_tahun', 2025)
            );
    }
}
