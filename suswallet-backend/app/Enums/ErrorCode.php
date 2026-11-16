<?php

namespace App\Enums;

use Illuminate\Http\JsonResponse;

enum ErrorCode: string
{
    case Unauthenticated = 'UNAUTHENTICATED';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case AccountInactive = 'ACCOUNT_INACTIVE';
    case IdempotencyKeyConflict = 'IDEMPOTENCY_KEY_CONFLICT';
    case InsufficientFunds = 'INSUFFICIENT_FUNDS';
    case NoRefundableTapFound = 'NO_REFUNDABLE_TAP_FOUND';
    case InvalidWalletTarget = 'INVALID_WALLET_TARGET';
    case RateLimitExceeded = 'RATE_LIMIT_EXCEEDED';

    public function defaultHttpStatus(): int
    {
        return match ($this) {
            self::Unauthenticated, self::InvalidCredentials => 401,
            self::AccountInactive => 403,
            self::IdempotencyKeyConflict => 409,
            self::RateLimitExceeded => 429,
            self::InsufficientFunds, self::NoRefundableTapFound, self::InvalidWalletTarget => 422,
        };
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::Unauthenticated => 'Unauthenticated.',
            self::InvalidCredentials => 'Invalid university email or password.',
            self::AccountInactive => 'Your SUCard account is currently inactive.',
            self::IdempotencyKeyConflict => 'This idempotency key was already used with different request parameters.',
            self::InsufficientFunds => 'Insufficient wallet balance.',
            self::NoRefundableTapFound => 'No recent card tap found to refund.',
            self::InvalidWalletTarget => 'Selected campus service was not found.',
            self::RateLimitExceeded => 'Too many attempts. Please try again later.',
        };
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    public function toResponse(?string $message = null, ?int $status = null, array $headers = []): JsonResponse
    {
        return response()->json([
            'error_code' => $this->value,
            'message' => $message ?? $this->defaultMessage(),
        ], $status ?? $this->defaultHttpStatus(), $headers);
    }
}
