<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluacion_digital_intentos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('evaluacion_digital_id')
                ->constrained('evaluaciones_digitales')
                ->cascadeOnDelete();

            $table->foreignId('estudiante_id')
                ->constrained('estudiantes')
                ->cascadeOnDelete();

            $table->string('estado', 15)->default('enviada');
            $table->timestamp('enviada_at')->nullable();
            $table->unsignedSmallInteger('puntaje')->nullable();
            $table->timestamp('calificada_at')->nullable();

            $table->foreignId('calificada_por')
                ->nullable()
                ->constrained('docentes')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['evaluacion_digital_id', 'estudiante_id'],
                'eval_digital_intentos_unique'
            );

            $table->index(['estudiante_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_digital_intentos');
    }
};
