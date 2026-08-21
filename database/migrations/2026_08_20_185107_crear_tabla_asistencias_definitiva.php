<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si no existe 'asistencia', la creamos con la estructura correcta
        if (!Schema::hasTable('asistencia')) {
            Schema::create('asistencia', function (Blueprint $table) {
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
        Schema::dropIfExists('asistencia');
    }
};
