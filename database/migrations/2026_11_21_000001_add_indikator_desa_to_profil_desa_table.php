<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profil_desa', 'indikator_desa')) {
            Schema::table('profil_desa', function (Blueprint $table) {
                $table->json('indikator_desa')->nullable()->after('potensi_unggulan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profil_desa', 'indikator_desa')) {
            Schema::table('profil_desa', function (Blueprint $table) {
                $table->dropColumn('indikator_desa');
            });
        }
    }
};
