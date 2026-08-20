<?php

namespace App\Http\Controllers\Api;

use App\Domains\Auth\Services\OtpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private OtpService $otpService) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'tenant_slug' => 'required|string',
        ]);

        $this->otpService->send($data['phone'], $data['tenant_slug']);

        return response()->json(['message' => 'OTP enviado.']);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'code' => 'required|string|size:6',
            'tenant_slug' => 'required|string',
            'name' => 'nullable|string|max:120',
            'role' => 'nullable|in:passenger,driver',
        ]);

        $registerData = null;
        if ($request->filled('name')) {
            $registerData = [
                'name' => $data['name'],
                'role' => $data['role'] ?? 'passenger',
            ];
        }

        $user = $this->otpService->verify(
            $data['phone'],
            $data['code'],
            $data['tenant_slug'],
            $registerData,
        );

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('driver'),
        ]);
    }
}
