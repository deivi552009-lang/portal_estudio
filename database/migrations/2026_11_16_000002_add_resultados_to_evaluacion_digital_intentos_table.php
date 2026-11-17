<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluacion_digital_intentos', function (Blueprint $table) {
            $table->decimal('puntaje_total', 6, 2)->nullable();
            $table->decimal('nota', 4, 2)->nullable();
            $table->timestamp('corregida_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('evaluacion_digital_intentos', function (Blueprint $table) {
            $table->dropColumn([
                'puntaje_total',
                'nota',
                'corregida_at',
            ]);
        });
    }
};
