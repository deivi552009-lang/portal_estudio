
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calificaciones', function (Blueprint $table) {
            $table->text('comentario')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('calificaciones', function (Blueprint $table) {
            $table->dropColumn('comentario');
        });
    }
};
