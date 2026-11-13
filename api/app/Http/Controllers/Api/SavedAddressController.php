<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = SavedAddress::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get(['id', 'label', 'address', 'lat', 'lng']);

        return response()->json(['data' => $addresses]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:60',
            'address' => 'required|string|max:255',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $address = SavedAddress::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($address, 201);
    }

    public function destroy(Request $request, SavedAddress $savedAddress): JsonResponse
    {
        abort_unless($savedAddress->user_id === $request->user()->id, 403);

        $savedAddress->delete();

        return response()->json(['deleted' => true]);
    }
}
