<?php

namespace App\Domains\Auth\Services;

use App\Models\OtpCode;
use App\Models\Tenant;
use App\Models\User;
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
            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $registerData['name'],
                'phone' => $phone,
                'role' => $registerData['role'] ?? 'passenger',
                'status' => ($registerData['role'] ?? 'passenger') === 'driver' ? 'pending' : 'active',
            ]);
        }

        if (! $user) {
            abort(404, 'Usuário não encontrado. Complete o cadastro.');
        }

        return $user;
    }
}
