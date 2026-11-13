<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverCnhDocumentController extends Controller
{
    public function show(Request $request, Driver $driver): StreamedResponse|Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if (! $driver->cnh_document_path || ! Storage::disk('local')->exists($driver->cnh_document_path)) {
            abort(404, 'Documento não encontrado.');
        }

        return Storage::disk('local')->response($driver->cnh_document_path);
    }
}
