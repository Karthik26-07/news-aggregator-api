<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => $this->faker->randomElement(['NewsAPI', 'The Guardian', 'New York Times', 'BBC News']),
            'category' => $this->faker->randomElement(['tech', 'politics', 'sports', 'health', 'business']),
            'source' => $this->faker->randomElement(['TechCrunch', 'The Guardian', 'NYTimes', 'BBC']),
            'title' => $this->faker->sentence(6), // e.g., "AI Revolutionizes Tech Industry"
            'content' => $this->faker->paragraphs(3, true), // 3 paragraphs joined into a string
            'summary' => $this->faker->sentence(10), // e.g., "AI advancements lead to breakthroughs in tech."
            'author' => $this->faker->optional()->name(), // Nullable, sometimes returns null
            'article_url' => $this->faker->unique()->url(), // Unique URL
            'image_url' => $this->faker->optional()->imageUrl(640, 480, 'news'), // Nullable image URL
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'), // Random date in the past year
            'created_at' => now()
        ];
    }
}
