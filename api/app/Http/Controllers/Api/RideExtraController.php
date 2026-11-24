<?php

namespace App\Http\Controllers\Api;

use App\Domains\Rides\Services\RideExtraService;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\RideExtra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RideExtraController extends Controller
{
    public function __construct(private RideExtraService $extras) {}

    public function index(Request $request, Ride $ride): JsonResponse
    {
        return response()->json(['data' => $this->extras->listFor($ride, $request->user())->map(fn (RideExtra $e) => $this->formatar($e))->values()]);
    }

    public function store(Request $request, Ride $ride): JsonResponse
    {
        $d = $request->validate([
            'amount' => ['required', 'numeric'],
            'reason' => ['required', 'string', Rule::in(array_keys(RideExtra::REASONS))],
            'note' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json($this->formatar($this->extras->create($ride, $request->user(), (float) $d['amount'], $d['reason'], $d['note'] ?? null)), 201);
    }

    public function accept(Request $request, Ride $ride, RideExtra $extra): JsonResponse
    {
        return response()->json($this->formatar($this->extras->accept($ride, $request->user(), $extra)));
    }

    public function dispute(Request $request, Ride $ride, RideExtra $extra): JsonResponse
    {
        return response()->json($this->formatar($this->extras->dispute($ride, $request->user(), $extra)));
    }

    public function cancel(Request $request, Ride $ride, RideExtra $extra): JsonResponse
    {
        return response()->json($this->formatar($this->extras->cancel($ride, $request->user(), $extra)));
    }

    /** @return array<string, mixed> */
    private function formatar(RideExtra $e): array
    {
        return [
            'id' => $e->id,
            'ride_id' => $e->ride_id,
            'amount' => (float) $e->amount,
            'reason' => $e->reason,
            'reason_label' => $e->reasonLabel(),
            'note' => $e->note,
            'status' => $e->status,
            'created_at' => optional($e->created_at)->toIso8601String(),
        ];
    }
}
