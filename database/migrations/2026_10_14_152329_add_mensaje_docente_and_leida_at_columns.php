
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->text('mensaje_docente')->nullable();
        });

        Schema::table('actividad_respuestas', function (Blueprint $table) {
            $table->timestamp('leida_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('actividad_respuestas', function (Blueprint $table) {
            $table->dropColumn('leida_at');
        });

        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn('mensaje_docente');
        });
    }
};
