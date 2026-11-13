<?php

namespace App\Http\Controllers\Api;

use App\Domains\Payments\Services\AsaasCustomerService;
use App\Http\Controllers\Controller;
use App\Support\Cpf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request, AsaasCustomerService $customers): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isPassenger(), 403);

        $data = $request->validate([
            'cpf' => ['required', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'email' => ['required', 'email', 'max:120'],
            'home_address' => ['required', 'string', 'max:255'],
            'home_address_number' => ['required', 'string', 'max:20'],
            'home_postal_code' => ['required', 'string', 'size:8', 'regex:/^\d{8}$/'],
            'home_city' => ['required', 'string', 'max:80'],
            'home_state' => ['required', 'string', 'size:2'],
            'home_lat' => ['nullable', 'numeric'],
            'home_lng' => ['nullable', 'numeric'],
        ]);

        if (! Cpf::isValid($data['cpf'])) {
            abort(422, 'CPF inválido.');
        }

        $user->update([
            'cpf' => $data['cpf'],
            'email' => $data['email'],
            'home_address' => $data['home_address'],
            'home_address_number' => $data['home_address_number'],
            'home_postal_code' => $data['home_postal_code'],
            'home_city' => $data['home_city'],
            'home_state' => strtoupper($data['home_state']),
            'home_lat' => $data['home_lat'] ?? $user->home_lat,
            'home_lng' => $data['home_lng'] ?? $user->home_lng,
        ]);

        if ($user->asaas_customer_id) {
            $customers->syncCustomer($user->fresh(), $user->tenant);
        }

        return response()->json($user->fresh());
    }
}
