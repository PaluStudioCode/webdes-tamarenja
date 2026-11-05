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
        Schema::create('apbdes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('jenis_dokumen', 20)->default('murni');
            $table->boolean('is_aktif_publik')->default(true);
            $table->boolean('is_terkunci')->default(false);
            $table->string('nomor_perdes', 100)->nullable();
            $table->date('tanggal_penetapan')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tahun_anggaran', 'jenis_dokumen']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apbdes');
    }
};
