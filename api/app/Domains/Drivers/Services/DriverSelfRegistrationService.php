<?php

namespace App\Domains\Drivers\Services;

use App\Models\City;
use App\Models\Driver;
use App\Models\Scopes\TenantScope;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DriverSelfRegistrationService
{
    public function register(array $data, UploadedFile $cnhDocument, string $tenantSlug): User
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        if (User::query()->where('tenant_id', $tenant->id)->where('phone', $data['phone'])->exists()) {
            abort(422, 'Telefone já cadastrado.');
        }

        $city = City::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->findOrFail($data['city_id']);

        abort_unless($city->acceptsDriverSignup(), 422, 'O cadastro de motoristas não está aberto nesta cidade.');

        $path = $cnhDocument->store('driver-cnh/'.$tenant->id, 'local');

        return DB::transaction(function () use ($data, $tenant, $city, $path): User {
            $user = User::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => 'driver',
                'status' => 'pending',
            ]);

            $driver = Driver::query()->create([
                'user_id' => $user->id,
                'registration_source' => 'app',
                'city_id' => $city->id,
                'departure_address' => $city->departure_address,
                'departure_lat' => $city->departure_lat,
                'departure_lng' => $city->departure_lng,
                'cnh_number' => $data['cnh_number'],
                'cnh_expiry' => $data['cnh_expiry'],
                'cnh_document_path' => $path,
                'background_check_status' => 'pending',
                'subscription_status' => 'pending',
                'online' => false,
                'rating_avg' => 5.0,
                'is_female_driver' => (bool) ($data['is_female_driver'] ?? false),
            ]);

            Vehicle::query()->create([
                'id' => (string) Str::uuid(),
                'driver_id' => $driver->user_id,
                'plate' => $data['vehicle_plate'],
                'model' => $data['vehicle_model'] ?? 'A definir',
                'color' => $data['vehicle_color'] ?? 'A definir',
                'year' => (int) ($data['vehicle_year'] ?? date('Y')),
            ]);

            Subscription::query()->create([
                'id' => (string) Str::uuid(),
                'driver_id' => $driver->user_id,
                'plan_name' => 'Mensal Parceiro',
                'amount' => 99.90,
                'due_day' => 10,
                'status' => 'pending',
                'next_charge_at' => now()->addDays(30),
            ]);

            return $user->fresh(['driver.vehicles']);
        });
    }
}
