<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => self::buildWalletPayload($request->user()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildWalletPayload(User $user): array
    {
        $mainWallet = $user->mainWallet()->first();
        $serviceWallets = $user->serviceWallets()
            ->get()
            ->sortBy(function ($wallet): int {
                return match ($wallet->vendor?->code) {
                    'SHUTTLE' => 1,
                    'CAFETERIA' => 2,
                    'PRINTING' => 3,
                    default => 99,
                };
            })
            ->values();

        return [
            'main_wallet' => $mainWallet?->toApiArray(),
            'service_wallets' => $serviceWallets->map(fn ($wallet) => $wallet->toApiArray())->all(),
        ];
    }
}
