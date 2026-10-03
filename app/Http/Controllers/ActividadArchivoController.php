<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ActividadArchivoController extends Controller
{
    public function __invoke(Actividad $actividad)
    {
        $actividad->load('grupo.docente');

        abort_unless(
            $actividad->grupo?->docente?->user_id === Auth::id(),
            403
        );

        abort_unless(
            $actividad->archivo &&
            Storage::disk('public')->exists($actividad->archivo),
            404
        );

        $path = Storage::disk('public')->path($actividad->archivo);

        $mimeType = Storage::disk('public')
            ->mimeType($actividad->archivo)
            ?? 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}