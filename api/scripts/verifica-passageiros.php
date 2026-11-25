<?php
// Confere o recurso Passageiros do painel: so passageiros, escopo por marca, somente leitura.
// Rodar: php artisan tinker --execute="require 'scripts/verifica-passageiros.php';"
use App\Filament\Resources\Passengers\PassengerResource;
use App\Filament\Resources\Passengers\Tables\PassengersTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$ok = 0;
$falhas = [];
$check = function (string $n, bool $c) use (&$ok, &$falhas) {
    if ($c) {
        $ok++;
        echo "  ok  $n\n";
    } else {
        $falhas[] = $n;
        echo "  FALHOU  $n\n";
    }
};

DB::beginTransaction();
try {
    $ta = Tenant::first();
    $tb = Tenant::where('id', '!=', $ta->id)->first();
    $mk = fn ($t, $role, $tel, $extra = []) => User::create(array_merge(['tenant_id' => $t->id, 'name' => 'ZT '.$role, 'phone' => $tel, 'role' => $role, 'status' => 'active'], $extra));
    $pa = $mk($ta, 'passenger', '5551900009991');
    $pb = $mk($tb, 'passenger', '5551900009992');
    $dr = $mk($ta, 'driver', '5551900009993');
    $super = $mk($ta, 'admin', '5551900009994', ['is_platform_admin' => true]);
    $adm = $mk($ta, 'admin', '5551900009995');

    auth()->setUser($adm);
    $ids = PassengerResource::getEloquentQuery()->pluck('users.id')->all();
    $check('admin da marca ve o passageiro da propria marca', in_array($pa->id, $ids));
    $check('admin da marca NAO ve passageiro de outra marca', ! in_array($pb->id, $ids));
    $check('motorista e admin nao aparecem na lista', ! in_array($dr->id, $ids) && ! in_array($adm->id, $ids));

    auth()->setUser($super);
    $ids = PassengerResource::getEloquentQuery()->pluck('users.id')->all();
    $check('super-admin ve passageiros de todas as marcas', in_array($pa->id, $ids) && in_array($pb->id, $ids));

    $check('somente leitura: sem criar/editar/apagar', ! PassengerResource::canCreate() && ! PassengerResource::canEdit($pa) && ! PassengerResource::canDelete($pa));
    $okAdmin = PassengerResource::canViewAny();
    auth()->setUser($pa);
    $check('acesso liberado ao admin e negado ao passageiro', $okAdmin && ! PassengerResource::canViewAny());
    $check('telefone formatado', PassengersTable::formatPhone('5551994928705') === '+55 (51) 99492-8705');
} finally {
    DB::rollBack();
}
echo "\nPASSAGEIROS: $ok ok, ".count($falhas)." falhas\n";
