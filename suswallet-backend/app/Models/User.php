<?php

namespace App\Models;

use App\Http\Resources\UserProfileResource;
use App\Services\WalletService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function mainWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->main();
    }

    public function serviceWallets(): HasMany
    {
        return $this->hasMany(Wallet::class)->service()->with('vendor');
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
     * @return array{
     *     id: string,
     *     student_id: string,
     *     su_username: string,
     *     email: string,
     *     full_name: string,
     *     initials: string,
     *     phone: ?string,
     *     masked_phone: ?string,
     *     avatar_path: ?string,
     *     avatar_url: ?string,
     *     is_active: bool,
     *     sucard_status: string
     * }
     */
    public function revokeOtherTokens(): void
    {
        $currentToken = $this->currentAccessToken();

        if ($currentToken && isset($currentToken->id)) {
            $this->tokens()->where('id', '!=', $currentToken->id)->delete();
        } else {
            $this->tokens()->delete();
        }
    }

    /**
     * @return array{avatar_path?: ?string}
     */
    public function resolveUpdatedAvatarPath(
        ?UploadedFile $avatarFile,
        bool $hasAvatarPathInput,
        ?string $avatarPathInput
    ): array {
        if ($avatarFile !== null) {
            $this->deleteStoredAvatar($this->avatar_path);
            $storedPath = $avatarFile->store('avatars', 'public');

            return ['avatar_path' => '/storage/'.$storedPath];
        }

        if ($hasAvatarPathInput) {
            if ($avatarPathInput !== $this->avatar_path) {
                $this->deleteStoredAvatar($this->avatar_path);
            }

            return ['avatar_path' => $avatarPathInput];
        }

        return [];
    }

    private function deleteStoredAvatar(?string $avatarPath): void
    {
        if ($avatarPath && str_starts_with($avatarPath, '/storage/avatars/')) {
            $stillReferenced = self::where('id', '!=', $this->id)
                ->where('avatar_path', $avatarPath)
                ->exists();

            if (! $stillReferenced) {
                $oldRelative = substr($avatarPath, strlen('/storage/'));
                Storage::disk('public')->delete($oldRelative);
            }
        }
    }

    public function toProfileArray(): array
    {
        return (new UserProfileResource($this))->resolve();
    }
}
