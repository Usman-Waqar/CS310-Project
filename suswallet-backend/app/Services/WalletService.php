<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletFilter;
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
        $existing = $user->wallets()->get(['id', 'is_main', 'vendor_id']);

        if (! $existing->contains('is_main', true)) {
            Wallet::create([
                'user_id' => $user->id,
                'vendor_id' => null,
                ...Wallet::defaultAttributes(true),
            ]);
        }

        $existingVendorIds = $existing->where('is_main', false)
            ->pluck('vendor_id')
            ->all();

        $vendors = Vendor::active()->get();

        foreach ($vendors as $vendor) {
            if (! in_array($vendor->id, $existingVendorIds, true)) {
                Wallet::create([
                    'user_id' => $user->id,
                    'vendor_id' => $vendor->id,
                    ...Wallet::defaultAttributes(false),
                ]);
            }
        }
    }

    /**
     * Provision missing service wallets for existing users when a vendor is created or activated.
     */
    public function provisionServiceWalletsForVendor(Vendor $vendor): void
    {
        if (! $vendor->is_active) {
            return;
        }

        $missingUserIds = User::whereDoesntHave('wallets', static function ($query) use ($vendor): void {
            $query->where('vendor_id', $vendor->id);
        })->pluck('id');

        foreach ($missingUserIds as $userId) {
            Wallet::create([
                'user_id' => $userId,
                'vendor_id' => $vendor->id,
                ...Wallet::defaultAttributes(false),
            ]);
        }
    }

    /**
     * Build the Main Wallet and sorted active Service Wallets payload in a single wallets query.
     *
     * @return array{
     *     main_wallet: ?array<string, mixed>,
     *     service_wallets: list<array<string, mixed>>
     * }
     */
    public function buildWalletPayload(User $user): array
    {
        $wallets = $user->wallets()->with('vendor')->get();

        /** @var Wallet|null $mainWallet */
        $mainWallet = $wallets->firstWhere('is_main', true);

        $serviceWallets = $wallets->where('is_main', false)
            ->filter(static fn (Wallet $wallet): bool => (bool) $wallet->vendor?->is_active)
            ->sortBy(static fn (Wallet $wallet): int => $wallet->vendor?->sortOrder() ?? 99)
            ->values();

        return [
            'main_wallet' => $mainWallet?->toApiArray(),
            'service_wallets' => $serviceWallets->map(
                static fn (Wallet $wallet): array => $wallet->toApiArray()
            )->all(),
        ];
    }

    /**
     * Query current-month summary metrics and filtered transactions for the student.
     *
     * @return array{
     *     filter: string,
     *     summary: array{
     *         deposited_this_month: string,
     *         deposited_this_month_formatted: string,
     *         spent_this_month: string,
     *         spent_this_month_formatted: string
     *     },
     *     transactions: list<array<string, mixed>>
     * }
     */
    public function listTransactionsWithSummary(
        User $user,
        WalletFilter|string $walletFilter = WalletFilter::All,
        ?int $limit = null
    ): array {
        $filter = WalletFilter::resolve($walletFilter);

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        // Single grouped aggregate query for current month's summary across all wallets
        $monthlyTotals = $user->transactions()
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->whereIn('type', [
                TransactionType::Deposit->value,
                TransactionType::CardTap->value,
                TransactionType::Refund->value,
            ])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->groupBy('type')
            ->pluck('total', 'type');

        $depositedThisMonth = Money::normalize((string) ($monthlyTotals[TransactionType::Deposit->value] ?? '0.00'));
        $tappedThisMonth = Money::normalize((string) ($monthlyTotals[TransactionType::CardTap->value] ?? '0.00'));
        $refundedThisMonth = Money::normalize((string) ($monthlyTotals[TransactionType::Refund->value] ?? '0.00'));

        $rawSpent = Money::sub($tappedThisMonth, $refundedThisMonth);
        $spentThisMonth = Money::comp($rawSpent, '0.00') > 0 ? $rawSpent : '0.00';

        $query = $user->transactions()
            ->with(['sourceWallet.vendor', 'destinationWallet.vendor'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($filter === WalletFilter::Main) {
            $mainWalletId = $user->mainWallet()->value('id');

            if ($mainWalletId) {
                $query->touchingWallet($mainWalletId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($filter !== WalletFilter::All) {
            $targetWalletId = $user->wallets()
                ->forVendorCode($filter->value)
                ->value('id');

            if ($targetWalletId) {
                $query->touchingWallet($targetWalletId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $transactions = $query->get();

        return [
            'filter' => $filter->value,
            'summary' => [
                'deposited_this_month' => $depositedThisMonth,
                'deposited_this_month_formatted' => Money::formatTry($depositedThisMonth, '+'),
                'spent_this_month' => $spentThisMonth,
                'spent_this_month_formatted' => Money::formatTry($spentThisMonth, '-'),
            ],
            'transactions' => $transactions->map(
                static fn (Transaction $tx): array => $tx->toApiArray($filter)
            )->all(),
        ];
    }

    /**
     * Credit the user's Main Wallet from a simulated bank card.
     *
     * @return array{transaction: Transaction, replayed: bool}
     */
    public function deposit(User $user, string $amount, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($user, $amount, $idempotencyKey): array {
            $mainWallet = $this->lockMainWallet($user);
            $normalizedAmount = Money::normalize($amount);

            if ($existing = $this->findIdempotentTransaction(
                user: $user,
                idempotencyKey: $idempotencyKey,
                expectedType: TransactionType::Deposit,
                expectedAmount: $normalizedAmount,
                expectedSourceWalletId: null,
                expectedDestinationWalletId: $mainWallet->id,
            )) {
                return ['transaction' => $existing, 'replayed' => true];
            }

            $mainWallet->update(['balance' => Money::add((string) $mainWallet->balance, $normalizedAmount)]);

            $transaction = Transaction::create([
                'initiated_by_user_id' => $user->id,
                'source_wallet_id' => null,
                'destination_wallet_id' => $mainWallet->id,
                'type' => TransactionType::Deposit,
                'amount' => $normalizedAmount,
                'status' => TransactionStatus::Completed,
                'idempotency_key' => $idempotencyKey,
                'is_refunded' => false,
                'description' => 'Card deposit',
                'metadata' => Transaction::depositMetadata(),
            ]);

            $mainWallet->setRelation('vendor', null);
            $this->hydrateTransactionRelations($transaction, null, $mainWallet);

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
            // Lock both wallets in a single deterministic UUID-ordered query before checking idempotency
            ['main' => $mainWallet, 'service' => $targetWallet, 'vendor' => $vendor] = $this->lockMainAndTargetServiceWallet(
                $user,
                $vendorCode,
                $destinationWalletId
            );

            $normalizedAmount = Money::normalize($amount);

            if ($existing = $this->findIdempotentTransaction(
                user: $user,
                idempotencyKey: $idempotencyKey,
                expectedType: TransactionType::Allocation,
                expectedAmount: $normalizedAmount,
                expectedSourceWalletId: $mainWallet->id,
                expectedDestinationWalletId: $targetWallet->id,
            )) {
                return ['transaction' => $existing, 'replayed' => true];
            }

            if (Money::comp((string) $mainWallet->balance, $normalizedAmount) < 0) {
                throw new LedgerException(
                    ErrorCode::InsufficientFunds,
                    'Insufficient Main Wallet balance for this transfer.'
                );
            }

            $mainWallet->update(['balance' => Money::sub((string) $mainWallet->balance, $normalizedAmount)]);
            $targetWallet->update(['balance' => Money::add((string) $targetWallet->balance, $normalizedAmount)]);

            $transaction = Transaction::create([
                'initiated_by_user_id' => $user->id,
                'source_wallet_id' => $mainWallet->id,
                'destination_wallet_id' => $targetWallet->id,
                'type' => TransactionType::Allocation,
                'amount' => $normalizedAmount,
                'status' => TransactionStatus::Completed,
                'idempotency_key' => $idempotencyKey,
                'is_refunded' => false,
                'description' => 'Allocated to '.$vendor->name,
                'metadata' => Transaction::allocationMetadata($vendor),
            ]);

            $mainWallet->setRelation('vendor', null);
            $targetWallet->setRelation('vendor', $vendor);
            $this->hydrateTransactionRelations($transaction, $mainWallet, $targetWallet);

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
                ->active()
                ->first();

            if (! $vendor) {
                throw new LedgerException(ErrorCode::InvalidWalletTarget, 'Selected campus service was not found.');
            }

            // Lock Main + Service wallets in a single deterministic UUID-ordered query before checking idempotency
            ['service' => $serviceWallet] = $this->lockWalletsByUserAndVendor($user, $vendor);

            $chargeAmount = Money::normalize($amount ?? (string) $vendor->default_tap_price);
            $resolvedDescription = $description ?: $vendor->defaultTapTitle();

            if ($existing = $this->findIdempotentTransaction(
                user: $user,
                idempotencyKey: $idempotencyKey,
                expectedType: TransactionType::CardTap,
                expectedAmount: $chargeAmount,
                expectedSourceWalletId: $serviceWallet->id,
                expectedDestinationWalletId: null,
                expectedDescription: $resolvedDescription,
            )) {
                return ['transaction' => $existing, 'replayed' => true];
            }

            if (Money::comp((string) $serviceWallet->balance, $chargeAmount) < 0) {
                throw new LedgerException(
                    ErrorCode::InsufficientFunds,
                    'Insufficient balance in your '.$vendor->name.' wallet.'
                );
            }

            $serviceWallet->update(['balance' => Money::sub((string) $serviceWallet->balance, $chargeAmount)]);

            $transaction = Transaction::create([
                'initiated_by_user_id' => $user->id,
                'source_wallet_id' => $serviceWallet->id,
                'destination_wallet_id' => null,
                'type' => TransactionType::CardTap,
                'amount' => $chargeAmount,
                'status' => TransactionStatus::Completed,
                'idempotency_key' => $idempotencyKey,
                'is_refunded' => false,
                'description' => $resolvedDescription,
                'metadata' => Transaction::cardTapMetadata($vendor),
            ]);

            $serviceWallet->setRelation('vendor', $vendor);
            $this->hydrateTransactionRelations($transaction, $serviceWallet, null);

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
            // Lock user's Main Wallet first as a per-user mutex to serialize concurrent refund requests
            $this->lockMainWallet($user);

            $expectedVendor = $vendorCode !== null ? strtoupper($vendorCode) : null;

            if ($existing = $this->findIdempotentTransaction(
                user: $user,
                idempotencyKey: $idempotencyKey,
                expectedType: TransactionType::Refund,
                expectedVendorCode: $expectedVendor,
            )) {
                return ['transaction' => $existing, 'replayed' => true];
            }

            $tapQuery = Transaction::where('initiated_by_user_id', $user->id)
                ->unrefundedTaps();

            if ($expectedVendor !== null) {
                $tapQuery->whereHas('sourceWallet', static function ($query) use ($expectedVendor): void {
                    $query->forVendorCode($expectedVendor);
                });
            }

            $lastTap = $tapQuery->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $lastTap || ! $lastTap->source_wallet_id) {
                throw new LedgerException(
                    ErrorCode::NoRefundableTapFound,
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

            $transaction = Transaction::create([
                'initiated_by_user_id' => $user->id,
                'source_wallet_id' => null,
                'destination_wallet_id' => $serviceWallet->id,
                'type' => TransactionType::Refund,
                'amount' => $refundAmount,
                'status' => TransactionStatus::Completed,
                'idempotency_key' => $idempotencyKey,
                'is_refunded' => false,
                'description' => 'Refund',
                'metadata' => Transaction::refundMetadata($lastTap, $serviceWallet->vendor, $expectedVendor),
            ]);

            $this->hydrateTransactionRelations($transaction, null, $serviceWallet);

            return ['transaction' => $transaction, 'replayed' => false];
        });
    }

    /**
     * Resolve the active vendor for an allocation and lock the user's Main and Service wallets in a single query.
     *
     * @return array{main: Wallet, service: Wallet, vendor: Vendor}
     */
    private function lockMainAndTargetServiceWallet(
        User $user,
        ?string $vendorCode,
        ?string $destinationWalletId
    ): array {
        if ($destinationWalletId !== null) {
            $locked = Wallet::where('user_id', $user->id)
                ->where(static function ($query) use ($destinationWalletId): void {
                    $query->where('is_main', true)
                        ->orWhere(static function ($sub) use ($destinationWalletId): void {
                            $sub->where('id', $destinationWalletId)->where('is_main', false);
                        });
                })
                ->with('vendor')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var Wallet|null $targetWallet */
            $targetWallet = $locked->firstWhere('id', $destinationWalletId);

            if (! $targetWallet || $targetWallet->is_main || ! $targetWallet->vendor || ! $targetWallet->vendor->is_active) {
                throw new LedgerException(
                    ErrorCode::InvalidWalletTarget,
                    'Funds can only be allocated to one of your active service wallets.'
                );
            }

            if ($vendorCode !== null && $targetWallet->vendor->codeValue() !== strtoupper($vendorCode)) {
                throw new LedgerException(
                    ErrorCode::InvalidWalletTarget,
                    'Provided vendor_code does not match destination_wallet_id.'
                );
            }

            /** @var Wallet|null $mainWallet */
            $mainWallet = $locked->firstWhere('is_main', true);

            if (! $mainWallet) {
                $this->ensureMainWallet($user);
                $relocked = $this->lockWalletsByUserAndVendor($user, $targetWallet->vendor);
                $mainWallet = $relocked['main'];
                $targetWallet = $relocked['service'];
            }

            return [
                'main' => $mainWallet,
                'service' => $targetWallet,
                'vendor' => $targetWallet->vendor,
            ];
        }

        $vendor = Vendor::where('code', strtoupper((string) $vendorCode))
            ->active()
            ->first();

        if (! $vendor) {
            throw new LedgerException(ErrorCode::InvalidWalletTarget, 'Selected campus service was not found.');
        }

        $lockedPair = $this->lockWalletsByUserAndVendor($user, $vendor);

        return [
            'main' => $lockedPair['main'],
            'service' => $lockedPair['service'],
            'vendor' => $vendor,
        ];
    }

    /**
     * Lock a user's Main Wallet and the Service Wallet for the given Vendor in one deterministic FOR UPDATE query.
     *
     * @return array{main: Wallet, service: Wallet}
     */
    private function lockWalletsByUserAndVendor(User $user, Vendor $vendor): array
    {
        $locked = Wallet::where('user_id', $user->id)
            ->where(static function ($query) use ($vendor): void {
                $query->where('is_main', true)
                    ->orWhere('vendor_id', $vendor->id);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var Wallet|null $mainWallet */
        $mainWallet = $locked->firstWhere('is_main', true);
        /** @var Wallet|null $serviceWallet */
        $serviceWallet = $locked->firstWhere('vendor_id', $vendor->id);

        if ($mainWallet && $serviceWallet) {
            return ['main' => $mainWallet, 'service' => $serviceWallet];
        }

        if (! $mainWallet) {
            $this->ensureMainWallet($user);
        }

        if (! $serviceWallet) {
            $this->ensureServiceWallet($user, $vendor);
        }

        $relocked = Wallet::where('user_id', $user->id)
            ->where(static function ($query) use ($vendor): void {
                $query->where('is_main', true)
                    ->orWhere('vendor_id', $vendor->id);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var Wallet $mainWallet */
        $mainWallet = $relocked->firstWhere('is_main', true);
        /** @var Wallet $serviceWallet */
        $serviceWallet = $relocked->firstWhere('vendor_id', $vendor->id);

        return ['main' => $mainWallet, 'service' => $serviceWallet];
    }

    private function lockMainWallet(User $user): Wallet
    {
        $wallet = Wallet::where('user_id', $user->id)
            ->main()
            ->lockForUpdate()
            ->first();

        if ($wallet !== null) {
            return $wallet;
        }

        $this->ensureMainWallet($user);

        return Wallet::where('user_id', $user->id)
            ->main()
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function findIdempotentTransaction(
        User $user,
        string $idempotencyKey,
        TransactionType|string $expectedType,
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
                ErrorCode::IdempotencyKeyConflict,
                'This idempotency key belongs to another request.'
            );
        }

        if (! $existing->matchesIdempotentPayload(
            expectedType: $expectedType,
            expectedAmount: $expectedAmount,
            expectedSourceWalletId: $expectedSourceWalletId,
            expectedDestinationWalletId: $expectedDestinationWalletId,
            expectedVendorCode: $expectedVendorCode,
            expectedDescription: $expectedDescription,
        )) {
            throw new LedgerException(
                ErrorCode::IdempotencyKeyConflict,
                'This idempotency key was already used with different request parameters.'
            );
        }

        return $existing;
    }

    private function hydrateTransactionRelations(
        Transaction $transaction,
        ?Wallet $sourceWallet,
        ?Wallet $destinationWallet
    ): void {
        $transaction->setRelation('sourceWallet', $sourceWallet);
        $transaction->setRelation('destinationWallet', $destinationWallet);
    }

    private function ensureMainWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'vendor_id' => null, 'is_main' => true],
            ['balance' => Wallet::DEFAULT_BALANCE, 'currency' => Wallet::CURRENCY_TRY]
        );
    }

    private function ensureServiceWallet(User $user, Vendor $vendor): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'vendor_id' => $vendor->id],
            Wallet::defaultAttributes(false)
        );
    }
}
