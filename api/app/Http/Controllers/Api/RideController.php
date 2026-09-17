<?php

namespace App\Http\Controllers\Api;

use App\Domains\Maps\Services\ServiceAreaService;
use App\Domains\Rides\Services\RideService;
use App\Domains\Rides\Services\RideTrackingService;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function __construct(
        private RideService $rideService,
        private RideTrackingService $trackingService,
        private ServiceAreaService $serviceArea,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Ride::query()->with(['passenger:id,name', 'driver.user:id,name', 'city']);

        if ($request->user()->isPassenger()) {
            $query->where('passenger_id', $request->user()->id);
        } elseif ($request->user()->isDriver()) {
            $query->where('driver_id', $request->user()->id);
        }

        $rides = $query->latest('requested_at')->paginate(20);

        return response()->json($rides);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isPassenger(), 403);

        $data = $request->validate([
            'city_id' => 'required|uuid',
            'origin_lat' => 'required|numeric',
            'origin_lng' => 'required|numeric',
            'origin_address' => 'nullable|string|max:255',
            'destination_lat' => 'required|numeric',
            'destination_lng' => 'required|numeric',
            'destination_address' => 'nullable|string|max:255',
        ]);

        $city = \App\Models\City::query()->findOrFail($data['city_id']);

        $this->serviceArea->assertWithinServiceArea(
            (float) $data['origin_lat'],
            (float) $data['origin_lng'],
            $city,
            null,
            'origem da corrida',
        );
        $this->serviceArea->assertWithinServiceArea(
            (float) $data['destination_lat'],
            (float) $data['destination_lng'],
            $city,
            null,
            'destino',
        );

        $ride = $this->rideService->request($request->user(), $data);

        return response()->json($this->trackingService->mergeIntoRide($ride), 202);
    }

    public function estimate(Request $request): JsonResponse
    {
        abort_unless($request->user()->isPassenger(), 403);

        $data = $request->validate([
            'city_id' => 'required|uuid',
            'origin_lat' => 'required|numeric',
            'origin_lng' => 'required|numeric',
            'destination_lat' => 'required|numeric',
            'destination_lng' => 'required|numeric',
        ]);

        $city = \App\Models\City::query()->findOrFail($data['city_id']);

        $this->serviceArea->assertWithinServiceArea(
            (float) $data['origin_lat'],
            (float) $data['origin_lng'],
            $city,
            null,
            'origem da corrida',
        );
        $this->serviceArea->assertWithinServiceArea(
            (float) $data['destination_lat'],
            (float) $data['destination_lng'],
            $city,
            null,
            'destino',
        );

        $estimate = app(\App\Domains\Rides\Services\FareCalculatorService::class)->estimate(
            (float) $data['origin_lat'],
            (float) $data['origin_lng'],
            (float) $data['destination_lat'],
            (float) $data['destination_lng'],
            $city,
        );

        return response()->json($estimate);
    }

    /**
     * Corrida em andamento do usuário autenticado, usada pelos apps para
     * retomar o acompanhamento depois de um recarregamento de página.
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();

        // Corrida real nunca dura horas; o teto evita que uma corrida
        // abandonada em testes sequestre a tela ao abrir o app.
        $query = Ride::query()
            ->with(['passenger:id,name,phone', 'driver.user:id,name,phone', 'driver.vehicles', 'driver.city:id,departure_lat,departure_lng', 'city', 'ratings'])
            ->where('requested_at', '>=', now()->subHours(6));

        if ($user->isDriver()) {
            // Motorista não retoma tela de pagamento após recarregar — só corrida em andamento.
            $query->where('driver_id', $user->id)
                ->whereIn('status', ['accepted', 'driver_arrived', 'in_progress']);
        } else {
            $query->where('passenger_id', $user->id)
                ->where(function ($q): void {
                    $q->whereIn('status', ['accepted', 'driver_arrived', 'in_progress', 'awaiting_payment'])
                        ->orWhere(function ($searching): void {
                            $searching->where('status', 'searching')
                                ->where('requested_at', '>=', now()->subMinutes(10));
                        });
                });
        }

        $ride = $query->latest('requested_at')->first();

        if (! $ride) {
            return response()->json(['ride' => null]);
        }

        return response()->json([
            'ride' => $this->trackingService->mergeIntoRide($ride),
        ]);
    }

    public function show(Ride $ride): JsonResponse
    {
        $this->authorizeRide(request()->user(), $ride);

        $ride->load(['ratings']);

        return response()->json($this->trackingService->mergeIntoRide($ride));
    }

    public function accept(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->isDriver(), 403);

        $ride = $this->rideService->accept($ride, $request->user());

        return response()->json($this->trackingService->mergeIntoRide($ride));
    }

    public function decline(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->isDriver(), 403);
        $this->rideService->decline($ride, $request->user());

        return response()->json(['message' => 'Corrida recusada.']);
    }

    public function arrive(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->isDriver(), 403);

        $ride = $this->rideService->arrive($ride, $request->user());

        return response()->json($this->trackingService->mergeIntoRide($ride));
    }

    public function start(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->isDriver(), 403);

        $ride = $this->rideService->start($ride, $request->user());

        return response()->json($this->trackingService->mergeIntoRide($ride));
    }

    public function complete(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->isDriver(), 403);

        $ride = $this->rideService->complete($ride, $request->user());

        return response()->json($this->trackingService->mergeIntoRide($ride));
    }

    public function cancel(Ride $ride, Request $request): JsonResponse
    {
        $this->authorizeRide($request->user(), $ride);

        $data = $request->validate(['reason' => 'nullable|string|max:255']);

        $ride = $this->rideService->cancel($ride, $request->user(), $data['reason'] ?? null);

        return response()->json($this->trackingService->mergeIntoRide($ride));
    }

    public function rate(Ride $ride, Request $request): JsonResponse
    {
        $this->authorizeRide($request->user(), $ride);

        $data = $request->validate([
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:255',
        ]);

        $this->rideService->rate($ride, $request->user(), $data['score'], $data['comment'] ?? null);

        return response()->json(['message' => 'Avaliação registrada.']);
    }

    private function authorizeRide($user, Ride $ride): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->id !== $ride->passenger_id && $user->id !== $ride->driver_id) {
            abort(403);
        }
    }
}
