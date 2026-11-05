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
        Schema::create('surat_masuk', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_agenda');
            $table->unsignedInteger('nomor_urut_agenda');
            $table->string('nomor_surat_masuk', 100);
            $table->date('tanggal_surat')->nullable();
            $table->date('tanggal_terima');
            $table->string('pengirim', 150);
            $table->string('perihal', 255);
            $table->string('tujuan_disposisi', 150)->nullable();
            $table->text('catatan_disposisi')->nullable();
            $table->string('berkas_scan_path', 255)->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tahun_agenda', 'nomor_urut_agenda']);
            $table->index(['tahun_agenda', 'tanggal_terima']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_masuk');
    }
};
