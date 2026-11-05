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
        Schema::create('dokumen_ppid', function (Blueprint $table) {
            $table->id();
            $table->string('judul_dokumen', 200);
            $table->string('kategori', 40);
            $table->unsignedSmallInteger('tahun_dokumen')->nullable();
            $table->string('file_pdf_path', 255);
            $table->unsignedInteger('ukuran_file_kb')->default(0);
            $table->unsignedInteger('jumlah_unduhan')->default(0);
            $table->boolean('is_publik')->default(true);
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_ppid');
    }
};
