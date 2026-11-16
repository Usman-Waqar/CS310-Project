<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VendorCode;
use App\Http\Controllers\Api\WalletController;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Services\WalletService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SusWalletApiTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Vendor::seedDefaults();

        $this->student = User::factory()->create([
            'student_id' => '00031234',
            'su_username' => 'student',
            'email' => 'student@sabanciuniv.edu',
            'full_name' => 'Student Name',
            'phone' => '+905321234512',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_student_can_login_fetch_me_and_logout_with_sabanci_email(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'student@gmail.com',
            'password' => 'password123',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'student@sabanciuniv.edu',
            'password' => 'wrong-password',
        ])->assertStatus(401)
            ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'STUDENT@sabanciuniv.edu',
            'password' => 'password123',
            'device_name' => 'TestDevice',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.student_id', '00031234')
            ->assertJsonPath('data.user.initials', 'SN')
            ->assertJsonPath('data.user.masked_phone', '+90 5** *** ** 12')
            ->assertJsonPath('data.user.sucard_status', 'Active');

        $token = $loginResponse->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'student@sabanciuniv.edu');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_student_can_change_password_and_forgot_password_resets_db(): void
    {
        $token = $this->student->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'wrongpassword',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ])->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password123',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ])->assertOk();

        $this->assertTrue(Hash::check('newpass123', $this->student->fresh()->password));

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'student@sabanciuniv.edu',
            'student_id' => '99999999',
            'password' => 'resetpass123',
            'password_confirmation' => 'resetpass123',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'student@sabanciuniv.edu',
            'student_id' => '00031234',
            'password' => 'resetpass123',
            'password_confirmation' => 'resetpass123',
        ])->assertOk();

        $this->assertTrue(Hash::check('resetpass123', $this->student->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_edit_profile_updates_allowed_fields_and_ignores_locked_fields(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.student_id', '00031234');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post('/api/v1/profile', [
            'full_name' => 'Updated Student',
            'phone' => '+905559998877',
            'avatar' => $file,
            'student_id' => '99999999',
            'su_username' => 'hacker',
            'email' => 'hacker@sabanciuniv.edu',
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.full_name', 'Updated Student')
            ->assertJsonPath('data.initials', 'US')
            ->assertJsonPath('data.student_id', '00031234')
            ->assertJsonPath('data.su_username', 'student')
            ->assertJsonPath('data.email', 'student@sabanciuniv.edu');

        $fresh = $this->student->fresh();
        $this->assertNotNull($fresh->avatar_path);
        $this->assertStringStartsWith('/storage/avatars/', $fresh->avatar_path);
        $this->assertSame(url($fresh->avatar_path), $response->json('data.avatar_url'));

        $uploadedRelativePath = str_replace('/storage/', '', $fresh->avatar_path);
        Storage::disk('public')->assertExists($uploadedRelativePath);

        // Reject path traversal sequences in avatar_path
        $this->patchJson('/api/v1/profile', [
            'avatar_path' => '/storage/../.env',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['avatar_path']);

        // Another user pointing avatar_path to student's uploaded file and clearing it cannot delete student's file
        $otherStudent = User::factory()->create();
        Sanctum::actingAs($otherStudent);
        $this->patchJson('/api/v1/profile', [
            'avatar_path' => $fresh->avatar_path,
        ])->assertOk();
        $this->patchJson('/api/v1/profile', [
            'avatar_path' => null,
        ])->assertOk();
        Storage::disk('public')->assertExists($uploadedRelativePath);

        Sanctum::actingAs($this->student);

        // Replacing avatar_path deletes the old uploaded file from public disk
        $this->patchJson('/api/v1/profile', [
            'avatar_path' => 'https://cdn.example.com/avatar.png',
        ])->assertOk()
            ->assertJsonPath('data.avatar_path', 'https://cdn.example.com/avatar.png')
            ->assertJsonPath('data.avatar_url', 'https://cdn.example.com/avatar.png');

        Storage::disk('public')->assertMissing($uploadedRelativePath);

        // Clearing avatar_path removes avatar_url as well
        $this->patchJson('/api/v1/profile', [
            'avatar_path' => null,
        ])->assertOk()
            ->assertJsonPath('data.avatar_path', null)
            ->assertJsonPath('data.avatar_url', null);
    }

    public function test_wallets_endpoint_returns_main_and_ordered_service_wallets_with_turkish_formatting(): void
    {
        Sanctum::actingAs($this->student);

        $response = $this->getJson('/api/v1/wallets');

        $response->assertOk()
            ->assertJsonPath('data.main_wallet.is_main', true)
            ->assertJsonPath('data.main_wallet.balance', '0.00')
            ->assertJsonPath('data.main_wallet.balance_formatted', '₺0,00')
            ->assertJsonPath('data.service_wallets.0.vendor.code', 'SHUTTLE')
            ->assertJsonPath('data.service_wallets.0.vendor.default_tap_price', '21.50')
            ->assertJsonPath('data.service_wallets.0.vendor.default_tap_price_formatted', '₺21,50')
            ->assertJsonPath('data.service_wallets.1.vendor.code', 'CAFETERIA')
            ->assertJsonPath('data.service_wallets.2.vendor.code', 'PRINTING');
    }

    public function test_database_prevents_duplicate_main_wallets_per_user(): void
    {
        $this->expectException(QueryException::class);

        Wallet::create([
            'user_id' => $this->student->id,
            'vendor_id' => null,
            'is_main' => true,
            'balance' => '0.00',
            'currency' => 'TRY',
        ]);
    }

    public function test_full_ledger_flow_bcmath_precision_and_context_aware_allocation_signs(): void
    {
        Sanctum::actingAs($this->student);

        // Enforce min 10 and max 2 decimals on deposit
        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => 5,
            'idempotency_key' => 'dep-too-small',
        ])->assertStatus(422);

        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => '100.123',
            'idempotency_key' => 'dep-too-many-decimals',
        ])->assertStatus(422);

        // 1. Deposit 1250 TL (and verify idempotency replay)
        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => 1250,
            'idempotency_key' => 'dep-1',
        ])->assertCreated()
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonPath('data.wallets.main_wallet.balance', '1250.00')
            ->assertJsonPath('data.wallets.main_wallet.balance_formatted', '₺1.250,00');

        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => 1250,
            'idempotency_key' => 'dep-1',
        ])->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('data.wallets.main_wallet.balance', '1250.00');

        // 2. Verify zero float drift with bcmath (0.10 + 0.20 == 0.30) and allocate 100 TL to Shuttle
        $this->postJson('/api/v1/transactions/allocate', [
            'vendor_code' => 'SHUTTLE',
            'amount' => '0.10',
            'idempotency_key' => 'alloc-cent-1',
        ])->assertCreated();

        $this->postJson('/api/v1/transactions/allocate', [
            'vendor_code' => 'SHUTTLE',
            'amount' => '0.20',
            'idempotency_key' => 'alloc-cent-2',
        ])->assertCreated()
            ->assertJsonPath('data.wallets.service_wallets.0.balance', '0.30')
            ->assertJsonPath('data.wallets.main_wallet.balance', '1249.70');

        $this->postJson('/api/v1/transactions/allocate', [
            'vendor_code' => 'SHUTTLE',
            'amount' => '100.00',
            'idempotency_key' => 'alloc-1',
        ])->assertCreated()
            ->assertJsonPath('data.wallets.main_wallet.balance', '1149.70')
            ->assertJsonPath('data.wallets.service_wallets.0.balance', '100.30');

        $printingWalletId = $this->student->wallets()
            ->whereHas('vendor', fn ($q) => $q->where('code', 'PRINTING'))
            ->value('id');

        $this->postJson('/api/v1/transactions/allocate', [
            'destination_wallet_id' => $printingWalletId,
            'amount' => '50.00',
            'idempotency_key' => 'alloc-2',
        ])->assertCreated()
            ->assertJsonPath('data.wallets.main_wallet.balance', '1099.70');

        // Reject allocating to Main Wallet
        $mainWalletId = $this->student->mainWallet()->value('id');
        $this->postJson('/api/v1/transactions/allocate', [
            'destination_wallet_id' => $mainWalletId,
            'amount' => '10.00',
            'idempotency_key' => 'alloc-main-invalid',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_WALLET_TARGET');

        // 3. Insufficient funds guard on allocation
        $this->postJson('/api/v1/transactions/allocate', [
            'vendor_code' => 'CAFETERIA',
            'amount' => '5000.00',
            'idempotency_key' => 'alloc-fail',
        ])->assertStatus(422)->assertJsonPath('error_code', 'INSUFFICIENT_FUNDS');

        // 4. Tap Shuttle (default 21.50 TL) and Printing (8.00 TL)
        $this->postJson('/api/v1/transactions/tap', [
            'vendor_code' => 'SHUTTLE',
            'idempotency_key' => 'tap-1',
        ])->assertCreated()
            ->assertJsonPath('data.transaction.amount', '21.50')
            ->assertJsonPath('data.transaction.amount_formatted', '-₺21,50')
            ->assertJsonPath('data.transaction.sign', '-');

        $this->postJson('/api/v1/transactions/tap', [
            'vendor_code' => 'PRINTING',
            'amount' => '8.00',
            'idempotency_key' => 'tap-2',
        ])->assertCreated();

        // 5. Refund last Shuttle tap (21.50 TL back to Shuttle)
        $this->postJson('/api/v1/transactions/refund-last-tap', [
            'vendor_code' => 'SHUTTLE',
            'idempotency_key' => 'ref-1',
        ])->assertCreated()
            ->assertJsonPath('data.transaction.type', 'REFUND')
            ->assertJsonPath('data.transaction.amount', '21.50')
            ->assertJsonPath('data.transaction.amount_formatted', '+₺21,50');

        // 6. Second Shuttle refund fails
        $this->postJson('/api/v1/transactions/refund-last-tap', [
            'vendor_code' => 'SHUTTLE',
            'idempotency_key' => 'ref-2',
        ])->assertStatus(422)->assertJsonPath('error_code', 'NO_REFUNDABLE_TAP_FOUND');

        // 7. Verify context-aware ALLOCATION signs across ALL (''), MAIN ('-'), and SHUTTLE ('+')
        $allResponse = $this->getJson('/api/v1/transactions?wallet=ALL');
        $allAllocTx = collect($allResponse->json('data.transactions'))->firstWhere('idempotency_key', 'alloc-1');
        $this->assertSame('', $allAllocTx['sign']);
        $this->assertSame('₺100,00', $allAllocTx['amount_formatted']);

        $allResponse->assertOk()
            ->assertJsonPath('data.summary.deposited_this_month', '1250.00')
            ->assertJsonPath('data.summary.deposited_this_month_formatted', '+₺1.250,00')
            ->assertJsonPath('data.summary.spent_this_month', '8.00')
            ->assertJsonPath('data.summary.spent_this_month_formatted', '-₺8,00');

        $shuttleResponse = $this->getJson('/api/v1/transactions?wallet=SHUTTLE');
        $shuttleAllocTx = collect($shuttleResponse->json('data.transactions'))->firstWhere('idempotency_key', 'alloc-1');
        $this->assertSame('+', $shuttleAllocTx['sign']);
        $this->assertSame('+₺100,00', $shuttleAllocTx['amount_formatted']);

        $mainResponse = $this->getJson('/api/v1/transactions?wallet=MAIN');
        $mainAllocTx = collect($mainResponse->json('data.transactions'))->firstWhere('idempotency_key', 'alloc-1');
        $this->assertSame('-', $mainAllocTx['sign']);
        $this->assertSame('-₺100,00', $mainAllocTx['amount_formatted']);

        // Verify limit parameter
        $this->getJson('/api/v1/transactions?wallet=ALL&limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.transactions');
    }

    public function test_idempotency_key_conflict_between_different_users_returns_409(): void
    {
        $otherUser = User::factory()->create();

        Sanctum::actingAs($this->student);
        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => '100.00',
            'idempotency_key' => 'shared-key-1',
        ])->assertCreated();

        Sanctum::actingAs($otherUser);
        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => '100.00',
            'idempotency_key' => 'shared-key-1',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_CONFLICT');
    }

    public function test_database_seeder_provisions_team_and_wireframe_demo_balances(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('vendors', 3);
        $this->assertDatabaseCount('users', 4);

        $demoStudent = User::where('email', 'student@sabanciuniv.edu')->firstOrFail();
        Sanctum::actingAs($demoStudent);

        $wallets = $this->getJson('/api/v1/wallets');
        $wallets->assertOk()
            ->assertJsonPath('data.main_wallet.balance', '1250.00')
            ->assertJsonPath('data.main_wallet.balance_formatted', '₺1.250,00')
            ->assertJsonPath('data.service_wallets.0.balance', '85.50')
            ->assertJsonPath('data.service_wallets.0.balance_formatted', '₺85,50')
            ->assertJsonPath('data.service_wallets.1.balance', '240.00')
            ->assertJsonPath('data.service_wallets.1.balance_formatted', '₺240,00')
            ->assertJsonPath('data.service_wallets.2.balance', '40.00')
            ->assertJsonPath('data.service_wallets.2.balance_formatted', '₺40,00');

        $transactions = $this->getJson('/api/v1/transactions');
        $transactions->assertOk()
            ->assertJsonPath('data.summary.deposited_this_month', '1741.00')
            ->assertJsonPath('data.summary.deposited_this_month_formatted', '+₺1.741,00')
            ->assertJsonPath('data.summary.spent_this_month', '125.50')
            ->assertJsonPath('data.summary.spent_this_month_formatted', '-₺125,50')
            ->assertJsonCount(10, 'data.transactions');

        // Confirm demo student's most recent unrefunded tap is Shuttle fare (21.50 TL)
        $this->postJson('/api/v1/transactions/refund-last-tap', [
            'idempotency_key' => 'demo-refund-check',
        ])->assertCreated()
            ->assertJsonPath('data.transaction.amount', '21.50')
            ->assertJsonPath('data.wallets.service_wallets.0.balance', '107.00');
    }

    public function test_public_auth_endpoints_enforce_rate_limiting(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'student@sabanciuniv.edu',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'student@sabanciuniv.edu',
            'password' => 'wrong-password',
        ])->assertStatus(429)
            ->assertJsonPath('error_code', 'RATE_LIMIT_EXCEEDED');
    }

    public function test_strict_idempotency_validates_type_amount_and_target_wallet(): void
    {
        Sanctum::actingAs($this->student);

        // Initial deposit
        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => '150.00',
            'idempotency_key' => 'strict-dep-1',
        ])->assertCreated();

        // Exact replay succeeds with 200 OK
        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => '150.00',
            'idempotency_key' => 'strict-dep-1',
        ])->assertOk()
            ->assertJsonPath('idempotent_replay', true);

        // Same key with different deposit amount -> 409 conflict
        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => '200.00',
            'idempotency_key' => 'strict-dep-1',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_CONFLICT');

        // Same key across different operation type -> 409 conflict
        $this->postJson('/api/v1/transactions/allocate', [
            'vendor_code' => 'SHUTTLE',
            'amount' => '150.00',
            'idempotency_key' => 'strict-dep-1',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_CONFLICT');

        // Allocation target conflict on reused key -> 409 conflict
        $this->postJson('/api/v1/transactions/allocate', [
            'vendor_code' => 'SHUTTLE',
            'amount' => '50.00',
            'idempotency_key' => 'strict-alloc-1',
        ])->assertCreated();

        $this->postJson('/api/v1/transactions/allocate', [
            'vendor_code' => 'CAFETERIA',
            'amount' => '50.00',
            'idempotency_key' => 'strict-alloc-1',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_CONFLICT');

        // Tap amount & description conflicts vs exact replay
        $this->postJson('/api/v1/transactions/tap', [
            'vendor_code' => 'SHUTTLE',
            'amount' => '21.50',
            'idempotency_key' => 'strict-tap-1',
        ])->assertCreated();

        $this->postJson('/api/v1/transactions/tap', [
            'vendor_code' => 'SHUTTLE',
            'amount' => '21.50',
            'idempotency_key' => 'strict-tap-1',
        ])->assertOk()
            ->assertJsonPath('idempotent_replay', true);

        $this->postJson('/api/v1/transactions/tap', [
            'vendor_code' => 'SHUTTLE',
            'amount' => '10.00',
            'idempotency_key' => 'strict-tap-1',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_CONFLICT');

        $this->postJson('/api/v1/transactions/tap', [
            'vendor_code' => 'SHUTTLE',
            'amount' => '21.50',
            'description' => 'Custom shuttle note',
            'idempotency_key' => 'strict-tap-1',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_CONFLICT');

        // Refund vendor conflicts (different vendor OR omitted vs explicit) vs exact replay
        $this->postJson('/api/v1/transactions/refund-last-tap', [
            'vendor_code' => 'SHUTTLE',
            'idempotency_key' => 'strict-ref-1',
        ])->assertCreated();

        $this->postJson('/api/v1/transactions/refund-last-tap', [
            'vendor_code' => 'CAFETERIA',
            'idempotency_key' => 'strict-ref-1',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_CONFLICT');

        $this->postJson('/api/v1/transactions/refund-last-tap', [
            'idempotency_key' => 'strict-ref-1',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_CONFLICT');

        $this->postJson('/api/v1/transactions/refund-last-tap', [
            'vendor_code' => 'SHUTTLE',
            'idempotency_key' => 'strict-ref-1',
        ])->assertOk()
            ->assertJsonPath('idempotent_replay', true);
    }

    public function test_allocate_rejects_mismatched_vendor_and_inactive_vendor(): void
    {
        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => '200.00',
            'idempotency_key' => 'alloc-prep-1',
        ])->assertCreated();

        $printingWalletId = $this->student->wallets()
            ->whereHas('vendor', fn ($q) => $q->where('code', 'PRINTING'))
            ->value('id');

        // Conflicting vendor_code and destination_wallet_id
        $this->postJson('/api/v1/transactions/allocate', [
            'vendor_code' => 'SHUTTLE',
            'destination_wallet_id' => $printingWalletId,
            'amount' => '25.00',
            'idempotency_key' => 'alloc-mismatch-1',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_WALLET_TARGET');

        // Inactive vendor wallet target via destination_wallet_id & tap
        Vendor::where('code', 'PRINTING')->update(['is_active' => false]);

        $this->postJson('/api/v1/transactions/allocate', [
            'destination_wallet_id' => $printingWalletId,
            'amount' => '25.00',
            'idempotency_key' => 'alloc-inactive-vendor-1',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_WALLET_TARGET');

        $this->postJson('/api/v1/transactions/tap', [
            'vendor_code' => 'PRINTING',
            'amount' => '8.00',
            'idempotency_key' => 'tap-inactive-vendor-1',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_WALLET_TARGET');
    }

    public function test_inactive_student_token_is_revoked_and_change_password_revokes_other_tokens(): void
    {
        $this->assertSame('Europe/Istanbul', config('app.timezone'));

        $deviceOneToken = $this->student->createToken('device-1')->plainTextToken;
        $deviceTwoToken = $this->student->createToken('device-2')->plainTextToken;

        // Changing password with device-1 revokes device-2 while preserving device-1
        $this->withHeader('Authorization', 'Bearer '.$deviceOneToken)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password123',
                'password' => 'updatedpass123',
                'password_confirmation' => 'updatedpass123',
            ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        app('auth')->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$deviceTwoToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');

        app('auth')->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$deviceOneToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        // Deactivating the student blocks protected routes (403 ACCOUNT_INACTIVE) and deletes the token
        $this->student->update(['is_active' => false]);

        app('auth')->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$deviceOneToken)
            ->getJson('/api/v1/wallets')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ACCOUNT_INACTIVE');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        app('auth')->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$deviceOneToken)
            ->getJson('/api/v1/wallets')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    public function test_user_creation_automatically_provisions_main_and_active_service_wallets(): void
    {
        $printingVendor = Vendor::where('code', 'PRINTING')->firstOrFail();
        $printingVendor->update(['is_active' => false]);

        $autoUser = User::factory()->create();

        Sanctum::actingAs($autoUser);

        $this->getJson('/api/v1/wallets')
            ->assertOk()
            ->assertJsonPath('data.main_wallet.is_main', true)
            ->assertJsonPath('data.main_wallet.balance', '0.00')
            ->assertJsonCount(2, 'data.service_wallets')
            ->assertJsonPath('data.service_wallets.0.vendor.code', 'SHUTTLE')
            ->assertJsonPath('data.service_wallets.1.vendor.code', 'CAFETERIA');

        // Re-activating a vendor automatically provisions missing service wallets for existing users
        $printingVendor->update(['is_active' => true]);

        $this->getJson('/api/v1/wallets')
            ->assertOk()
            ->assertJsonCount(3, 'data.service_wallets')
            ->assertJsonPath('data.service_wallets.2.vendor.code', 'PRINTING');

        // Deactivating a previously provisioned vendor hides its wallet from the wallets payload
        $printingVendor->update(['is_active' => false]);

        $this->getJson('/api/v1/wallets')
            ->assertOk()
            ->assertJsonCount(2, 'data.service_wallets');

        $printingVendor->update(['is_active' => true]);

        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => '50.00',
            'idempotency_key' => 'auto-provision-dep-1',
        ])->assertCreated()
            ->assertJsonPath('data.wallets.main_wallet.balance', '50.00');
    }

    public function test_models_cast_enums_and_factories_create_valid_records(): void
    {
        $shuttleVendor = Vendor::where('code', VendorCode::Shuttle)->firstOrFail();
        $this->assertSame(VendorCode::Shuttle, $shuttleVendor->code);

        $mainWallet = $this->student->wallets()->main()->firstOrFail();
        $shuttleWallet = $this->student->wallets()
            ->service()
            ->where('vendor_id', $shuttleVendor->id)
            ->with('vendor')
            ->firstOrFail();

        $depositTx = Transaction::factory()->deposit($mainWallet, '100.00')->create();
        $allocTx = Transaction::factory()->allocation($mainWallet, $shuttleWallet, '50.00')->create();
        $tapTx = Transaction::factory()->cardTap($shuttleWallet, '21.50')->create();
        $refundTx = Transaction::factory()->refund($shuttleWallet, $tapTx)->create();

        $this->assertSame(TransactionType::Deposit, $depositTx->type);
        $this->assertSame(TransactionType::Allocation, $allocTx->type);
        $this->assertSame(TransactionType::CardTap, $tapTx->type);
        $this->assertSame(TransactionType::Refund, $refundTx->type);
        $this->assertSame(TransactionStatus::Completed, $depositTx->status);

        $this->assertSame(1, Transaction::where('initiated_by_user_id', $this->student->id)->unrefundedTaps()->count());
        $this->assertSame(2, Transaction::where('initiated_by_user_id', $this->student->id)->touchingWallet($mainWallet->id)->count());

        $factoryVendor = Vendor::factory()->cafeteria()->inactive()->make();
        $this->assertSame(VendorCode::Cafeteria, $factoryVendor->code);
        $this->assertFalse($factoryVendor->is_active);

        $factoryWallet = Wallet::factory()->service($shuttleVendor)->make(['user_id' => $this->student->id]);
        $this->assertFalse($factoryWallet->is_main);

        $this->assertSame(
            $shuttleWallet->id,
            $this->student->wallets()->forVendorCode(VendorCode::Shuttle)->value('id')
        );
        $this->assertSame(
            $shuttleWallet->id,
            $this->student->wallets()->forVendorCode('SHUTTLE')->value('id')
        );

        $this->assertTrue($depositTx->matchesIdempotentPayload(
            expectedType: TransactionType::Deposit,
            expectedAmount: '100.00',
            expectedSourceWalletId: null,
            expectedDestinationWalletId: $mainWallet->id,
        ));
        $this->assertFalse($depositTx->matchesIdempotentPayload(
            expectedType: TransactionType::Deposit,
            expectedAmount: '99.00',
            expectedSourceWalletId: null,
            expectedDestinationWalletId: $mainWallet->id,
        ));
        $this->assertTrue($tapTx->matchesIdempotentPayload(
            expectedType: TransactionType::CardTap,
            expectedAmount: '21.50',
            expectedSourceWalletId: $shuttleWallet->id,
            expectedDestinationWalletId: null,
            expectedDescription: 'Shuttle fare',
        ));
        $this->assertFalse($tapTx->matchesIdempotentPayload(
            expectedType: TransactionType::CardTap,
            expectedAmount: '21.50',
            expectedSourceWalletId: $shuttleWallet->id,
            expectedDestinationWalletId: null,
            expectedDescription: 'Different description',
        ));
        $this->assertTrue($refundTx->matchesIdempotentPayload(
            expectedType: TransactionType::Refund,
            expectedVendorCode: 'SHUTTLE',
        ));
        $this->assertFalse($refundTx->matchesIdempotentPayload(
            expectedType: TransactionType::Refund,
            expectedVendorCode: null,
        ));

        // Backward-compatible model & controller wrappers match API Resource serialization
        $this->assertSame($this->student->email, $this->student->toProfileArray()['email']);
        $this->assertSame('SHUTTLE', $shuttleWallet->toApiArray()['vendor']['code']);
        $this->assertSame('ALLOCATION', $allocTx->toApiArray()['type']);
        $this->assertSame('+', $allocTx->toApiArray('SHUTTLE')['sign']);
        $this->assertCount(3, WalletController::buildWalletPayload($this->student)['service_wallets']);
    }

    public function test_query_optimizations_for_wallet_payload_deposit_and_vendor_provisioning(): void
    {
        $walletService = app(WalletService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $payload = $walletService->buildWalletPayload($this->student);
        $walletQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotNull($payload['main_wallet']);
        $this->assertCount(3, $payload['service_wallets']);
        $this->assertCount(2, $walletQueries);

        // Saving an active vendor whose service wallets already exist triggers zero wallet inserts
        $shuttle = Vendor::where('code', VendorCode::Shuttle)->firstOrFail();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $shuttle->update(['default_tap_price' => '22.00']);
        $vendorUpdateQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $walletInserts = array_filter(
            $vendorUpdateQueries,
            static fn (array $entry): bool => str_contains(strtolower($entry['query']), 'insert into "wallets"')
                || str_contains(strtolower($entry['query']), 'insert into `wallets`')
        );
        $this->assertCount(0, $walletInserts);

        $countWalletSelects = static fn (array $queries): int => count(array_filter(
            $queries,
            static fn (array $entry): bool => str_contains(strtolower($entry['query']), 'from "wallets"')
                && str_starts_with(strtolower(trim($entry['query'])), 'select')
        ));

        // Deposit skips redundant pre-lock wallet SELECT and uses in-memory relation hydration
        Sanctum::actingAs($this->student);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson('/api/v1/transactions/deposit', [
            'amount' => '100.00',
            'idempotency_key' => 'opt-dep-1',
        ])->assertCreated();
        $depositQueries = DB::getQueryLog();
        DB::disableQueryLog();

        // 1 lockMainWallet SELECT + 1 buildWalletPayload SELECT = 2 total SELECTs on wallets
        $this->assertSame(2, $countWalletSelects($depositQueries));

        // Allocate locks Main + Service wallets in a single FOR UPDATE SELECT (+ 1 buildWalletPayload SELECT = 2)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson('/api/v1/transactions/allocate', [
            'vendor_code' => 'SHUTTLE',
            'amount' => '50.00',
            'idempotency_key' => 'opt-alloc-1',
        ])->assertCreated();
        $allocateQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(2, $countWalletSelects($allocateQueries));

        // Card tap locks Main + Service wallets in a single FOR UPDATE SELECT (+ 1 buildWalletPayload SELECT = 2)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson('/api/v1/transactions/tap', [
            'vendor_code' => 'SHUTTLE',
            'idempotency_key' => 'opt-tap-1',
        ])->assertCreated();
        $tapQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(2, $countWalletSelects($tapQueries));

        // User creation checks existing wallets once and directly inserts missing rows (1 SELECT on wallets)
        DB::flushQueryLog();
        DB::enableQueryLog();
        User::factory()->create();
        $userProvisionQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(1, $countWalletSelects($userProvisionQueries));

        // DatabaseSeeder inserts demo transactions with target timestamps via Carbon::setTestNow (0 transaction UPDATEs)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->seed(DatabaseSeeder::class);
        $seederQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $txUpdates = array_filter(
            $seederQueries,
            static fn (array $entry): bool => str_contains(strtolower($entry['query']), 'update "transactions"')
                || str_contains(strtolower($entry['query']), 'update `transactions`')
        );
        $this->assertCount(0, $txUpdates);
    }
}
