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
        Schema::create('surat_keluar', function (Blueprint $table) {
            $table->id();
            $table->char('qr_token', 36)->nullable()->unique();
            $table->foreignId('penduduk_id')
                ->constrained('penduduk')
                ->restrictOnDelete();
            $table->string('sumber_pengajuan', 20)->default('loket');
            $table->string('jenis_surat', 50);
            $table->string('kode_klasifikasi', 20);
            $table->unsignedSmallInteger('tahun_surat')->nullable();
            $table->unsignedInteger('nomor_urut')->nullable();
            $table->string('nomor_surat', 100)->nullable()->unique();
            $table->date('tanggal_surat')->nullable();
            $table->string('keperluan', 255);
            $table->json('data_khusus');
            $table->string('no_wa_pemohon', 20)->nullable();
            $table->string('opsi_penandatangan', 30)->nullable();
            $table->string('nama_penandatangan', 150)->nullable();
            $table->string('jabatan_penandatangan', 150)->nullable();
            $table->string('nip_penandatangan', 50)->nullable();
            $table->string('status', 30)->default('terbit');
            $table->string('alasan_penolakan', 255)->nullable();
            $table->string('alasan_pembatalan', 255)->nullable();
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tahun_surat', 'nomor_urut']);
            $table->index(['status', 'created_at']);
            $table->index(['tahun_surat', 'tanggal_surat']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_keluar');
    }
};
