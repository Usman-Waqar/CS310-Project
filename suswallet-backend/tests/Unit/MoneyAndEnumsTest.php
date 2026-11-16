<?php

namespace Tests\Unit;

use App\Enums\ErrorCode;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VendorCode;
use App\Enums\WalletFilter;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Wallet;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyAndEnumsTest extends TestCase
{
    public function test_money_arithmetic_and_normalization_use_exact_bcmath_scale_two(): void
    {
        $this->assertSame('1250.00', Money::normalize(' 1250 '));
        $this->assertSame('21.50', Money::normalize('21,5'));
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('1249.70', Money::sub('1250.00', '0.30'));
        $this->assertSame(0, Money::comp('100.0', '100.00'));
        $this->assertSame(1, Money::comp('100.01', '100.00'));
        $this->assertSame(-1, Money::comp('99.99', '100.00'));
    }

    public function test_money_formats_turkish_lira_with_dots_commas_and_signs(): void
    {
        $this->assertSame('₺0,00', Money::formatTry('0.00'));
        $this->assertSame('₺1.250,00', Money::formatTry('1250.00'));
        $this->assertSame('+₺500,00', Money::formatTry('500.00', '+'));
        $this->assertSame('-₺21,50', Money::formatTry('21.50', '-'));
        $this->assertSame('-₺12,34', Money::formatTry('-12.34'));
    }

    public function test_vendor_code_enum_centralizes_seed_metadata_and_sort_order(): void
    {
        $this->assertSame(['SHUTTLE', 'CAFETERIA', 'PRINTING'], VendorCode::values());
        $this->assertSame(1, VendorCode::Shuttle->sortOrder());
        $this->assertSame(2, VendorCode::Cafeteria->sortOrder());
        $this->assertSame(3, VendorCode::Printing->sortOrder());

        $this->assertSame('Shuttle fare', VendorCode::Shuttle->defaultTapTitle());
        $this->assertSame('Cafeteria', VendorCode::Cafeteria->defaultTapTitle());
        $this->assertSame('Print job', VendorCode::Printing->defaultTapTitle());

        $seeded = VendorCode::seedAll();
        $this->assertCount(3, $seeded);
        $this->assertSame('SHUTTLE', $seeded[0]['code']);
        $this->assertSame('21.50', $seeded[0]['default_tap_price']);
    }

    public function test_transaction_type_and_wallet_filter_resolve_context_aware_signs(): void
    {
        $this->assertSame('+', TransactionType::Deposit->resolveSign(WalletFilter::All));
        $this->assertSame('+', TransactionType::Refund->resolveSign(WalletFilter::Main));
        $this->assertSame('-', TransactionType::CardTap->resolveSign(WalletFilter::Shuttle));

        $this->assertSame('', TransactionType::Allocation->resolveSign(WalletFilter::All));
        $this->assertSame('-', TransactionType::Allocation->resolveSign(WalletFilter::Main));
        $this->assertSame('+', TransactionType::Allocation->resolveSign(WalletFilter::Shuttle));
        $this->assertSame('+', TransactionType::Allocation->resolveSign('cafeteria'));
        $this->assertSame('+', TransactionType::Allocation->resolveSign(WalletFilter::Printing));

        $this->assertSame(VendorCode::Shuttle, WalletFilter::Shuttle->vendorCode());
        $this->assertNull(WalletFilter::All->vendorCode());
        $this->assertNull(WalletFilter::Main->vendorCode());
        $this->assertSame(WalletFilter::Shuttle, WalletFilter::resolve(' shuttle '));
        $this->assertSame(WalletFilter::All, WalletFilter::resolve('invalid'));
        $this->assertSame(WalletFilter::All, WalletFilter::resolve(null));
        $this->assertSame('COMPLETED', TransactionStatus::Completed->value);
        $this->assertSame('IDEMPOTENCY_KEY_CONFLICT', ErrorCode::IdempotencyKeyConflict->value);
        $this->assertSame(401, ErrorCode::InvalidCredentials->defaultHttpStatus());
        $this->assertSame(403, ErrorCode::AccountInactive->defaultHttpStatus());
        $this->assertSame(409, ErrorCode::IdempotencyKeyConflict->defaultHttpStatus());
        $this->assertSame(422, ErrorCode::InsufficientFunds->defaultHttpStatus());
        $this->assertSame(429, ErrorCode::RateLimitExceeded->defaultHttpStatus());
        $this->assertSame('Invalid university email or password.', ErrorCode::InvalidCredentials->defaultMessage());

        $this->assertSame('TRY', Wallet::CURRENCY_TRY);
        $this->assertSame('0.00', Wallet::DEFAULT_BALANCE);
        $this->assertSame(
            ['is_main' => false, 'balance' => '0.00', 'currency' => 'TRY'],
            Wallet::defaultAttributes()
        );
        $this->assertSame(
            ['is_main' => true, 'balance' => '250.00', 'currency' => 'TRY'],
            Wallet::defaultAttributes(true, '250.00')
        );
        $this->assertSame('suswallet-mobile', LoginRequest::DEFAULT_DEVICE_NAME);
    }
}
