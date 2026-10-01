<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El modelo Asistencia usa el nombre plural por convención de Laravel.
        if (!Schema::hasTable('asistencias') && !Schema::hasTable('asistencia')) {
            Schema::create('asistencias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
                $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
                $table->date('fecha');
                $table->string('presente', 20)->default('presente'); // 'presente', 'ausente', 'excusa'
                $table->timestamps();

                $table->unique(['grupo_id', 'estudiante_id', 'fecha']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
