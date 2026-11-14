<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^(Expo|Exponent)PushToken\[.+\]$/'],
            'platform' => 'nullable|string|max:20',
        ]);
        $user = $request->user();

        // O mesmo aparelho pode trocar de conta: o token passa a pertencer a quem entrou por último.
        DeviceToken::withoutGlobalScopes()->updateOrCreate(
            ['token' => $data['token']],
            ['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'platform' => $data['platform'] ?? null, 'last_used_at' => now()],
        );

        return response()->json(['registrado' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:255']);
        DeviceToken::withoutGlobalScopes()->where('token', $data['token'])->where('user_id', $request->user()->id)->delete();

        return response()->json(['removido' => true]);
    }
}
