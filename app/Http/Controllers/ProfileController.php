<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Tampilkan halaman pengaturan akun pribadi (/panel/akun).
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Akun/Edit', [
            'user' => $request->user()->only(['id', 'name', 'email', 'role']),
        ]);
    }

    /**
     * Perbarui nama dan email akun milik pengguna yang sedang login sendiri (auth()->id() === $user->id).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ((int) auth()->id() !== (int) $user->id) {
            abort(403, 'Anda hanya dapat memperbarui profil akun Anda sendiri.');
        }

        $validated = $request->validated();
        $user->name = $validated['name'];

        if (array_key_exists('email', $validated) && $validated['email'] !== null) {
            if ($user->email !== $validated['email']) {
                $user->email_verified_at = null;
            }
            $user->email = $validated['email'];
        }

        $user->save();

        return Redirect::route('panel.akun.edit')->with('notification', [
            'type' => 'success',
            'message' => 'Informasi profil akun berhasil diperbarui.',
        ]);
    }
}
