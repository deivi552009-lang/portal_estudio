<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluacion_digital_preguntas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('evaluacion_digital_id')
                ->constrained('evaluaciones_digitales')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('orden');
            $table->text('enunciado');
            $table->string('tipo', 20);

            $table->timestamps();

            $table->unique(
                ['evaluacion_digital_id', 'orden'],
                'eval_digital_preguntas_orden_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_digital_preguntas');
    }
};
