<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_respuestas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('actividad_id')
                ->constrained('actividades')
                ->cascadeOnDelete();

            $table->foreignId('estudiante_id')
                ->constrained('estudiantes')
                ->cascadeOnDelete();

            $table->string('asunto')->nullable();
            $table->text('respuesta')->nullable();
            $table->string('archivo')->nullable();

            $table->timestamps();

            $table->unique(
                ['actividad_id', 'estudiante_id'],
                'actividad_respuestas_actividad_estudiante_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_respuestas');
    }
};
