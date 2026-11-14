<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    public function index(Request $request): JsonResponse
    {
        if ($request->has('wallet')) {
            $request->merge(['wallet' => strtoupper((string) $request->query('wallet'))]);
        }

        $validated = $request->validate([
            'wallet' => ['nullable', 'in:ALL,MAIN,SHUTTLE,CAFETERIA,PRINTING'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $walletFilter = $validated['wallet'] ?? 'ALL';
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        // Single grouped aggregate query for current month's summary across all wallets
        $monthlyTotals = $user->transactions()
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->whereIn('type', [
                Transaction::TYPE_DEPOSIT,
                Transaction::TYPE_CARD_TAP,
                Transaction::TYPE_REFUND,
            ])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->groupBy('type')
            ->pluck('total', 'type');

        $depositedThisMonth = Money::normalize((string) ($monthlyTotals[Transaction::TYPE_DEPOSIT] ?? '0.00'));
        $tappedThisMonth = Money::normalize((string) ($monthlyTotals[Transaction::TYPE_CARD_TAP] ?? '0.00'));
        $refundedThisMonth = Money::normalize((string) ($monthlyTotals[Transaction::TYPE_REFUND] ?? '0.00'));

        $rawSpent = Money::sub($tappedThisMonth, $refundedThisMonth);
        $spentThisMonth = Money::comp($rawSpent, '0.00') > 0 ? $rawSpent : '0.00';

        // Filtered transaction list
        $query = $user->transactions()
            ->with(['sourceWallet.vendor', 'destinationWallet.vendor'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($walletFilter === 'MAIN') {
            $mainWalletId = $user->mainWallet()->value('id');
            $query->where(function ($sub) use ($mainWalletId): void {
                $sub->where('source_wallet_id', $mainWalletId)
                    ->orWhere('destination_wallet_id', $mainWalletId);
            });
        } elseif ($walletFilter !== 'ALL') {
            $targetWalletId = $user->wallets()
                ->whereHas('vendor', fn ($q) => $q->where('code', $walletFilter))
                ->value('id');

            if ($targetWalletId) {
                $query->where(function ($sub) use ($targetWalletId): void {
                    $sub->where('source_wallet_id', $targetWalletId)
                        ->orWhere('destination_wallet_id', $targetWalletId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (isset($validated['limit'])) {
            $query->limit((int) $validated['limit']);
        }

        $transactions = $query->get();

        return response()->json([
            'data' => [
                'filter' => $walletFilter,
                'summary' => [
                    'deposited_this_month' => $depositedThisMonth,
                    'deposited_this_month_formatted' => Money::formatTry($depositedThisMonth, '+'),
                    'spent_this_month' => $spentThisMonth,
                    'spent_this_month_formatted' => Money::formatTry($spentThisMonth, '-'),
                ],
                'transactions' => $transactions->map(
                    fn (Transaction $tx) => $tx->toApiArray($walletFilter)
                )->all(),
            ],
        ]);
    }

    public function deposit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:10', 'max:5000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->walletService->deposit(
            user: $request->user(),
            amount: Money::normalize((string) $validated['amount']),
            idempotencyKey: $validated['idempotency_key']
        );

        return $this->ledgerResponse($request, $result, 'Deposit completed.');
    }

    public function allocate(Request $request): JsonResponse
    {
        if ($request->filled('vendor_code')) {
            $request->merge(['vendor_code' => strtoupper((string) $request->input('vendor_code'))]);
        }

        $validated = $request->validate([
            'vendor_code' => ['required_without:destination_wallet_id', 'nullable', 'in:SHUTTLE,CAFETERIA,PRINTING'],
            'destination_wallet_id' => ['required_without:vendor_code', 'nullable', 'uuid'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:50000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->walletService->allocate(
            user: $request->user(),
            vendorCode: $validated['vendor_code'] ?? null,
            destinationWalletId: $validated['destination_wallet_id'] ?? null,
            amount: Money::normalize((string) $validated['amount']),
            idempotencyKey: $validated['idempotency_key']
        );

        return $this->ledgerResponse($request, $result, 'Funds allocated to service wallet.');
    }

    public function simulateCardTap(Request $request): JsonResponse
    {
        if ($request->filled('vendor_code')) {
            $request->merge(['vendor_code' => strtoupper((string) $request->input('vendor_code'))]);
        }

        $validated = $request->validate([
            'vendor_code' => ['required', 'in:SHUTTLE,CAFETERIA,PRINTING'],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:5000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->walletService->simulateTap(
            user: $request->user(),
            vendorCode: $validated['vendor_code'],
            amount: isset($validated['amount']) ? Money::normalize((string) $validated['amount']) : null,
            idempotencyKey: $validated['idempotency_key'],
            description: $validated['description'] ?? null
        );

        return $this->ledgerResponse($request, $result, 'Card tap recorded.');
    }

    public function refundLastTap(Request $request): JsonResponse
    {
        if ($request->filled('vendor_code')) {
            $request->merge(['vendor_code' => strtoupper((string) $request->input('vendor_code'))]);
        }

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:64'],
            'vendor_code' => ['nullable', 'in:SHUTTLE,CAFETERIA,PRINTING'],
        ]);

        $result = $this->walletService->refundLastTap(
            user: $request->user(),
            idempotencyKey: $validated['idempotency_key'],
            vendorCode: $validated['vendor_code'] ?? null
        );

        return $this->ledgerResponse($request, $result, 'Last card tap refunded.');
    }

    /**
     * @param  array{transaction: Transaction, replayed: bool}  $result
     */
    private function ledgerResponse(Request $request, array $result, string $message): JsonResponse
    {
        $tx = $result['transaction']->loadMissing(['sourceWallet.vendor', 'destinationWallet.vendor']);
        $status = $result['replayed'] ? 200 : 201;

        return response()->json([
            'message' => $message,
            'idempotent_replay' => $result['replayed'],
            'data' => [
                'transaction' => $tx->toApiArray(),
                'wallets' => WalletController::buildWalletPayload($request->user()),
            ],
        ], $status);
    }
}
