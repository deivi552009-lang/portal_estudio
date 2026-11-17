<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_digitales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->cascadeOnDelete();

            /*
             * En borrador queda NULL. Al publicar se vincula a la
             * evaluación oficial (Parcial) o a la personalizada (Quiz).
             * RESTRICT evita que borrar la evaluación de notas elimine
             * en cascada la evaluación digital y sus intentos.
             */
            $table->foreignId('evaluacion_id')
                ->nullable()
                ->unique()
                ->constrained('evaluaciones')
                ->restrictOnDelete();

            $table->string('tipo', 10);
            $table->string('parcial', 2)->nullable();
            $table->string('titulo', 255);
            $table->string('modalidad', 15)->default('preguntas');
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('archivo')->nullable();
            $table->string('estado', 15)->default('borrador');
            $table->timestamp('publicada_at')->nullable();

            $table->timestamps();

            $table->index(['grupo_id', 'estado']);
            $table->index(['grupo_id', 'tipo']);
        });

        /*
         * Refuerzos exclusivos de PostgreSQL. SQLite no permite añadir
         * CHECK con ALTER TABLE; las mismas reglas se validan en la
         * aplicación y se prueban en la Etapa 2.
         */
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE evaluaciones_digitales
                 ADD CONSTRAINT eval_digital_tipo_parcial_check
                 CHECK (
                    (tipo = 'quiz' AND parcial IS NULL)
                    OR (tipo = 'parcial' AND parcial IN ('P1', 'P2', 'P3', 'P4'))
                 )"
            );

            DB::statement(
                "ALTER TABLE evaluaciones_digitales
                 ADD CONSTRAINT eval_digital_modalidad_check
                 CHECK (modalidad IN ('preguntas', 'archivo', 'mixta'))"
            );

            DB::statement(
                "ALTER TABLE evaluaciones_digitales
                 ADD CONSTRAINT eval_digital_estado_publicacion_check
                 CHECK (
                    (estado = 'borrador' AND evaluacion_id IS NULL AND publicada_at IS NULL)
                    OR (estado = 'publicada' AND evaluacion_id IS NOT NULL AND publicada_at IS NOT NULL)
                 )"
            );

            DB::statement(
                'ALTER TABLE evaluaciones_digitales
                 ADD CONSTRAINT eval_digital_horario_check
                 CHECK (hora_fin > hora_inicio)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_digitales');
    }
};
