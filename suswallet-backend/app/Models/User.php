<?php

namespace App\Models;

use App\Services\WalletService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'student_id',
    'su_username',
    'email',
    'full_name',
    'phone',
    'avatar_path',
    'password',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            app(WalletService::class)->provisionUserWallets($user);
        });
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function mainWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('is_main', true);
    }

    public function serviceWallets(): HasMany
    {
        return $this->hasMany(Wallet::class)->where('is_main', false)->with('vendor');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'initiated_by_user_id');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];

        if (count($parts) === 0 || $parts[0] === '') {
            return 'SU';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        $first = mb_substr($parts[0], 0, 1);
        $last = mb_substr($parts[count($parts) - 1], 0, 1);

        return mb_strtoupper($first.$last);
    }

    public function maskedPhone(): ?string
    {
        if (blank($this->phone)) {
            return null;
        }

        $clean = preg_replace('/\s+/', '', trim((string) $this->phone)) ?? '';

        if (mb_strlen($clean) <= 6) {
            return $this->phone;
        }

        $prefix = mb_substr($clean, 0, 4);
        $lastTwo = mb_substr($clean, -2);

        return mb_substr($prefix, 0, 3).' '.mb_substr($prefix, 3).'** *** ** '.$lastTwo;
    }

    public function avatarUrl(): ?string
    {
        if (blank($this->avatar_path)) {
            return null;
        }

        if (str_starts_with($this->avatar_path, 'http://') || str_starts_with($this->avatar_path, 'https://')) {
            return $this->avatar_path;
        }

        return url($this->avatar_path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toProfileArray(): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'su_username' => $this->su_username,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'initials' => $this->initials(),
            'phone' => $this->phone,
            'masked_phone' => $this->maskedPhone(),
            'avatar_path' => $this->avatar_path,
            'avatar_url' => $this->avatarUrl(),
            'is_active' => $this->is_active,
            'sucard_status' => $this->is_active ? 'Active' : 'Inactive',
        ];
    }
}
