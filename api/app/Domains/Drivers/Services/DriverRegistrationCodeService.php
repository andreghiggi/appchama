<?php

namespace App\Domains\Drivers\Services;

use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DriverRegistrationCodeService
{
    public function generate(): string
    {
        do {
            $code = 'CHM-'.strtoupper(Str::random(6));
        } while (Driver::query()->where('registration_code', $code)->exists());

        return $code;
    }

    public function confirmForDriver(Driver $driver, string $code): Driver
    {
        $normalized = strtoupper(trim($code));

        if ($driver->registration_code !== $normalized) {
            abort(422, 'Código de cadastro inválido.');
        }

        if ($driver->registration_code_confirmed_at !== null) {
            return $driver;
        }

        $driver->update(['registration_code_confirmed_at' => now()]);

        return $driver->fresh(['user', 'vehicles']);
    }

    public function validateAndApprove(Driver $driver, string $code): Driver
    {
        if ($driver->registration_source === 'app') {
            abort(422, 'Este motorista se cadastrou pelo app. Use "Aprovar cadastro" em vez de validar código.');
        }

        $normalized = strtoupper(trim($code));

        if ($driver->registration_code !== $normalized) {
            abort(422, 'Código de cadastro não confere com este motorista.');
        }

        if ($driver->registration_code_confirmed_at === null) {
            abort(422, 'O motorista ainda não confirmou o código no app.');
        }

        $driver->update([
            'background_check_status' => 'approved',
            'subscription_status' => 'active',
            'registration_validated_at' => now(),
        ]);

        $driver->user?->update(['status' => 'active']);
        $driver->subscription?->update(['status' => 'active']);

        return $driver->fresh(['user', 'vehicles']);
    }

    public function findPendingByCode(string $code): ?Driver
    {
        return Driver::query()
            ->with(['user', 'vehicles'])
            ->where('registration_code', strtoupper(trim($code)))
            ->where('registration_source', '!=', 'app')
            ->where('background_check_status', 'pending')
            ->first();
    }

    public function loginByPhoneAndCode(string $phone, string $code, string $tenantSlug): User
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone', $phone)
            ->where('role', 'driver')
            ->with(['driver.vehicles'])
            ->first();

        if (! $user?->driver) {
            abort(404, 'Motorista não encontrado. Verifique o telefone ou solicite cadastro ao administrador.');
        }

        if ($user->driver->registration_source === 'app') {
            abort(422, 'Este cadastro foi feito pelo app. Use telefone e senha para entrar.');
        }

        $normalized = strtoupper(trim($code));

        if ($user->driver->registration_code !== $normalized) {
            abort(422, 'Código de cadastro inválido.');
        }

        if ($user->driver->registration_code_confirmed_at === null) {
            $user->driver->update(['registration_code_confirmed_at' => now()]);
            $user->load(['driver.vehicles']);
        }

        return $user;
    }

    public function loginByPhoneAndPassword(string $phone, string $password, string $tenantSlug): User
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone', $phone)
            ->where('role', 'driver')
            ->with(['driver.vehicles'])
            ->first();

        if (! $user?->driver) {
            abort(404, 'Motorista não encontrado. Crie sua conta ou verifique o telefone.');
        }

        if ($user->driver->registration_source !== 'app') {
            abort(422, 'Este cadastro foi feito pelo administrador. Use o código de cadastro para entrar.');
        }

        if (! $user->password || ! Hash::check($password, $user->password)) {
            abort(422, 'Senha incorreta.');
        }

        return $user;
    }

    public function approveAppDriver(Driver $driver): Driver
    {
        if ($driver->registration_source !== 'app') {
            abort(422, 'Aprovação por código disponível apenas para cadastros do administrador.');
        }

        if ($driver->background_check_status !== 'pending') {
            abort(422, 'Este cadastro já foi analisado.');
        }

        if (! $driver->cnh_document_path) {
            abort(422, 'Documento da CNH não foi enviado.');
        }

        $driver->update([
            'background_check_status' => 'approved',
            'subscription_status' => 'active',
            'registration_validated_at' => now(),
        ]);

        $driver->user?->update(['status' => 'active']);
        $driver->subscription?->update(['status' => 'active']);

        return $driver->fresh(['user', 'vehicles']);
    }
}
