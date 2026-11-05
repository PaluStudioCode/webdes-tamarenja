<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profil_desa', function (Blueprint $table) {
            $table->id();
            $table->string('nama_desa', 100);
            $table->string('kode_desa_surat', 30);
            $table->string('kecamatan', 100);
            $table->string('kabupaten', 100);
            $table->string('provinsi', 100);
            $table->string('kode_wilayah', 20)->nullable();
            $table->json('daftar_dusun')->nullable();
            $table->text('alamat_kantor');
            $table->string('jam_pelayanan', 150)->nullable();
            $table->string('email_desa', 150)->nullable();
            $table->string('no_wa_pelayanan', 20)->nullable();
            $table->string('logo_desa_path', 255)->nullable();
            $table->string('nama_kepala_desa', 150);
            $table->string('nip_kepala_desa', 50)->nullable();
            $table->string('foto_kepala_desa_path', 255)->nullable();
            $table->text('sambutan_kepala_desa')->nullable();
            $table->string('nama_sekdes', 150);
            $table->string('nip_sekdes', 50)->nullable();
            $table->text('visi')->nullable();
            $table->json('misi')->nullable();
            $table->text('sejarah_singkat')->nullable();
            $table->text('potensi_unggulan')->nullable();
            $table->unsignedSmallInteger('tahun_anggaran_aktif');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profil_desa');
    }
};
