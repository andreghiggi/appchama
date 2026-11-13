<?php

namespace App\Domains\Drivers\Services;

use App\Models\City;
use App\Models\Driver;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DriverProvisioningService
{
    public function __construct(
        private DriverRegistrationCodeService $registrationCodes,
    ) {}

    public function provisionForUser(User $user): Driver
    {
        if ($user->driver) {
            return $user->driver;
        }

        return DB::transaction(function () use ($user): Driver {
            $cityId = City::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('active', true)
                ->value('id');

            $driver = Driver::query()->create([
                'user_id' => $user->id,
                'registration_source' => 'admin',
                'registration_code' => $this->registrationCodes->generate(),
                'city_id' => $cityId,
                'cnh_number' => 'PENDENTE',
                'cnh_expiry' => now()->addYear()->toDateString(),
                'background_check_status' => 'pending',
                'is_female_driver' => false,
                'online' => false,
                'subscription_status' => 'pending',
                'rating_avg' => 5.0,
            ]);

            Vehicle::query()->create([
                'id' => (string) Str::uuid(),
                'driver_id' => $driver->user_id,
                'plate' => 'PEND-0001',
                'model' => 'A definir',
                'color' => 'A definir',
                'year' => (int) date('Y'),
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

            return $driver->fresh(['user', 'vehicles']);
        });
    }
}
