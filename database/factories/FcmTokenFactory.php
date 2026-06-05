<?php

namespace Database\Factories;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FcmToken>
 */
class FcmTokenFactory extends Factory
{
    protected $model = FcmToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => $this->faker->uuid().':'.$this->faker->uuid(),
            'device' => $this->faker->randomElement(['android', 'ios', 'web']),
        ];
    }
}
