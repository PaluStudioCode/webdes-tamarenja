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
        Schema::create('penduduk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kartu_keluarga_id')
                ->constrained('kartu_keluarga')
                ->restrictOnDelete();
            $table->char('nik', 16)->unique();
            $table->string('nama_lengkap', 150);
            $table->string('tempat_lahir', 100);
            $table->date('tanggal_lahir');
            $table->char('jenis_kelamin', 1);
            $table->string('agama', 30);
            $table->string('pendidikan', 50)->nullable();
            $table->string('pekerjaan', 100)->nullable();
            $table->string('status_perkawinan', 30);
            $table->string('hubungan_keluarga', 30);
            $table->string('status_kesejahteraan', 30)->default('non_prioritas');
            $table->string('status_kependudukan', 30)->default('aktif');
            $table->date('tanggal_mutasi')->nullable();
            $table->string('catatan_mutasi', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('nama_lengkap');
            $table->index('tanggal_lahir');
            $table->index(['nik', 'tanggal_lahir']);
            $table->index(['status_kependudukan', 'status_kesejahteraan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penduduk');
    }
};
