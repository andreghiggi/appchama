<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Driver;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Chama Demo',
            'slug' => 'chama-demo',
            'primary_color' => '#FF9F1C',
            'logo_url' => null,
            'status' => 'active',
        ]);

        config(['app.default_tenant_id' => $tenant->id]);

        $city = City::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'São Paulo',
            'state' => 'SP',
            'base_fare' => 5.00,
            'price_per_km' => 2.50,
            'price_per_min' => 0.50,
            'min_fare' => 8.00,
            'active' => true,
        ]);

        User::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Admin Chama',
            'phone' => '5511999990001',
            'email' => 'admin@chama.app',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $passenger = User::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Marina Costa',
            'phone' => '5511999990002',
            'role' => 'passenger',
            'status' => 'active',
        ]);

        $driverUser = User::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Roberto Alves',
            'phone' => '5511999990003',
            'role' => 'driver',
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'city_id' => $city->id,
            'cnh_number' => '12345678900',
            'cnh_expiry' => now()->addYears(2),
            'background_check_status' => 'approved',
            'is_female_driver' => false,
            'online' => false,
            'subscription_status' => 'active',
            'rating_avg' => 4.90,
        ]);

        Vehicle::query()->create([
            'id' => (string) Str::uuid(),
            'driver_id' => $driver->user_id,
            'plate' => 'ABC1G34',
            'model' => 'Chevrolet Onix',
            'color' => 'Prata',
            'year' => 2022,
        ]);

        Subscription::query()->create([
            'id' => (string) Str::uuid(),
            'driver_id' => $driver->user_id,
            'plan_name' => 'Mensal Parceiro',
            'amount' => 99.90,
            'due_day' => 10,
            'status' => 'active',
            'next_charge_at' => now()->addDays(18),
        ]);

        $this->command?->info('Tenant slug: chama-demo');
        $this->command?->info('Admin: admin@chama.app / password');
        $this->command?->info('Passageiro teste: 5511999990002');
        $this->command?->info('Motorista teste: 5511999990003');
    }
}
