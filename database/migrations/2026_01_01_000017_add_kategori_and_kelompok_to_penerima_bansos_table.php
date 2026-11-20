<?php

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
        Schema::table('penerima_bansos', function (Blueprint $table) {
            if (! Schema::hasColumn('penerima_bansos', 'kategori_bantuan')) {
                $table->string('kategori_bantuan', 30)
                    ->default('per_kk')
                    ->after('jenis_bantuan');
            }

            if (! Schema::hasColumn('penerima_bansos', 'nama_kelompok')) {
                $table->string('nama_kelompok', 150)
                    ->nullable()
                    ->after('kategori_bantuan');
            }
        });

        DB::table('penerima_bansos')
            ->whereIn('jenis_bantuan', [
                'pip',
                'pbi_jkn',
                'atensi',
                'bantuan_lansia',
                'bantuan_disabilitas',
                'bantuan_korban_bencana',
                'beasiswa_daerah',
            ])
            ->update(['kategori_bantuan' => 'perorangan']);

        DB::table('penerima_bansos')
            ->whereIn('jenis_bantuan', [
                'bantuan_kelompok_tani',
                'bantuan_umkm',
                'bantuan_sarana_pertanian',
            ])
            ->update(['kategori_bantuan' => 'kelompok']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penerima_bansos', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('penerima_bansos', 'kategori_bantuan')) {
                $cols[] = 'kategori_bantuan';
            }
            if (Schema::hasColumn('penerima_bansos', 'nama_kelompok')) {
                $cols[] = 'nama_kelompok';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
