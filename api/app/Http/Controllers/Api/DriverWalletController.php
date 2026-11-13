<?php

namespace App\Http\Controllers\Api;

use App\Domains\Payments\Services\DriverWalletService;
use App\Domains\Payments\Services\DriverWithdrawalService;
use App\Domains\Payments\Services\EarningsReportService;
use App\Http\Controllers\Controller;
use App\Models\DriverWithdrawal;
use App\Domains\Payments\Services\DriverWalletRechargeService;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverWalletController extends Controller
{
    public function __construct(
        private DriverWalletService $walletService,
        private DriverWithdrawalService $withdrawalService,
        private DriverWalletRechargeService $rechargeService,
        private EarningsReportService $earningsReport,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $summary = $this->walletService->summaryForDriver($driver);

        return response()->json([
            ...$summary,
            'payout_pix_key' => $driver->payout_pix_key,
            'payout_pix_key_type' => $driver->payout_pix_key_type,
        ]);
    }

    public function updatePayoutAccount(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $data = $request->validate([
            'pix_key' => 'required|string|max:120',
            'pix_key_type' => 'required|in:CPF,CNPJ,EMAIL,PHONE,EVP',
        ]);

        $driver->update([
            'payout_pix_key' => $data['pix_key'],
            'payout_pix_key_type' => $data['pix_key_type'],
        ]);

        return response()->json(['message' => 'Chave Pix atualizada.']);
    }

    public function recharge(Request $request): JsonResponse
    {
        $driver = $request->user()->driver; abort_unless($driver, 403);
        $data = $request->validate(['amount' => 'required|numeric|min:20']);
        $tenant = Tenant::query()->findOrFail($driver->user->tenant_id);
        return response()->json($this->rechargeService->createPix($driver, $tenant, (float) $data['amount']), 201);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $tenant = Tenant::query()->findOrFail($driver->user->tenant_id);
        $withdrawal = $this->withdrawalService->request($driver, (float) $data['amount'], $tenant);

        return response()->json($withdrawal, 201);
    }

    public function earningsReport(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $weeks = (int) $request->integer('weeks', 12);
        $weeks = max(1, min(26, $weeks));

        return response()->json(['weeks' => $this->earningsReport->weeklyReport($driver, $weeks)]);
    }

    public function listWithdrawals(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $withdrawals = DriverWithdrawal::query()
            ->where('driver_id', $driver->user_id)
            ->latest()
            ->limit(30)
            ->get();

        return response()->json(['data' => $withdrawals]);
    }
}
