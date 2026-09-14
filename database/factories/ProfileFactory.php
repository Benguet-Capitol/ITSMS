<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        $firstname = $this->faker->firstName();
        $lastname = $this->faker->lastName();

        return [
            'user_id' => User::factory(),
            'display_name' => "{$firstname} {$lastname}",
            'name' => ['firstname' => $firstname, 'lastname' => $lastname],
            'gender' => $this->faker->randomElement(['male', 'female']),
            'designation' => $this->faker->jobTitle(),
            'engagement' => 'ready',
        ];
    }
}
