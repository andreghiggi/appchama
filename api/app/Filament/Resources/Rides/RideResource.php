<?php

namespace App\Filament\Resources\Rides;

use App\Filament\Resources\Rides\Pages\CreateRide;
use App\Filament\Resources\Rides\Pages\EditRide;
use App\Filament\Resources\Rides\Pages\ListRides;
use App\Filament\Resources\Rides\Pages\ViewRide;
use App\Filament\Resources\Rides\Schemas\RideForm;
use App\Filament\Resources\Rides\Tables\RidesTable;
use App\Models\Ride;
use App\Models\Scopes\TenantScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RideResource extends Resource
{
    protected static ?string $model = Ride::class;

    protected static ?string $modelLabel = 'Corrida';

    protected static ?string $pluralModelLabel = 'Corridas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['passenger:id,name,phone', 'driver.user:id,name', 'city:id,name']);
    }

    public static function form(Schema $schema): Schema
    {
        return RideForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RidesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRides::route('/'),
            'view' => ViewRide::route('/{record}'),
            'create' => CreateRide::route('/create'),
            'edit' => EditRide::route('/{record}/edit'),
        ];
    }
}
