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

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::query()->create([
            'tenant_id' => $tenant->id,
            'phone' => $phone,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        $provider = config('services.sms.provider', 'log');

        if ($provider === 'log') {
            Log::info("OTP for {$phone}: {$code}");
        }
    }

    /** Exposto só em SMS_PROVIDER=log para facilitar testes. */
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
