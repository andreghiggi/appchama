<?php

namespace App\Filament\Resources\BrandUsers\Schemas;

use App\Models\Tenant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class BrandUserForm
{
    public static function configure(Schema $schema): Schema
    {
        $platform = (bool) auth()->user()?->is_platform_admin;

        return $schema->components([
            Section::make('Acesso ao painel')
                ->description('A senha temporária é gerada pelo sistema e mostrada uma única vez depois de salvar.')
                ->schema([
                    Select::make('tenant_id')
                        ->label('Marca')
                        ->options(fn () => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn () => app()->bound('currentTenantId') ? app('currentTenantId') : null)
                        ->required()
                        ->visible($platform)
                        ->disabledOn('edit'),
                    TextInput::make('name')->label('Nome')->required()->maxLength(120),
                    TextInput::make('email')
                        ->label('E-mail (usado para entrar)')
                        ->email()
                        ->required()
                        ->maxLength(120)
                        ->unique('users', 'email', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule),
                    Select::make('role')
                        ->label('Papel')
                        ->options($platform
                            ? ['admin' => 'Administrador da marca', 'support' => 'Suporte da marca']
                            : ['support' => 'Suporte da marca'])
                        ->default('support')
                        ->required(),
                ])
                ->columns(2),
        ]);
    }
}
