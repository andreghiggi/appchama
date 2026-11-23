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
            // atualizacao parcial: o app manda so o que faltava no cadastro
            'name' => ['nullable', 'string', 'max:120'],
            'cpf' => ['sometimes', 'required', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'email' => ['sometimes', 'required', 'email', 'max:120'],
            'home_address' => ['sometimes', 'required', 'string', 'max:255'],
            'home_address_number' => ['sometimes', 'required', 'string', 'max:20'],
            'home_postal_code' => ['sometimes', 'required', 'string', 'size:8', 'regex:/^\d{8}$/'],
            'home_city' => ['sometimes', 'required', 'string', 'max:80'],
            'home_state' => ['sometimes', 'required', 'string', 'size:2'],
            'home_lat' => ['nullable', 'numeric'],
            'home_lng' => ['nullable', 'numeric'],
        ]);

        if (isset($data['cpf']) && ! Cpf::isValid($data['cpf'])) {
            abort(422, 'CPF inválido.');
        }

        $nome = trim((string) ($data['name'] ?? ''));
        if ($nome !== '' && preg_match('/\S+\s+\S+/u', $nome) !== 1) {
            abort(422, 'Informe nome e sobrenome.');
        }

        $novos = array_intersect_key($data, array_flip(['cpf', 'email', 'home_address', 'home_address_number', 'home_postal_code', 'home_city']));
        if (isset($data['home_state'])) {
            $novos['home_state'] = strtoupper($data['home_state']);
        }
        if ($nome !== '') {
            $novos['name'] = $nome;
        }
        foreach (['home_lat', 'home_lng'] as $coord) {
            if (isset($data[$coord])) {
                $novos[$coord] = $data[$coord];
            }
        }
        $user->update($novos);

        if ($user->asaas_customer_id) {
            $customers->syncCustomer($user->fresh(), $user->tenant);
        }

        return response()->json($user->fresh());
    }
}
