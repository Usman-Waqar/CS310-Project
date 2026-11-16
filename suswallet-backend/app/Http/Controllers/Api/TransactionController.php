<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AllocateRequest;
use App\Http\Requests\Transaction\DepositRequest;
use App\Http\Requests\Transaction\ListTransactionsRequest;
use App\Http\Requests\Transaction\RefundLastTapRequest;
use App\Http\Requests\Transaction\SimulateCardTapRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    public function index(ListTransactionsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->walletService->listTransactionsWithSummary(
                user: $request->user(),
                walletFilter: $request->walletFilter(),
                limit: $request->limit(),
            ),
        ]);
    }

    public function deposit(DepositRequest $request): JsonResponse
    {
        $result = $this->walletService->deposit(
            user: $request->user(),
            amount: $request->normalizedAmount(),
            idempotencyKey: $request->validated('idempotency_key'),
        );

        return $this->ledgerResponse($request, $result, 'Deposit completed.');
    }

    public function allocate(AllocateRequest $request): JsonResponse
    {
        $result = $this->walletService->allocate(
            user: $request->user(),
            vendorCode: $request->validated('vendor_code'),
            destinationWalletId: $request->validated('destination_wallet_id'),
            amount: $request->normalizedAmount(),
            idempotencyKey: $request->validated('idempotency_key'),
        );

        return $this->ledgerResponse($request, $result, 'Funds allocated to service wallet.');
    }

    public function simulateCardTap(SimulateCardTapRequest $request): JsonResponse
    {
        $result = $this->walletService->simulateTap(
            user: $request->user(),
            vendorCode: $request->validated('vendor_code'),
            amount: $request->nullableNormalizedAmount(),
            idempotencyKey: $request->validated('idempotency_key'),
            description: $request->validated('description'),
        );

        return $this->ledgerResponse($request, $result, 'Card tap recorded.');
    }

    public function refundLastTap(RefundLastTapRequest $request): JsonResponse
    {
        $result = $this->walletService->refundLastTap(
            user: $request->user(),
            idempotencyKey: $request->validated('idempotency_key'),
            vendorCode: $request->validated('vendor_code'),
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
                'transaction' => (new TransactionResource($tx))->resolve($request),
                'wallets' => $this->walletService->buildWalletPayload($request->user()),
            ],
        ], $status);
    }
}
