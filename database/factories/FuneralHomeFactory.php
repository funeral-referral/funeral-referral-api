<?php

namespace Database\Factories;

use App\Enums\OwnershipType;
use App\Enums\Stage;
use App\Models\FuneralHome;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FuneralHome>
 */
class FuneralHomeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->lastName().' Funeral Home';

        return [
            'name' => $name,
            'normalized_name' => Str::of($name)->lower()->replaceMatches('/[^a-z0-9 ]/', '')->squish()->toString(),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state_code' => fake()->stateAbbr(),
            'county' => fake()->lastName().' County',
            'postal_code' => fake()->postcode(),
            'latitude' => fake()->latitude(25, 48),
            'longitude' => fake()->longitude(-124, -67),
            'phone' => fake()->numerify('+1##########'),
            'ownership_type' => fake()->randomElement(OwnershipType::cases()),
            'stage' => Stage::Found,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}
