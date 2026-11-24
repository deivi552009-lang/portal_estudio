<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluacion_digital_monitoreo_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('evaluacion_digital_id')
                ->constrained('evaluaciones_digitales')
                ->cascadeOnDelete();

            $table->foreignId('intento_id')
                ->nullable()
                ->constrained('evaluacion_digital_intentos')
                ->cascadeOnDelete();

            $table->foreignId('estudiante_id')
                ->constrained('estudiantes')
                ->cascadeOnDelete();

            $table->string('tipo_evento', 40);
            $table->boolean('es_alerta')->default(false);
            $table->string('descripcion', 500);

            $table->foreignId('pregunta_id')
                ->nullable()
                ->constrained('evaluacion_digital_preguntas')
                ->nullOnDelete();

            $table->foreignId('opcion_id')
                ->nullable()
                ->constrained('evaluacion_digital_opciones')
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['evaluacion_digital_id', 'estudiante_id', 'created_at'],
                'eval_dig_mon_eval_est_created_idx'
            );

            $table->index(
                ['intento_id', 'created_at'],
                'eval_dig_mon_intento_created_idx'
            );

            $table->index(
                ['intento_id', 'es_alerta'],
                'eval_dig_mon_intento_alerta_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_digital_monitoreo_logs');
    }
};
