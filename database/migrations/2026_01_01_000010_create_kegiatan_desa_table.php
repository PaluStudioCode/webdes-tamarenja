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
        Schema::create('kegiatan_desa', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 200);
            $table->string('slug', 220)->unique();
            $table->string('kategori', 40);
            $table->date('tanggal_kegiatan');
            $table->string('lokasi_dusun', 150);
            $table->text('uraian');
            $table->string('status_publikasi', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status_publikasi', 'tanggal_kegiatan'], 'kegiatan_desa_status_tanggal_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kegiatan_desa');
    }
};
