<?php

namespace App\Domains\Auth\Services;

use App\Domains\Drivers\Services\DriverProvisioningService;
use App\Models\OtpCode;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpService
{
    public function send(string $phone, string $tenantSlug): void
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        OtpCode::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone', $phone)
            ->delete();

        $fixo = $this->testCodeFor($phone);
        $code = $fixo ?? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::query()->create([
            'tenant_id' => $tenant->id,
            'phone' => $phone,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        $provider = config('services.sms.provider', 'log');

        if ($provider === 'log' && $fixo === null) {
            // O codigo so vai para o log em desenvolvimento; em producao o log nao guarda codigos.
            Log::info(config('services.sms.expose_debug_code')
                ? "OTP for {$phone}: {$code}"
                : 'OTP gerado para ****'.substr(preg_replace('/\D/', '', $phone), -4));
        }
    }

    /** Codigo fixo se o telefone esta na lista de contas de teste (OTP_TEST_ACCOUNTS); senao null. */
    public function testCodeFor(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return null;
        }
        foreach (explode(',', (string) config('services.otp.test_accounts', '')) as $par) {
            [$tel, $cod] = array_pad(explode(':', trim($par), 2), 2, '');
            $tel = preg_replace('/\D/', '', $tel);
            $cod = trim($cod);
            if ($tel !== '' && preg_match('/^\d{6}$/', $cod)
                && ($digits === $tel || $digits === '55'.$tel || '55'.$digits === $tel)) {
                return $cod;
            }
        }

        return null;
    }

    /** So usado quando OTP_EXPOSE_DEBUG_CODE=true (desenvolvimento). */
    public function peekLatestCode(string $phone, string $tenantSlug): ?string
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
        if (! $tenant) {
            return null;
        }

        return OtpCode::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone', $phone)
            ->latest()
            ->value('code');
    }

    public function phoneExists(string $phone, string $tenantSlug): bool
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone', $phone)
            ->exists();
    }

    public function verify(string $phone, string $code, string $tenantSlug, ?array $registerData = null): User
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        $otp = OtpCode::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone', $phone)
            ->latest()
            ->firstOrFail();

        if (! $otp->isValid($code)) {
            abort(422, 'Código OTP inválido ou expirado.');
        }

        $otp->update(['used' => true]);

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone', $phone)
            ->first();

        if (! $user && $registerData) {
            $role = $registerData['role'] ?? 'passenger';

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $registerData['name'],
                'phone' => $phone,
                'email' => $registerData['email'] ?? null,
                'cpf' => $registerData['cpf'] ?? null,
                'role' => $role,
                'status' => $role === 'driver' ? 'pending' : 'active',
                'home_address' => $registerData['home_address'] ?? null,
                'home_address_number' => $registerData['home_address_number'] ?? null,
                'home_postal_code' => $registerData['home_postal_code'] ?? null,
                'home_city' => $registerData['home_city'] ?? null,
                'home_state' => $registerData['home_state'] ?? null,
                'home_lat' => $registerData['home_lat'] ?? null,
                'home_lng' => $registerData['home_lng'] ?? null,
            ]);

            if ($role === 'driver') {
                app(DriverProvisioningService::class)->provisionForUser($user);
            }
        }

        if ($user && $registerData && ($registerData['role'] ?? 'passenger') === 'passenger') {
            $updates = [];

            if (filled($registerData['name'] ?? null)) {
                $updates['name'] = $registerData['name'];
            }
            if (filled($registerData['home_address'] ?? null)) {
                $updates['home_address'] = $registerData['home_address'];
                $updates['home_lat'] = $registerData['home_lat'] ?? null;
                $updates['home_lng'] = $registerData['home_lng'] ?? null;
            }
            foreach (['email', 'cpf', 'home_address_number', 'home_postal_code', 'home_city', 'home_state'] as $field) {
                if (filled($registerData[$field] ?? null)) {
                    $updates[$field] = $field === 'home_state'
                        ? strtoupper($registerData[$field])
                        : $registerData[$field];
                }
            }

            if ($updates !== []) {
                $user->update($updates);
            }
        }

        if (! $user) {
            abort(404, 'Usuário não encontrado. Complete o cadastro.');
        }

        return $user;
    }
}
