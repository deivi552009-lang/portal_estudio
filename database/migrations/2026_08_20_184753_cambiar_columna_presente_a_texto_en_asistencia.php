<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Detecta la tabla sin importar si está en singular o plural
        $tabla = Schema::hasTable('asistencia') ? 'asistencia' : (Schema::hasTable('asistencias') ? 'asistencias' : null);

        if ($tabla) {
            DB::statement("
                ALTER TABLE {$tabla}
                ALTER COLUMN presente TYPE VARCHAR(20)
                USING CASE
                    WHEN presente::text = 'true' THEN 'presente'
                    WHEN presente::text = '1' THEN 'presente'
                    WHEN presente::text = 'false' THEN 'ausente'
                    WHEN presente::text = '0' THEN 'ausente'
                    ELSE 'presente'
                END
            ");
        }
    }

    public function down(): void
    {
        // No requiere revertir
    }
};
