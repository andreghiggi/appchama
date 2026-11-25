<?php

namespace App\Domains\Payments\Services;

use App\Models\Driver;
use App\Models\DriverWallet;
use App\Models\DriverWalletTransaction;
use App\Models\DriverWithdrawal;
use App\Models\Ride;
use Illuminate\Support\Facades\DB;

class DriverWalletService
{
    /** Saldo minimo para comecar a operar (antes da primeira recarga). */
    public const MIN_TO_OPERATE = 20.0;
    /** Depois da primeira recarga o saldo pode ficar negativo ate este valor. */
    public const MAX_NEGATIVE = 20.0;
    public const MIN_WITHDRAWAL = 50.0;

    public function ensureWallet(Driver $driver): DriverWallet
    {
        return DriverWallet::query()->firstOrCreate(
            ['driver_id' => $driver->user_id],
            ['balance_available' => 0, 'prepaid_balance' => 0, 'total_earnings' => 0],
        );
    }

    /**
     * @return array{
     *     balance_available: float,
     *     total_earnings: float,
     *     direct_earnings: float,
     *     app_balance_earnings: float,
     *     pending_pix_rides: int,
     *     has_processing_withdrawal: bool,
     *     withdraw_hint: ?string
     * }
     */
    public function summaryForDriver(Driver $driver): array
    {
        $wallet = $this->ensureWallet($driver);

        $directEarnings = (float) DriverWalletTransaction::query()
            ->where('driver_id', $driver->user_id)
            ->where('type', 'ride_credit')
            ->where('description', 'like', '%pagamento direto%')
            ->sum('amount');

        $appBalanceEarnings = (float) DriverWalletTransaction::query()
            ->where('driver_id', $driver->user_id)
            ->where('type', 'ride_credit')
            ->where('description', 'like', 'Crédito corrida%')
            ->sum('amount');

        $pendingPixRides = Ride::query()
            ->where('driver_id', $driver->user_id)
            ->where('status', 'awaiting_payment')
            ->count();

        $hasProcessingWithdrawal = DriverWithdrawal::query()
            ->where('driver_id', $driver->user_id)
            ->where('status', 'processing')
            ->exists();

        $balance = (float) $wallet->balance_available;
        $activated = $wallet->activated_at !== null;
        $canOperate = $this->canOperate($wallet);
        $withdrawHint = null;
        $debtHint = null;

        if (! $canOperate) {
            $debtHint = $activated
                ? sprintf('Seu saldo está em R$ %s. Recarregue para voltar a aceitar corridas (o limite negativo é de R$ %s).', number_format($balance, 2, ',', '.'), number_format(self::MAX_NEGATIVE, 2, ',', '.'))
                : sprintf('Para começar a aceitar corridas, faça sua primeira recarga (mínimo R$ %s).', number_format(self::MIN_TO_OPERATE, 2, ',', '.'));
        }

        if ($hasProcessingWithdrawal) {
            $withdrawHint = 'Há um saque em processamento. Aguarde a conclusão antes de solicitar outro.';
        } elseif ($balance < self::MIN_WITHDRAWAL) {
            $withdrawHint = sprintf('O saque mínimo é de R$ %s.', number_format(self::MIN_WITHDRAWAL, 2, ',', '.'));
        }

        return [
            'balance_available' => $balance,
            // compatibilidade com apps antigos: a carteira agora e uma so
            'prepaid_balance' => $balance,
            'prepaid_recharge_minimum' => self::MIN_TO_OPERATE,
            'min_to_operate' => self::MIN_TO_OPERATE,
            'max_negative' => self::MAX_NEGATIVE,
            'withdraw_minimum' => self::MIN_WITHDRAWAL,
            'activated' => $activated,
            'can_operate' => $canOperate,
            'total_earnings' => (float) $wallet->total_earnings,
            'direct_earnings' => round($directEarnings, 2),
            'app_balance_earnings' => round($appBalanceEarnings, 2),
            'pending_pix_rides' => $pendingPixRides,
            'has_processing_withdrawal' => $hasProcessingWithdrawal,
            'withdraw_hint' => $withdrawHint,
            'prepaid_debt_hint' => $debtHint,
        ];
    }

    /** Antes da primeira recarga: saldo >= 20. Depois: saldo >= -20. */
    public function canOperate(DriverWallet $wallet): bool
    {
        $saldo = (float) $wallet->balance_available;

        return $wallet->activated_at !== null
            ? $saldo >= -self::MAX_NEGATIVE
            : $saldo >= self::MIN_TO_OPERATE;
    }

