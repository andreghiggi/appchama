<?php

namespace App\Http\Controllers\Api;

use App\Domains\Drivers\Services\DriverLocationService;
use App\Domains\Drivers\Services\DriverRegistrationCodeService;
use App\Domains\Rides\Services\RideOfferService;
use App\Domains\Rides\Services\RideTrackingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(
        private DriverLocationService $locationService,
        private RideOfferService $offerService,
        private DriverRegistrationCodeService $registrationCodes,
        private RideTrackingService $trackingService,
    ) {}

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

        $data = $request->validate([
            'lat' => 'sometimes|numeric',
            'lng' => 'sometimes|numeric',
        ]);

        $this->locationService->goOnline($driver, isset($data['lat'], $data['lng']) ? (float) $data['lat'] : null, isset($data['lat'], $data['lng']) ? (float) $data['lng'] : null);

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
            ->whereDate('paid_at', today())
            ->where('payment_status', 'paid')
            ->get();

        $walletSummary = app(\App\Domains\Payments\Services\DriverWalletService::class)
            ->summaryForDriver($driver);

        $awaitingPaymentRide = $driver->rides()
            ->where('status', 'awaiting_payment')
            ->where('requested_at', '>=', now()->subHours(6))
            ->latest('requested_at')
            ->first(['id', 'final_fare', 'driver_net_amount']);

        return response()->json([
            'online' => $driver->online,
            'subscription_status' => $driver->subscription_status,
            'rides_today' => $todayRides->count(),
            'earnings_today' => $todayRides->sum(fn ($r) => (float) ($r->driver_net_amount ?? $r->final_fare ?? 0)),
            'rating_avg' => $driver->rating_avg,
            'awaiting_payment_ride' => $awaitingPaymentRide ? [
                'id' => $awaitingPaymentRide->id,
                'final_fare' => (float) ($awaitingPaymentRide->final_fare ?? 0),
                'driver_net_amount' => (float) ($awaitingPaymentRide->driver_net_amount ?? 0),
            ] : null,
            ...$walletSummary,
        ]);
    }

    public function pendingOffer(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $offer = $this->offerService->getPendingOffer($driver->user_id);

        if (! $offer) {
            return response()->json(['ride' => null, 'expires_in' => null]);
        }

        $ride = $offer['ride'];

        return response()->json([
            'ride' => $this->trackingService->mergeIntoOffer($ride, $driver),
            'expires_in' => $offer['expires_in'],
        ]);
    }

    public function confirmRegistrationCode(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $data = $request->validate([
            'code' => 'required|string|max:12',
        ]);

        $driver = $this->registrationCodes->confirmForDriver($driver, $data['code']);

        return response()->json([
            'message' => 'Código confirmado.',
            'driver' => $driver,
        ]);
    }
}
