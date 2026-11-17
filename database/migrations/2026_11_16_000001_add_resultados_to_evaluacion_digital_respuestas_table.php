<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluacion_digital_respuestas', function (Blueprint $table) {
            $table->decimal('puntos_obtenidos', 4, 2)->nullable();
            $table->unsignedTinyInteger('valoracion_docente')->nullable();
            $table->foreignId('revisada_por')
                ->nullable()
                ->constrained('docentes')
                ->nullOnDelete();
            $table->timestamp('revisada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('evaluacion_digital_respuestas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revisada_por');
            $table->dropColumn([
                'puntos_obtenidos',
                'valoracion_docente',
                'revisada_at',
            ]);
        });
    }
};
