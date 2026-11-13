<?php

namespace App\Domains\Tenants\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Administradores e suporte das marcas.
 * - Plataforma (super-admin, ou linha de comando = ator nulo): cria admin e suporte de qualquer marca.
 * - Admin da marca: cria/gerencia SOMENTE suporte da própria marca.
 * - Ninguém altera administradores da plataforma por aqui.
 */
class BrandUserService
{
    public const ROLES = ['admin', 'support'];

    public function canManage(?User $actor, User $target): bool
    {
        if (! in_array($target->role, self::ROLES, true) || $target->is_platform_admin) {
            return false;
        }
        if ($actor === null || $actor->is_platform_admin) {
            return true;
        }

        return $actor->role === 'admin'
            && $actor->status === 'active'
            && $target->role === 'support'
            && $target->tenant_id === $actor->tenant_id;
    }

    public function canCreate(?User $actor, string $tenantId, string $role): bool
    {
        if (! in_array($role, self::ROLES, true)) {
            return false;
        }
        if ($actor === null || $actor->is_platform_admin) {
            return true;
        }

        return $actor->role === 'admin'
            && $actor->status === 'active'
            && $actor->tenant_id === $tenantId
            && $role === 'support';
    }

    /** @return array{0: User, 1: string} usuário criado e senha temporária (mostrada uma única vez) */
    public function create(?User $actor, Tenant|string $tenant, string $name, string $email, string $role): array
    {
        $tenant = $tenant instanceof Tenant ? $tenant : Tenant::query()->findOrFail($tenant);

        if (! $this->canCreate($actor, $tenant->id, $role)) {
            throw new AuthorizationException('Você não pode criar este tipo de usuário nesta marca.');
        }

        $email = Str::lower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'E-mail inválido.']);
        }
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'Este e-mail já está em uso.']);
        }
        if (trim($name) === '') {
            throw ValidationException::withMessages(['name' => 'Informe o nome.']);
        }

        $senha = Str::password(12, symbols: false);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => trim($name),
            'email' => $email,
            'phone' => null,
            'role' => $role,
            'status' => 'active',
            'password' => $senha,
        ]);

        return [$user, $senha];
    }

    public function resetPassword(?User $actor, User $target): string
    {
        $this->authorizeManage($actor, $target);
        $senha = Str::password(12, symbols: false);
        $target->forceFill(['password' => $senha])->save();
        $target->tokens()->delete();

        return $senha;
    }

    public function setActive(?User $actor, User $target, bool $active): void
    {
        $this->authorizeManage($actor, $target);
        if ($actor && $actor->id === $target->id) {
            throw new AuthorizationException('Você não pode desativar a si mesmo.');
        }
        $target->forceFill(['status' => $active ? 'active' : 'blocked'])->save();
        if (! $active) {
            $target->tokens()->delete();
        }
    }

    private function authorizeManage(?User $actor, User $target): void
    {
        if (! $this->canManage($actor, $target)) {
            throw new AuthorizationException('Você não pode gerenciar este usuário.');
        }
    }
}
