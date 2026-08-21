<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->cascadeOnDelete();

            $table->string('nombre');

            $table->enum('tipo', [
                'parcial',
                'asistencia_final',
                'personalizada'
            ]);

            $table->decimal('porcentaje', 5, 2)->nullable();

            $table->date('fecha')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};
