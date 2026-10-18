<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'offer_type' => 'rent',
            'property_type' => 'flat',
            'disposition' => fake()->randomElement(['1+kk', '2+kk', '3+1']),
            'area_m2' => fake()->numberBetween(25, 110),
            'price_halere' => fake()->numberBetween(12, 45) * 1000 * 100,
            'fees_halere' => 300000,
            'deposit_halere' => 4000000,
            'city' => 'Praha',
            'district' => 'Vinohrady',
            'lat' => 50.075, 'lng' => 14.44,
            'penb' => 'C',
            'description' => 'Fiktivní testovací inzerát se světlým bytem, dostatečně dlouhým popisem a vybavením.',
            'status' => 'active',
            'source' => 'internal',
            'published_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function status(string $s): static { return $this->state(['status' => $s]); }
    public function imported(): static { return $this->state(['source' => 'imported', 'user_id' => null, 'source_name' => 'test', 'source_id' => (string) fake()->unique()->numberBetween(1, 1e9)]); }
}
