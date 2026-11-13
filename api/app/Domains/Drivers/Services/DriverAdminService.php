<?php

namespace App\Domains\Drivers\Services;

use App\Models\City;
use App\Models\Driver;
use App\Models\Scopes\TenantScope;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DriverAdminService
{
    public function __construct(
        private DriverRegistrationCodeService $registrationCodes,
    ) {}

    public function createFromAdmin(array $data): Driver
    {
        return DB::transaction(function () use ($data): Driver {
            $city = City::query()
                ->withoutGlobalScope(TenantScope::class)
                ->find($data['city_id']);
            $tenantId = $city?->tenant_id ?? config('app.default_tenant_id');

            $backgroundStatus = $data['background_check_status'] ?? 'approved';
            $subscriptionStatus = $data['subscription_status'] ?? 'active';

            $user = User::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => $data['user_name'],
                'phone' => $data['user_phone'],
                'role' => 'driver',
                'status' => $this->isEligibleForRides($backgroundStatus, $subscriptionStatus) ? 'active' : 'pending',
            ]);

            $driver = Driver::query()->create([
                'user_id' => $user->id,
                'registration_source' => 'admin',
                'registration_code' => $this->registrationCodes->generate(),
                'registration_code_confirmed_at' => $this->isEligibleForRides($backgroundStatus, $subscriptionStatus) ? now() : null,
                'registration_validated_at' => $this->isEligibleForRides($backgroundStatus, $subscriptionStatus) ? now() : null,
                'city_id' => $data['city_id'],
                'departure_address' => $data['departure_address'] ?? null,
                'departure_lat' => $data['departure_lat'] ?? $city?->departure_lat,
                'departure_lng' => $data['departure_lng'] ?? $city?->departure_lng,
                'cnh_number' => filled($data['cnh_number'] ?? null) ? $data['cnh_number'] : 'PENDENTE',
                'cnh_expiry' => filled($data['cnh_expiry'] ?? null) ? $data['cnh_expiry'] : now()->addYear()->toDateString(),
                'background_check_status' => $backgroundStatus,
                'is_female_driver' => (bool) ($data['is_female_driver'] ?? false),
                'online' => false,
                'subscription_status' => $subscriptionStatus,
                'rating_avg' => 5.0,
            ]);

            Vehicle::query()->create([
                'id' => (string) Str::uuid(),
                'driver_id' => $driver->user_id,
                'plate' => $data['vehicle_plate'] ?? 'SEM-PLACA',
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
                'status' => $subscriptionStatus === 'active' ? 'active' : 'pending',
                'next_charge_at' => now()->addDays(30),
            ]);

            $this->seedDepartureLocation($driver->fresh(['city']));

            return $driver->fresh(['user', 'city', 'vehicles']);
        });
    }

    public function updateFromAdmin(Driver $driver, array $data): Driver
    {
        $backgroundStatus = $data['background_check_status'] ?? $driver->background_check_status;
        $subscriptionStatus = $data['subscription_status'] ?? $driver->subscription_status;

        $driver->user?->update([
            'name' => $data['user_name'] ?? $driver->user?->name,
            'phone' => $data['user_phone'] ?? $driver->user?->phone,
            'status' => $this->isEligibleForRides($backgroundStatus, $subscriptionStatus)
                ? 'active'
                : ($backgroundStatus === 'rejected' ? 'inactive' : 'pending'),
        ]);

        $driver->update([
            'city_id' => $data['city_id'] ?? $driver->city_id,
            'departure_address' => $data['departure_address'] ?? $driver->departure_address,
            'departure_lat' => array_key_exists('departure_lat', $data) ? $data['departure_lat'] : $driver->departure_lat,
            'departure_lng' => array_key_exists('departure_lng', $data) ? $data['departure_lng'] : $driver->departure_lng,
            'cnh_number' => filled($data['cnh_number'] ?? null)
                ? $data['cnh_number']
                : ($driver->cnh_number ?: 'PENDENTE'),
            'cnh_expiry' => filled($data['cnh_expiry'] ?? null)
                ? $data['cnh_expiry']
                : ($driver->cnh_expiry?->toDateString() ?? now()->addYear()->toDateString()),
            'background_check_status' => $backgroundStatus,
            'is_female_driver' => (bool) ($data['is_female_driver'] ?? $driver->is_female_driver),
            'subscription_status' => $subscriptionStatus,
        ]);

        if ($backgroundStatus === 'rejected') {
            $driver->update(['online' => false]);
        }

        if ($this->isEligibleForRides($backgroundStatus, $subscriptionStatus)) {
            $driver->update([
                'registration_validated_at' => $driver->registration_validated_at ?? now(),
                'registration_code_confirmed_at' => $driver->registration_code_confirmed_at ?? now(),
            ]);
        }

        $vehicle = $driver->vehicles()->first();
        if ($vehicle) {
            $vehicle->update([
                'plate' => $data['vehicle_plate'] ?? $vehicle->plate,
                'model' => $data['vehicle_model'] ?? $vehicle->model,
                'color' => $data['vehicle_color'] ?? $vehicle->color,
                'year' => (int) ($data['vehicle_year'] ?? $vehicle->year),
            ]);
        }

        $driver->subscription?->update([
            'status' => $subscriptionStatus === 'active' ? 'active' : 'pending',
        ]);

        $this->seedDepartureLocation($driver->fresh(['city']));

        return $driver->fresh(['user', 'city', 'vehicles']);
    }

    public function fillFormData(Driver $driver): array
    {
        $vehicle = $driver->vehicles()->first();

        return [
            'user_name' => $driver->user?->name,
            'user_phone' => $driver->user?->phone,
            'city_id' => $driver->city_id,
            'departure_address' => $driver->departure_address,
            'departure_lat' => $driver->departure_lat,
            'departure_lng' => $driver->departure_lng,
            'cnh_number' => $driver->cnh_number,
            'cnh_expiry' => $driver->cnh_expiry,
            'background_check_status' => $driver->background_check_status,
            'subscription_status' => $driver->subscription_status,
            'registration_code' => $driver->registration_code,
            'registration_source' => $driver->registration_source,
            'cnh_document_path' => $driver->cnh_document_path,
            'registration_code_confirmed_at' => $driver->registration_code_confirmed_at,
            'registration_validated_at' => $driver->registration_validated_at,
            'is_female_driver' => $driver->is_female_driver,
            'vehicle_plate' => $vehicle?->plate,
            'vehicle_model' => $vehicle?->model,
            'vehicle_color' => $vehicle?->color,
            'vehicle_year' => $vehicle?->year,
        ];
    }

    /** Garante coordenadas e elegibilidade após cadastro de veículo avulso. */
    public function ensureMatchingReady(Driver $driver): void
    {
        if ($driver->background_check_status === 'approved'
            && $driver->subscription_status === 'active'
            && $driver->vehicles()->exists()) {
            $driver->user?->update(['status' => 'active']);
            $driver->subscription?->update(['status' => 'active']);
        }

        $this->seedDepartureLocation($driver->fresh(['city']));
    }

    /** Garante coordenadas de partida para matching quando o motorista ficar online. */
    private function seedDepartureLocation(Driver $driver): void
    {
        $driver->loadMissing('city');

        $lat = $driver->departure_lat ?? $driver->city?->departure_lat;
        $lng = $driver->departure_lng ?? $driver->city?->departure_lng;

        if ($lat === null || $lng === null) {
            return;
        }

        if ($driver->departure_lat === null || $driver->departure_lng === null) {
            $driver->update([
                'departure_lat' => $lat,
                'departure_lng' => $lng,
                'current_lat' => $driver->current_lat ?? $lat,
                'current_lng' => $driver->current_lng ?? $lng,
            ]);
        }
    }

    private function isEligibleForRides(string $backgroundStatus, string $subscriptionStatus): bool
    {
        return $backgroundStatus === 'approved'
            && $subscriptionStatus === 'active';
    }
}
