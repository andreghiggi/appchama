<?php

use App\Http\Controllers\DriverCnhDocumentController;
use App\Http\Controllers\DriverEarningsReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Para onde o Asaas devolve o passageiro depois do cartão (o app atualiza sozinho ao voltar).
Route::get('/pagamento/retorno', function () {
    $ok = request('status') !== 'cancelado';

    return response(
        '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>Pagamento</title></head><body style="font-family:sans-serif;text-align:center;padding:48px 24px;background:#111;color:#fff">'
        .'<h1 style="color:#FCD200">'.($ok ? 'Pagamento recebido!' : 'Pagamento não concluído').'</h1>'
        .'<p>'.($ok ? 'Pode voltar ao aplicativo: a corrida será finalizada automaticamente.' : 'Volte ao aplicativo para tentar de novo ou escolher outra forma de pagamento.').'</p>'
        .'</body></html>'
    );
})->name('pagamento.retorno');

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
