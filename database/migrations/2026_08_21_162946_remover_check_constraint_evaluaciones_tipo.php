<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remueve la restricción de validación de texto en PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE evaluaciones DROP CONSTRAINT IF EXISTS evaluaciones_tipo_check');
        }
    }

    public function down(): void
    {
        // En caso de rollback no reinstauramos el constraint
    }
};
