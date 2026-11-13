<?php

use App\Domains\Drivers\Services\DriverRegistrationCodeService;
use App\Models\Driver;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(DriverRegistrationCodeService::class);

        Driver::query()
            ->whereNull('registration_code')
            ->where(function ($q): void {
                $q->where('registration_source', 'admin')
                    ->orWhereNull('registration_source');
            })
            ->each(function (Driver $driver) use ($service): void {
                $driver->update([
                    'registration_code' => $service->generate(),
                    'registration_source' => $driver->registration_source ?? 'admin',
                ]);
            });
    }

    public function down(): void
    {
        // irreversible
    }
};
