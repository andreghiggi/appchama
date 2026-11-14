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

    /**
     * Carrinhos no mapa do passageiro: motoristas online e livres perto do ponto informado.
     * Devolve só posição e um código anônimo (nunca nome, telefone ou id real).
     */
    public function nearby(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.5|max:15',
        ]);
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        $radius = (float) ($data['radius_km'] ?? 5);
        $dLat = $radius / 111.0;
        $dLng = $radius / (111.0 * max(0.2, cos(deg2rad($lat))));

        $drivers = \App\Models\Driver::query()
            ->where('online', true)
            ->where('background_check_status', 'approved')
            ->whereNotNull('current_lat')
            ->where('last_location_at', '>=', now()->subMinutes(3))
            ->whereBetween('current_lat', [$lat - $dLat, $lat + $dLat])
            ->whereBetween('current_lng', [$lng - $dLng, $lng + $dLng])
            ->limit(30)
            ->get(['user_id', 'current_lat', 'current_lng']);

        $livres = $drivers->filter(
            fn ($d) => \Illuminate\Support\Facades\Redis::get("driver:{$d->user_id}:status") !== 'busy'
        );

        return response()->json([
            'drivers' => $livres->values()->map(fn ($d) => [
                'id' => substr(hash('sha256', $d->user_id.config('app.key')), 0, 12),
                'latitude' => (float) $d->current_lat,
                'longitude' => (float) $d->current_lng,
            ]),
        ]);
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
