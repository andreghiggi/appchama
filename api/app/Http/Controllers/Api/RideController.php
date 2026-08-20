<?php

namespace App\Http\Controllers\Api;

use App\Domains\Rides\Services\RideService;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function __construct(private RideService $rideService) {}

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

        $ride = $this->rideService->request($request->user(), $data);

        return response()->json($ride, 202);
    }

    public function show(Ride $ride): JsonResponse
    {
        $this->authorizeRide(request()->user(), $ride);

        return response()->json($ride->load(['passenger', 'driver.user', 'city', 'ratings']));
    }

    public function accept(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->isDriver(), 403);

        return response()->json($this->rideService->accept($ride, $request->user()));
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

        return response()->json($this->rideService->arrive($ride, $request->user()));
    }

    public function start(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->isDriver(), 403);

        return response()->json($this->rideService->start($ride, $request->user()));
    }

    public function complete(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->isDriver(), 403);

        return response()->json($this->rideService->complete($ride, $request->user()));
    }

    public function cancel(Ride $ride, Request $request): JsonResponse
    {
        $this->authorizeRide($request->user(), $ride);

        $data = $request->validate(['reason' => 'nullable|string|max:255']);

        return response()->json($this->rideService->cancel($ride, $request->user(), $data['reason'] ?? null));
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
