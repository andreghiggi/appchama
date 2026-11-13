<?php

namespace App\Http\Controllers;

use App\Domains\Payments\Services\EarningsReportService;
use App\Models\Driver;
use Illuminate\View\View;

class DriverEarningsReportController extends Controller
{
    public function show(Driver $driver, EarningsReportService $reports): View
    {
        $driver->loadMissing('user:id,name');

        return view('reports.driver-earnings', [
            'driverName' => $driver->user?->name ?? 'Motorista',
            'weeks' => $reports->weeklyReport($driver, 12),
        ]);
    }
}
