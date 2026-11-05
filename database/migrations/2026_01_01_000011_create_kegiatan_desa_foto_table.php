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
        Schema::create('kegiatan_desa_foto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_desa_id')
                ->constrained('kegiatan_desa')
                ->cascadeOnDelete();
            $table->string('foto_path', 255);
            $table->unsignedTinyInteger('urutan')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kegiatan_desa_foto');
    }
};
