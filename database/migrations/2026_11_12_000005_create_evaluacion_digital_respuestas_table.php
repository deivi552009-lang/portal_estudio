<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluacion_digital_respuestas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('intento_id')
                ->constrained('evaluacion_digital_intentos')
                ->cascadeOnDelete();

            $table->foreignId('pregunta_id')
                ->constrained('evaluacion_digital_preguntas')
                ->cascadeOnDelete();

            $table->foreignId('opcion_id')
                ->nullable()
                ->constrained('evaluacion_digital_opciones')
                ->nullOnDelete();

            $table->text('respuesta_texto')->nullable();
            $table->boolean('es_correcta')->nullable();

            $table->timestamps();

            $table->unique(
                ['intento_id', 'pregunta_id'],
                'eval_digital_respuestas_unique'
            );

            $table->index('pregunta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_digital_respuestas');
    }
};
