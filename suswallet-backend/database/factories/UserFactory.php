<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = fake()->unique()->userName();

        return [
            'student_id' => '000'.fake()->unique()->numerify('#####'),
            'su_username' => $username,
            'email' => $username.'@sabanciuniv.edu',
            'full_name' => fake()->name(),
            'phone' => '+9053'.fake()->numerify('#######'),
            'avatar_path' => null,
            'password' => static::$password ??= Hash::make('password123'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }
}
