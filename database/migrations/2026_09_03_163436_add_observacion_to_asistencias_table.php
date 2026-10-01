<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asistencias') && !Schema::hasColumn('asistencias', 'observacion')) {
            Schema::table('asistencias', function (Blueprint $table) {
                $table->text('observacion')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('asistencias') && Schema::hasColumn('asistencias', 'observacion')) {
            Schema::table('asistencias', function (Blueprint $table) {
                $table->dropColumn('observacion');
            });
        }
    }
};
