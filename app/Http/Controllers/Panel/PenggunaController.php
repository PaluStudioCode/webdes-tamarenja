<?php

namespace App\Http\Controllers\Panel;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PenggunaController extends Controller
{
    /**
     * Tampilkan daftar akun pengguna internal (/panel/pengaturan/pengguna).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('manage-village-settings');

        $search = trim((string) $request->query('search', ''));
        $roleFilter = trim((string) $request->query('role', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($roleFilter, ['operator', 'kepala_desa'], true), function ($query) use ($roleFilter) {
                $query->where('role', $roleFilter);
            })
            ->orderByDesc('is_active')
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role instanceof UserRole ? $u->role->value : (string) $u->role,
                'role_label' => $u->role instanceof UserRole ? $u->role->label() : (string) $u->role,
                'is_active' => (bool) $u->is_active,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'created_at' => $u->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Pengaturan/Pengguna', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
            ],
            'activeOperatorCount' => User::query()
                ->where('role', UserRole::Operator)
                ->where('is_active', true)
                ->count(),
        ]);
    }

    /**
     * Tambah akun pengguna internal baru.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:150', Rule::unique(User::class, 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', Password::min(8), 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Nama lengkap perangkat desa wajib diisi.',
            'email.required' => 'Alamat email kedinasan wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email ini sudah terdaftar.',
            'role.required' => 'Peran pengguna wajib dipilih.',
            'password.required' => 'Kata sandi awal wajib diisi.',
            'password.min' => 'Kata sandi minimal terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ]);

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Akun pengguna {$validated['name']} berhasil ditambahkan.",
        ]);
    }

    /**
     * Perbarui data akun internal, peran, status aktif, atau setel ulang kata sandi.
     */
    public function update(Request $request, User $pengguna): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:150',
                Rule::unique(User::class, 'email')->ignore($pengguna->id),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', Password::min(8), 'confirmed'],
        ], [
            'name.required' => 'Nama lengkap perangkat desa wajib diisi.',
            'email.required' => 'Alamat email kedinasan wajib diisi.',
            'email.unique' => 'Alamat email ini sudah digunakan oleh akun lain.',
            'role.required' => 'Peran pengguna wajib dipilih.',
            'password.min' => 'Kata sandi baru minimal terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $authUser = $request->user();
        $nextRole = UserRole::from($validated['role']);
        $nextActive = (bool) $validated['is_active'];

        // Proteksi anti self-lockout: dilarang menonaktifkan atau menurunkan role akun sendiri
        if ((int) $authUser->id === (int) $pengguna->id) {
            if (! $nextActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'Anda tidak dapat menonaktifkan akun yang sedang Anda gunakan sendiri.',
                ]);
            }

            if ($pengguna->isOperator() && $nextRole !== UserRole::Operator) {
                throw ValidationException::withMessages([
                    'role' => 'Anda tidak dapat mengubah peran akun Operator Anda sendiri.',
                ]);
            }
        }

        // Pastikan selalu tersisa minimal 1 akun Operator Desa aktif
        $wasActiveOperator = $pengguna->isOperator() && $pengguna->is_active;
        $willRemainActiveOperator = $nextRole === UserRole::Operator && $nextActive;

        if ($wasActiveOperator && ! $willRemainActiveOperator) {
            $this->ensureAnotherActiveOperatorExists($pengguna);
        }

        $pengguna->name = $validated['name'];
        $pengguna->email = $validated['email'];
        $pengguna->role = $nextRole;
        $pengguna->is_active = $nextActive;

        if (! empty($validated['password'])) {
            $pengguna->password = Hash::make($validated['password']);
        }

        $pengguna->save();

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Data akun {$pengguna->name} berhasil diperbarui.",
        ]);
    }

    /**
     * Aktifkan atau nonaktifkan status akun pengguna internal.
     */
    public function toggleStatus(Request $request, User $pengguna): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $authUser = $request->user();
        $nextActive = ! $pengguna->is_active;

        if ((int) $authUser->id === (int) $pengguna->id && ! $nextActive) {
            return back()->with('notification', [
                'type' => 'error',
                'message' => 'Anda tidak dapat menonaktifkan akun yang sedang Anda gunakan sendiri.',
            ]);
        }

        if ($pengguna->isOperator() && $pengguna->is_active && ! $nextActive) {
            if (! $this->hasOtherActiveOperator($pengguna)) {
                return back()->with('notification', [
                    'type' => 'error',
                    'message' => 'Sistem wajib menyisakan minimal 1 akun Operator Desa yang aktif.',
                ]);
            }
        }

        $pengguna->update(['is_active' => $nextActive]);

        $statusText = $nextActive ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Akun {$pengguna->name} berhasil {$statusText}.",
        ]);
    }

    /**
     * Hapus akun pengguna internal dengan proteksi anti self-lockout & minimal 1 operator aktif.
     */
    public function destroy(Request $request, User $pengguna): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $authUser = $request->user();

        if ((int) $authUser->id === (int) $pengguna->id) {
            return back()->with('notification', [
                'type' => 'error',
                'message' => 'Anda tidak dapat menghapus akun yang sedang Anda gunakan sendiri.',
            ]);
        }

        if ($pengguna->isOperator() && $pengguna->is_active && ! $this->hasOtherActiveOperator($pengguna)) {
            return back()->with('notification', [
                'type' => 'error',
                'message' => 'Tidak dapat menghapus satu-satunya akun Operator Desa yang masih aktif.',
            ]);
        }

        try {
            $nama = $pengguna->name;
            $pengguna->delete();

            return back()->with('notification', [
                'type' => 'success',
                'message' => "Akun {$nama} berhasil dihapus.",
            ]);
        } catch (QueryException) {
            return back()->with('notification', [
                'type' => 'error',
                'message' => 'Akun ini sudah memiliki riwayat data operasional desa. Silakan nonaktifkan status akun tanpa menghapusnya.',
            ]);
        }
    }

    private function hasOtherActiveOperator(User $target): bool
    {
        return User::query()
            ->where('role', UserRole::Operator)
            ->where('is_active', true)
            ->where('id', '!=', $target->id)
            ->exists();
    }

    private function ensureAnotherActiveOperatorExists(User $target): void
    {
        if (! $this->hasOtherActiveOperator($target)) {
            throw ValidationException::withMessages([
                'role' => 'Sistem wajib menyisakan minimal 1 akun Operator Desa yang aktif.',
            ]);
        }
    }
}
