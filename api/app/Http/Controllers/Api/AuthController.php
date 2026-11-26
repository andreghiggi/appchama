<?php

namespace App\Http\Controllers\Api;

use App\Domains\Auth\Services\OtpService;
use App\Domains\Drivers\Services\DriverRegistrationCodeService;
use App\Domains\Drivers\Services\DriverSelfRegistrationService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        private OtpService $otpService,
        private DriverRegistrationCodeService $registrationCodes,
        private DriverSelfRegistrationService $selfRegistration,
    ) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'tenant_slug' => 'required|string',
        ]);

        $this->otpService->send($data['phone'], $data['tenant_slug']);

        $payload = [
            'message' => 'OTP enviado.',
            'user_exists' => $this->otpService->phoneExists($data['phone'], $data['tenant_slug']),
        ];
        if (config('services.sms.expose_debug_code', false)) {
            $payload['debug_code'] = $this->otpService->peekLatestCode($data['phone'], $data['tenant_slug']);
        }

        return response()->json($payload);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'code' => 'required|string|size:6',
            'tenant_slug' => 'required|string',
            'name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:120',
            'cpf' => 'nullable|string|size:11|regex:/^\d{11}$/',
            'role' => 'nullable|in:passenger,driver',
            'home_address' => 'nullable|string|max:255',
            'home_address_number' => 'nullable|string|max:20',
            'home_postal_code' => 'nullable|string|size:8|regex:/^\d{8}$/',
            'home_city' => 'nullable|string|max:80',
            'home_state' => 'nullable|string|size:2',
            'home_lat' => 'nullable|numeric',
            'home_lng' => 'nullable|numeric',
        ]);

        if ($request->filled('name')) {
            abort_unless($request->filled('email'), 422, 'Informe o e-mail.');
            abort_unless($request->filled('cpf'), 422, 'Informe o CPF.');
            abort_unless(\App\Support\Cpf::isValid($data['cpf']), 422, 'CPF inválido.');
            abort_unless($request->filled('home_address_number'), 422, 'Informe o número do endereço.');
            abort_unless($request->filled('home_postal_code'), 422, 'Informe o CEP.');
            abort_unless($request->filled('home_city'), 422, 'Informe a cidade.');
            abort_unless($request->filled('home_state'), 422, 'Informe o estado (UF).');
        }

        $registerData = null;
        if ($request->filled('name')) {
            $registerData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'cpf' => $data['cpf'],
                'role' => $data['role'] ?? 'passenger',
                'home_address' => $data['home_address'] ?? null,
                'home_address_number' => $data['home_address_number'] ?? null,
                'home_postal_code' => $data['home_postal_code'] ?? null,
                'home_city' => $data['home_city'] ?? null,
                'home_state' => strtoupper($data['home_state'] ?? ''),
                'home_lat' => $data['home_lat'] ?? null,
                'home_lng' => $data['home_lng'] ?? null,
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
            'has_password' => filled($user->password),
        ]);
    }

    /** Passageiro entra com telefone e a senha que ele mesmo criou (sem gastar SMS). */
    public function passengerLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'password' => 'required|string|max:100',
            'tenant_slug' => 'required|string',
        ]);

        $tenant = Tenant::query()->where('slug', $data['tenant_slug'])->firstOrFail();
        $user = User::query()->where('tenant_id', $tenant->id)->where('phone', $data['phone'])->first();

        abort_if($user && $user->isDriver(), 422, 'Este telefone está cadastrado como motorista. Para pedir corridas, use outro telefone.');
        abort_if($user && $user->isPassenger() && blank($user->password), 422, 'Você ainda não criou uma senha. Toque em "Entrar com código por SMS".');
        abort_unless($user && $user->isPassenger() && Hash::check($data['password'], (string) $user->password), 422, 'Telefone ou senha incorretos.');
        abort_if($user->status === 'blocked', 403, 'Conta bloqueada. Fale com o suporte.');

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $user,
            'has_password' => true,
        ]);
    }

    /** Cria ou troca a senha do passageiro logado (depois de confirmar o telefone por codigo). */
    public function setPassword(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isPassenger(), 403, 'Somente a conta de passageiro define senha por aqui.');

        $data = $request->validate(
            ['password' => ['required', 'confirmed', Password::min(6)]],
            ['password.min' => 'A senha precisa ter pelo menos 6 caracteres.', 'password.confirmed' => 'As senhas não conferem.'],
        );
        $user->update(['password' => $data['password']]);

        return response()->json(['message' => 'Senha salva.']);
    }

    public function driverRegister(Request $request): JsonResponse
    {
        $this->normalizeCnhExpiry($request);

        $data = $request->validate([
            'tenant_slug' => 'required|string',
            'name' => 'required|string|max:120',
            'phone' => 'required|string|max:20',
            'password' => ['required', 'confirmed', Password::min(6)],
            'city_id' => 'required|uuid|exists:cities,id',
            'cnh_number' => 'required|string|max:20',
            'cnh_expiry' => 'required|date|after:today',
            'vehicle_plate' => 'required|string|max:10',
            'vehicle_model' => 'nullable|string|max:80',
            'vehicle_color' => 'nullable|string|max:40',
            'vehicle_year' => 'nullable|integer|min:1990|max:'.(date('Y') + 1),
            'is_female_driver' => 'nullable|boolean',
            'cnh_document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ], [
            'cnh_expiry.date' => 'Informe a validade da CNH no formato DD/MM/AAAA (ex: 13/09/2026).',
            'cnh_expiry.after' => 'A validade da CNH deve ser uma data futura.',
        ]);

        $user = $this->selfRegistration->register(
            $data,
            $request->file('cnh_document'),
            $data['tenant_slug'],
        );

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'message' => 'Cadastro enviado. Aguarde aprovação da equipe.',
        ], 201);
    }

    public function driverLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'tenant_slug' => 'required|string',
            'password' => 'required_without:registration_code|nullable|string',
            'registration_code' => 'required_without:password|nullable|string|max:12',
        ]);

        if (filled($data['password'] ?? null)) {
            $user = $this->registrationCodes->loginByPhoneAndPassword(
                $data['phone'],
                $data['password'],
                $data['tenant_slug'],
            );
        } else {
            $user = $this->registrationCodes->loginByPhoneAndCode(
                $data['phone'],
                $data['registration_code'],
                $data['tenant_slug'],
            );
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    private function normalizeCnhExpiry(Request $request): void
    {
        $raw = $request->input('cnh_expiry');
        if (! is_string($raw) || trim($raw) === '') {
            return;
        }

        $raw = trim($raw);

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $matches)) {
            $request->merge([
                'cnh_expiry' => sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]),
            ]);

            return;
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $matches)) {
            $request->merge([
                'cnh_expiry' => sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]),
            ]);
        }
    }
}
