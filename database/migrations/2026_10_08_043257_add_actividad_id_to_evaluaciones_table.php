<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->foreignId('actividad_id')
                ->nullable()
                ->after('grupo_id')
                ->constrained('actividades')
                ->cascadeOnDelete();

            $table->unique(
                'actividad_id',
                'evaluaciones_actividad_id_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropUnique('evaluaciones_actividad_id_unique');
            $table->dropForeign(['actividad_id']);
            $table->dropColumn('actividad_id');
        });
    }
};
