<?php

use App\Http\Controllers\DriverCnhDocumentController;
use App\Http\Controllers\DriverEarningsReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/admin/drivers/{driver}/cnh-document', [DriverCnhDocumentController::class, 'show'])
        ->name('admin.drivers.cnh-document');
});

Route::middleware(['web', 'auth'])->get('/admin/tenant-filter/{slug}', function (string $slug) {
    abort_unless(auth()->user()?->is_platform_admin, 403);
    if ($slug === 'todas') {
        session()->forget('tenant_filter');
    } else {
        abort_unless(\App\Models\Tenant::query()->where('slug', $slug)->exists(), 404);
        session(['tenant_filter' => $slug]);
    }

    return redirect('/admin');
})->name('tenant.filter');

Route::middleware(['web', 'signed'])->group(function (): void {
    Route::get('/motorista/relatorio/{driver}', [DriverEarningsReportController::class, 'show'])
        ->name('motorista.relatorio');
});
