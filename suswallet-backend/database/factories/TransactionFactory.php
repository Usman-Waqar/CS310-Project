<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'initiated_by_user_id' => User::factory(),
            'source_wallet_id' => null,
            'destination_wallet_id' => null,
            'type' => TransactionType::Deposit,
            'amount' => '100.00',
            'status' => TransactionStatus::Completed,
            'idempotency_key' => 'factory-'.Str::uuid()->toString(),
            'is_refunded' => false,
            'description' => 'Card deposit',
            'metadata' => Transaction::depositMetadata(),
        ];
    }

    public function deposit(Wallet $destinationWallet, string $amount = '100.00'): static
    {
        return $this->state(fn (array $attributes): array => [
            'initiated_by_user_id' => $destinationWallet->user_id,
            'source_wallet_id' => null,
            'destination_wallet_id' => $destinationWallet->id,
            'type' => TransactionType::Deposit,
            'amount' => $amount,
            'description' => 'Card deposit',
            'metadata' => Transaction::depositMetadata(),
        ]);
    }

    public function allocation(Wallet $sourceWallet, Wallet $destinationWallet, string $amount = '50.00'): static
    {
        $vendor = $destinationWallet->vendor;
        $vendorName = $vendor?->name ?? 'Service';

        return $this->state(fn (array $attributes): array => [
            'initiated_by_user_id' => $sourceWallet->user_id,
            'source_wallet_id' => $sourceWallet->id,
            'destination_wallet_id' => $destinationWallet->id,
            'type' => TransactionType::Allocation,
            'amount' => $amount,
            'description' => 'Allocated to '.$vendorName,
            'metadata' => Transaction::allocationMetadata($vendor),
        ]);
    }

    public function cardTap(Wallet $sourceWallet, string $amount = '21.50', bool $isRefunded = false): static
    {
        $vendor = $sourceWallet->vendor;
        $defaultTitle = $vendor ? $vendor->defaultTapTitle() : 'Service tap';

        return $this->state(fn (array $attributes): array => [
            'initiated_by_user_id' => $sourceWallet->user_id,
            'source_wallet_id' => $sourceWallet->id,
            'destination_wallet_id' => null,
            'type' => TransactionType::CardTap,
            'amount' => $amount,
            'is_refunded' => $isRefunded,
            'description' => $defaultTitle,
            'metadata' => Transaction::cardTapMetadata($vendor),
        ]);
    }

    public function refund(Wallet $destinationWallet, Transaction $refundedTap): static
    {
        $vendor = $destinationWallet->vendor;

        return $this->state(fn (array $attributes): array => [
            'initiated_by_user_id' => $destinationWallet->user_id,
            'source_wallet_id' => null,
            'destination_wallet_id' => $destinationWallet->id,
            'type' => TransactionType::Refund,
            'amount' => (string) $refundedTap->amount,
            'description' => 'Refund',
            'metadata' => Transaction::refundMetadata(
                $refundedTap,
                $vendor,
                $vendor?->codeValue() ?? 'SERVICE'
            ),
        ]);
    }
}
