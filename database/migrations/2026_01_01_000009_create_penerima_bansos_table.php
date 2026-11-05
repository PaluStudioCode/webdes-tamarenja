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
        Schema::create('penerima_bansos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penduduk_id')
                ->constrained('penduduk')
                ->restrictOnDelete();
            $table->string('jenis_bantuan', 40);
            $table->string('bentuk_nominal', 150);
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('tahap_penyaluran', 50);
            $table->string('kategori_ekonomi', 30)->default('non_prioritas');
            $table->string('status_kepesertaan', 30)->default('aktif');
            $table->string('keterangan', 255)->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['penduduk_id', 'jenis_bantuan', 'tahun_anggaran', 'tahap_penyaluran'],
                'penerima_bansos_unq_komposit'
            );
            $table->index(
                ['tahun_anggaran', 'jenis_bantuan', 'status_kepesertaan'],
                'penerima_bansos_tahun_jenis_status_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penerima_bansos');
    }
};
