<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FilterTahunController extends Controller
{
    /**
     * Perbarui filter Tahun Anggaran khusus sesi pengguna yang sedang login
     * tanpa mengubah profil_desa.tahun_anggaran_aktif publik.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tahun' => ['required', 'integer', 'between:2020,2100'],
        ], [
            'tahun.required' => 'Tahun anggaran wajib dipilih.',
            'tahun.integer' => 'Tahun anggaran harus berupa angka bulat.',
            'tahun.between' => 'Tahun anggaran harus berada di antara 2020 hingga 2100.',
        ]);

        $tahun = (int) $validated['tahun'];
        $request->session()->put('filter_tahun', $tahun);

        return back()->with('notification', [
            'type' => 'info',
            'message' => "Filter tampilan diubah ke Tahun Anggaran {$tahun}.",
        ]);
    }
}
