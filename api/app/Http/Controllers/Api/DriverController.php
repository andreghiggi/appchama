<?php

namespace App\Http\Controllers\Api;

use App\Domains\Drivers\Services\DriverLocationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(private DriverLocationService $locationService) {}

    public function updateLocation(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;

        if (! $driver) {
            abort(403, 'Usuário não é motorista.');
        }

        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $this->locationService->update($driver, $data['lat'], $data['lng']);

        return response()->json(['message' => 'Localização atualizada.']);
    }

    public function goOnline(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $this->locationService->goOnline($driver);

        return response()->json(['online' => true]);
    }

    public function goOffline(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $this->locationService->goOffline($driver);

        return response()->json(['online' => false]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $todayRides = $driver->rides()
            ->whereDate('completed_at', today())
            ->where('status', 'completed')
            ->get();

        return response()->json([
            'online' => $driver->online,
            'subscription_status' => $driver->subscription_status,
            'rides_today' => $todayRides->count(),
            'earnings_today' => $todayRides->sum('final_fare'),
            'rating_avg' => $driver->rating_avg,
        ]);
    }
}
