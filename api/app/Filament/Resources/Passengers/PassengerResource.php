<?php

namespace App\Filament\Resources\Passengers;

use App\Filament\Resources\Passengers\Pages\ListPassengers;
use App\Filament\Resources\Passengers\Tables\PassengersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Lista (somente leitura) dos passageiros cadastrados na marca. */
class PassengerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Passageiro';

    protected static ?string $pluralModelLabel = 'Passageiros';

    protected static ?string $slug = 'passageiros';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()
            ->where('role', 'passenger')
            ->with('tenant:id,name')
            ->withCount('passengerRides')
            ->withMax('passengerRides', 'requested_at');

        // Admin de marca só enxerga a própria marca; o super-admin vê todas (ou a marca filtrada no topo).
        if ($user && ! $user->is_platform_admin) {
            $query->where('users.tenant_id', $user->tenant_id);
        } elseif (app()->bound('currentTenantId')) {
            $query->where('users.tenant_id', app('currentTenantId'));
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return (bool) $u && $u->isAdmin() && $u->status === 'active';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return PassengersTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListPassengers::route('/')];
    }
}
