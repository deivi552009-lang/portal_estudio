<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('evaluaciones')) {
            Schema::create('evaluaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
                $table->string('nombre', 50);
                $table->decimal('porcentaje', 5, 2)->default(0);
                $table->integer('orden')->default(0);
                $table->string('tipo', 50)->default('taller');
                $table->timestamps();
            });
        } elseif (!Schema::hasColumn('evaluaciones', 'orden')) {
            Schema::table('evaluaciones', function (Blueprint $table) {
                $table->integer('orden')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};
