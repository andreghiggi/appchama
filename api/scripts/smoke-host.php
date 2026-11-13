<?php
use Illuminate\Http\Request;
$kernel = app(\Illuminate\Contracts\Http\Kernel::class);
foreach (['https://adm.vaide55.com.br/admin/login' => 'Vaide55 Admin', 'https://appchama.agilizeerp.com.br/admin/login' => 'AppChama Admin', 'https://api.vaide55.com.br/up' => null] as $url => $esperado) {
    foreach (['currentTenantId','tenantFromHost'] as $k) app()->forgetInstance($k);
    $res = $kernel->handle(Request::create($url, 'GET'));
    $html = $res->getContent();
    echo $url.' -> '.$res->getStatusCode().($esperado ? ' | contem "'.$esperado.'": '.(str_contains($html, $esperado) ? 'SIM' : 'NAO') : '')."\n";
    if ($esperado && preg_match('/--primary-500:\s*([^;]+);/', $html, $m)) echo "   cor primaria: ".trim($m[1])."\n";
}
