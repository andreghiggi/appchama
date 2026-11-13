<?php

namespace App\Filament\Resources\RidePayments;

use App\Filament\Resources\RidePayments\Pages\ListRidePayments;
use App\Filament\Resources\RidePayments\Tables\RidePaymentsTable;
use App\Models\RidePayment;
use App\Models\Scopes\TenantScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RidePaymentResource extends Resource
{
    protected static ?string $model = RidePayment::class;

    protected static ?string $modelLabel = 'Pagamento';

    protected static ?string $pluralModelLabel = 'Pagamentos de corridas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['ride.passenger:id,name', 'ride.driver.user:id,name']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return RidePaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRidePayments::route('/'),
        ];
    }
}
