<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si la tabla se llama 'asistencia' (en singular)
        if (Schema::hasTable('asistencia')) {
            DB::statement("
                ALTER TABLE asistencia
                ALTER COLUMN presente TYPE VARCHAR(20)
                USING CASE
                    WHEN presente::text = 'true' THEN 'presente'
                    WHEN presente::text = 'false' THEN 'ausente'
                    ELSE 'presente'
                END
            ");
        }
        // Si se llama 'asistencias' (en plural)
        elseif (Schema::hasTable('asistencias')) {
            DB::statement("
                ALTER TABLE asistencias
                ALTER COLUMN presente TYPE VARCHAR(20)
                USING CASE
                    WHEN presente::text = 'true' THEN 'presente'
                    WHEN presente::text = 'false' THEN 'ausente'
                    ELSE 'presente'
                END
            ");
        }
    }

    public function down(): void
    {
        // No es necesario revertir
    }
};
