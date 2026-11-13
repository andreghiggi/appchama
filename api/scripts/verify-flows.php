<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$checks = [];

$checks['routes_driver_register'] = (bool) Illuminate\Support\Facades\Route::has('generated::QaobrghKWWzAHoEY')
    || collect(Illuminate\Support\Facades\Route::getRoutes())->contains(fn ($r) => str_contains($r->uri(), 'auth/driver/register'));

$routes = collect(Illuminate\Support\Facades\Route::getRoutes())->map(fn ($r) => $r->uri());
$checks['route_driver_login'] = $routes->contains(fn ($u) => str_ends_with($u, 'auth/driver/login'));
$checks['route_driver_register'] = $routes->contains(fn ($u) => str_ends_with($u, 'auth/driver/register'));
$checks['route_payment'] = $routes->contains(fn ($u) => str_contains($u, 'rides/{ride}/payment'));
$checks['route_webhook'] = $routes->contains(fn ($u) => str_contains($u, 'webhooks/asaas'));
$checks['route_wallet'] = $routes->contains(fn ($u) => str_contains($u, 'drivers/wallet'));

$checks['migration_registration_source'] = Illuminate\Support\Facades\Schema::hasColumn('drivers', 'registration_source');
$checks['migration_cnh_document'] = Illuminate\Support\Facades\Schema::hasColumn('drivers', 'cnh_document_path');

$adminDriversWithoutCode = App\Models\Driver::query()
    ->where('registration_source', '!=', 'app')
    ->whereNull('registration_code')
    ->count();
$checks['admin_drivers_have_code'] = $adminDriversWithoutCode === 0;

foreach ($checks as $name => $ok) {
    echo ($ok ? '[OK]' : '[FAIL]').' '.$name.PHP_EOL;
}

exit(collect($checks)->every(fn ($v) => $v) ? 0 : 1);
