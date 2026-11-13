<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kartu_keluarga', function (Blueprint $table) {
            $table->string('status_kepemilikan_rumah', 50)->nullable()->after('status_kesejahteraan');
            $table->string('kondisi_rumah', 50)->nullable()->after('status_kepemilikan_rumah');
            $table->string('jenis_listrik', 50)->nullable()->after('kondisi_rumah');
            $table->string('kamar_mandi', 30)->nullable()->after('jenis_listrik');
            $table->string('penghasilan_bulanan', 100)->nullable()->after('kamar_mandi');
            $table->string('kepemilikan_usaha', 150)->nullable()->after('penghasilan_bulanan');
            $table->string('kepemilikan_hewan_ternak', 150)->nullable()->after('kepemilikan_usaha');
            $table->string('kepemilikan_kendaraan_elektronik', 200)->nullable()->after('kepemilikan_hewan_ternak');
            $table->string('kepemilikan_shml', 50)->nullable()->after('kepemilikan_kendaraan_elektronik');
            $table->string('kepemilikan_tanah_bidang', 50)->nullable()->after('kepemilikan_shml');
            $table->string('kepemilikan_tanah_sawah', 50)->nullable()->after('kepemilikan_tanah_bidang');
            $table->text('tanaman')->nullable()->after('kepemilikan_tanah_sawah');
        });

        Schema::table('penduduk', function (Blueprint $table) {
            $table->string('suku', 50)->nullable()->after('agama');
            $table->string('kewarganegaraan', 10)->default('WNI')->after('suku');
            $table->string('peserta_kip_pip', 50)->nullable()->after('pendidikan');
            $table->string('kelas_sekolah', 50)->nullable()->after('peserta_kip_pip');
            $table->string('jenis_bpjs', 50)->nullable()->after('status_kesejahteraan');
        });
    }

    public function down(): void
    {
        Schema::table('penduduk', function (Blueprint $table) {
            $table->dropColumn([
                'suku',
                'kewarganegaraan',
                'peserta_kip_pip',
                'kelas_sekolah',
                'jenis_bpjs',
            ]);
        });

        Schema::table('kartu_keluarga', function (Blueprint $table) {
            $table->dropColumn([
                'status_kepemilikan_rumah',
                'kondisi_rumah',
                'jenis_listrik',
                'kamar_mandi',
                'penghasilan_bulanan',
                'kepemilikan_usaha',
                'kepemilikan_hewan_ternak',
                'kepemilikan_kendaraan_elektronik',
                'kepemilikan_shml',
                'kepemilikan_tanah_bidang',
                'kepemilikan_tanah_sawah',
                'tanaman',
            ]);
        });
    }
};
