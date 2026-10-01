<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_academicos', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('semestre');

            $table->unsignedSmallInteger('anio');

            $table->boolean('activo')->default(false);

            $table->timestamps();

            $table->unique(['semestre', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_academicos');
    }
};
