<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Services\WalletService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(WalletService $walletService): void
    {
        $vendors = [
            [
                'code' => 'SHUTTLE',
                'name' => 'Shuttle',
                'category' => 'TRANSPORT',
                'default_tap_price' => '21.50',
                'icon_name' => 'directions_bus',
                'is_active' => true,
            ],
            [
                'code' => 'CAFETERIA',
                'name' => 'Cafeteria',
                'category' => 'DINING',
                'default_tap_price' => '96.00',
                'icon_name' => 'restaurant',
                'is_active' => true,
            ],
            [
                'code' => 'PRINTING',
                'name' => 'Printing',
                'category' => 'PRINT',
                'default_tap_price' => '8.00',
                'icon_name' => 'print',
                'is_active' => true,
            ],
        ];

        foreach ($vendors as $vendorData) {
            Vendor::updateOrCreate(['code' => $vendorData['code']], $vendorData);
        }

        $defaultPassword = Hash::make('password123');

        $students = [
            [
                'student_id' => '00035008',
                'su_username' => 'tunarkarimov',
                'email' => 'tunarkarimov@sabanciuniv.edu',
                'full_name' => 'Tunar Karimov',
                'phone' => '+905301112233',
            ],
            [
                'student_id' => '00033623',
                'su_username' => 'mekancherkezov',
                'email' => 'mekancherkezov@sabanciuniv.edu',
                'full_name' => 'Mekan Cherkezov',
                'phone' => '+905304445566',
            ],
            [
                'student_id' => '00033738',
                'su_username' => 'usmankhan',
                'email' => 'usmankhan@sabanciuniv.edu',
                'full_name' => 'Muhammad Usman Waqar Khan',
                'phone' => '+905307778899',
            ],
            [
                'student_id' => '00031234',
                'su_username' => 'student',
                'email' => 'student@sabanciuniv.edu',
                'full_name' => 'Student Name',
                'phone' => '+905321234512',
            ],
        ];

        foreach ($students as $studentData) {
            $user = User::firstOrCreate(
                ['email' => $studentData['email']],
                array_merge($studentData, [
                    'password' => $defaultPassword,
                    'is_active' => true,
                ])
            );

            $walletService->provisionUserWallets($user);

            if ($user->email === 'student@sabanciuniv.edu' && $user->transactions()->count() === 0) {
                $this->seedDemoTransactions($walletService, $user);
            }
        }
    }

    private function seedDemoTransactions(WalletService $walletService, User $user): void
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $dayStart = $now->copy()->startOfDay();
        $yesterday = $now->copy()->subDay();

        if ($yesterday->isSameMonth($now)) {
            $timestamps = [
                $yesterday->copy()->setTime(10, 15),
                $yesterday->copy()->setTime(17, 55),
                $yesterday->copy()->setTime(18, 10),
                $yesterday->copy()->setTime(18, 15),
                $yesterday->copy()->setTime(18, 20),
                $yesterday->copy()->setTime(19, 10),
                $now->copy()->subMinutes(90)->gte($dayStart) ? $now->copy()->subMinutes(90) : $dayStart->copy()->addSeconds(1),
                $now->copy()->subMinutes(85)->gte($dayStart) ? $now->copy()->subMinutes(85) : $dayStart->copy()->addSeconds(2),
                $now->copy()->subMinutes(45)->gte($dayStart) ? $now->copy()->subMinutes(45) : $dayStart->copy()->addSeconds(3),
                $now->copy()->subMinutes(15)->gte($dayStart) ? $now->copy()->subMinutes(15) : $dayStart->copy()->addSeconds(4),
            ];
        } else {
            $timestamps = array_map(
                fn (int $sec): CarbonInterface => $monthStart->copy()->addSeconds($sec),
                range(1, 10)
            );
        }

        $stamp = static function (Transaction $tx, CarbonInterface $at): void {
            $tx->timestamps = false;
            $tx->created_at = $at;
            $tx->updated_at = $at;
            $tx->save();
        };

        $stamp($walletService->deposit($user, '1241.00', 'seed-deposit-1-'.Str::uuid())['transaction'], $timestamps[0]);
        $stamp($walletService->deposit($user, '500.00', 'seed-deposit-2-'.Str::uuid())['transaction'], $timestamps[1]);
        $stamp($walletService->allocate($user, 'SHUTTLE', null, '107.00', 'seed-alloc-shuttle-'.Str::uuid())['transaction'], $timestamps[2]);
        $stamp($walletService->allocate($user, 'CAFETERIA', null, '336.00', 'seed-alloc-cafeteria-'.Str::uuid())['transaction'], $timestamps[3]);
        $stamp($walletService->allocate($user, 'PRINTING', null, '48.00', 'seed-alloc-printing-'.Str::uuid())['transaction'], $timestamps[4]);
        $stamp($walletService->simulateTap($user, 'PRINTING', '8.00', 'seed-tap-print-1-'.Str::uuid(), 'Print job')['transaction'], $timestamps[5]);
        $stamp($walletService->simulateTap($user, 'PRINTING', '4.00', 'seed-tap-print-2-'.Str::uuid(), 'Failed print job')['transaction'], $timestamps[6]);
        $stamp($walletService->refundLastTap($user, 'seed-refund-print-'.Str::uuid(), 'PRINTING')['transaction'], $timestamps[7]);
        $stamp($walletService->simulateTap($user, 'CAFETERIA', '96.00', 'seed-tap-cafe-'.Str::uuid(), 'Cafeteria')['transaction'], $timestamps[8]);
        $stamp($walletService->simulateTap($user, 'SHUTTLE', '21.50', 'seed-tap-shuttle-'.Str::uuid(), 'Shuttle fare')['transaction'], $timestamps[9]);
    }
}
