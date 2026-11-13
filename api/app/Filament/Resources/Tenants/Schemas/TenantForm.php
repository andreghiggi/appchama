<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Marca')
                ->schema([
                    TextInput::make('name')->label('Nome')->required()->maxLength(120),
                    TextInput::make('slug')
                        ->label('Apelido (não muda depois)')
                        ->required()
                        ->maxLength(40)
                        ->regex('/^[a-z0-9][a-z0-9-]{1,40}$/')
                        ->helperText('Só letras minúsculas, números e hífen. Ex.: vaide55')
                        ->unique('tenants', 'slug', ignoreRecord: true)
                        ->disabledOn('edit'),
                    TextInput::make('tagline')->label('Lema (slogan)')->maxLength(160)->columnSpanFull(),
                    ColorPicker::make('primary_color')->label('Cor principal')->default('#FF9F1C'),
                    TextInput::make('logo_url')->label('Endereço da logo (URL)')->url()->maxLength(255),
                    Select::make('status')->label('Situação')
                        ->options(['active' => 'Ativa', 'suspended' => 'Suspensa'])
                        ->default('active')->required(),
                ])->columns(2),
            Section::make('Cores do app')
                ->description('A cor principal acima é o destaque (botões). Aqui ficam o fundo escuro e o texto sobre o destaque. Vazio = padrão do AppChama.')
                ->schema([
                    ColorPicker::make('theme.ink')->label('Fundo escuro'),
                    ColorPicker::make('theme.ink2')->label('Fundo escuro 2 (cartões)'),
                    ColorPicker::make('theme.amberDark')->label('Destaque escuro (pressionado)'),
                    ColorPicker::make('theme.onAmber')->label('Texto sobre o destaque'),
                ])->columns(4),
            Section::make('Contatos e textos legais')
                ->description('Aparecem no app (suporte, política de privacidade, termos e exclusão de conta). Exigidos pelas lojas.')
                ->schema([
                    TextInput::make('contacts.whatsapp')->label('WhatsApp (com DDD)')->maxLength(30),
                    TextInput::make('contacts.email')->label('E-mail de suporte')->email()->maxLength(120),
                    TextInput::make('contacts.phone')->label('Telefone')->maxLength(30),
                    TextInput::make('contacts.site')->label('Site')->url()->maxLength(255),
                    TextInput::make('legal.privacy_url')->label('Política de privacidade (URL)')->url()->maxLength(255),
                    TextInput::make('legal.terms_url')->label('Termos de uso (URL)')->url()->maxLength(255),
                    TextInput::make('legal.delete_account_url')->label('Exclusão de conta (URL)')->url()->maxLength(255),
                ])->columns(3),
            Section::make('Recursos do app')
                ->schema([
                    Toggle::make('features.driver_wallet')->label('Carteira do motorista')->default(true),
                    Toggle::make('features.ratings')->label('Avaliações')->default(true),
                ])->columns(2),
            Section::make('Endereços da marca')
                ->description('O endereço decide qual marca atende a requisição. Cada endereço só pode pertencer a uma marca.')
                ->schema([
                    Repeater::make('domains')
                        ->relationship('domains')
                        ->label('Endereços')
                        ->schema([
                            TextInput::make('host')->label('Endereço (ex.: api.vaide55.com.br)')->required()
                                ->regex('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/')
                                ->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state)))
                                ->unique('tenant_domains', 'host', ignoreRecord: true),
                            Select::make('type')->label('Uso')
                                ->options(['api' => 'API (apps)', 'admin' => 'Painel (adm)', 'site' => 'Site'])
                                ->default('api')->required(),
                        ])->columns(2)->defaultItems(0)->addActionLabel('Adicionar endereço'),
                ]),
            Section::make('Pagamentos')
                ->description('A conta Asaas da marca é configurada na página "Pagamentos Asaas" (escolha a marca no seletor do topo).')
                ->schema([
                    TextInput::make('platform_commission_pct')->label('Comissão da plataforma (%)')
                        ->numeric()->minValue(0)->maxValue(100)->step(0.01)->default(15)->suffix('%'),
                    Select::make('asaas_environment')->label('Ambiente do Asaas')
                        ->options(['sandbox' => 'Teste (sandbox)', 'production' => 'Produção'])->default('sandbox'),
                    Toggle::make('payment_methods_enabled.pix')->label('Pix')->default(true),
                    Toggle::make('payment_methods_enabled.credit_card')->label('Cartão')->default(true),
                    Toggle::make('payment_methods_enabled.direct_driver')->label('Pagamento direto ao motorista')->default(false),
                ])->columns(3),
        ]);
    }
}
