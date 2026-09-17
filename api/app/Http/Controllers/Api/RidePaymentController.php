<?php

namespace App\Http\Controllers\Api;

use App\Domains\Payments\Services\RidePaymentService;
use App\Domains\Rides\Services\RideTrackingService;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RidePaymentController extends Controller
{
    public function __construct(
        private RidePaymentService $payments,
        private RideTrackingService $trackingService,
    ) {}

    public function show(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->id === $ride->passenger_id, 403);

        return response()->json($this->payments->paymentStatus($ride));
    }

    public function pay(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->id === $ride->passenger_id, 403);

        $data = $request->validate([
            'method' => 'required|in:pix,credit_card',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        $result = $this->payments->initiatePayment(
            $ride,
            $data['method'],
            $data['success_url'] ?? null,
            $data['cancel_url'] ?? null,
        );

        $response = $this->payments->paymentStatus($ride->fresh());

        if (isset($result['pix_qr_code'])) {
            $response['pix_qr_code'] = $result['pix_qr_code'];
            $response['pix_copy_paste'] = $result['pix_copy_paste'];
        }

        if (isset($result['checkout_url'])) {
            $response['checkout_url'] = $result['checkout_url'];
        }

        return response()->json($response);
    }

    public function chooseMethod(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->id === $ride->passenger_id, 403);

        $data = $request->validate([
            'method' => 'required|in:direct_driver',
        ]);

        return response()->json(
            $this->payments->choosePaymentMethod($ride, $data['method']),
        );
    }

    public function confirmDirect(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->id === $ride->driver_id, 403);

        $updated = $this->payments->confirmDirectPayment($ride);

        return response()->json($this->trackingService->mergeIntoRide($updated));
    }

    public function cancel(Ride $ride, Request $request): JsonResponse
    {
        abort_unless($request->user()->id === $ride->passenger_id, 403);

        return response()->json($this->payments->cancelPendingPayment($ride));
    }
}
