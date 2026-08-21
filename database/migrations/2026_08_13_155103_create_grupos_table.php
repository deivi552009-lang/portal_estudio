<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('materia_id')
                ->constrained('materias');

            $table->foreignId('docente_id')
                ->constrained('docentes');

            $table->unsignedTinyInteger('semestre');
            $table->unsignedSmallInteger('anio');

            $table->date('fecha_creacion');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