    public function creditFromRide(Ride $ride, bool $addToAvailableBalance): DriverWallet
    {
        abort_unless($ride->driver_id, 422, 'Corrida sem motorista.');

        $driver = $ride->driver ?? Driver::query()->findOrFail($ride->driver_id);
        $net = (float) $ride->driver_net_amount;

        return DB::transaction(function () use ($driver, $ride, $net, $addToAvailableBalance): DriverWallet {
            if (DriverWalletTransaction::query()
                ->where('ride_id', $ride->id)
                ->where('type', 'ride_credit')
                ->exists()) {
                return $this->ensureWallet($driver);
            }

            $wallet = $this->ensureWallet($driver);
            $wallet = DriverWallet::query()->lockForUpdate()->findOrFail($driver->user_id);

            $wallet->total_earnings = round((float) $wallet->total_earnings + $net, 2);

            if ($addToAvailableBalance) {
                $wallet->balance_available = round((float) $wallet->balance_available + $net, 2);
            }

            $wallet->save();

            DriverWalletTransaction::query()->create([
                'driver_id' => $driver->user_id,
                'type' => 'ride_credit',
                'amount' => $net,
                'balance_after' => $wallet->balance_available,
                'prepaid_balance_after' => 0,
                'ride_id' => $ride->id,
                'description' => $addToAvailableBalance
                    ? 'Crédito corrida #'.substr($ride->id, 0, 8)
                    : 'Ganho corrida (pagamento direto) #'.substr($ride->id, 0, 8),
                'created_at' => now(),
            ]);

            return $wallet->fresh();
        });
    }

    public function assertCanAcceptRide(Driver $driver, float $grossFare = 0, float $commissionPct = 0): void
    {
        $wallet = $this->ensureWallet($driver);
        if ($this->canOperate($wallet)) {
            return;
        }
        $saldo = (float) $wallet->balance_available;
        abort(422, $wallet->activated_at !== null
            ? sprintf('Seu saldo da carteira está em R$ %s (limite negativo R$ %s). Recarregue na aba Carteira para voltar a aceitar corridas.', number_format($saldo, 2, ',', '.'), number_format(self::MAX_NEGATIVE, 2, ',', '.'))
            : sprintf('Para aceitar corridas, faça sua primeira recarga na aba Carteira (mínimo R$ %s).', number_format(self::MIN_TO_OPERATE, 2, ',', '.')));
    }

    public function debitCommissionForRide(Ride $ride): DriverWallet
    {
        abort_unless($ride->driver_id, 422, 'Corrida sem motorista.');
        $driver = $ride->driver ?? Driver::query()->findOrFail($ride->driver_id);
        $fee = round((float) $ride->platform_fee_amount, 2);
        return DB::transaction(function () use ($driver, $ride, $fee): DriverWallet {
            $existing = DriverWalletTransaction::query()->where('ride_id', $ride->id)->where('type', 'commission_debit')->first();
            if ($existing) return $this->ensureWallet($driver);
            $wallet = DriverWallet::query()->lockForUpdate()->findOrFail($driver->user_id);
            // Usado no pagamento direto (o motorista ficou com o dinheiro): os 15% saem da carteira.
            // Nunca aborta por saldo insuficiente: a corrida já foi paga e travar desfaria o pagamento.
            // O saldo pode ficar negativo; novas corridas só dentro do limite (canOperate).
            $wallet->balance_available = round((float) $wallet->balance_available - $fee, 2);
            $wallet->save();
            DriverWalletTransaction::query()->create([
                'driver_id' => $driver->user_id, 'type' => 'commission_debit', 'amount' => -$fee,
                'balance_after' => $wallet->balance_available, 'prepaid_balance_after' => 0,
                'ride_id' => $ride->id, 'description' => 'Comissão '.($ride->tenant?->name ?: 'da plataforma').' de '.rtrim(rtrim(number_format((float) ($ride->tenant?->platform_commission_pct ?? 15), 2, '.', ''), '0'), '.').'% corrida #'.substr($ride->id, 0, 8), 'created_at' => now(),
            ]);
            return $wallet->fresh();
        });
    }

    public function creditPrepaidRecharge(
        string $driverId, float $amount, string $rechargeId
    ): DriverWallet {
        return DB::transaction(function () use ($driverId, $amount, $rechargeId): DriverWallet {
            $wallet = DriverWallet::query()->lockForUpdate()->firstOrCreate(
                ['driver_id' => $driverId], ['balance_available' => 0, 'prepaid_balance' => 0, 'total_earnings' => 0]
            );
            if (DriverWalletTransaction::query()->where('description', 'like', '%recarga #'.substr($rechargeId, 0, 8).'%')->exists()) return $wallet;
            $wallet->balance_available = round((float) $wallet->balance_available + $amount, 2);
            $wallet->activated_at ??= now();
            $wallet->save();
            DriverWalletTransaction::query()->create([
                'driver_id' => $driverId, 'type' => 'wallet_recharge', 'amount' => $amount,
                'balance_after' => $wallet->balance_available, 'prepaid_balance_after' => 0,
                'description' => 'Recarga carteira #'.substr($rechargeId, 0, 8), 'created_at' => now(),
            ]);
            return $wallet->fresh();
        });
    }

