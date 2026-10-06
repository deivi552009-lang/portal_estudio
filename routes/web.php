<?php

use App\Http\Controllers\ActividadArchivoController;
use App\Http\Controllers\ProfileController;
use App\Livewire\Docente\Dashboard;
use App\Livewire\Docente\GrupoAsistencia;
use App\Livewire\Docente\GrupoEstudiantes;
use App\Livewire\Docente\GrupoNotas;
use App\Livewire\Estudiante\Dashboard as EstudianteDashboard;
use App\Livewire\Estudiante\Notas;
use App\Livewire\Estudiante\Talleres;
use App\Livewire\Docente\HistorialAsistencia;
use App\Livewire\Docente\GrupoActividades;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('inicio');
});

Route::get('/dashboard', function () {
    $rol = strtolower(auth()->user()->role?->nombre ?? '');

    if ($rol === 'estudiante') {
        return redirect()->route('estudiante.dashboard');
    }

    if ($rol === 'docente') {
        return redirect()->route('docente.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


// ============================================================
// PERFIL DEL USUARIO
// ============================================================

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

});


// ============================================================
// RUTAS DEL DOCENTE
// ============================================================

Route::middleware(['auth', 'role:docente'])->group(function () {

    // Dashboard del docente
    Route::get('/docente/dashboard', Dashboard::class)
        ->name('docente.dashboard');

    // Gestión de grupos y estudiantes
    Route::get('/docente/grupos', GrupoEstudiantes::class)
        ->name('docente.grupos');

    // Notas de un grupo
    Route::get('/docente/grupo/{grupoId}/notas', GrupoNotas::class)
        ->name('docente.grupo.notas');

    // Registro de asistencia
    Route::get('/docente/grupo/{grupoId}/asistencia', GrupoAsistencia::class)
        ->name('docente.grupo.asistencia');

    // Historial de asistencia
    Route::get(
        '/docente/grupo/{grupoId}/asistencia/historial',
        HistorialAsistencia::class
    )->name('docente.grupo.asistencia.historial');

    // Actividades
    Route::get('/docente/actividades', GrupoActividades::class)
        ->name('docente.actividades');

    // Visualización inline de archivos adjuntos
    Route::get(
        '/docente/actividad/{actividad}/archivo',
        ActividadArchivoController::class
    )->name('docente.actividad.archivo');

    Route::get('/docente/grupo/{grupoId}/actividades', GrupoActividades::class)
        ->name('docente.grupo.actividades');

});

// ============================================================
// RUTAS DEL ESTUDIANTE
// ============================================================

Route::middleware(['auth', 'role:estudiante'])->group(function () {

    Route::get('/estudiante/dashboard', EstudianteDashboard::class)
        ->name('estudiante.dashboard');

    Route::get('/estudiante/notas', Notas::class)
        ->name('estudiante.notas');

    Route::get('/estudiante/talleres', Talleres::class)
        ->name('estudiante.talleres');
});


require __DIR__.'/auth.php';
