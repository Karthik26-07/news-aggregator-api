<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserPreference>
 */
class UserPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        // Fetch existing articles to derive realistic preferences
        $articles = Article::all();

        // Sample sources, categories, and authors from articles or fallback to defaults
        $sources = $articles->pluck('source')->unique()->values()->all() ?: ['TechCrunch', 'BBC', 'Yahoo! News'];
        $categories = $articles->pluck('category')->unique()->values()->all() ?: ['tech', 'sports', 'politics'];
        $authors = $articles->pluck('author')->unique()->values()->all() ?: ['Jane Doe', 'John Smith', 'Indiewire'];
        return [
            'user_id' => User::factory(), // Creates a new user if not overridden
            'preferred_sources' => $this->faker->boolean(70) // 70% chance of having preferences
                ? implode(',', $this->faker->randomElements($sources, rand(1, min(3, count($sources)))))
                : null,
            'preferred_categories' => $this->faker->boolean(70)
                ? implode(',', $this->faker->randomElements($categories, rand(1, min(3, count($categories)))))
                : null,
            'preferred_authors' => $this->faker->boolean(70)
                ? implode(',', $this->faker->randomElements($authors, rand(1, min(3, count($authors)))))
                : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Configure the factory with relationships.
     *
     * @return $this
     */
    public function configure()
    {
        return $this->afterMaking(function (UserPreference $preference) {
            // Ensure user_id references an existing user if not already set
            if (!$preference->user_id) {
                $preference->user_id = User::factory()->create()->id;
            }
        })->afterCreating(function (UserPreference $preference) {
            // Ensure the user exists in the database
            if (!User::find($preference->user_id)) {
                User::factory()->create(['id' => $preference->user_id]);
            }
        });
    }
}
