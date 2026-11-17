<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluacion_digital_opciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pregunta_id')
                ->constrained('evaluacion_digital_preguntas')
                ->cascadeOnDelete();

            $table->string('texto', 500);
            $table->boolean('es_correcta')->default(false);
            $table->unsignedSmallInteger('orden');

            $table->timestamps();

            $table->unique(
                ['pregunta_id', 'orden'],
                'eval_digital_opciones_orden_unique'
            );
        });

        /*
         * Una sola opción correcta por pregunta. El índice parcial
         * funciona en PostgreSQL y SQLite; MySQL no lo admite, pero el
         * proyecto usa PostgreSQL y SQLite para pruebas.
         */
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX eval_digital_opciones_correcta_unique
                 ON evaluacion_digital_opciones (pregunta_id)
                 WHERE es_correcta'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_digital_opciones');
    }
};
