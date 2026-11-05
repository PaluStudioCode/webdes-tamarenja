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
        Schema::create('apbdes_rincian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apbdes_id')
                ->constrained('apbdes')
                ->cascadeOnDelete();
            $table->string('kelompok', 30);
            $table->string('kode_pos', 50);
            $table->string('nama_pos', 150);
            $table->boolean('is_otomatis_modul_1')->default(false);
            $table->unsignedBigInteger('pagu_anggaran')->default(0);
            $table->unsignedBigInteger('realisasi_tahap_1')->default(0);
            $table->unsignedBigInteger('realisasi_tahap_2')->default(0);
            $table->unsignedBigInteger('realisasi_tahap_3')->default(0);
            $table->unsignedBigInteger('total_realisasi')->default(0);
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['apbdes_id', 'kode_pos']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apbdes_rincian');
    }
};
