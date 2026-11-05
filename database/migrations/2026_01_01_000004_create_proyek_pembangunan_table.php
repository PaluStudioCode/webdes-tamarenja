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
        Schema::create('proyek_pembangunan', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('nama_kegiatan', 200);
            $table->string('lokasi_dusun', 150);
            $table->unsignedBigInteger('pagu_anggaran');
            $table->unsignedBigInteger('realisasi_anggaran')->default(0);
            $table->string('sumber_dana', 30);
            $table->string('volume_fisik', 150)->nullable();
            $table->string('waktu_pelaksanaan', 100)->nullable();
            $table->string('pelaksana_kegiatan', 150)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedTinyInteger('progres_fisik')->default(0);
            $table->string('status_proyek', 30)->default('belum_dimulai');
            $table->string('foto_0_path', 255)->nullable();
            $table->string('foto_50_path', 255)->nullable();
            $table->string('foto_100_path', 255)->nullable();
            $table->text('catatan_kendala')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_anggaran', 'status_proyek']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyek_pembangunan');
    }
};
