<?php

namespace App\Services;

use App\Exceptions\LedgerException;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Provision a user's Main Wallet and one Service Wallet per active vendor.
     */
    public function provisionUserWallets(User $user): void
    {
        $this->ensureMainWallet($user);

        $vendors = Vendor::where('is_active', true)->get();

        foreach ($vendors as $vendor) {
            $this->ensureServiceWallet($user, $vendor);
        }
    }

    /**
     * Credit the user's Main Wallet from a simulated bank card.
     *
     * @return array{transaction: Transaction, replayed: bool}
     */
    public function deposit(User $user, string $amount, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($user, $amount, $idempotencyKey): array {
            $this->ensureMainWallet($user);

            $mainWallet = Wallet::where('user_id', $user->id)
                ->where('is_main', true)
                ->lockForUpdate()
                ->firstOrFail();

            $normalizedAmount = Money::normalize($amount);

            if ($existing = $this->findIdempotentTransaction(
                user: $user,
                idempotencyKey: $idempotencyKey,
                expectedType: Transaction::TYPE_DEPOSIT,
                expectedAmount: $normalizedAmount,
                expectedSourceWalletId: null,
                expectedDestinationWalletId: $mainWallet->id,
            )) {
                return ['transaction' => $existing, 'replayed' => true];
            }

            $newBalance = Money::add((string) $mainWallet->balance, $normalizedAmount);
            $mainWallet->update(['balance' => $newBalance]);

            $transaction = Transaction::create([
                'initiated_by_user_id' => $user->id,
                'source_wallet_id' => null,
                'destination_wallet_id' => $mainWallet->id,
                'type' => Transaction::TYPE_DEPOSIT,
                'amount' => $normalizedAmount,
                'status' => 'COMPLETED',
                'idempotency_key' => $idempotencyKey,
                'is_refunded' => false,
                'description' => 'Card deposit',
                'metadata' => [
                    'vendor_code' => 'MAIN',
                    'subtitle' => 'Simulated top-up',
                ],
            ]);

            return ['transaction' => $transaction, 'replayed' => false];
        });
    }

    /**
     * Move funds one-way from the user's Main Wallet into a Service Wallet.
     *
     * @return array{transaction: Transaction, replayed: bool}
     */
    public function allocate(
        User $user,
        ?string $vendorCode,
        ?string $destinationWalletId,
        string $amount,
        string $idempotencyKey
    ): array {
        return DB::transaction(function () use ($user, $vendorCode, $destinationWalletId, $amount, $idempotencyKey): array {
            $mainWalletRef = $this->ensureMainWallet($user);

            if ($destinationWalletId !== null) {
                $targetWalletRef = Wallet::where('id', $destinationWalletId)
                    ->where('user_id', $user->id)
                    ->where('is_main', false)
                    ->with('vendor')
                    ->first();

                if (! $targetWalletRef || ! $targetWalletRef->vendor || ! $targetWalletRef->vendor->is_active) {
                    throw new LedgerException(
                        'INVALID_WALLET_TARGET',
                        'Funds can only be allocated to one of your active service wallets.'
                    );
                }

                if ($vendorCode !== null && $targetWalletRef->vendor->code !== strtoupper($vendorCode)) {
                    throw new LedgerException(
                        'INVALID_WALLET_TARGET',
                        'Provided vendor_code does not match destination_wallet_id.'
                    );
                }

                $vendor = $targetWalletRef->vendor;
            } else {
                $vendor = Vendor::where('code', strtoupper((string) $vendorCode))
                    ->where('is_active', true)
                    ->first();

                if (! $vendor) {
                    throw new LedgerException('INVALID_WALLET_TARGET', 'Selected campus service was not found.');
                }

                $targetWalletRef = $this->ensureServiceWallet($user, $vendor);
            }

            // Lock both wallets in deterministic UUID order before checking idempotency to prevent TOCTOU races
            $walletIds = [$mainWalletRef->id, $targetWalletRef->id];
            sort($walletIds);

            $locked = Wallet::whereIn('id', $walletIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $normalizedAmount = Money::normalize($amount);

            if ($existing = $this->findIdempotentTransaction(
                user: $user,
                idempotencyKey: $idempotencyKey,
                expectedType: Transaction::TYPE_ALLOCATION,
                expectedAmount: $normalizedAmount,
                expectedSourceWalletId: $mainWalletRef->id,
                expectedDestinationWalletId: $targetWalletRef->id,
            )) {
                return ['transaction' => $existing, 'replayed' => true];
            }

            /** @var Wallet $mainWallet */
            $mainWallet = $locked->get($mainWalletRef->id);
            /** @var Wallet $targetWallet */
            $targetWallet = $locked->get($targetWalletRef->id);

            if (Money::comp((string) $mainWallet->balance, $normalizedAmount) < 0) {
                throw new LedgerException(
                    'INSUFFICIENT_FUNDS',
                    'Insufficient Main Wallet balance for this transfer.'
                );
            }

            $mainWallet->update(['balance' => Money::sub((string) $mainWallet->balance, $normalizedAmount)]);
            $targetWallet->update(['balance' => Money::add((string) $targetWallet->balance, $normalizedAmount)]);

            $transaction = Transaction::create([
                'initiated_by_user_id' => $user->id,
                'source_wallet_id' => $mainWallet->id,
                'destination_wallet_id' => $targetWallet->id,
                'type' => Transaction::TYPE_ALLOCATION,
                'amount' => $normalizedAmount,
                'status' => 'COMPLETED',
                'idempotency_key' => $idempotencyKey,
                'is_refunded' => false,
                'description' => 'Allocated to '.$vendor->name,
                'metadata' => [
                    'vendor_code' => $vendor->code,
                    'vendor_name' => $vendor->name,
                    'subtitle' => 'Main → '.$vendor->name,
                ],
            ]);

            return ['transaction' => $transaction, 'replayed' => false];
        });
    }

    /**
     * Simulate a card tap against a specific service wallet.
     *
     * @return array{transaction: Transaction, replayed: bool}
     */
    public function simulateTap(
        User $user,
        string $vendorCode,
        ?string $amount,
        string $idempotencyKey,
        ?string $description = null
    ): array {
        return DB::transaction(function () use ($user, $vendorCode, $amount, $idempotencyKey, $description): array {
            $vendor = Vendor::where('code', strtoupper($vendorCode))
                ->where('is_active', true)
                ->first();

            if (! $vendor) {
                throw new LedgerException('INVALID_WALLET_TARGET', 'Selected campus service was not found.');
            }

            $mainWalletRef = $this->ensureMainWallet($user);
            $serviceWalletRef = $this->ensureServiceWallet($user, $vendor);

            // Lock Main + Service wallets in deterministic UUID order as a per-user mutex before checking idempotency
            $walletIds = [$mainWalletRef->id, $serviceWalletRef->id];
            sort($walletIds);

            $locked = Wallet::whereIn('id', $walletIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $chargeAmount = Money::normalize($amount ?? (string) $vendor->default_tap_price);

            $defaultTitles = [
                'SHUTTLE' => 'Shuttle fare',
                'CAFETERIA' => 'Cafeteria',
                'PRINTING' => 'Print job',
            ];

            $resolvedDescription = $description ?: ($defaultTitles[$vendor->code] ?? $vendor->name.' tap');

            if ($existing = $this->findIdempotentTransaction(
                user: $user,
                idempotencyKey: $idempotencyKey,
                expectedType: Transaction::TYPE_CARD_TAP,
                expectedAmount: $chargeAmount,
                expectedSourceWalletId: $serviceWalletRef->id,
                expectedDestinationWalletId: null,
                expectedDescription: $resolvedDescription,
            )) {
                return ['transaction' => $existing, 'replayed' => true];
            }

            /** @var Wallet $serviceWallet */
            $serviceWallet = $locked->get($serviceWalletRef->id);

            if (Money::comp((string) $serviceWallet->balance, $chargeAmount) < 0) {
                throw new LedgerException(
                    'INSUFFICIENT_FUNDS',
                    'Insufficient balance in your '.$vendor->name.' wallet.'
                );
            }

            $serviceWallet->update(['balance' => Money::sub((string) $serviceWallet->balance, $chargeAmount)]);

            $transaction = Transaction::create([
                'initiated_by_user_id' => $user->id,
                'source_wallet_id' => $serviceWallet->id,
                'destination_wallet_id' => null,
                'type' => Transaction::TYPE_CARD_TAP,
                'amount' => $chargeAmount,
                'status' => 'COMPLETED',
                'idempotency_key' => $idempotencyKey,
                'is_refunded' => false,
                'description' => $resolvedDescription,
                'metadata' => [
                    'vendor_code' => $vendor->code,
                    'vendor_name' => $vendor->name,
                    'subtitle' => 'Card tap · '.$vendor->name,
                ],
            ]);

            return ['transaction' => $transaction, 'replayed' => false];
        });
    }

    /**
     * Cancel and refund the user's most recent unrefunded CARD_TAP transaction.
     *
     * @return array{transaction: Transaction, replayed: bool}
     */
    public function refundLastTap(User $user, string $idempotencyKey, ?string $vendorCode = null): array
    {
        return DB::transaction(function () use ($user, $idempotencyKey, $vendorCode): array {
            $this->ensureMainWallet($user);

            // Lock user's Main Wallet first as a per-user mutex to serialize concurrent refund requests
            Wallet::where('user_id', $user->id)
                ->where('is_main', true)
                ->lockForUpdate()
                ->firstOrFail();

            $expectedVendor = $vendorCode !== null ? strtoupper($vendorCode) : null;

            if ($existing = $this->findIdempotentTransaction(
                user: $user,
                idempotencyKey: $idempotencyKey,
                expectedType: Transaction::TYPE_REFUND,
                expectedVendorCode: $expectedVendor,
            )) {
                return ['transaction' => $existing, 'replayed' => true];
            }

            $tapQuery = Transaction::where('initiated_by_user_id', $user->id)
                ->where('type', Transaction::TYPE_CARD_TAP)
                ->where('is_refunded', false);

            if ($vendorCode !== null) {
                $upperCode = strtoupper($vendorCode);
                $tapQuery->whereHas('sourceWallet.vendor', function ($query) use ($upperCode): void {
                    $query->where('code', $upperCode);
                });
            }

            $lastTap = $tapQuery->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $lastTap || ! $lastTap->source_wallet_id) {
                throw new LedgerException(
                    'NO_REFUNDABLE_TAP_FOUND',
                    'No recent card tap found to refund.'
                );
            }

            $serviceWallet = Wallet::where('id', $lastTap->source_wallet_id)
                ->with('vendor')
                ->lockForUpdate()
                ->firstOrFail();

            $refundAmount = Money::normalize((string) $lastTap->amount);

            $lastTap->update(['is_refunded' => true]);
            $serviceWallet->update(['balance' => Money::add((string) $serviceWallet->balance, $refundAmount)]);

            $vendorCodeResolved = $serviceWallet->vendor?->code ?? ($lastTap->metadata['vendor_code'] ?? 'SERVICE');
            $vendorNameResolved = $serviceWallet->vendor?->name ?? ($lastTap->metadata['vendor_name'] ?? 'Service');

            $transaction = Transaction::create([
                'initiated_by_user_id' => $user->id,
                'source_wallet_id' => null,
                'destination_wallet_id' => $serviceWallet->id,
                'type' => Transaction::TYPE_REFUND,
                'amount' => $refundAmount,
                'status' => 'COMPLETED',
                'idempotency_key' => $idempotencyKey,
                'is_refunded' => false,
                'description' => 'Refund',
                'metadata' => [
                    'vendor_code' => $vendorCodeResolved,
                    'vendor_name' => $vendorNameResolved,
                    'requested_vendor_code' => $expectedVendor,
                    'refunded_transaction_id' => $lastTap->id,
                    'subtitle' => 'Refunded '.$lastTap->description,
                ],
            ]);

            return ['transaction' => $transaction, 'replayed' => false];
        });
    }

    private function findIdempotentTransaction(
        User $user,
        string $idempotencyKey,
        string $expectedType,
        ?string $expectedAmount = null,
        ?string $expectedSourceWalletId = null,
        ?string $expectedDestinationWalletId = null,
        ?string $expectedVendorCode = null,
        ?string $expectedDescription = null,
    ): ?Transaction {
        $existing = Transaction::where('idempotency_key', $idempotencyKey)
            ->with(['sourceWallet.vendor', 'destinationWallet.vendor'])
            ->first();

        if (! $existing) {
            return null;
        }

        if ($existing->initiated_by_user_id !== $user->id) {
            throw new LedgerException(
                'IDEMPOTENCY_KEY_CONFLICT',
                'This idempotency key belongs to another request.',
                409
            );
        }

        $refundVendorMismatch = $expectedType === Transaction::TYPE_REFUND
            && (
                array_key_exists('requested_vendor_code', $existing->metadata ?? [])
                    ? $existing->metadata['requested_vendor_code'] !== $expectedVendorCode
                    : ($existing->metadata['vendor_code'] ?? null) !== $expectedVendorCode
            );

        $mismatch = $existing->type !== $expectedType
            || ($expectedAmount !== null && Money::comp((string) $existing->amount, $expectedAmount) !== 0)
            || ($expectedType !== Transaction::TYPE_REFUND && (
                $existing->source_wallet_id !== $expectedSourceWalletId
                || $existing->destination_wallet_id !== $expectedDestinationWalletId
            ))
            || $refundVendorMismatch
            || ($expectedDescription !== null && $existing->description !== $expectedDescription);

        if ($mismatch) {
            throw new LedgerException(
                'IDEMPOTENCY_KEY_CONFLICT',
                'This idempotency key was already used with different request parameters.',
                409
            );
        }

        return $existing;
    }

    private function ensureMainWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'vendor_id' => null, 'is_main' => true],
            ['balance' => '0.00', 'currency' => 'TRY']
        );
    }

    private function ensureServiceWallet(User $user, Vendor $vendor): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'vendor_id' => $vendor->id],
            ['is_main' => false, 'balance' => '0.00', 'currency' => 'TRY']
        );
    }
}
