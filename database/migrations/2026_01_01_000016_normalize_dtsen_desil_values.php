<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('kartu_keluarga')
            ->where('status_kesejahteraan', 'dtsen_desil_3_4')
            ->update(['status_kesejahteraan' => 'dtsen_desil_3']);

        DB::table('penduduk')
            ->where('status_kesejahteraan', 'dtsen_desil_3_4')
            ->update(['status_kesejahteraan' => 'dtsen_desil_3']);

        DB::table('penerima_bansos')
            ->where('kategori_ekonomi', 'dtsen_desil_3_4')
            ->update(['kategori_ekonomi' => 'dtsen_desil_3']);
    }

    public function down(): void
    {
        // Tidak perlu dikembalikan ke format gabungan lama
    }
};
