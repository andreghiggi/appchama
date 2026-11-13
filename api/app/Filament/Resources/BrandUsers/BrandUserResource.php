<?php

namespace App\Filament\Resources\BrandUsers;

use App\Domains\Tenants\Services\BrandUserService;
use App\Filament\Resources\BrandUsers\Pages\CreateBrandUser;
use App\Filament\Resources\BrandUsers\Pages\EditBrandUser;
use App\Filament\Resources\BrandUsers\Pages\ListBrandUsers;
use App\Filament\Resources\BrandUsers\Schemas\BrandUserForm;
use App\Filament\Resources\BrandUsers\Tables\BrandUsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BrandUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Administrador';

    protected static ?string $pluralModelLabel = 'Administradores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Marcas e acessos';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'administradores';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()
            ->whereIn('role', BrandUserService::ROLES)
            ->where('is_platform_admin', false);

        // Admin de marca só enxerga a própria marca; o super-admin vê todas (ou a marca filtrada no topo).
        if ($user && ! $user->is_platform_admin) {
            $query->where('tenant_id', $user->tenant_id);
        } elseif (app()->bound('currentTenantId')) {
            $query->where('tenant_id', app('currentTenantId'));
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return (bool) $u && $u->isAdmin() && $u->status === 'active' && ($u->is_platform_admin || $u->role === 'admin');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return app(BrandUserService::class)->canManage(auth()->user(), $record);
    }

    public static function canDelete(Model $record): bool
    {
        return false; // desative em vez de apagar (mantém o histórico)
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return BrandUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandUsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrandUsers::route('/'),
            'create' => CreateBrandUser::route('/create'),
            'edit' => EditBrandUser::route('/{record}/edit'),
        ];
    }
}
