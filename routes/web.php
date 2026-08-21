<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('inicio');
});

// Rutas del Docente
Volt::route('/docente/grupo/{grupoId}/notas', 'docente.⚡grupo-notas');
Volt::route('/docente/grupo/{grupoId}/asistencia', 'docente.⚡grupo-asistencia');
