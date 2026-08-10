<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            [
                'nombre' => 'Superadministrador',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Administrativo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Docente',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Estudiante',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
