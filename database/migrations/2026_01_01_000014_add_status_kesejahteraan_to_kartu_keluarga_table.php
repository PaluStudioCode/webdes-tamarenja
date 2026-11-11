<?php

use App\Enums\StatusKesejahteraan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kartu_keluarga', function (Blueprint $table) {
            $table->string('status_kesejahteraan', 25)
                ->default(StatusKesejahteraan::NonPrioritas->value)
                ->after('alamat');
            $table->index('status_kesejahteraan');
        });

        // Backfill otomatis: ambil status_kesejahteraan dari Kepala Keluarga (atau anggota pertama)
        // pada masing-masing KK, lalu seragamkan seluruh anggota di dalam KK yang sama.
        $kkRows = DB::table('kartu_keluarga')->select('id')->get();

        foreach ($kkRows as $kk) {
            $memberStatus = DB::table('penduduk')
                ->where('kartu_keluarga_id', $kk->id)
                ->orderByRaw("CASE WHEN hubungan_keluarga = 'kepala_keluarga' THEN 0 ELSE 1 END ASC")
                ->orderBy('id')
                ->value('status_kesejahteraan');

            $resolvedStatus = is_string($memberStatus) && $memberStatus !== ''
                ? $memberStatus
                : StatusKesejahteraan::NonPrioritas->value;

            DB::table('kartu_keluarga')
                ->where('id', $kk->id)
                ->update(['status_kesejahteraan' => $resolvedStatus]);

            DB::table('penduduk')
                ->where('kartu_keluarga_id', $kk->id)
                ->update(['status_kesejahteraan' => $resolvedStatus]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kartu_keluarga', function (Blueprint $table) {
            $table->dropIndex(['status_kesejahteraan']);
            $table->dropColumn('status_kesejahteraan');
        });
    }
};
