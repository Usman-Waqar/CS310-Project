<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->walletService->buildWalletPayload($request->user()),
        ]);
    }

    /**
     * @return array{
     *     main_wallet: ?array<string, mixed>,
     *     service_wallets: list<array<string, mixed>>
     * }
     */
    public static function buildWalletPayload(User $user): array
    {
        return app(WalletService::class)->buildWalletPayload($user);
    }
}
