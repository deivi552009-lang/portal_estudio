<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla para definir las columnas/actividades del grupo (Talleres, Tareas, Parciales)
        if (!Schema::hasTable('evaluaciones')) {
            Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->string('nombre'); // Ej: "Taller Factura", "Tarea Pseint", "Parcial #1"
            $table->decimal('porcentaje', 5, 2)->default(0); // Ej: 10%
            $table->integer('orden')->default(0);
            $table->timestamps();
            });
        }

        // Tabla para registrar la nota de cada alumno en cada evaluación
        if (!Schema::hasTable('calificaciones')) {
            Schema::create('calificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->decimal('nota', 3, 1)->default(0.0); // Ej: 4.5
            $table->timestamps();

            $table->unique(['evaluacion_id', 'estudiante_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calificaciones');
        Schema::dropIfExists('evaluaciones');
    }
};