    /**
     * Credito manual de comissao feito pelo painel admin (ajuda nos testes e acertos): soma ao saldo da carteira de comissao
     * e deixa o motivo e quem fez no extrato do motorista.
     */
    public function creditManual(string $driverId, float $amount, ?string $motivo, ?string $feitoPor): DriverWallet
    {
        abort_unless($amount > 0 && $amount <= 5000, 422, 'Informe um valor entre R$ 0,01 e R$ 5.000,00.');

        return DB::transaction(function () use ($driverId, $amount, $motivo, $feitoPor): DriverWallet {
            // o admin pode estar vendo outra marca no painel: busca e cria sempre na marca do proprio motorista
            $tenantId = Driver::withoutGlobalScopes()->where('user_id', $driverId)->value('tenant_id');
            abort_unless($tenantId, 404, 'Motorista não encontrado.');
            $wallet = DriverWallet::withoutGlobalScopes()->lockForUpdate()->where('driver_id', $driverId)->first()
                ?? DriverWallet::withoutGlobalScopes()->forceCreate(['driver_id' => $driverId, 'tenant_id' => $tenantId, 'balance_available' => 0, 'prepaid_balance' => 0, 'total_earnings' => 0]);
            $wallet->balance_available = round((float) $wallet->balance_available + $amount, 2);
            $wallet->activated_at ??= now();
            $wallet->save();
            DriverWalletTransaction::withoutGlobalScopes()->forceCreate([
                'driver_id' => $driverId, 'tenant_id' => $tenantId, 'type' => 'manual_credit', 'amount' => round($amount, 2),
                'balance_after' => $wallet->balance_available, 'prepaid_balance_after' => 0,
                'description' => mb_substr('Crédito manual (admin'.($feitoPor ? ': '.$feitoPor : '').')'.($motivo ? ' - '.$motivo : ''), 0, 250),
                'created_at' => now(),
            ]);

            return $wallet->fresh();
        });
    }

    public function debitForWithdrawal(string $driverId, float $amount, string $withdrawalId): DriverWallet
    {
        return DB::transaction(function () use ($driverId, $amount, $withdrawalId): DriverWallet {
            if (DriverWalletTransaction::query()
                ->where('withdrawal_id', $withdrawalId)
                ->where('type', 'withdrawal_debit')
                ->exists()) {
                return DriverWallet::query()->firstOrCreate(
                    ['driver_id' => $driverId],
                    ['balance_available' => 0, 'prepaid_balance' => 0, 'total_earnings' => 0],
                );
            }

            $wallet = DriverWallet::query()->lockForUpdate()->firstOrCreate(
                ['driver_id' => $driverId],
                ['balance_available' => 0, 'prepaid_balance' => 0, 'total_earnings' => 0],
            );

            if ((float) $wallet->balance_available < $amount) {
                abort(422, 'Saldo insuficiente para saque.');
            }

            $wallet->balance_available = round((float) $wallet->balance_available - $amount, 2);
            $wallet->save();

            DriverWalletTransaction::query()->create([
                'driver_id' => $driverId,
                'type' => 'withdrawal_debit',
                'amount' => -$amount,
                'balance_after' => $wallet->balance_available,
                'withdrawal_id' => $withdrawalId,
                'description' => 'Saque solicitado',
                'created_at' => now(),
            ]);

            return $wallet->fresh();
        });
    }

    public function refundFailedWithdrawal(string $driverId, float $amount, string $withdrawalId, ?string $reason = null): DriverWallet
    {
        return DB::transaction(function () use ($driverId, $amount, $withdrawalId, $reason): DriverWallet {
            if (DriverWalletTransaction::query()
                ->where('withdrawal_id', $withdrawalId)
                ->where('type', 'withdrawal_refund')
                ->exists()) {
                return DriverWallet::query()->firstOrCreate(
                    ['driver_id' => $driverId],
                    ['balance_available' => 0, 'prepaid_balance' => 0, 'total_earnings' => 0],
                );
            }

            $wallet = DriverWallet::query()->lockForUpdate()->firstOrCreate(
                ['driver_id' => $driverId],
                ['balance_available' => 0, 'prepaid_balance' => 0, 'total_earnings' => 0],
            );
            $wallet->balance_available = round((float) $wallet->balance_available + $amount, 2);
            $wallet->save();

            DriverWalletTransaction::query()->create([
                'driver_id' => $driverId,
                'type' => 'withdrawal_refund',
                'amount' => $amount,
                'balance_after' => $wallet->balance_available,
                'withdrawal_id' => $withdrawalId,
                'description' => $reason ? 'Estorno saque: '.$reason : 'Estorno saque',
                'created_at' => now(),
            ]);

            return $wallet->fresh();
        });
    }
}
