<?php

namespace App\Filament\Pages;

use App\Domains\Payments\Services\AsaasAccountService;
use App\Domains\Payments\Services\AsaasWebhookSetupService;
use Filament\Forms\Components\Placeholder;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ManagePaymentSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Pagamentos Asaas';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Pagamentos Asaas';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $asaasCapabilities = null;

    public static function shouldRegisterNavigation(): bool
    {
        return app()->bound('currentTenantId');
    }

    public function mount(): void
    {
        $tenant = $this->resolveTenant();
        $methods = $tenant->enabledPaymentMethods();
        $this->refreshAsaasCapabilities($tenant);

        $this->form->fill([
            'asaas_api_key' => '',
            'asaas_environment' => $tenant->asaas_environment ?? 'sandbox',
            'asaas_webhook_token' => $tenant->asaas_webhook_token,
            'platform_commission_pct' => $this->normalizeCommission($tenant->platform_commission_pct),
            'payment_pix' => (bool) ($methods['pix'] ?? true),
            'payment_credit_card' => (bool) ($methods['credit_card'] ?? true),
            'payment_direct_driver' => (bool) ($methods['direct_driver'] ?? true),
            'asaas_capabilities_summary' => $this->capabilitiesSummary($this->asaasCapabilities ?? []),
        ]);
    }

    public function save(): void
    {
        try {
            $tenant = $this->resolveTenant();
            $data = $this->formData();
            $client = app(\App\Domains\Payments\Services\AsaasClient::class);
            $environment = (string) ($data['asaas_environment'] ?? $tenant->asaas_environment ?? 'sandbox');

            $webhookToken = trim((string) ($data['asaas_webhook_token'] ?? ''));
            if ($webhookToken !== '' && $client->looksLikeApiKey($webhookToken)) {
                Notification::make()
                    ->danger()
                    ->title('Campo incorreto')
                    ->body('O valor em "Token do webhook" parece ser a API Key do Asaas. Cole a API Key no campo correto (Integrações → API Key). O token do webhook é gerado pelo App Chama.')
                    ->send();

                return;
            }

            $newApiKey = filled($data['asaas_api_key'] ?? null)
                ? trim((string) $data['asaas_api_key'])
                : null;

            $environmentChanged = $environment !== ($tenant->asaas_environment ?? 'sandbox');

            if ($newApiKey !== null) {
                $client->verifyCredentials($newApiKey, $environment);
            } elseif ($environmentChanged && $this->clientConfigured($tenant)) {
                $client->verifyCredentials((string) $tenant->asaas_api_key, $environment);
            }

            // Comissão e formas de pagamento liberadas: só a plataforma altera.
            $isPlatform = (bool) auth()->user()?->is_platform_admin;

            $payload = [
                'asaas_environment' => $environment,
                'platform_commission_pct' => $isPlatform
                    ? $this->normalizeCommission($data['platform_commission_pct'] ?? $tenant->platform_commission_pct)
                    : $tenant->platform_commission_pct,
                'payment_methods_enabled' => $isPlatform
                    ? [
                        'pix' => (bool) $data['payment_pix'],
                        'credit_card' => (bool) $data['payment_credit_card'],
                        'direct_driver' => (bool) $data['payment_direct_driver'],
                    ]
                    : ($tenant->payment_methods_enabled ?? []),
            ];

            if ($newApiKey !== null) {
                $payload['asaas_api_key'] = $newApiKey;
            }

            if ($webhookToken !== '') {
                $payload['asaas_webhook_token'] = $webhookToken;
            } elseif (! filled($tenant->asaas_webhook_token)) {
                $payload['asaas_webhook_token'] = Str::random(40);
            }

            $tenant->update($payload);
            $tenant->refresh();
            app(AsaasAccountService::class)->forgetCache($tenant);

            $body = 'API Key salva e validada no Asaas.';
            if ($this->clientConfigured($tenant)) {
                try {
                    app(AsaasWebhookSetupService::class)->register($tenant);
                } catch (\Throwable $e) {
                    $body .= ' Webhook: '.$e->getMessage().'.';
                }

                $summary = $this->syncPaymentMethodsFromAsaas($tenant, notify: false);
                $body = 'API Key salva · Pix/cartão sincronizados. '.$summary;
            }

            $this->syncFormFromTenant($tenant->fresh());

            Notification::make()
                ->success()
                ->title('Configurações salvas')
                ->body($body)
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Erro ao salvar credenciais Asaas')
                ->body($e->getMessage())
                ->send();
        }
    }

    private function clientConfigured(Tenant $tenant): bool
    {
        return app(\App\Domains\Payments\Services\AsaasClient::class)->isConfigured($tenant);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $webhookUrl = app(AsaasWebhookSetupService::class)->webhookUrl();

        return $schema->components([
            Section::make('Credenciais Asaas')
                ->description("Webhook: {$webhookUrl}")
                ->schema([
                    Placeholder::make('asaas_api_key_status')
                        ->label('Status da API Key')
                        ->content(fn (): string => $this->clientConfigured($this->resolveTenant())
                            ? 'Configurada — deixe o campo abaixo em branco para manter a chave atual.'
                            : 'Não configurada — cole a API Key do Asaas (sandbox ou produção).')
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    TextInput::make('asaas_api_key')
                        ->label('API Key Asaas')
                        ->password()
                        ->revealable()
                        ->placeholder('Integrações → API Key (começa com $aact_...)')
                        ->helperText('Chave copiada do painel Asaas. Não confundir com o token do webhook.'),
                    Select::make('asaas_environment')
                        ->label('Ambiente')
                        ->options([
                            'sandbox' => 'Sandbox (testes)',
                            'production' => 'Produção',
                        ])
                        ->default('sandbox')
                        ->helperText('Sandbox usa api-sandbox.asaas.com — a chave precisa ser do mesmo ambiente.'),
                    TextInput::make('asaas_webhook_token')
                        ->label('Token do webhook')
                        ->maxLength(64)
                        ->helperText('Deixe em branco para gerar automaticamente. Não cole a API Key aqui.'),
                ])
                ->columns(2),
            Section::make('Comissão da plataforma')
                ->schema([
                    TextInput::make('platform_commission_pct')
                        ->label('Percentual (%)')
                        ->disabled(fn (): bool => ! auth()->user()?->is_platform_admin)
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->suffix('%')
                        ->helperText('Descontado automaticamente. O motorista vê sempre o valor líquido.'),
                ]),
            Section::make('Formas de pagamento')
                ->description('Ao salvar, consultamos o Asaas e ligamos Pix/cartão conforme a conta.')
                ->schema([
                    Placeholder::make('asaas_capabilities_summary')
                        ->label('Disponível no Asaas agora')
                        ->content(fn (): string => (string) ($this->data['asaas_capabilities_summary'] ?? 'Consultando Asaas...'))
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Toggle::make('payment_pix')
                        ->label('Pix (Asaas)')
                        ->disabled(fn (): bool => ! auth()->user()?->is_platform_admin)
                        ->default(true)
                        ->helperText(fn (): string => ($this->asaasCapabilities['pix'] ?? false)
                            ? 'Pix liberado na conta Asaas'
                            : 'Pix indisponível no Asaas — verifique aprovação da conta'),
                    Toggle::make('payment_credit_card')
                        ->label('Cartão (Checkout Asaas)')
                        ->disabled(fn (): bool => ! auth()->user()?->is_platform_admin)
                        ->default(true)
                        ->helperText(fn (): string => ($this->asaasCapabilities['credit_card'] ?? false)
                            ? 'Cartão liberado na conta Asaas'
                            : 'Cartão indisponível — dados comerciais precisam estar aprovados'),
                    Toggle::make('payment_direct_driver')
                        ->label('Pagamento direto ao motorista')
                        ->disabled(fn (): bool => ! auth()->user()?->is_platform_admin)
                        ->default(true)
                        ->helperText('Não passa pelo Asaas — motorista confirma recebimento na hora'),
                ])
                ->columns(3),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Salvar API Key e formas')
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    private function resolveTenant(): Tenant
    {
        if (! app()->bound('currentTenantId')) {
            abort(422, 'Escolha uma marca no seletor "Marca:" do topo para ver os pagamentos.');
        }

        return Tenant::query()->findOrFail(app('currentTenantId'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $tenant = $this->resolveTenant();

        return array_merge([
            'asaas_api_key' => '',
            'asaas_environment' => $tenant->asaas_environment ?? 'sandbox',
            'asaas_webhook_token' => $tenant->asaas_webhook_token,
            'platform_commission_pct' => $this->normalizeCommission($tenant->platform_commission_pct),
            'payment_pix' => true,
            'payment_credit_card' => true,
            'payment_direct_driver' => true,
        ], $this->data ?? []);
    }

    private function syncPaymentMethodsFromAsaas(Tenant $tenant, bool $notify = true): string
    {
        app(AsaasAccountService::class)->forgetCache($tenant);
        $caps = $this->refreshAsaasCapabilities($tenant);
        $summary = $this->capabilitiesSummary($caps);

        if ($caps['error'] ?? null) {
            if ($notify) {
                throw new \RuntimeException($caps['error']);
            }

            return $summary;
        }

        $directDriver = (bool) (
            $this->data['payment_direct_driver']
            ?? $tenant->enabledPaymentMethods()['direct_driver']
            ?? true
        );

        $pix = (bool) ($caps['pix'] ?? false);
        $card = (bool) ($caps['credit_card'] ?? false);

        $tenant->update([
            'payment_methods_enabled' => [
                'pix' => $pix,
                'credit_card' => $card,
                'direct_driver' => $directDriver,
            ],
        ]);
        $tenant->refresh();

        $this->data['payment_pix'] = $pix;
        $this->data['payment_credit_card'] = $card;
        $this->data['payment_direct_driver'] = $directDriver;
        $this->data['asaas_capabilities_summary'] = $summary;

        $this->form->fill([
            'payment_pix' => $pix,
            'payment_credit_card' => $card,
            'payment_direct_driver' => $directDriver,
            'asaas_capabilities_summary' => $summary,
        ]);

        return $summary;
    }

    private function syncFormFromTenant(Tenant $tenant): void
    {
        $methods = $tenant->enabledPaymentMethods();

        $this->form->fill([
            'asaas_api_key' => '',
            'asaas_environment' => $tenant->asaas_environment ?? 'sandbox',
            'asaas_webhook_token' => $tenant->asaas_webhook_token,
            'platform_commission_pct' => $this->normalizeCommission($tenant->platform_commission_pct),
            'payment_pix' => (bool) ($methods['pix'] ?? true),
            'payment_credit_card' => (bool) ($methods['credit_card'] ?? true),
            'payment_direct_driver' => (bool) ($methods['direct_driver'] ?? true),
            'asaas_capabilities_summary' => $this->capabilitiesSummary($this->asaasCapabilities ?? []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshAsaasCapabilities(Tenant $tenant): array
    {
        if (! $this->clientConfigured($tenant)) {
            $this->asaasCapabilities = [
                'asaas_ready' => false,
                'pix' => false,
                'credit_card' => false,
                'error' => 'API Key não configurada',
            ];
            $summary = $this->capabilitiesSummary($this->asaasCapabilities);
            $this->data['asaas_capabilities_summary'] = $summary;
            $this->form->fill(['asaas_capabilities_summary' => $summary]);

            return $this->asaasCapabilities;
        }

        $this->asaasCapabilities = app(AsaasAccountService::class)->availablePaymentMethods($tenant);
        $summary = $this->capabilitiesSummary($this->asaasCapabilities);
        $this->data['asaas_capabilities_summary'] = $summary;
        $this->form->fill([
            'asaas_capabilities_summary' => $summary,
        ]);

        return $this->asaasCapabilities;
    }

    private function normalizeCommission(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    /**
     * @param  array<string, mixed>  $caps
     */
    private function capabilitiesSummary(array $caps): string
    {
        if ($caps['error'] ?? null) {
            return 'Asaas: '.$caps['error'];
        }

        if (! ($caps['asaas_ready'] ?? false)) {
            return 'Conta Asaas ainda não aprovada (general: '.($caps['general'] ?? '—').').';
        }

        $pix = ($caps['pix'] ?? false) ? 'sim' : 'não';
        $card = ($caps['credit_card'] ?? false) ? 'sim' : 'não';

        return 'Conta aprovada · Pix: '.$pix.' · Cartão: '.$card
            .' · Dados comerciais: '.($caps['commercial_info'] ?? '—');
    }
}
